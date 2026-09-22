<?php

declare(strict_types = 1);

use QuantumTecnology\FalconFiscalHub\Auth\TokenManager;
use QuantumTecnology\FalconFiscalHub\Exceptions\AuthException;
use QuantumTecnology\FalconFiscalHub\FiscalHubConfig;
use QuantumTecnology\FalconFiscalHub\Resources\Nfse\NfseResource;
use QuantumTecnology\FalconFiscalHub\Tests\Support\FakeHttpClient;

it('manda a chave de API no Authorization: Bearer', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(200, ['data' => []]);
    $nfse->list();

    expect($http->lastCall()['headers']['Authorization'])->toBe('Bearer ffx_test_token');
});

it('faz login no caminho do Fiscal Hub, não no do DataHub', function (): void {
    $config = FiscalHubConfig::withCredentials('https://fiscal.example.com', 'dono@exemplo.com', 'senha');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);

    $http->queue(200, ['data' => ['access_token' => 'jwt-abc', 'expires_in' => 3600]]);

    expect($tokens->getToken())->toBe('jwt-abc');

    // O caminho difere do DataHub (/auth/v1/login): copiar aquele daria 404
    // travestido de falha de autenticação.
    expect($http->lastCall()['url'])->toBe('https://fiscal.example.com/user/public/auth/v1/login')
        ->and($http->lastCall()['payload'])->toBe(['email' => 'dono@exemplo.com', 'password' => 'senha']);
});

it('cacheia o token e não faz login duas vezes', function (): void {
    $config = FiscalHubConfig::withCredentials('https://fiscal.example.com', 'dono@exemplo.com', 'senha');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);

    $http->queue(200, ['data' => ['access_token' => 'jwt-abc', 'expires_in' => 3600]]);

    $tokens->getToken();
    $tokens->getToken();

    expect($http->calls)->toHaveCount(1);
});

it('renova o token uma vez ao tomar 401 com credenciais', function (): void {
    $config = FiscalHubConfig::withCredentials('https://fiscal.example.com', 'dono@exemplo.com', 'senha');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);
    $nfse   = new NfseResource($http, $tokens, $config);

    $http->queue(200, ['data' => ['access_token' => 'token-velho', 'expires_in' => 3600]]) // login
        ->queue(401, ['message' => 'Unauthenticated.'])                                    // 1ª tentativa
        ->queue(200, ['data' => ['access_token' => 'token-novo', 'expires_in' => 3600]])    // relogin
        ->queue(200, ['data' => []]);

    $nfse->list();

    // A retentativa leva o token NOVO. Sem invalidar o cache, o SDK repetiria
    // a chamada com a mesma credencial recusada e entraria em 401 eterno.
    expect($http->lastCall()['headers']['Authorization'])->toBe('Bearer token-novo');
});

it('não tenta renovar quando é chave de API', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(401, ['message' => 'Chave de API inválida ou revogada.']);

    // Com chave de API não há o que renovar: insistir só mascararia a causa
    // real (chave revogada ou expirada no painel).
    expect(fn () => $nfse->list())->toThrow(AuthException::class);
    expect($http->calls)->toHaveCount(1);
});

it('exige token ou credenciais', function (): void {
    $config = FiscalHubConfig::make('https://fiscal.example.com');
    $tokens = new TokenManager(new FakeHttpClient(), $config);

    expect(fn () => $tokens->getToken())->toThrow(AuthException::class);
});
