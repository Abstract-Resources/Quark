<?php

declare(strict_types=1);

namespace bitrule\quark;

use bitrule\quark\command\GroupCommand;
use bitrule\quark\provider\RestAPIProvider;
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

        RestAPIProvider::getInstance()->loadAll($this);

        $this->getServer()->getCommandMap()->registerAll('quart', [
            new GroupCommand('group', 'Manage our network groups')
        ]);
    }

    /**
     * @return string
     */
    public static function prefix(): string {
        return TextFormat::ESCAPE . 's' . TextFormat::BOLD . 'Quart' . TextFormat::RESET . TextFormat::DARK_GRAY . '> ';
    }
}