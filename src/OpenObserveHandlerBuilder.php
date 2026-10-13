<?php

namespace Minhyung\Monolog;

use Monolog\Level;
use Psr\Log\LoggerInterface;

/** Mutable builder. The last authentication setting wins. */
class OpenObserveHandlerBuilder
{
    private ?string $token = null;
    private bool $ignoreFailure = false;
    private int|string|Level $level = Level::Debug;
    private bool $bubble = true;
    private ?LoggerInterface $fallbackLogger = null;
    private float $connectTimeout = 2.0;
    private float $timeout = 5.0;

    public function __construct(
        private string $host,
        private string $organizationId,
        private string $streamName,
    ) {
    }

    /** Accept the token without the Basic prefix; validation happens at build(). */
    public function withToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function withCredentials(string $username, string $password): self
    {
        return $this->withToken(base64_encode("{$username}:{$password}"));
    }

    public function withIgnoreFailure(bool $ignoreFailure): self
    {
        $this->ignoreFailure = $ignoreFailure;
        return $this;
    }

    public function withLevel(int|string|Level $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function withBubble(bool $bubble): self
    {
        $this->bubble = $bubble;
        return $this;
    }

    public function withFallbackLogger(?LoggerInterface $fallbackLogger): self
    {
        $this->fallbackLogger = $fallbackLogger;
        return $this;
    }

    public function withConnectTimeout(float $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    public function withTimeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function build(): OpenObserveHandler
    {
        if ($this->token === null) {
            throw new \LogicException('Configure authentication with withToken() or withCredentials() before build().');
        }

        return new OpenObserveHandler(
            host: $this->host,
            organizationId: $this->organizationId,
            streamName: $this->streamName,
            token: $this->token,
            ignoreFailure: $this->ignoreFailure,
            level: $this->level,
            bubble: $this->bubble,
            fallbackLogger: $this->fallbackLogger,
            connectTimeout: $this->connectTimeout,
            timeout: $this->timeout,
        );
    }
}
