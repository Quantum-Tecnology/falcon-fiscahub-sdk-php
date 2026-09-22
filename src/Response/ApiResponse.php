<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Response;

use QuantumTecnology\FalconFiscalHub\Http\HttpResponse;

class ApiResponse
{
    /** @var array<string, mixed>|list<mixed> */
    public readonly array $data;

    /** @var array<string, mixed> */
    public readonly array $errors;

    /** @var array<string, mixed> */
    public readonly array $meta;

    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $meta
     * @param string|null $code Código de negócio (TEMPLATE_NOT_FOUND, OPTED_OUT…).
     *                          Fica na RAIZ do envelope, não dentro de `data` —
     *                          e é ele que diz o que fazer, já que vários casos
     *                          diferentes compartilham o mesmo HTTP 422.
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $statusCode,
        public readonly string $message,
        array $data = [],
        array $errors = [],
        array $meta = [],
        public readonly ?string $code = null,
    ) {
        $this->data   = $data;
        $this->errors = $errors;
        $this->meta   = $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'    => $this->success,
            'statusCode' => $this->statusCode,
            'message'    => $this->message,
            'code'       => $this->code,
            'data'       => $this->data,
            'errors'     => $this->errors,
            'meta'       => $this->meta,
        ];
    }

    public static function fromHttpResponse(HttpResponse $response): self
    {
        $body = $response->json();

        $success = $body['success'] ?? $response->isSuccessful();
        $message = $body['message'] ?? ($response->isSuccessful() ? 'OK' : 'Error');
        $data    = $body['data'] ?? $body;
        $errors  = $body['errors'] ?? [];

        if (!is_array($data)) {
            $data = [$data];
        }

        if (!is_array($errors)) {
            $errors = [$errors];
        }

        $meta = [];

        foreach (['current_page', 'last_page', 'per_page', 'total', 'from', 'to'] as $key) {
            if (isset($body[$key])) {
                $meta[$key] = $body[$key];
            }
        }

        /*
         * Bloco `pagination`, IRMAO de `data` — o formato que a API do DataHub
         * realmente usa:
         *
         *     { data: [...], pagination: { current_page, per_page, total } }
         *
         * Ate a 1.6.1 so eram consultados a raiz do corpo e o interior de
         * `data`, entao `meta` ficava VAZIO em toda listagem. O sintoma nao era
         * erro: o consumidor recebia a primeira pagina e nada indicava que
         * havia mais — em /private/v1/cities, milhares de registros viravam 15.
         *
         * Os outros dois caminhos continuam valendo: servico que responda no
         * formato antigo nao pode quebrar por causa desta adicao.
         */
        $pagination = $body['pagination'] ?? null;

        if (is_array($pagination)) {
            foreach (['current_page', 'last_page', 'per_page', 'total', 'from', 'to'] as $key) {
                if (isset($pagination[$key])) {
                    $meta[$key] = $pagination[$key];
                }
            }
        }

        if (isset($data['current_page'])) {
            foreach (['current_page', 'last_page', 'per_page', 'total', 'from', 'to'] as $key) {
                if (isset($data[$key])) {
                    $meta[$key] = $data[$key];
                }
            }

            if (isset($data['data'])) {
                $data = $data['data'];
            }
        }

        return new self(
            success: (bool) $success,
            statusCode: $response->getStatusCode(),
            message: (string) $message,
            data: $data,
            errors: $errors,
            meta: $meta,
            // Raiz do envelope, irmão de `data`: é onde o Falcon Hub põe o
            // código de negócio. Sem ler daqui, quem integra fica só com o HTTP
            // e não distingue "modelo não existe" de "faltou variável".
            code: isset($body['code']) && is_string($body['code']) ? $body['code'] : null,
        );
    }
}
