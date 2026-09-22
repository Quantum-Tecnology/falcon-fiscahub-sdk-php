<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Resources;

use QuantumTecnology\FalconFiscalHub\Auth\TokenManager;
use QuantumTecnology\FalconFiscalHub\FiscalHubConfig;
use QuantumTecnology\FalconFiscalHub\Exceptions\AuthException;
use QuantumTecnology\FalconFiscalHub\Exceptions\ForbiddenException;
use QuantumTecnology\FalconFiscalHub\Exceptions\NotFoundException;
use QuantumTecnology\FalconFiscalHub\Exceptions\QuotaExceededException;
use QuantumTecnology\FalconFiscalHub\Exceptions\RateLimitException;
use QuantumTecnology\FalconFiscalHub\Exceptions\ServerException;
use QuantumTecnology\FalconFiscalHub\Exceptions\ValidationException;
use QuantumTecnology\FalconFiscalHub\Http\HttpClientInterface;
use QuantumTecnology\FalconFiscalHub\Http\HttpResponse;
use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;

abstract class AbstractResource
{
    protected HttpClientInterface $http;
    protected TokenManager $tokenManager;
    protected FiscalHubConfig $config;

    public function __construct(
        HttpClientInterface $http,
        TokenManager $tokenManager,
        FiscalHubConfig $config,
    ) {
        $this->http         = $http;
        $this->tokenManager = $tokenManager;
        $this->config       = $config;
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function get(string $path, array $query = []): ApiResponse
    {
        return $this->requestWithAuth('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function post(string $path, array $data = []): ApiResponse
    {
        return $this->requestWithAuth('POST', $path, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function put(string $path, array $data = []): ApiResponse
    {
        return $this->requestWithAuth('PUT', $path, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function patch(string $path, array $data = [], array $headers = []): ApiResponse
    {
        return $this->requestWithAuth('PATCH', $path, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function delete(string $path, array $data = []): ApiResponse
    {
        return $this->requestWithAuth('DELETE', $path, $data);
    }

    /**
     * GET de um ARQUIVO (PDF, XML): devolve o corpo cru. Erros continuam
     * virando as mesmas exceções dos endpoints JSON — só o sucesso é diferente.
     */
    protected function getRaw(string $path): string
    {
        $response = $this->send('GET', $path, [], []);

        if ($response->isSuccessful()) {
            return $response->getBody();
        }

        $this->handleResponse($response);

        return $response->getBody();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $extraHeaders
     */
    private function requestWithAuth(string $method, string $path, array $payload = [], array $extraHeaders = []): ApiResponse
    {
        return $this->handleResponse($this->send($method, $path, $payload, $extraHeaders));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $extraHeaders
     */
    private function send(string $method, string $path, array $payload, array $extraHeaders): HttpResponse
    {
        $url     = $this->buildUrl($path);
        $headers = $this->authHeaders() + $extraHeaders;

        $response = $this->doRequest($method, $url, $payload, $headers);

        // Auto-retry on 401 (token expired)
        if ($response->getStatusCode() === 401 && $this->config->hasCredentials()) {
            $this->tokenManager->invalidate();
            $headers  = $this->authHeaders() + $extraHeaders;
            $response = $this->doRequest($method, $url, $payload, $headers);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function doRequest(string $method, string $url, array $payload, array $headers): HttpResponse
    {
        return match ($method) {
            'GET'    => $this->http->get($url, $payload, $headers),
            'POST'   => $this->http->post($url, $payload, $headers),
            'PUT'    => $this->http->put($url, $payload, $headers),
            'PATCH'  => $this->http->patch($url, $payload, $headers),
            'DELETE' => $this->http->delete($url, $payload, $headers),
            default  => $this->http->get($url, $payload, $headers),
        };
    }

    private function handleResponse(HttpResponse $response): ApiResponse
    {
        $apiResponse = ApiResponse::fromHttpResponse($response);
        $statusCode  = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return $apiResponse;
        }

        $body = $response->json();

        match (true) {
            $statusCode === 401 => throw new AuthException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            /*
             * 402: acabou a cota de emissão do plano, ou o plano não comporta
             * mais cadastros. Não é rate limit: resolve com upgrade (ou na
             * virada do mês), não esperando alguns segundos.
             */
            $statusCode === 402 => throw new QuotaExceededException(
                $apiResponse->message,
                (int) ($body['used'] ?? 0),
                (int) ($body['limit'] ?? 0),
                isset($body['code']) ? (string) $body['code'] : null,
                $statusCode,
                null,
                $apiResponse,
            ),
            /*
             * 403: a chave não tem a permissão da rota (`code: MISSING_ABILITY`,
             * `ability` diz qual), ou o plano do dono não inclui API. É
             * configuração da chave no painel — tentar de novo não resolve.
             */
            $statusCode === 403 => throw new ForbiddenException(
                $apiResponse->message,
                isset($body['ability']) ? (string) $body['ability'] : null,
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode === 404 => throw new NotFoundException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode === 422 => throw new ValidationException(
                $apiResponse->message,
                $apiResponse->errors,
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode === 429 => throw new RateLimitException(
                $apiResponse->message,
                $response->retryAfter(),
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode >= 500 => throw new ServerException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            default => $apiResponse,
        };

        return $apiResponse;
    }

    private function buildUrl(string $path): string
    {
        return $this->config->getBaseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenManager->getToken(),
        ];
    }

    protected function sanitizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? $value;
    }
}
