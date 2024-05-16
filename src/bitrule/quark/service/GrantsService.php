<?php

declare(strict_types=1);

namespace bitrule\quark\service;

use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\GrantsInfo;
use bitrule\quark\object\LocalStorage;
use bitrule\quark\Quark;
use bitrule\quark\service\response\EmptyResponse;
use bitrule\quark\service\response\PongResponse;
use Closure;
use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\ObjectSet;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class GrantsService {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    public const PLAYER_NOT_FOUND_RESPONSE = 'Player not found';

    public const QUERY_XUID = '?xuid=';
    public const QUERY_NAME = '?name=';
    public const QUERY_STATE = '&state=';
    public const QUERY_TYPE = '&type=';

    /** @var array<string, GrantsInfo> */
    private array $grantsInfo = [];
    /** @var ObjectSet<string> */
    private ObjectSet $failedRequests;

    public function init(): void {
        $this->failedRequests = new ObjectSet();
    }

    /**
     * @param GrantsInfo $grantsInfo
     */
    public function cache(GrantsInfo $grantsInfo): void {
        $this->grantsInfo[$grantsInfo->getXuid()] = $grantsInfo;
    }

    /**
     * @param string $xuid
     *
     * @return GrantsInfo|null
     */
    public function getGrantsInfo(string $xuid): ?GrantsInfo {
        return $this->grantsInfo[$xuid] ?? null;
    }

    /**
     * @phpstan-return ObjectSet<string>
     */
    public function getFailedRequests(): ObjectSet {
        return $this->failedRequests;
    }

    /**
     * @param string  $query
     * @param Closure(GrantsInfo): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function requestGrants(string $query, Closure $onCompletion, Closure $onFail): void {
        Curl::getRequest(
            Quark::URL . '/grants' . $query,
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(Quark::CODE_BAD_REQUEST_GATEWAY, 'No response'));

                    return;
                }

                $code = $result->getCode();
                $response = json_decode($result->getBody(), true);
                $message = is_array($response) && isset($response['message']) ? $response['message'] : null;

                if ($code !== Quark::CODE_OK) {
                    $onFail(EmptyResponse::create(
                        $code,
                        $message
                    ));

                    return;
                }

                if (!is_array($response)) {
                    $onFail(EmptyResponse::create(Quark::CODE_INTERNAL_SERVER_ERROR, 'Invalid response'));

                    return;
                }

                if (!isset($response['known_name'])) {
                    $onFail(EmptyResponse::create(Quark::CODE_INTERNAL_SERVER_ERROR, self::PLAYER_NOT_FOUND_RESPONSE));

                    return;
                }

                if (!isset($response['state']) || !isset($response['xuid'])) {
                    $onFail(EmptyResponse::create(Quark::CODE_INTERNAL_SERVER_ERROR, 'State or xuid not found in response'));

                    return;
                }

                $onCompletion(new GrantsInfo(
                    $response['xuid'],
                    $response['known_name'],
                    $response['state'],
                    array_map(fn(array $grantData) => GrantData::wrap($grantData), $response['active'] ?? []),
                    array_map(fn(array $grantData) => GrantData::wrap($grantData), $response['expired'] ?? [])
                ));
            }
        );
    }

    /**
     * Sent a post request to the Quark API to grant a player a group
     *
     * @param GrantsInfo                   $grantsInfo
     * @param GrantData                    $grantData
     * @param Closure(PongResponse): void  $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function postGrant(GrantsInfo $grantsInfo, GrantData $grantData, Closure $onCompletion, Closure $onFail): void {
        $data = [
            '_id' => $grantData->getId(),
            'group_id' => $grantData->getGroupId(),
            'source_xuid' => $grantsInfo->getXuid(),
            'created_at' => $grantData->getCreatedAt(),
            'who_granted' => $grantData->getWhoGranted(),
            'scopes' => $grantData->getScopes(),
        ];

        if ($grantData->getExpiresAt() !== null) $data['expires_at'] = $grantData->getExpiresAt();
        if ($grantData->getRevokedAt() !== null) $data['revoked_at'] = $grantData->getRevokedAt();
        if ($grantData->getWhoRevoked() !== null) $data['who_revoked'] = $grantData->getWhoRevoked();

        $timestamp = microtime(true);

        Curl::postRequest(
            Quark::URL . '/grants?state=' . $grantsInfo->getState(),
            $data,
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($timestamp, $onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(Quark::CODE_BAD_REQUEST_GATEWAY, 'No response'));

                    return;
                }

                $code = $result->getCode();
                $response = json_decode($result->getBody(), true);
                $message = is_array($response) && isset($response['message']) ? $response['message'] : null;

                if ($code !== Quark::CODE_OK) {
                    $onFail(EmptyResponse::create(
                        $code,
                        $message
                    ));

                    return;
                }

                if ($message === null) {
                    $onFail(EmptyResponse::create(Quark::CODE_INTERNAL_SERVER_ERROR, 'Invalid response'));

                    return;
                }

                $onCompletion(new PongResponse(
                    $code,
                    $timestamp,
                    microtime(true)
                ));
            }
        );
    }

    /**
     * @param string $xuid
     */
    public function unload(string $xuid): void {
        unset($this->grantsInfo[$xuid]);

        Curl::postRequest(
            Quark::URL . '/grants/unload',
            [
                'xuid' => $xuid,
                'timestamp' => Quark::now(),
            ],
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($xuid): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to unload grants');
                }

                if ($result->getCode() === Quark::CODE_OK) {
                    Quark::getInstance()->getLogger()->info(TextFormat::GREEN . 'Unloaded grants for ' . $xuid);
                } else {
                    Quark::getInstance()->getLogger()->error('Failed to unload grants for ' . $xuid . ': ' . EmptyResponse::create($result->getCode())->getMessage());
                }
            }
        );
    }

    /**
     * @param string $xuid
     * @param bool   $online
     * @param string $type
     *
     * @return string
     */
    public static function createQueryByXuid(string $xuid, bool $online, string $type): string {
        return self::QUERY_XUID . $xuid . self::QUERY_STATE . ($online ? 'online' : 'offline') . self::QUERY_TYPE . $type;
    }

    /**
     * @param string $name
     * @param bool   $online
     * @param string $type
     *
     * @return string
     */
    public static function createQueryByName(string $name, bool $online, string $type): string {
        return self::QUERY_NAME . $name . self::QUERY_STATE . ($online ? 'online' : 'offline') . self::QUERY_TYPE . $type;
    }
}