<?php

declare(strict_types=1);

namespace bitrule\quark\command;

use abstractplugin\command\BaseCommand;
use bitrule\quark\command\group\GroupCreateArgument;
use bitrule\quark\command\group\GroupDisplayArgument;
use bitrule\quark\command\group\GroupPrefixArgument;
use bitrule\quark\command\group\GroupPriorityArgument;
use bitrule\quark\command\group\GroupSuffixArgument;
use pocketmine\lang\Translatable;

final class GroupCommand extends BaseCommand {

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = []) {
        parent::__construct($name, $description, $usageMessage, $aliases);

        $this->setPermission($this->getPermission());

        $this->registerParent(
            new GroupCreateArgument('create', $this->getPermission() . '.create'),
            new GroupPrefixArgument('prefix', $this->getPermission() . '.prefix'),
            new GroupSuffixArgument('suffix', $this->getPermission() . '.suffix'),
            new GroupDisplayArgument('display', $this->getPermission() . '.display'),
            new GroupPriorityArgument('priority', $this->getPermission() . '.priority')
        );
    }

    /**
     * @return string|null
     */
    public function getPermission(): ?string {
        return 'quark.command.group';
    }
}