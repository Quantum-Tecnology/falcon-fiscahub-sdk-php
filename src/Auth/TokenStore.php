<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Auth;

final class TokenStore implements TokenStoreInterface
{
    /** @var array<string, array{value: string, expires_at: int}> */
    private array $store = [];

    public function get(string $key): ?string
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        if ($this->store[$key]['expires_at'] < time()) {
            unset($this->store[$key]);

            return null;
        }

        return $this->store[$key]['value'];
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => time() + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }
}
