<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Exceptions;

use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;
use Throwable;

/**
 * A cota do mês acabou, ou o plano não inclui o recurso (HTTP 402).
 *
 * Carrega `used`/`limit` acessíveis porque o sistema integrado precisa reagir
 * programaticamente: mostrar quanto falta, parar de tentar até o mês virar, ou
 * avisar o próprio usuário. Uma mensagem de texto obrigaria cada consumidor a
 * fazer parsing de string para descobrir isso.
 */
class QuotaExceededException extends FalconException
{
    private int $used;
    private int $limit;
    private ?string $reason;

    public function __construct(
        string $message = 'Quota exceeded',
        int $used = 0,
        int $limit = 0,
        ?string $reason = null,
        int $code = 402,
        ?Throwable $previous = null,
        ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous, $response);
        $this->used   = $used;
        $this->limit  = $limit;
        $this->reason = $reason;
    }

    public function getUsed(): int
    {
        return $this->used;
    }

    /** -1 significa ilimitado. */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * `QUOTA_EXCEEDED` quando acabou a cota de emissão do plano no mês. O
     * limite de cadastros (emitentes, tomadores) também responde 402, sem código.
     *
     * Nos dois casos a solução é upgrade (ou esperar o mês virar, na cota de
     * emissão) — nunca repetir a chamada em seguida.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
