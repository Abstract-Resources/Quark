<?php

declare(strict_types=1);

namespace bitrule\quark;

use bitrule\quark\provider\RestAPIProvider;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

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
    }
}