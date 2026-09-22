<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Http;

final class HttpResponse
{
    /** @var array<string, string> */
    private array $headers;

    private ?array $decoded = null;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $statusCode,
        private string $body,
        array $headers = [],
    ) {
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $lower) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->decoded === null) {
            $this->decoded = json_decode($this->body, true) ?? [];
        }

        return $this->decoded;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function retryAfter(): ?int
    {
        $value = $this->getHeader('Retry-After');

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
