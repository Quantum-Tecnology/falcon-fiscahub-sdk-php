<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub;

use QuantumTecnology\FalconFiscalHub\Auth\CacheTokenStore;
use QuantumTecnology\FalconFiscalHub\Auth\TokenManager;
use QuantumTecnology\FalconFiscalHub\Auth\TokenStore;
use QuantumTecnology\FalconFiscalHub\Http\CurlHttpClient;
use QuantumTecnology\FalconFiscalHub\Http\HttpClientInterface;
use QuantumTecnology\FalconFiscalHub\Resources\NatureOperations\NatureOperationResource;
use QuantumTecnology\FalconFiscalHub\Resources\Nfse\NfseResource;
use QuantumTecnology\FalconFiscalHub\Resources\Persons\PersonResource;

/**
 * Cliente da API do Falcon Fiscal Hub.
 *
 * ```php
 * $fiscal = new FiscalHubClient(FiscalHubConfig::make(
 *     'https://fiscal.falcon-server.com.br',
 *     'ffx_abc12345_...',            // chave criada no painel (Chaves de API)
 * ));
 *
 * $nota = $fiscal->nfse()->emit([
 *     'nature_operation_id' => '01a0...',   // natureza com o perfil de NFS-e
 *     'external_reference'  => 'pedido-123', // idempotência: reenviar não duplica
 *     'service'             => ['valor_servicos' => 49.9],
 * ]);
 * ```
 */
final class FiscalHubClient
{
    private HttpClientInterface $http;
    private TokenManager $tokenManager;
    private FiscalHubConfig $config;

    public function __construct(FiscalHubConfig $config, ?HttpClientInterface $http = null)
    {
        $this->config = $config;
        $this->http   = $http ?? new CurlHttpClient($config);

        $tokenStore = null !== $config->getCache()
            ? new CacheTokenStore($config->getCache())
            : new TokenStore();

        $this->tokenManager = new TokenManager($this->http, $config, $tokenStore);
    }

    /** Notas fiscais de serviço: emitir, consultar, baixar PDF/XML, cancelar. */
    public function nfse(): NfseResource
    {
        return new NfseResource($this->http, $this->tokenManager, $this->config);
    }

    /** Pessoas (emitentes e tomadores): achar pelo documento e cadastrar. */
    public function persons(): PersonResource
    {
        return new PersonResource($this->http, $this->tokenManager, $this->config);
    }

    /** Naturezas de operação — as de NFS-e são o perfil de emissão. */
    public function natureOperations(): NatureOperationResource
    {
        return new NatureOperationResource($this->http, $this->tokenManager, $this->config);
    }

    public function getConfig(): FiscalHubConfig
    {
        return $this->config;
    }
}
