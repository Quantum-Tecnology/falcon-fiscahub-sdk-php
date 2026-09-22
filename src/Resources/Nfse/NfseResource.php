<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Resources\Nfse;

use QuantumTecnology\FalconFiscalHub\Exceptions\FiscalRejectionException;
use QuantumTecnology\FalconFiscalHub\Resources\AbstractResource;
use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;

/**
 * Notas fiscais de serviço (NFS-e, padrão nacional).
 *
 * O caminho mais curto é `emit()`: você informa a natureza de operação (que
 * carrega emitente, série e códigos de serviço — o "perfil de emissão"
 * configurado no painel), o tomador e o valor.
 *
 * Toda nota emitida por chave de API sai com o NOME da chave como origem — é
 * assim que o dono da conta filtra e reconhece as notas do seu sistema.
 *
 * Permissões da chave: `nfse:read` para consultar e baixar, `nfse:write` para
 * emitir e cancelar. Sem elas, `ForbiddenException`.
 */
final class NfseResource extends AbstractResource
{
    /** Status em que a nota já foi para o fisco — não se transmite de novo. */
    private const ALREADY_SENT = ['processing', 'sent', 'authorized', 'canceled'];

    /**
     * Emite a nota: cria e transmite, numa chamada só.
     *
     * ⭐ Mande sempre `external_reference` (o id do pedido/cobrança no seu
     * sistema). Com ela a emissão é IDEMPOTENTE: se o seu worker rodar duas
     * vezes, o timeout estourar ou o deploy cair no meio, chamar de novo devolve
     * a MESMA nota em vez de emitir outra. Nota duplicada precisa ser cancelada
     * no prazo, às vezes com multa.
     *
     * Campos usuais:
     *   - `nature_operation_id`: natureza com o perfil de NFS-e (emitente,
     *     série e códigos vêm dela).
     *   - `recipient_person_id`: tomador (ver `persons()`). Opcional: sem ele a
     *     nota sai com "tomador não identificado", como o leiaute permite.
     *   - `service.valor_servicos`: valor. `service.descricao` sobrescreve a
     *     descrição padrão da natureza.
     *
     * @param array<string, mixed> $payload
     *
     * @throws FiscalRejectionException quando o fisco rejeita a nota. Ela fica
     *                                  salva como rejeitada, com o motivo nos
     *                                  eventos; corrija e chame `transmit()`.
     */
    public function emit(array $payload): ApiResponse
    {
        $created = $this->create($payload);
        $status  = $created->data['status']['value'] ?? null;

        // Referência repetida: a nota já existia e já foi ao fisco. Devolve como
        // está — transmitir de novo seria recusado (e não é o que se quer).
        if (in_array($status, self::ALREADY_SENT, true)) {
            return $created;
        }

        $reference = $payload['external_reference'] ?? null;

        return $this->transmit(
            (string) $created->data['id'],
            null === $reference ? null : 'nfse-' . $reference,
        );
    }

    /**
     * Cria a nota como rascunho (não vai ao fisco). Idempotente por
     * `external_reference`: repetir devolve a existente.
     *
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): ApiResponse
    {
        return $this->post('integration/v1/nfse', $payload);
    }

    /**
     * Transmite um rascunho (ou uma nota rejeitada, depois de corrigida).
     *
     * @param string|null $idempotencyKey Repetir a chamada com a mesma chave em
     *                                    até 24h devolve a resposta original em
     *                                    vez de transmitir de novo.
     *
     * @throws FiscalRejectionException quando o fisco rejeita.
     */
    public function transmit(string $id, ?string $idempotencyKey = null): ApiResponse
    {
        $headers = null === $idempotencyKey ? [] : ['Idempotency-Key' => $idempotencyKey];

        $response = $this->patch("integration/v1/nfse/{$id}/transmit", [], $headers);

        if ('denied' === ($response->data['status']['value'] ?? null)) {
            throw new FiscalRejectionException(
                $this->rejectionReason($response->data),
                $response->data,
                0,
                null,
                $response,
            );
        }

        return $response;
    }

    public function find(string $id): ApiResponse
    {
        return $this->get("integration/v1/nfse/{$id}");
    }

    /**
     * Lista as notas. Filtros úteis: `filter[status]`, `filter[source]` (o nome
     * de um aplicativo, `panel` ou `import`), `search`, `page`, `per_page`.
     * A paginação vem em `meta`.
     *
     * @param array<string, mixed> $query
     */
    public function list(array $query = []): ApiResponse
    {
        return $this->get('integration/v1/nfse', $query);
    }

    /**
     * Cancela uma nota autorizada. O prazo e as regras são do município.
     */
    public function cancel(string $id, string $reason): ApiResponse
    {
        return $this->patch("integration/v1/nfse/{$id}/cancel", ['reason' => $reason]);
    }

    /** O DANFSe em PDF (bytes). Rascunho devolve uma prévia sem validade. */
    public function pdf(string $id): string
    {
        return $this->getRaw("integration/v1/nfse/{$id}/pdf");
    }

    /** O XML da nota (o autorizado, quando existe). */
    public function xml(string $id): string
    {
        return $this->getRaw("integration/v1/nfse/{$id}/xml");
    }

    /** Linha do tempo da nota: envio, autorização, rejeições com o motivo. */
    public function events(string $id): ApiResponse
    {
        return $this->get("integration/v1/nfse/{$id}/events");
    }

    /**
     * O motivo da rejeição, lido do último evento de erro — é onde o Fiscal Hub
     * guarda o que o fisco respondeu.
     *
     * @param array<string, mixed> $invoice
     */
    private function rejectionReason(array $invoice): string
    {
        $events = is_array($invoice['events'] ?? null) ? $invoice['events'] : [];

        foreach (array_reverse($events) as $event) {
            if ('error' === ($event['type']['value'] ?? null) && !empty($event['description'])) {
                return (string) $event['description'];
            }
        }

        return 'A NFS-e foi rejeitada. Veja os eventos da nota para o motivo.';
    }
}
