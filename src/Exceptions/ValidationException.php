<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Exceptions;

class ValidationException extends FalconException
{
    /** @var array<string, mixed> */
    private array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message = '',
        array $errors = [],
        int $code = 422,
        ?\Throwable $previous = null,
        ?\QuantumTecnology\FalconFiscalHub\Response\ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous, $response);
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
