<?php

declare(strict_types=1);

namespace bitrule\quark\service;

use bitrule\quark\object\group\Group;
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
use function count;
use function is_array;
use function json_decode;
use function microtime;
use function round;
use function strtolower;

final class GroupService {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /** @var array<string, Group> */
    private array $groups = [];

    /**
     * Requests all groups from the API
     */
    public function loadAll(): void {
        $timestamp = microtime(true);

        Curl::getRequest(
            Service::URL . '/groups',
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($timestamp): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to fetch groups');
                }

                if ($result->getCode() === Service::CODE_NOT_FOUND) {
                    throw new RuntimeException('API Route not found');
                }

                if ($result->getCode() === Service::CODE_FORBIDDEN) {
                    throw new RuntimeException('API key is not set');
                }

                if ($result->getCode() === Service::CODE_UNAUTHORIZED) {
                    throw new RuntimeException('This server is not authorized to fetch groups');
                }

                if ($result->getCode() !== Service::CODE_OK) {
                    throw new RuntimeException('Failed to fetch groups (HTTP ' . $result->getCode() . ')');
                }

                $response = json_decode($result->getBody(), true);
                if (!is_array($response)) {
                    throw new RuntimeException('Invalid response');
                }

                foreach ($response as $groupData) {
                    if (!is_array($groupData)) {
                        throw new RuntimeException('Invalid group data');
                    }

                    if (!isset($groupData['_id'], $groupData['name'], $groupData['priority'])) {
                        throw new RuntimeException('Invalid group data');
                    }

                    $group = new Group(
                        $groupData['_id'],
                        $groupData['name'],
                        $groupData['priority'],
                        $groupData['display'] ?? null,
                        $groupData['prefix'] ?? null,
                        $groupData['suffix'] ?? null,
                        $groupData['color'] ?? null
                    );

                    $this->groups[strtolower($group->getName())] = $group;
                }

                Quark::getInstance()->getLogger()->info(TextFormat::GREEN . 'Loaded ' . count($this->groups) . ' groups in ' . round(microtime(true) - $timestamp, 2) . 'ms');
            }
        );
    }

    /**
     * @param Group                        $group
     * @param Closure(PongResponse): void  $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function postCreate(Group $group, Closure $onCompletion, Closure $onFail): void {
        $data = [
        	'id' => $group->getId(),
        	'name' => $group->getName(),
        	'priority' => $group->getPriority()
        ];

        if ($group->getDisplayName() !== null) $data['display'] = $group->getDisplayName();
        if ($group->getPrefix() !== null) $data['prefix'] = $group->getPrefix();
        if ($group->getSuffix() !== null) $data['suffix'] = $group->getSuffix();
        if ($group->getColor() !== null) $data['color'] = $group->getColor();

        $timestamp = microtime(true);

        Curl::postRequest(
            Service::URL . '/groups/create',
            $data,
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($onCompletion, $onFail, $timestamp): void {
                if ($result === null) {
                    $onFail(new EmptyResponse(Service::CODE_BAD_REQUEST_GATEWAY, 'No response'));

                    return;
                }

                $response = json_decode($result->getBody(), true);
                $code = $result->getCode();

                if ($code !== Service::CODE_OK) {
                    $onFail(EmptyResponse::create(
                        $code,
                        is_array($response) && isset($response['message']) ? $response['message'] : null
                    ));

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
     * @param Group $group
     */
    public function registerNewGroup(Group $group): void {
        $this->groups[strtolower($group->getName())] = $group;
    }

    /**
     * @param string $name
     *
     * @return Group|null
     */
    public function getGroupByName(string $name): ?Group {
        return $this->groups[strtolower($name)] ?? null;
    }

    /**
     * @param string $id
     *
     * @return Group|null
     */
    public function getGroupById(string $id): ?Group {
        foreach ($this->groups as $group) {
            if ($group->getId() !== $id) continue;

            return $group;
        }

        return null;
    }
}