<?php

declare(strict_types = 1);

use QuantumTecnology\FalconFiscalHub\Exceptions\FalconException;
use QuantumTecnology\FalconFiscalHub\FiscalHub;
use QuantumTecnology\FalconFiscalHub\FiscalHubConfig;
use QuantumTecnology\FalconFiscalHub\Resources\Nfse\NfseResource;

afterEach(fn () => FiscalHub::reset());

it('avisa quando o SDK não foi configurado', function (): void {
    expect(fn () => FiscalHub::nfse())->toThrow(FalconException::class);
});

it('entrega os recursos depois de configurado', function (): void {
    FiscalHub::configure(FiscalHubConfig::make('https://fiscal.example.com', 'ffx_x'));

    expect(FiscalHub::nfse())->toBeInstanceOf(NfseResource::class);
});

it('tira a barra final da URL, para não montar //integration', function (): void {
    expect(FiscalHubConfig::make('https://fiscal.example.com/', 'ffx_x')->getBaseUrl())
        ->toBe('https://fiscal.example.com');
});
