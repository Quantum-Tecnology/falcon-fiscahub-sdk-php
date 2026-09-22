<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Http;

use QuantumTecnology\FalconFiscalHub\Exceptions\TimeoutException;
use QuantumTecnology\FalconFiscalHub\FiscalHubConfig;

final class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;
    private int $connectTimeout;
    private int $retries;
    private int $retryDelay;
    /** @var object|null PSR-3 LoggerInterface */
    private ?object $logger;

    public function __construct(FiscalHubConfig $config)
    {
        $this->timeout        = $config->getTimeout();
        $this->connectTimeout = $config->getConnectTimeout();
        $this->retries        = $config->getRetries();
        $this->retryDelay     = $config->getRetryDelay();
        $this->logger         = $config->getLogger();
    }

    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $this->request('GET', $url, null, $headers);
    }

    public function post(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $data, $headers);
    }

    public function put(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    public function patch(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->request('PATCH', $url, $data, $headers);
    }

    public function delete(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->request('DELETE', $url, $data !== [] ? $data : null, $headers);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string> $headers
     */
    private function request(string $method, string $url, ?array $data, array $headers): HttpResponse
    {
        $attempt  = 0;
        $lastException = null;

        while ($attempt <= $this->retries) {
            if ($attempt > 0) {
                $delay = $this->retryDelay * $attempt;
                $this->log('debug', "Retrying request (attempt {$attempt})", [
                    'method' => $method,
                    'url'    => $url,
                    'delay'  => $delay,
                ]);
                usleep($delay * 1000);
            }

            try {
                $response = $this->execute($method, $url, $data, $headers);

                if ($response->getStatusCode() === 429 && $attempt < $this->retries) {
                    $retryAfter = $response->retryAfter();

                    if ($retryAfter !== null && $retryAfter <= 60) {
                        $this->log('warning', "Rate limited, waiting {$retryAfter}s", ['url' => $url]);
                        sleep($retryAfter);
                        $attempt++;
                        continue;
                    }
                }

                if ($response->getStatusCode() >= 500 && $attempt < $this->retries) {
                    $attempt++;
                    continue;
                }

                $this->log('debug', "Request completed", [
                    'method' => $method,
                    'url'    => $url,
                    'status' => $response->getStatusCode(),
                ]);

                return $response;
            } catch (TimeoutException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt > $this->retries) {
                    throw $e;
                }
            }
        }

        throw $lastException ?? new TimeoutException("Request failed after {$this->retries} retries");
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string> $headers
     */
    private function execute(string $method, string $url, ?array $data, array $headers): HttpResponse
    {
        $ch = curl_init();

        $curlHeaders = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        switch ($method) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'PUT':
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = $method;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
        }

        curl_setopt_array($ch, $options);

        /** @var string|false $rawResponse */
        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $errno  = curl_errno($ch);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === CURLE_OPERATION_TIMEOUTED) {
                throw new TimeoutException("Request timed out: {$error}");
            }

            throw new TimeoutException("cURL error ({$errno}): {$error}");
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $body       = substr($rawResponse, $headerSize);

        return new HttpResponse($statusCode, $body, $this->parseHeaders($rawHeaders));
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines   = explode("\r\n", trim($rawHeaders));

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value]       = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        if (method_exists($this->logger, $level)) {
            $this->logger->{$level}("[FalconSDK] {$message}", $context);
        }
    }
}
