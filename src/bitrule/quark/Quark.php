<?php

declare(strict_types=1);

namespace bitrule\quark;

use bitrule\quark\command\GrantCommand;
use bitrule\quark\command\GroupCommand;
use bitrule\quark\listener\PlayerChatListener;
use bitrule\quark\listener\PlayerJoinListener;
use bitrule\quark\listener\PlayerPreLoginListener;
use bitrule\quark\listener\PlayerQuitListener;
use bitrule\quark\service\GroupService;
use DateInterval;
use DateTime;
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
    public const CODE_BAD_REQUEST_GATEWAY = 502;

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

        GroupService::getInstance()->loadAll();

        $this->getServer()->getCommandMap()->registerAll('quart', [
            new GroupCommand('group', 'Manage our network groups'),
            new GrantCommand()
        ]);

        $this->getServer()->getPluginManager()->registerEvents(new PlayerPreLoginListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerJoinListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerChatListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerQuitListener(), $this);
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

    /**
     * @return string
     */
    public static function now(): string {
        return date('d-m-Y H:i:s');
    }

    /**
     * Parses the input string to a string date
     *
     * @param string $input
     *
     * @return string|null
     */
    public static function parseFromInput(string $input): ?string {
        $split = str_split($input);
        $numbers = '';

        $now = new DateTime('NOW');
        $changes = false;

        foreach ($split as $char) {
            if (is_numeric($char)) {
                $numbers .= $char;

                continue;
            }

            if (!is_numeric($numbers)) continue;

            try {
                $now->add(self::convertInputToValue((int) $numbers, $char));

                $changes = true;
            } catch (Exception $e) {
                self::getInstance()->getLogger()->logException($e);
            }
        }

        return $changes ? $now->format('d-m-Y H:i:s') : null;
    }

    /**
     * @param int    $value
     * @param string $unit
     *
     * @return DateInterval
     * @throws Exception
     */
    private static function convertInputToValue(int $value, string $unit): DateInterval {
        return match ($unit) {
            'M' => DateInterval::createFromDateString($value . ' months'),
            'h' => DateInterval::createFromDateString($value . ' hours'),
            'd' => DateInterval::createFromDateString($value . ' days'),
            'w' => self::convertInputToValue($value * 7, 'd'),
            'm' => DateInterval::createFromDateString($value . ' minutes'),
            'y' => DateInterval::createFromDateString($value . ' years'),
            default => throw new Exception('Invalid unit')
        };
    }
}