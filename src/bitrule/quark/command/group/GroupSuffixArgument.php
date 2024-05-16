<?php

declare(strict_types=1);

namespace bitrule\quark\command\group;

use abstractplugin\command\Argument;
use bitrule\quark\Quark;
use bitrule\quark\service\GroupService;
use bitrule\quark\service\response\EmptyResponse;
use bitrule\quark\service\response\GroupCreateResponse;
use bitrule\quark\service\response\PongResponse;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class GroupSuffixArgument extends Argument {

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param array         $args
     */
    public function onConsoleExecute(CommandSender $sender, string $commandLabel, array $args): void {
        $groupName = array_shift($args);
        if (!is_string($groupName)) {
            $sender->sendMessage(TextFormat::RED . 'Group name must be a string');

            return;
        }

        $suffix = array_shift($args);
        if (!is_string($suffix)) {
            $sender->sendMessage(TextFormat::RED . 'Suffix must be a string');

            return;
        }

        $group = GroupService::getInstance()->getGroupByName($groupName);
        if ($group === null) {
            $sender->sendMessage(TextFormat::RED . 'Group ' . $groupName . ' does not exist');

            return;
        }

        $group->setSuffix($suffix);

        GroupService::getInstance()->postCreate(
            $group,
            function (PongResponse $pong) use ($suffix, $group, $sender): void {
                $sender->sendMessage(sprintf(
                    Quark::prefix() . TextFormat::colorize('&aThe suffix of the group %s has been set to \'&b%s&a\' in %.2fms'),
                    $group->getName(),
                    $suffix,
                    round($pong->getResponseTimestamp() - $pong->getInitialTimestamp(), 2)
                ));
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(Quark::prefix() . $response->getMessage());

                Quark::getInstance()->getLogger()->error('[Status Code: ' . $response->getStatusCode() . '] => ' . $response->getMessage());
            }
        );
    }
}