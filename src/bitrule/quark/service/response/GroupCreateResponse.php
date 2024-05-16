<?php

declare(strict_types=1);

namespace bitrule\quark\service\response;

final class GroupCreateResponse {

    /**
     * @param int    $statusCode
     * @param string $message
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $message
    ) {}

    /**
     * @return int
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }
}