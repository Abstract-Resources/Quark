<?php

declare(strict_types=1);

namespace bitrule\quark;

use bitrule\quark\command\GroupCommand;
use bitrule\quark\listener\PlayerJoinListener;
use bitrule\quark\listener\PlayerPreLoginListener;
use bitrule\quark\registry\GroupRegistry;
use Exception;
use InvalidArgumentException;
use libasynCurl\Curl;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

final class Quark extends PluginBase {
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

    protected function onLoad(): void {
        $this->saveDefaultConfig();
    }

    protected function onEnable(): void {
        self::setInstance($this);

        $bootstrap = 'phar://' . $this->getServer()->getPluginPath() . $this->getName() . '.phar/vendor/autoload.php';
        if (!is_file($bootstrap)) {
            $this->getLogger()->error('Could not find autoload.php in plugin phar, directory: ' . $bootstrap);
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        require_once $bootstrap;

        try {
            Curl::register($this);
        } catch (Exception $e) {
            if ($e instanceof InvalidArgumentException) {
                $this->getLogger()->warning('libasynCurl is already loaded!');

                return;
            }

            $this->getLogger()->logException($e);
        }

        $this->saveDefaultConfig();

        $apiKey = $this->getConfig()->get('api-key');
        if (!is_string($apiKey)) {
            throw new InvalidArgumentException('Invalid API key');
        }

        $this->defaultHeaders[] = 'X-API-KEY: ' . $apiKey;

        GroupRegistry::getInstance()->loadAll();

        $this->getServer()->getCommandMap()->registerAll('quart', [
            new GroupCommand('group', 'Manage our network groups')
        ]);

        $this->getServer()->getPluginManager()->registerEvents(new PlayerPreLoginListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerJoinListener(), $this);
    }

    /**
     * @return array
     */
    public static function defaultHeaders(): array {
        return self::getInstance()->defaultHeaders;
    }

    /**
     * @return string
     */
    public static function prefix(): string {
        return TextFormat::ESCAPE . 's' . TextFormat::BOLD . 'Quark' . TextFormat::RESET . TextFormat::DARK_GRAY . '> ';
    }
}
