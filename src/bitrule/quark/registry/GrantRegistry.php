<?php

declare(strict_types=1);

namespace bitrule\quark\registry;

use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\LocalStorage;
use bitrule\quark\Quark;
use libasynCurl\Curl;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\Server;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\SingletonTrait;
use RuntimeException;

final class GrantRegistry {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /** @var array<string, LocalStorage> */
    private array $localStorages = [];

    /**
     * @param string $name
     *
     * @return Promise<LocalStorage>
     */
    public function fetchByName(string $name): Promise {
        $promiseResolver = new PromiseResolver();

        $player = Server::getInstance()->getPlayerByPrefix($name);
        if ($player !== null && ($localStorage = $this->getLocalStorage($player->getXuid())) !== null) {
            $promiseResolver->resolve($localStorage);

            return $promiseResolver->getPromise();
        }

        Curl::getRequest(
            Quark::URL . '/grants?name=' . $name . '&state=offline',
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($promiseResolver, $name): void {
                if ($result === null) {
                    $promiseResolver->reject();

                    return;
                }

                $code = $result->getCode();
                if ($code === Quark::CODE_NOT_FOUND) {
                    $promiseResolver->reject();

                    return;
                }

                if ($code === Quark::CODE_FORBIDDEN) {
                    $promiseResolver->reject();

                    return;
                }

                if ($code === Quark::CODE_UNAUTHORIZED) {
                    $promiseResolver->reject();

                    return;
                }

                if ($code !== Quark::CODE_OK) {
                    $promiseResolver->reject();

                    return;
                }

                $response = json_decode($result->getBody(), true);
                if (!is_array($response)) {
                    $promiseResolver->reject();

                    return;
                }

                if (!isset($response['state']) || !isset($response['xuid'])) {
                    $promiseResolver->reject();

                    return;
                }

                $activeGrants = $response['active'] ?? [];
                $expiredGrants = $response['expired'] ?? [];

                foreach ($activeGrants as $index => $grantData) {
                    $activeGrants[$index] = GrantData::wrap($grantData);
                }

                foreach ($expiredGrants as $index => $grantData) {
                    $expiredGrants[$index] = GrantData::wrap($grantData);
                }

                $promiseResolver->resolve(new LocalStorage(
                    $response['xuid'],
                    $response['state'],
                    $activeGrants,
                    $expiredGrants
                ));
            }
        );

        return $promiseResolver->getPromise();
    }

    /**
     * @param string $xuid
     *
     * @return bool
     */
    public function fetchByXuid(string $xuid): bool {
        $logger = Quark::getInstance()->getLogger();

        Curl::getRequest(
            Quark::URL . '/grants?xuid=' . $xuid . '&state=online',
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($xuid, $logger): void {
                $code = $result !== null ? $result->getCode() : Quark::CODE_NOT_FOUND;
                $message = null;
                if ($code === Quark::CODE_NOT_FOUND) {
                    $message = 'API Route not found';
                } elseif ($code === Quark::CODE_FORBIDDEN) {
                    $message = 'API key is not set';
                } elseif ($code === Quark::CODE_UNAUTHORIZED) {
                    $message = 'This server is not authorized to create groups';
                } elseif ($code !== Quark::CODE_OK) {
                    $message = 'Failed to fetch grants (HTTP ' . $code . ')';
                }

                if ($message !== null) {
                    $logger->error($message);

                    return;
                }

                if ($result === null) {
                    throw new RuntimeException('Failed to fetch grants');
                }

                $response = json_decode($result->getBody(), true);
                if (!is_array($response)) {
                    $logger->error('Invalid response');

                    return;
                }

                if (!isset($response['state'])) {
                    $logger->error('State not found in response');

                    return;
                }

                $activeGrants = $response['active'] ?? [];
                $expiredGrants = $response['expired'] ?? [];

                foreach ($activeGrants as $index => $grantData) {
                    $activeGrants[$index] = GrantData::wrap($grantData);
                }

                foreach ($expiredGrants as $index => $grantData) {
                    $expiredGrants[$index] = GrantData::wrap($grantData);
                }

                $this->localStorages[$xuid] = new LocalStorage($xuid, $response['state'], $activeGrants, $expiredGrants);

                $logger->info('Fetched grants for ' . $xuid);
            }
        );

        return true;
    }

    /**
     * Sent a post request to the Quark API to grant a player a group
     *
     * @param string    $sourceXuid
     * @param string    $state
     * @param GrantData $grantData
     */
    public function postGrant(string $sourceXuid, string $state, GrantData $grantData): void {
        $data = [
            '_id' => $grantData->getId(),
            'group_id' => $grantData->getGroupId(),
            'source_xuid' => $sourceXuid,
            'created_at' => $grantData->getCreatedAt(),
            'who_granted' => $grantData->getWhoGranted(),
            'scopes' => $grantData->getScopes(),
        ];

        if ($grantData->getExpiresAt() !== null) $data['expires_at'] = $grantData->getExpiresAt();
        if ($grantData->getRevokedAt() !== null) $data['revoked_at'] = $grantData->getRevokedAt();
        if ($grantData->getWhoRevoked() !== null) $data['who_revoked'] = $grantData->getWhoRevoked();

        Curl::postRequest(
            Quark::URL . '/grants?state=' . $state,
            $data,
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to post grant');
                }

                $code = $result->getCode();
                if ($code === Quark::CODE_BAD_REQUEST) {
                    $message = 'Invalid grant data';
                } elseif ($code === Quark::CODE_NOT_FOUND) {
                    $message = 'API Route not found';
                } elseif ($code === Quark::CODE_FORBIDDEN) {
                    $message = 'API key is not set';
                } elseif ($code === Quark::CODE_UNAUTHORIZED) {
                    $message = 'This server is not authorized to create groups';
                } elseif ($code !== Quark::CODE_OK) {
                    $message = 'Failed to post grant (HTTP ' . $code . ')';
                } else {
                    $response = json_decode($result->getBody(), true);
                    if (!is_array($response) || !isset($response['message'])) {
                        $message = 'Invalid response';
                    } else {
                        $message = $response['message'];
                    }
                }

                Quark::getInstance()->getLogger()->error($message);
            }
        );
    }

    /**
     * @param string $xuid
     *
     * @return LocalStorage|null
     */
    public function getLocalStorage(string $xuid): ?LocalStorage {
        return $this->localStorages[$xuid] ?? null;
    }

    /**
     * @param string $xuid
     */
    public function unload(string $xuid): void {
        unset($this->localStorages[$xuid]);

        Curl::postRequest(
            Quark::URL . '/grants/unload',
            [
                'xuid' => $xuid,
                'timestamp' => Quark::now(),
            ],
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to unload grants');
                }

                $code = $result->getCode();
                if ($code === Quark::CODE_BAD_REQUEST_GATEWAY) {
                    $message = 'Timestamp is older than the last fetch timestamp';
                } elseif ($code === Quark::CODE_NOT_FOUND) {
                    $message = 'API Route not found';
                } elseif ($code === Quark::CODE_FORBIDDEN) {
                    $message = 'API key is not set';
                } elseif ($code === Quark::CODE_UNAUTHORIZED) {
                    $message = 'This server is not authorized to create groups';
                } elseif ($code !== Quark::CODE_OK) {
                    $message = 'Failed to unload grants (HTTP ' . $code . ')';
                } else {
                    $response = json_decode($result->getBody(), true);
                    if (!is_array($response) || !isset($response['message'])) {
                        $message = 'Invalid response';
                    } else {
                        $message = $response['message'];
                    }
                }

                Quark::getInstance()->getLogger()->error($message);
            }
        );
    }
}