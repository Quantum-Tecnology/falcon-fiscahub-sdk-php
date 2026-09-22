<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Resources\Persons;

use QuantumTecnology\FalconFiscalHub\Resources\AbstractResource;
use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;

/**
 * Pessoas do Fiscal Hub: emitentes (a empresa que emite) e tomadores (quem
 * recebe o serviço).
 *
 * Para emitir para um cliente seu, ache-o pelo documento e só cadastre se não
 * existir (`findOrCreate()`). Cadastrar de novo a cada nota duplicaria o
 * tomador e gastaria o limite de cadastros do plano.
 *
 * Permissões da chave: `cadastros:read` para buscar, `cadastros:write` para
 * cadastrar.
 */
final class PersonResource extends AbstractResource
{
    /**
     * A pessoa com este CPF/CNPJ, ou null. Pontuação é ignorada.
     *
     * @return array<string, mixed>|null
     */
    public function findByDocument(string $document): ?array
    {
        $digits = $this->sanitizeDigits($document);

        if ('' === $digits) {
            return null;
        }

        $response = $this->get('integration/v1/persons', ['filter' => ['document' => $digits], 'per_page' => 1]);
        $first    = $response->data[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * Cadastra. Campos mínimos de um tomador PF: `type` = pf, `cpf` e
     * `razao_social` (o nome). Endereço é opcional na NFS-e.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): ApiResponse
    {
        return $this->post('integration/v1/persons', $data);
    }

    /**
     * Acha pelo documento ou cadastra — o id é o que vai em
     * `recipient_person_id` na emissão.
     *
     * @param array<string, mixed> $data Precisa ter `cpf` ou `cnpj`.
     *
     * @return array<string, mixed> a pessoa
     */
    public function findOrCreate(array $data): array
    {
        $document = (string) ($data['cnpj'] ?? $data['cpf'] ?? '');
        $existing = $this->findByDocument($document);

        if (null !== $existing) {
            return $existing;
        }

        return $this->create($data)->data;
    }
}
