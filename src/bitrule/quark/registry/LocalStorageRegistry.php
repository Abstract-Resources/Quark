<?php

declare(strict_types=1);

namespace bitrule\quark\registry;

use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\LocalStorage;
use bitrule\quark\Quark;
use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\SingletonTrait;
use RuntimeException;

final class LocalStorageRegistry {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /** @var array<string, LocalStorage> */
    private array $localStorages = [];
    /** @var string[] */
    private array $pendingRequests = [];

    /**
     * @param string $xuid
     *
     * @return bool
     */
    public function fetchByXuid(string $xuid): bool {
        if (in_array($xuid, $this->pendingRequests, true)) return false;

        $this->pendingRequests[] = $xuid;

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

                $pendingRequestIndex = array_search($xuid, $this->pendingRequests, true);
                if (!is_int($pendingRequestIndex)) {
                    $logger->error('Failed to find pending request');

                    return;
                }

                unset($this->pendingRequests[$pendingRequestIndex]);

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

                $activeGrants = $response['active'] ?? [];
                $expiredGrants = $response['expired'] ?? [];

                foreach ($activeGrants as $index => $grantData) {
                    $activeGrants[$index] = GrantData::wrap($grantData);
                }

                foreach ($expiredGrants as $index => $grantData) {
                    $expiredGrants[$index] = GrantData::wrap($grantData);
                }

                $this->localStorages[$xuid] = new LocalStorage($xuid, $activeGrants, $expiredGrants);

                $logger->info('Fetched grants for ' . $xuid);
            }
        );

        return true;
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
     *
     * @return bool
     */
    public function hasPendingRequest(string $xuid): bool {
        return in_array($xuid, $this->pendingRequests, true);
    }

    /**
     * @param string $xuid
     */
    public function unloadLocalStorage(string $xuid): void {
        unset($this->localStorages[$xuid]);

        $pendingRequestIndex = array_search($xuid, $this->pendingRequests, true);
        if (is_int($pendingRequestIndex)) {
            unset($this->pendingRequests[$pendingRequestIndex]);
        }

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