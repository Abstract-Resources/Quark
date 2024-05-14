<?php

declare(strict_types=1);

namespace bitrule\quark\registry;

use bitrule\quark\group\Group;
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

    // API URL
    public const URL = 'http://127.0.0.1:3000/api';

    // HTTP status codes
    public const CODE_OK = 200;
    public const CODE_BAD_REQUEST = 400;
    public const CODE_FORBIDDEN = 401;
    public const CODE_UNAUTHORIZED = 403;
    public const CODE_NOT_FOUND = 404;
    public const CODE_INTERNAL_SERVER_ERROR = 500;

    private array $defaultHeaders = [
        'Content-Type: application/json'
    ];

    private ?string $apiKey = null;

    /** @var array<string, Group> */
    private array $groups = [];

    /**
     * @param string $apiKey The API key
     */
    public function loadAll(string $apiKey): void {
        $this->apiKey = $apiKey;

        $this->defaultHeaders[] = 'X-API-KEY: ' . $apiKey;

        $timestamp = microtime(true);

        Curl::getRequest(
            self::URL . '/groups',
            10,
            $this->defaultHeaders,
            function (?InternetRequestResult $result) use ($timestamp): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to fetch groups');
                }

                if ($result->getCode() === self::CODE_NOT_FOUND) {
                    throw new RuntimeException('API Route not found');
                }

                if ($result->getCode() === self::CODE_FORBIDDEN) {
                    throw new RuntimeException('API key is not set');
                }

                if ($result->getCode() === self::CODE_UNAUTHORIZED) {
                    throw new RuntimeException('This server is not authorized to fetch groups');
                }

                if ($result->getCode() !== self::CODE_OK) {
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

                    $this->groups[$group->getName()] = $group;
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
        $promiseResolver = new PromiseResolver();
        $logger = Quark::getInstance()->getLogger();

        $timestamp = microtime(true);
        if ($this->apiKey === null) {
            $promiseResolver->resolve(new Pong(
                self::CODE_FORBIDDEN,
                $timestamp,
                $timestamp,
                'API key is not set'
            ));

            $logger->error('API key is not set');

            return $promiseResolver->getPromise();
        }

        $data = [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'priority' => $group->getPriority()
        ];

        if ($group->getDisplayName() !== null) $data['display'] = $group->getDisplayName();
        if ($group->getPrefix() !== null) $data['prefix'] = $group->getPrefix();
        if ($group->getSuffix() !== null) $data['suffix'] = $group->getSuffix();
        if ($group->getColor() !== null) $data['color'] = $group->getColor();

        Curl::postRequest(
            self::URL . '/groups/create',
            $data,
            10,
            $this->defaultHeaders,
            function (?InternetRequestResult $result) use ($timestamp, $logger, $promiseResolver): void {
                $code = $result !== null ? $result->getCode() : self::CODE_NOT_FOUND;
                $message = '';
                if ($code === self::CODE_NOT_FOUND) {
                    $logger->error('Failed to create group');
                } elseif ($code === self::CODE_FORBIDDEN) {
                    $logger->error('API key is not set');
                } elseif ($code === self::CODE_UNAUTHORIZED) {
                    $logger->error('This server is not authorized to create groups');
                } elseif ($code !== self::CODE_OK) {
                    $logger->error('Failed to create group');
                } else {
                    $response = $result !== null ? json_decode($result->getBody(), true) : null;
                    if (!is_array($response) || !isset($response['message'])) {
                        $code = self::CODE_BAD_REQUEST;
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
}