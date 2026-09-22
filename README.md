# Falcon Fiscal Hub SDK (PHP)

SDK PHP para a API do **Falcon Fiscal Hub**. Permite que o seu sistema:

- **emita NFS-e** (padrão nacional) numa chamada só, sem nota duplicada;
- **consulte, baixe PDF/XML e cancele** notas;
- **ache ou cadastre o tomador** pelo CPF/CNPJ;
- **liste as naturezas de operação** prontas para NFS-e (o perfil de emissão).

Sem dependências além de `ext-curl` e `ext-json`.

## Instalação

```bash
composer require quantumtecnology/falcon-fiscalhub-sdk
```

## Antes de começar: as notas saem no CNPJ do dono da conta

> ⚠️ Uma nota emitida pelo seu sistema vale como se o dono da conta do Fiscal
> Hub a tivesse emitido. Nota autorizada **não se desfaz**: cancelar tem prazo e,
> dependendo do município, multa.
>
> - Mande **sempre** `external_reference` (o id do pedido/cobrança no seu
>   sistema). Reenviar a mesma referência devolve a nota que já existe em vez de
>   emitir outra — é o que protege o dono da conta do seu retry.
> - Peça só as permissões que o seu sistema usa.

O dono da conta cria a chave no painel (**Chaves de API**), escolhe as
permissões e aceita o termo de responsabilidade. Cada chave tem um **nome** — o
do seu sistema — e esse nome vira a **origem** das notas que ela emitir: é como o
dono filtra e reconhece as suas notas na listagem.

| Permissão | Para |
|---|---|
| `nfse:read` | listar, consultar, baixar PDF/XML |
| `nfse:write` | emitir, transmitir, cancelar |
| `cadastros:read` | buscar tomador e naturezas de operação |
| `cadastros:write` | cadastrar tomador |

## Uso

```php
use QuantumTecnology\FalconFiscalHub\FiscalHubClient;
use QuantumTecnology\FalconFiscalHub\FiscalHubConfig;

$fiscal = new FiscalHubClient(FiscalHubConfig::make(
    'https://fiscal.falcon-server.com.br', // URL da API — vem da SUA config
    'ffx_abc12345_...',                    // chave criada no painel
));
```

A URL não está no SDK de propósito: é configuração do seu ambiente.

### Emitir uma NFS-e

A **natureza de operação** é o perfil de emissão: o dono da conta cadastra nela o
emitente, a série, os códigos de serviço (cTribNac, cTribMun, NBS), o ISS e a
descrição padrão. O seu sistema só informa o que muda de nota para nota.

```php
// Uma vez: qual natureza usar (guarde o id na sua config).
$perfis = $fiscal->natureOperations()->nfseProfiles();

// Tomador: acha pelo documento ou cadastra. Opcional — sem tomador a nota sai
// com "tomador não identificado", como o leiaute nacional permite.
$tomador = $fiscal->persons()->findOrCreate([
    'type'         => 'pf',
    'cpf'          => '12345678901',
    'razao_social' => 'Ana Silva',
]);

$nota = $fiscal->nfse()->emit([
    'nature_operation_id' => $naturezaId,
    'recipient_person_id' => $tomador['id'],
    'external_reference'  => 'pedido-123',   // ⭐ idempotência
    'service'             => ['valor_servicos' => 49.90],
]);

$nota->data['status']['value']; // authorized
$nota->data['number'];
```

`emit()` cria a nota e transmite. Se a referência já tiver sido emitida, devolve
a nota existente **sem transmitir de novo**.

### Baixar, consultar, cancelar

```php
$pdf = $fiscal->nfse()->pdf($notaId);   // bytes do DANFSe
$xml = $fiscal->nfse()->xml($notaId);

$fiscal->nfse()->find($notaId);
$fiscal->nfse()->events($notaId);        // linha do tempo (inclui motivos de rejeição)
$fiscal->nfse()->cancel($notaId, 'Serviço não prestado.');

// Listagem paginada: a paginação vem em ->meta
$pagina = $fiscal->nfse()->list(['page' => 2, 'filter' => ['status' => 'authorized']]);
$pagina->meta['total'];
```

## Erros

Toda falha vira exceção tipada (todas estendem `FalconException`):

| Exceção | Quando | O que fazer |
|---|---|---|
| `FiscalRejectionException` | o fisco **rejeitou** a nota (o HTTP foi 200) | corrigir o cadastro e `transmit()` de novo; a nota fica salva como rejeitada |
| `ValidationException` (422) | dado inválido | `getErrors()` diz os campos |
| `ForbiddenException` (403) | a chave não tem a permissão | `getAbility()` diz qual; ajustar no painel |
| `QuotaExceededException` (402) | acabou a cota de emissão do plano | upgrade — **não** repetir |
| `AuthException` (401) | chave inválida, revogada ou expirada | nova chave no painel |
| `RateLimitException` (429) | mais de 120 req/min na chave | `getRetryAfter()` |
| `NotFoundException` (404) | nota de outra conta ou inexistente | — |
| `ServerException` (5xx) / `TimeoutException` | indisponível | tentar depois (com a mesma `external_reference`) |

```php
use QuantumTecnology\FalconFiscalHub\Exceptions\FiscalRejectionException;

try {
    $fiscal->nfse()->emit($payload);
} catch (FiscalRejectionException $e) {
    $e->getMessage();     // motivo do fisco
    $e->getInvoiceId();   // a nota rejeitada, para corrigir e retransmitir
}
```

## Timeout

60 segundos por padrão: a transmissão é **síncrona** (assina, envia ao Ambiente
Nacional e espera a autorização). Cortar antes faria o seu sistema achar que
falhou uma nota que foi autorizada — e com `external_reference`, tentar de novo
é seguro de qualquer forma.

## Testes

```bash
composer test
```
