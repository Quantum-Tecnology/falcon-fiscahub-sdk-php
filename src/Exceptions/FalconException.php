<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Exceptions;

use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;
use RuntimeException;
use Throwable;

class FalconException extends RuntimeException
{
    private ?ApiResponse $response;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse(): ?ApiResponse
    {
        return $this->response;
    }
}
