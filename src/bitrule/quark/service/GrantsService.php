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
use function array_map;
use function is_array;
use function json_decode;
use function microtime;
use function strtolower;

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
     * @param string                       $id
     * @param string                       $type
     * @param PlayerState                  $state
     * @param Closure(GrantsInfo): void    $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function lookup(string $id, string $type, PlayerState $state, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(Service::CODE_BAD_REQUEST_GATEWAY, 'Service is not running'));

            return;
        }

        $grantsInfo = $this->getGrantsInfo($id);
        if ($grantsInfo !== null) {
            $onCompletion($grantsInfo);

            return;
        }

        Curl::getRequest(
            Service::URL . '/grants/' . $id . '/lookup/' . $type . '?state=' . $state->name,
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
            Service::URL . '/grants/' . $grantsInfo->getXuid() . '/save?state=' . strtolower($grantsInfo->getState()->name),
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
    }
}