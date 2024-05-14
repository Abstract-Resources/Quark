<?php

declare(strict_types=1);

namespace bitrule\quark\registry;

use bitrule\quark\object\group\Group;
use bitrule\quark\Pong;
use bitrule\quark\Quark;
use libasynCurl\Curl;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class GroupRegistry {
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
            Quark::URL . '/groups',
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($timestamp): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to fetch groups');
                }

                if ($result->getCode() === Quark::CODE_NOT_FOUND) {
                    throw new RuntimeException('API Route not found');
                }

                if ($result->getCode() === Quark::CODE_FORBIDDEN) {
                    throw new RuntimeException('API key is not set');
                }

                if ($result->getCode() === Quark::CODE_UNAUTHORIZED) {
                    throw new RuntimeException('This server is not authorized to fetch groups');
                }

                if ($result->getCode() !== Quark::CODE_OK) {
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
     * @param Group $group
     *
     * @return Promise<Pong>
     */
    public function postCreate(Group $group): Promise {
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

        $logger = Quark::getInstance()->getLogger();
        $promiseResolver = new PromiseResolver();

        Curl::postRequest(
            Quark::URL . '/groups/create',
            $data,
            10,
            Quark::defaultHeaders(),
            function (?InternetRequestResult $result) use ($timestamp, $logger, $promiseResolver): void {
                $code = $result !== null ? $result->getCode() : Quark::CODE_NOT_FOUND;
                $message = '';
                if ($code === Quark::CODE_NOT_FOUND) {
                    $logger->error('Failed to create group');
                } elseif ($code === Quark::CODE_FORBIDDEN) {
                    $logger->error('API key is not set');
                } elseif ($code === Quark::CODE_UNAUTHORIZED) {
                    $logger->error('This server is not authorized to create groups');
                } elseif ($code !== Quark::CODE_OK) {
                    $logger->error('Failed to create group');
                } else {
                    $response = $result !== null ? json_decode($result->getBody(), true) : null;
                    if (!is_array($response) || !isset($response['message'])) {
                        $code = Quark::CODE_BAD_REQUEST;
                    } else {
                        $message = $response['message'];
                    }
                }

                $promiseResolver->resolve(new Pong(
                    $code,
                    $timestamp,
                    microtime(true),
                        $message
                ));
            }
        );

        return $promiseResolver->getPromise();
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