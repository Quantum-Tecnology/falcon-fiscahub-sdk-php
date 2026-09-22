<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Exceptions;

class RateLimitException extends FalconException
{
    private ?int $retryAfter;

    public function __construct(
        string $message = 'Rate limit exceeded',
        ?int $retryAfter = null,
        int $code = 429,
        ?\Throwable $previous = null,
        ?\QuantumTecnology\FalconFiscalHub\Response\ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous, $response);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
