<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function get(string $url, array $query = [], array $headers = []): HttpResponse;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function post(string $url, array $data = [], array $headers = []): HttpResponse;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function put(string $url, array $data = [], array $headers = []): HttpResponse;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function patch(string $url, array $data = [], array $headers = []): HttpResponse;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function delete(string $url, array $data = [], array $headers = []): HttpResponse;
}
