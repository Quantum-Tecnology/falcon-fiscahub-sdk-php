<?php

declare(strict_types = 1);

use QuantumTecnology\FalconFiscalHub\Exceptions\FiscalRejectionException;
use QuantumTecnology\FalconFiscalHub\Exceptions\ForbiddenException;
use QuantumTecnology\FalconFiscalHub\Exceptions\QuotaExceededException;
use QuantumTecnology\FalconFiscalHub\Exceptions\RateLimitException;
use QuantumTecnology\FalconFiscalHub\Exceptions\ValidationException;
use QuantumTecnology\FalconFiscalHub\Resources\Nfse\NfseResource;

/** A nota como o Fiscal Hub devolve (InvoiceIndexResource), no status pedido. */
function invoiceBody(string $status, array $extra = []): array
{
    return ['data' => array_merge([
        'id'                 => 'inv-1',
        'status'             => ['value' => $status, 'label' => ucfirst($status)],
        'origin'             => ['value' => 'integration', 'label' => 'Aplicativo'],
        'source_application' => 'DataHub',
        'external_reference' => 'charge-1',
    ], $extra)];
}

it('emite: cria o rascunho e transmite, com a referência como chave de idempotência', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(200, invoiceBody('draft'))
        ->queue(200, invoiceBody('authorized', ['number' => '42']));

    $response = $nfse->emit([
        'nature_operation_id' => 'nat-1',
        'external_reference'  => 'charge-1',
        'service'             => ['valor_servicos' => 29.9],
    ]);

    [$create, $transmit] = $http->calls;

    expect($create['method'])->toBe('POST')
        ->and($create['url'])->toBe('https://fiscal.example.com/integration/v1/nfse')
        ->and($create['payload']['external_reference'])->toBe('charge-1')
        ->and($transmit['method'])->toBe('PATCH')
        ->and($transmit['url'])->toBe('https://fiscal.example.com/integration/v1/nfse/inv-1/transmit')
        ->and($transmit['headers']['Idempotency-Key'])->toBe('nfse-charge-1')
        ->and($response->data['status']['value'])->toBe('authorized')
        ->and($response->data['number'])->toBe('42');
});

it('emitir de novo a mesma referência já autorizada NÃO transmite outra vez', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    // O Fiscal Hub devolve a nota existente para a referência repetida.
    $http->queue(200, invoiceBody('authorized'));

    $response = $nfse->emit(['external_reference' => 'charge-1', 'service' => ['valor_servicos' => 10]]);

    expect($http->calls)->toHaveCount(1)
        ->and($response->data['status']['value'])->toBe('authorized');
});

it('rejeição do fisco vira exceção com o motivo e a nota', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(200, invoiceBody('draft'))
        ->queue(200, invoiceBody('denied', ['events' => [
            ['type' => ['value' => 'emission_requested'], 'description' => 'Emissão solicitada'],
            ['type' => ['value' => 'error'], 'description' => 'NFS-e rejeitada pela SEFAZ: E0312 cTribNac inválido'],
        ]]));

    try {
        $nfse->emit(['external_reference' => 'charge-1']);
        $this->fail('Deveria ter lançado FiscalRejectionException.');
    } catch (FiscalRejectionException $e) {
        // 200 do servidor com a nota rejeitada: sem a exceção, quem integra
        // trataria como sucesso e seguiria sem nota.
        expect($e->getMessage())->toContain('E0312')
            ->and($e->getInvoiceId())->toBe('inv-1');
    }
});

it('permissão faltando na chave vira ForbiddenException dizendo qual', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(403, [
        'message' => 'Esta chave de API não tem permissão para esta operação (nfse:write).',
        'code'    => 'MISSING_ABILITY',
        'ability' => 'nfse:write',
    ]);

    try {
        $nfse->create([]);
        $this->fail('Deveria ter lançado ForbiddenException.');
    } catch (ForbiddenException $e) {
        expect($e->getAbility())->toBe('nfse:write')
            ->and($e->getResponse()?->code)->toBe('MISSING_ABILITY');
    }
});

it('limite de emissão do plano é cota (402), não rate limit', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(402, ['message' => 'Limite de emissão do plano atingido.', 'code' => 'QUOTA_EXCEEDED']);

    try {
        $nfse->transmit('inv-1');
        $this->fail('Deveria ter lançado QuotaExceededException.');
    } catch (QuotaExceededException $e) {
        // Não é RateLimitException: esperar alguns segundos não resolve.
        expect($e)->not->toBeInstanceOf(RateLimitException::class)
            ->and($e->getReason())->toBe('QUOTA_EXCEEDED');
    }
});

it('erro de validação traz os campos', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(422, [
        'message' => 'Natureza de operação não encontrada nesta conta.',
        'errors'  => ['nature_operation_id' => ['Natureza de operação não encontrada nesta conta.']],
    ]);

    try {
        $nfse->create(['nature_operation_id' => 'de-outra-conta']);
        $this->fail('Deveria ter lançado ValidationException.');
    } catch (ValidationException $e) {
        expect($e->getErrors())->toHaveKey('nature_operation_id');
    }
});

it('baixa o PDF e o XML como arquivo, não como JSON', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queueRaw(200, '%PDF-1.4 conteúdo', ['Content-Type' => 'application/pdf'])
        ->queueRaw(200, '<NFSe>...</NFSe>', ['Content-Type' => 'application/xml']);

    expect($nfse->pdf('inv-1'))->toStartWith('%PDF')
        ->and($http->lastCall()['url'])->toBe('https://fiscal.example.com/integration/v1/nfse/inv-1/pdf');

    expect($nfse->xml('inv-1'))->toStartWith('<NFSe>');
});

it('erro ao baixar o PDF continua virando exceção', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(403, ['message' => 'Sem permissão.', 'ability' => 'nfse:read']);

    expect(fn () => $nfse->pdf('inv-1'))->toThrow(ForbiddenException::class);
});

it('cancela com o motivo', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(200, invoiceBody('canceled'));
    $nfse->cancel('inv-1', 'Serviço não prestado.');

    expect($http->lastCall()['method'])->toBe('PATCH')
        ->and($http->lastCall()['url'])->toBe('https://fiscal.example.com/integration/v1/nfse/inv-1/cancel')
        ->and($http->lastCall()['payload'])->toBe(['reason' => 'Serviço não prestado.']);
});

it('lê a paginação da listagem (bloco pagination, irmão de data)', function (): void {
    [$nfse, $http] = resourceWithFake(NfseResource::class);

    $http->queue(200, [
        'data'       => [['id' => 'inv-1'], ['id' => 'inv-2']],
        'pagination' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 2, 'total' => 5],
    ]);

    $response = $nfse->list(['filter' => ['source' => 'DataHub']]);

    // A lição do SDK do DataHub: sem ler `pagination`, o integrado recebe a
    // primeira página e nada indica que há mais.
    expect($response->meta['total'])->toBe(5)
        ->and($response->meta['last_page'])->toBe(3)
        ->and($http->lastCall()['payload'])->toBe(['filter' => ['source' => 'DataHub']]);
});
