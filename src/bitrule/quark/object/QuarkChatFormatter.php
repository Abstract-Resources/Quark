<?php

declare(strict_types=1);

namespace bitrule\quark\object;

use bitrule\quark\service\GroupService;
use pocketmine\lang\Translatable;
use pocketmine\player\chat\ChatFormatter;
use pocketmine\utils\TextFormat;

final class QuarkChatFormatter implements ChatFormatter {

    /**
     * @param GrantsInfo $grantsInfo
     */
    public function __construct(private readonly GrantsInfo $grantsInfo) {}

    /**
     * Returns the formatted message to broadcast.
     * This can return a plain string (which will be used as-is) or a Translatable (which will be translated into
     * each recipient's language).
     */
    public function format(string $username, string $message): Translatable|string {
        $highestGroup = $this->grantsInfo->getHighestGroup();
        if ($highestGroup === null) {
            $highestGroup = GroupService::getInstance()->getGroupByName('default');
        }

        $prefix = $highestGroup !== null && $highestGroup->getPrefix() !== null ? $highestGroup->getPrefix() : TextFormat::GRAY;
        $suffix = $highestGroup !== null && $highestGroup->getSuffix() !== null ? $highestGroup->getSuffix() : TextFormat::DARK_GRAY . ': ';

        return TextFormat::colorize($prefix . $username . $suffix . $message);
    }
}