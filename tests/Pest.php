<?php

declare(strict_types = 1);

use QuantumTecnology\FalconFiscalHub\Auth\TokenManager;
use QuantumTecnology\FalconFiscalHub\FiscalHubConfig;
use QuantumTecnology\FalconFiscalHub\Tests\Support\FakeHttpClient;

/**
 * Monta um resource com HTTP fake e token fixo.
 *
 * Token fixo (e não credenciais) de propósito na maioria dos testes: é o modo
 * normal de uso servidor-a-servidor, e evita que cada teste precise programar
 * também a resposta do login.
 *
 * @template T
 *
 * @param class-string<T> $resourceClass
 *
 * @return array{0: T, 1: FakeHttpClient}
 */
function resourceWithFake(string $resourceClass, ?FiscalHubConfig $config = null): array
{
    $config = $config ?? FiscalHubConfig::make('https://fiscal.example.com', 'ffx_test_token');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);

    return [new $resourceClass($http, $tokens, $config), $http];
}
