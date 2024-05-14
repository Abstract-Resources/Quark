<?php

declare(strict_types=1);

namespace bitrule\quark;

final class Pong {

    /**
     * @param int    $statusCode
     * @param float  $initialTimestamp
     * @param float  $responseTimestamp
     * @param string $message
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly float $initialTimestamp,
        private readonly float $responseTimestamp,
        private readonly string $message
    ) {}

    /**
     * Status code returned by the server
     *
     * @return int
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * @return float
     */
    public function getInitialTimestamp(): float {
        return $this->initialTimestamp;
    }

    /**
     * @return float
     */
    public function getResponseTimestamp(): float {
        return $this->responseTimestamp;
    }

    /**
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }
}