<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Resources\NatureOperations;

use QuantumTecnology\FalconFiscalHub\Resources\AbstractResource;
use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;

/**
 * Naturezas de operação.
 *
 * Para NFS-e, a natureza é o PERFIL DE EMISSÃO: guarda emitente, série,
 * códigos de serviço (cTribNac, cTribMun, NBS), ISS e a descrição padrão. O dono
 * da conta configura isso no painel; o seu sistema só escolhe qual usar e
 * informa, a cada nota, o tomador e o valor.
 *
 * Permissão da chave: `cadastros:read`.
 */
final class NatureOperationResource extends AbstractResource
{
    /**
     * Só as naturezas prontas para NFS-e — é o que um seletor de "qual natureza
     * usar" deve oferecer.
     */
    public function nfseProfiles(): ApiResponse
    {
        return $this->list(['filter' => ['allow_nfse' => '1'], 'per_page' => 100]);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function list(array $query = []): ApiResponse
    {
        return $this->get('integration/v1/nature-operations', $query);
    }

    public function find(string $id): ApiResponse
    {
        return $this->get("integration/v1/nature-operations/{$id}");
    }
}
