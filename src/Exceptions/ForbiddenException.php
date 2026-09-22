<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconFiscalHub\Exceptions;

use QuantumTecnology\FalconFiscalHub\Response\ApiResponse;
use Throwable;

/**
 * A chave não pode fazer isso (HTTP 403).
 *
 * Na maioria das vezes é permissão da chave: ela foi criada sem `nfse:write`, e
 * `getAbility()` diz qual permissão faltou. Pode ser também o plano do dono da
 * conta sem API liberada. Nos dois casos a solução é no painel do Fiscal Hub —
 * tentar de novo não muda nada.
 */
class ForbiddenException extends FalconException
{
    private ?string $ability;

    public function __construct(
        string $message = 'Forbidden',
        ?string $ability = null,
        int $code = 403,
        ?Throwable $previous = null,
        ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous, $response);
        $this->ability = $ability;
    }

    /** A permissão que faltou (ex.: `nfse:write`), quando o motivo foi esse. */
    public function getAbility(): ?string
    {
        return $this->ability;
    }
}
