<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Tests\Support;

use QuantumTecnology\FalconFiscalHub\Http\HttpClientInterface;
use QuantumTecnology\FalconFiscalHub\Http\HttpResponse;

/**
 * HTTP client de teste: devolve respostas programadas e grava o que foi pedido.
 *
 * Serve para verificar as duas metades do contrato — o que o SDK ENVIA (caminho,
 * corpo, cabeçalho de autorização) e como ele INTERPRETA o que volta. Um teste
 * que só checa o retorno deixa passar um caminho errado, que em produção vira
 * 404 travestido de outra coisa.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, payload: array<string, mixed>, headers: array<string, string>}> */
    public array $calls = [];

    /** @var list<HttpResponse> */
    private array $queue = [];

    /** Enfileira uma resposta. Chamadas consomem a fila em ordem. */
    public function queue(int $status, array $body, array $headers = []): self
    {
        $this->queue[] = new HttpResponse($status, json_encode($body), $headers);

        return $this;
    }

    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        return $this->record('GET', $url, $query, $headers);
    }

    public function post(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->record('POST', $url, $data, $headers);
    }

    public function put(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->record('PUT', $url, $data, $headers);
    }

    public function patch(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->record('PATCH', $url, $data, $headers);
    }

    public function delete(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->record('DELETE', $url, $data, $headers);
    }

    /** Enfileira uma resposta crua (PDF, XML) — o corpo não é JSON. */
    public function queueRaw(int $status, string $body, array $headers = []): self
    {
        $this->queue[] = new HttpResponse($status, $body, $headers);

        return $this;
    }

    /** A última chamada feita, para inspeção no teste. */
    public function lastCall(): array
    {
        return $this->calls[count($this->calls) - 1];
    }

    private function record(string $method, string $url, array $payload, array $headers): HttpResponse
    {
        $this->calls[] = compact('method', 'url', 'payload', 'headers');

        return array_shift($this->queue) ?? new HttpResponse(200, '{}');
    }
}
