<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Exceptions;

use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;
use Throwable;

/**
 * A nota foi transmitida e o Ambiente Nacional / a prefeitura REJEITOU.
 *
 * Não é erro de HTTP: o Fiscal Hub respondeu 200 com a nota em `denied`, e é o
 * SDK que transforma isso em exceção — senão quem integra trataria a resposta
 * como sucesso e seguiria sem nota. A nota continua existindo (como rejeitada),
 * com o motivo nos eventos; corrigido o cadastro, `transmit()` de novo.
 */
class FiscalRejectionException extends FalconException
{
    /** @var array<string, mixed> */
    private array $invoice;

    /**
     * @param array<string, mixed> $invoice A nota como o Fiscal Hub devolveu.
     */
    public function __construct(
        string $message,
        array $invoice = [],
        int $code = 0,
        ?Throwable $previous = null,
        ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous, $response);
        $this->invoice = $invoice;
    }

    /** @return array<string, mixed> */
    public function getInvoice(): array
    {
        return $this->invoice;
    }

    public function getInvoiceId(): ?string
    {
        return isset($this->invoice['id']) ? (string) $this->invoice['id'] : null;
    }
}
