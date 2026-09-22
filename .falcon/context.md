---
slug: sdk-fiscalhub
name: SDK PHP do Fiscal Hub
kind: package
---

## O que é e por que existe

Cliente PHP da API do Fiscal Hub, usado pelos outros backends Falcon (o primeiro
é o DataHub, que emite a NFS-e das assinaturas) para emitir e consultar NFS-e
por chave de API (`ffx_...`), sem reimplementar autenticação, retry e erros.

## Decisões

- **Espelha o `falcon-crmhub-sdk-php` peça por peça.** Mesmo formato de Config,
  Client, facade, HTTP e exceções: o padrão de SDK da casa.
- **`emit()` = create + transmit, idempotente por `external_reference`.** Nota
  fiscal é ato irreversível; o SDK manda a referência e o `Idempotency-Key`, e o
  Fiscal Hub garante no banco (unique por dono + aplicativo + referência).
- **Rejeição do fisco vira exceção** (`FiscalRejectionException`), mesmo com
  HTTP 200 — senão o consumidor trataria nota rejeitada como sucesso.
- **URL não fica no SDK**: é pacote público; o endereço vem da config de quem
  consome.

## Pegadinhas

- 🟠 **Repositório se chama `falcon-fiscahub-sdk-php` (sem o "l")**, mas o pacote
  é `quantumtecnology/falcon-fiscalhub-sdk`. O nome do pacote vem do
  composer.json; o do repo aparece só na URL do GitHub/Packagist.
- 🟠 **Publicar tem três passos**: CHANGELOG + tag + conferir no Packagist (e
  configurar o webhook de auto-update, que no SDK do DataHub ficou faltando).
