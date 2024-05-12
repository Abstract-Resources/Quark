<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\group\Group;
use bitrule\quark\provider\RestAPIProvider;
use bitrule\quark\Quark;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

final class GroupCreateArgument extends Argument {

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param array         $args
     */
    public function onConsoleExecute(CommandSender $sender, string $commandLabel, array $args): void {
        $name = array_shift($args);
        if (!is_string($name)) {
            $sender->sendMessage(TextFormat::RED . 'Name must be a string');

            return;
        }

        if (RestAPIProvider::getInstance()->getGroupByName($name) !== null) {
            $sender->sendMessage(Quark::prefix() . TextFormat::RED . 'Group ' . $name . ' already exists');

            return;
        }

        RestAPIProvider::getInstance()->create(new Group(Uuid::uuid4()->toString(), $name));
        $sender->sendMessage(Quark::prefix() . TextFormat::GREEN . 'Group ' . $name . ' created');
    }
}