<?php

declare(strict_types=1);

namespace bitrule\quark\provider;

use bitrule\quark\group\Group;
use bitrule\quark\Quark;
use Exception;
use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\SingletonTrait;
use RuntimeException;

final class RestAPIProvider {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    // API URL
    public const URL = 'http://127.0.0.1:3000';

    // HTTP status codes
    public const CODE_OK = 200;
    public const CODE_BAD_REQUEST = 400;
    public const CODE_UNAUTHORIZED = 401;
    public const CODE_FORBIDDEN = 403;
    public const CODE_NOT_FOUND = 404;
    public const CODE_INTERNAL_SERVER_ERROR = 500;

    private ?string $apiKey = null;

    /** @var array<string, Group> */
    private array $groups = [];

    /**
     * @param Quark $plugin
     */
    public function loadAll(Quark $plugin): void {
        $apiKey = $plugin->getConfig()->get('api-key');
        if (!is_string($apiKey)) {
            throw new \InvalidArgumentException('Invalid API key');
        }

        $this->apiKey = $apiKey;

        try {
            Curl::register($plugin);
        } catch (Exception $e) {
            $plugin->getLogger()->warning('libasynCurl is already loaded!');
            $plugin->getLogger()->logException($e);
        }

        Curl::getRequest(
            self::URL . '/groups',
            10,
            ['x-api-key' => $apiKey],
            function (?InternetRequestResult $result) use ($plugin): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to fetch groups');
                }

                if ($result->getCode() === self::CODE_UNAUTHORIZED) {
                    $plugin->getLogger()->error('This server is not authorized to fetch groups');

                    return;
                }

                if ($result->getCode() !== self::CODE_OK) {
                    $plugin->getLogger()->error('Failed to fetch groups');

                    return;
                }

                $response = json_decode($result->getBody(), true);
                if (!is_array($response)) {
                    throw new RuntimeException('Invalid response');
                }

                foreach ($response as $groupData) {
                    if (!is_array($groupData)) {
                        throw new RuntimeException('Invalid group data');
                    }

                    if (!isset($groupData['id'], $groupData['name'], $groupData['priority'])) {
                        throw new RuntimeException('Invalid group data');
                    }

                    $group = new Group(
                        $groupData['id'],
                        $groupData['name'],
                        $groupData['priority'],
                        $groupData['displayName'] ?? null,
                        $groupData['prefix'] ?? null,
                        $groupData['suffix'] ?? null,
                        $groupData['color'] ?? null
                    );

                    $this->groups[$group->getName()] = $group;
                }
            }
        );
    }

    /**
     * @param string $name
     *
     * @return Group|null
     */
    public function getGroupByName(string $name): ?Group {
        return $this->groups[$name] ?? null;
    }

    /**
     * @param Group $group
     */
    public function create(Group $group): void {
        if ($this->apiKey === null) {
            throw new RuntimeException('API key is not set');
        }

        Curl::postRequest(
            self::URL . '/groups/create',
            [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'priority' => $group->getPriority(),
                'display' => $group->getDisplayName(),
                'prefix' => $group->getPrefix(),
                'suffix' => $group->getSuffix(),
                'color' => $group->getColor()
            ],
            10,
            ['x-api-key' => $this->apiKey],
            function (?InternetRequestResult $result): void {
                if ($result === null) {
                    throw new RuntimeException('Failed to create group');
                }

                if ($result->getCode() === self::CODE_UNAUTHORIZED) {
                    throw new RuntimeException('This server is not authorized to create groups');
                }

                if ($result->getCode() !== self::CODE_OK) {
                    throw new RuntimeException('Failed to create group');
                }

                $response = json_decode($result->getBody(), true);
                if (!is_array($response)) {
                    throw new RuntimeException('Invalid response');
                }

                if (!isset($response['message'])) {
                    throw new RuntimeException('Invalid response');
                }

                Quark::getInstance()->getLogger()->info('Provider: ' . $response['message']);
            }
        );
    }
}