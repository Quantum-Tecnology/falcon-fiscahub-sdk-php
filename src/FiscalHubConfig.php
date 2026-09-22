<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub;

/**
 * Configuração imutável do cliente.
 *
 * Dois modos de autenticação, como nos SDKs do DataHub e do Falcon Vendas:
 *   - `make($baseUrl, $token)`: chave de API (ffx_...) criada no painel do
 *     Fiscal Hub. É o modo normal para servidor-a-servidor, e o único em que a
 *     nota sai com o NOME do aplicativo como origem.
 *   - `withCredentials($baseUrl, $email, $password)`: login do dono da conta,
 *     com o token cacheado e renovado sozinho. A nota sai como emissão própria.
 */
final class FiscalHubConfig
{
    private string $baseUrl;
    private ?string $token;
    private ?string $email;
    private ?string $password;
    private int $timeout;
    private int $connectTimeout;
    private int $retries;
    private int $retryDelay;
    /** @var object|null PSR-16 CacheInterface */
    private ?object $cache;
    /** @var object|null PSR-3 LoggerInterface */
    private ?object $logger;

    /**
     * @param int $timeout Segundos até desistir da resposta.
     *
     *                     60s porque a TRANSMISSÃO da NFS-e é síncrona: o Fiscal
     *                     Hub assina, envia ao Ambiente Nacional e espera a
     *                     autorização na mesma chamada, e a SEFAZ/ADN às vezes
     *                     demora. Cortar antes faria o seu sistema achar que
     *                     falhou uma nota que foi autorizada.
     */
    public function __construct(
        string $baseUrl,
        ?string $token = null,
        ?string $email = null,
        ?string $password = null,
        int $timeout = 60,
        int $connectTimeout = 10,
        int $retries = 3,
        int $retryDelay = 2000,
        ?object $cache = null,
        ?object $logger = null,
    ) {
        $this->baseUrl        = rtrim($baseUrl, '/');
        $this->token          = $token;
        $this->email          = $email;
        $this->password       = $password;
        $this->timeout        = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->retries        = $retries;
        $this->retryDelay     = $retryDelay;
        $this->cache          = $cache;
        $this->logger         = $logger;
    }

    /** Chave de API (ffx_...) criada no painel (Chaves de API). */
    public static function make(string $baseUrl, ?string $token = null): self
    {
        return new self(baseUrl: $baseUrl, token: $token);
    }

    public static function withCredentials(string $baseUrl, string $email, string $password): self
    {
        return new self(baseUrl: $baseUrl, email: $email, password: $password);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    public function getCache(): ?object
    {
        return $this->cache;
    }

    public function getLogger(): ?object
    {
        return $this->logger;
    }

    public function hasCredentials(): bool
    {
        return null !== $this->email && null !== $this->password;
    }

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== $this->token;
    }
}
