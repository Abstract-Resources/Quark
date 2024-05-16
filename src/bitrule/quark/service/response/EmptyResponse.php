<?php

declare(strict_types=1);

namespace bitrule\quark\service\response;

use bitrule\quark\Quark;

final class EmptyResponse {

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

    /**
     * @param int         $code
     * @param string|null $message
     *
     * @return self
     */
    public static function create(int $code, ?string $message = null): self {
        if ($message === null) {
            $message = match ($code) {
                Quark::CODE_BAD_REQUEST => 'Invalid group data',
                Quark::CODE_NOT_FOUND => 'API Route not found',
                Quark::CODE_FORBIDDEN => 'API key is not set',
                Quark::CODE_UNAUTHORIZED => 'This server is not authorized to create groups',
                Quark::CODE_INTERNAL_SERVER_ERROR => 'Internal server error',
                Quark::CODE_BAD_REQUEST_GATEWAY => 'Bad request gateway',
                default => 'Failed to create group (HTTP ' . $code . ')'
            };
        }

        return new self(
            $code,
            $message
        );
    }
}