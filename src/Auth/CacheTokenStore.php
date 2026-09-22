<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Auth;

/**
 * Token store adapter for PSR-16 CacheInterface.
 *
 * Accepts any object implementing PSR-16's get/set/delete methods
 * without requiring psr/simple-cache as a hard dependency.
 */
final class CacheTokenStore implements TokenStoreInterface
{
    /** @var object PSR-16 CacheInterface */
    private object $cache;

    public function __construct(object $cache)
    {
        $this->cache = $cache;
    }

    public function get(string $key): ?string
    {
        /** @var string|null $value */
        $value = $this->cache->get($key);

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->cache->set($key, $value, $ttlSeconds);
    }

    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }
}
