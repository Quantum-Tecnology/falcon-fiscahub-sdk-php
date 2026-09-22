<?php

declare(strict_types = 1);

use QuantumTecnology\FalconFiscalHub\Resources\NatureOperations\NatureOperationResource;
use QuantumTecnology\FalconFiscalHub\Resources\Persons\PersonResource;

it('acha o tomador pelo documento, ignorando pontuação', function (): void {
    [$persons, $http] = resourceWithFake(PersonResource::class);

    $http->queue(200, ['data' => [['id' => 'p-1', 'cpf' => '12345678901']]]);

    $person = $persons->findByDocument('123.456.789-01');

    expect($person['id'])->toBe('p-1')
        ->and($http->lastCall()['url'])->toBe('https://fiscal.example.com/integration/v1/persons')
        ->and($http->lastCall()['payload']['filter'])->toBe(['document' => '12345678901']);
});

it('findOrCreate reaproveita quem já existe, sem cadastrar de novo', function (): void {
    [$persons, $http] = resourceWithFake(PersonResource::class);

    $http->queue(200, ['data' => [['id' => 'p-1']]]);

    $person = $persons->findOrCreate(['type' => 'pf', 'cpf' => '12345678901', 'razao_social' => 'Ana']);

    // Cadastrar a cada nota duplicaria o tomador e gastaria o limite do plano.
    expect($person['id'])->toBe('p-1')
        ->and($http->calls)->toHaveCount(1);
});

it('findOrCreate cadastra quando não acha', function (): void {
    [$persons, $http] = resourceWithFake(PersonResource::class);

    $http->queue(200, ['data' => []])
        ->queue(200, ['data' => ['id' => 'p-novo']]);

    $person = $persons->findOrCreate(['type' => 'pf', 'cpf' => '12345678901', 'razao_social' => 'Ana']);

    expect($person['id'])->toBe('p-novo')
        ->and($http->lastCall()['method'])->toBe('POST');
});

it('documento vazio não consulta nada', function (): void {
    [$persons, $http] = resourceWithFake(PersonResource::class);

    expect($persons->findByDocument('...'))->toBeNull()
        ->and($http->calls)->toHaveCount(0);
});

it('lista só as naturezas prontas para NFS-e', function (): void {
    [$natures, $http] = resourceWithFake(NatureOperationResource::class);

    $http->queue(200, ['data' => [['id' => 'nat-1', 'description' => 'Assinatura', 'allow_nfse' => true]]]);

    $response = $natures->nfseProfiles();

    expect($response->data[0]['id'])->toBe('nat-1')
        ->and($http->lastCall()['url'])->toBe('https://fiscal.example.com/integration/v1/nature-operations')
        ->and($http->lastCall()['payload']['filter'])->toBe(['allow_nfse' => '1']);
});
