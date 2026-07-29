<?php

namespace App\Services\AI;

use RuntimeException;
use Throwable;

/**
 * Normalized exception every provider adapter throws on failure, so
 * AIGatewayService applies the same retry/failover policy regardless of
 * which provider threw it (docs/06-AI-Provider-Interface.md §2.4).
 */
class AIProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $retryable = false,
        private readonly bool $timeout = false,
        private readonly bool $invalidJson = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isTimeout(): bool
    {
        return $this->timeout;
    }

    /**
     * True when the provider responded successfully but the content
     * wasn't valid JSON - AIResponseProcessor retries this with a
     * corrective prompt against the *same* provider, unlike a transport
     * failure which triggers failover to the user's secondary provider
     * (FR-AI-06).
     */
    public function isInvalidJson(): bool
    {
        return $this->invalidJson;
    }
}
