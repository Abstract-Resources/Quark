<?php

declare(strict_types=1);

namespace bitrule\quark\command;

use abstractplugin\command\BaseCommand;
use bitrule\quark\command\group\GroupCreateArgument;
use pocketmine\lang\Translatable;

final class GroupCommand extends BaseCommand {

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = []) {
        parent::__construct($name, $description, $usageMessage, $aliases);

        $this->setPermission($this->getPermission());

        $this->registerParent(
            new GroupCreateArgument('create', $this->getPermission() . '.create')
        );
    }

    /**
     * @return string|null
     */
    public function getPermission(): ?string {
        return 'quark.command.group';
    }
}