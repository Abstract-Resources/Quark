<?php

declare(strict_types=1);

namespace bitrule\quark\provider;

use bitrule\quark\group\Group;
use bitrule\quark\Quark;
use Exception;
use libasynCurl\Curl;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
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
     * @param Group $group
     *
     * @return Promise<int>
     */
    public function postCreate(Group $group): Promise {
        $logger = Quark::getInstance()->getLogger();

        $promiseResolver = new PromiseResolver();

        if ($this->apiKey === null) {
            $promiseResolver->reject();

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
            ['x-api-key' => $this->apiKey],
            function (?InternetRequestResult $result) use ($logger, $promiseResolver): void {
                if ($result === null) {
                    $promiseResolver->reject();

                    $logger->error('Failed to create group');

                    return;
                }

                $code = $result->getCode();
                if ($code === self::CODE_UNAUTHORIZED) {
                    $logger->error('This server is not authorized to create groups');
                } elseif ($code !== self::CODE_OK) {
                    $logger->error('Failed to create group');
                } else {
                    $response = json_decode($result->getBody(), true);
                    if (!is_array($response)) {
                        throw new RuntimeException('Invalid response');
                    }

                    if (!isset($response['message'])) {
                        throw new RuntimeException('Invalid response');
                    }
                }

                $promiseResolver->resolve($code);
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