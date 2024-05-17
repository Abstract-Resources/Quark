<?php

declare(strict_types=1);

namespace bitrule\quark\service;

use bitrule\gorufus\object\query\PlayerState;
use bitrule\quark\object\grant\GrantData;
use bitrule\quark\object\GrantsInfo;
use bitrule\quark\Quark;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use bitrule\services\Service;
use Closure;
use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use RuntimeException;
use function array_map;
use function array_search;
use function in_array;
use function is_array;
use function json_decode;
use function microtime;

final class GrantsService {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    public const QUERY_XUID = '?xuid=';
    public const QUERY_NAME = '?name=';
    public const QUERY_STATE = '&state=';
    public const QUERY_TYPE = '&type=';

    /** @var array<string, GrantsInfo> */
    private array $grantsInfo = [];
    /** @var string[] */
    private array $failedRequests = [];

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
     * @param string  $query
     * @param Closure(GrantsInfo): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function requestGrants(string $query, Closure $onCompletion, Closure $onFail): void {
        Curl::getRequest(
            Service::URL . '/grants' . $query,
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(Service::CODE_BAD_REQUEST_GATEWAY, 'No response'));

                    return;
                }

                $code = $result->getCode();
                $response = json_decode($result->getBody(), true);
                $message = is_array($response) && isset($response['message']) ? $response['message'] : null;

                if ($code !== Service::CODE_OK) {
                    $onFail(EmptyResponse::create(
                        $code,
                        $message
                    ));

                    return;
                }

                if (!is_array($response)) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'Invalid response'));

                    return;
                }

                if (!isset($response['known_name'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, Service::PLAYER_NOT_FOUND_RESPONSE));

                    return;
                }

                if (!isset($response['state']) || !isset($response['xuid'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'State or xuid not found in response'));

                    return;
                }

                $onCompletion(new GrantsInfo(
                    $response['xuid'],
                    $response['known_name'],
                    PlayerState::valueOf($response['state']),
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
            Service::URL . '/grants?state=' . strtolower($grantsInfo->getState()->name),
            $data,
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($timestamp, $onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(Service::CODE_BAD_REQUEST_GATEWAY, 'No response'));

                    return;
                }

                $code = $result->getCode();
                $response = json_decode($result->getBody(), true);
                $message = is_array($response) && isset($response['message']) ? $response['message'] : null;

                if ($code !== Service::CODE_OK) {
                    $onFail(EmptyResponse::create(
                        $code,
                        $message
                    ));

                    return;
                }

                if ($message === null) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'Invalid response'));

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
            Service::URL . '/grants/unload',
            [
            	'xuid' => $xuid,
            	'timestamp' => Quark::now(),
            ],
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($xuid): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to unload grants');
                }

                if ($result->getCode() === Service::CODE_OK) {
                    Quark::getInstance()->getLogger()->info(TextFormat::GREEN . 'Unloaded grants for ' . $xuid);
                } else {
                    Quark::getInstance()->getLogger()->error('Failed to unload grants for ' . $xuid . ': ' . EmptyResponse::create($result->getCode())->getMessage());
                }
            }
        );
    }

    /**
     * @param string $xuid
     */
    public function addFailedRequest(string $xuid): void {
        $this->failedRequests[] = $xuid;
    }

    /**
     * @param string $xuid
     *
     * @return bool
     */
    public function hasFailedRequest(string $xuid): bool {
        return in_array($xuid, $this->failedRequests, true);
    }

    /**
     * @param string $xuid
     */
    public function removeFailedRequest(string $xuid): void {
        $index = array_search($xuid, $this->failedRequests, true);
        if ($index === false) {
            throw new RuntimeException('Failed request not found');
        }

        unset($this->failedRequests[$index]);
    }
}