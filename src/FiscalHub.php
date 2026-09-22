<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub;

use QuantumTecnology\FalconFiscalHub\Exceptions\FalconException;
use QuantumTecnology\FalconFiscalHub\Resources\NatureOperations\NatureOperationResource;
use QuantumTecnology\FalconFiscalHub\Resources\Nfse\NfseResource;
use QuantumTecnology\FalconFiscalHub\Resources\Persons\PersonResource;

/**
 * Facade estática do SDK do Falcon Fiscal Hub.
 *
 * ```php
 * FiscalHub::configure(FiscalHubConfig::make('https://fiscal.falcon-server.com.br', 'ffx_...'));
 * FiscalHub::nfse()->emit([...]);
 * ```
 *
 * Em aplicação com injeção de dependência, prefira instanciar o FiscalHubClient
 * e registrá-lo no container: o estado estático atrapalha teste paralelo e
 * força `reset()` entre casos.
 */
final class FiscalHub
{
    private static ?FiscalHubClient $instance = null;

    public static function configure(FiscalHubConfig $config): void
    {
        self::$instance = new FiscalHubClient($config);
    }

    public static function client(): FiscalHubClient
    {
        if (null === self::$instance) {
            throw new FalconException(
                'FiscalHub SDK not configured. Call FiscalHub::configure(FiscalHubConfig::make(...)) first.',
            );
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function nfse(): NfseResource
    {
        return self::client()->nfse();
    }

    public static function persons(): PersonResource
    {
        return self::client()->persons();
    }

    public static function natureOperations(): NatureOperationResource
    {
        return self::client()->natureOperations();
    }
}
