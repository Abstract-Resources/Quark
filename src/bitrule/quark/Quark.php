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
use bitrule\services\listener\PlayerLoginListener;
use bitrule\services\Service;
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
}