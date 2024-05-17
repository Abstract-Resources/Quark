<?php

declare(strict_types=1);

namespace bitrule\quark;

use bitrule\quark\command\GrantCommand;
use bitrule\quark\command\GroupCommand;
use bitrule\quark\listener\PlayerChatListener;
use bitrule\quark\listener\PlayerJoinListener;
use bitrule\quark\listener\PlayerLoginListener;
use bitrule\quark\listener\PlayerPreLoginListener;
use bitrule\quark\listener\PlayerQuitListener;
use bitrule\quark\service\GroupService;
use bitrule\services\Service;
use DateInterval;
use DateTime;
use Exception;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use function date;
use function is_bool;
use function is_numeric;
use function str_split;

final class Quark extends PluginBase {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    protected function onLoad(): void {
        $this->saveDefaultConfig();
    }

    protected function onEnable(): void {
        self::setInstance($this);

        Service::getInstance()->load($this);

        GroupService::getInstance()->loadAll();

        $this->getServer()->getCommandMap()->registerAll('quart', [
        	new GroupCommand('group', 'Manage our network groups'),
        	new GrantCommand()
        ]);

        $this->getServer()->getPluginManager()->registerEvents(new PlayerPreLoginListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerLoginListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerJoinListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerChatListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerQuitListener(), $this);
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
        $dateInterval = match ($unit) {
            'M' => DateInterval::createFromDateString($value . ' months'),
            'h' => DateInterval::createFromDateString($value . ' hours'),
            'd' => DateInterval::createFromDateString($value . ' days'),
            'w' => self::convertInputToValue($value * 7, 'd'),
            'm' => DateInterval::createFromDateString($value . ' minutes'),
            'y' => DateInterval::createFromDateString($value . ' years'),
            default => throw new Exception('Invalid unit')
        };

        if (is_bool($dateInterval)) {
            throw new Exception('Invalid unit');
        }

        return $dateInterval;
    }
}