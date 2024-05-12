<?php

declare(strict_types=1);

namespace bitrule\quark;

use bitrule\quark\command\GroupCommand;
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

    protected function onLoad(): void {
        $this->saveDefaultConfig();
    }

    protected function onEnable(): void {
        self::setInstance($this);

        try {
            Curl::register($this);
        } catch (Exception $e) {
            if ($e instanceof InvalidArgumentException) {
                $this->getLogger()->warning('libasynCurl is already loaded!');

                return;
            }

            $this->getLogger()->logException($e);
        }

        $apiKey = $this->getConfig()->get('api-key');
        if (!is_string($apiKey)) {
            throw new InvalidArgumentException('Invalid API key');
        }

        GroupRegistry::getInstance()->loadAll($apiKey);

        $this->getServer()->getCommandMap()->registerAll('quart', [
            new GroupCommand('group', 'Manage our network groups')
        ]);
    }

    /**
     * @return string
     */
    public static function prefix(): string {
        return TextFormat::ESCAPE . 's' . TextFormat::BOLD . 'Quark' . TextFormat::RESET . TextFormat::DARK_GRAY . '> ';
    }
}
