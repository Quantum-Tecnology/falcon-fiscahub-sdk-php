# Changelog

Todas as mudanças relevantes do SDK PHP do Falcon Fiscal Hub são documentadas aqui.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

**Publicar uma versão tem três passos:** atualizar este arquivo, criar a tag no GitHub e conferir que ela chegou ao Packagist. Consumidores travam a versão no `composer.lock`, então a correção só chega a cada serviço depois de um `composer update` lá.

## [1.0.0] - 2026-09-22

### Adicionado

- **Primeira versão.** Espelha o SDK do Falcon Vendas (`falcon-crmhub-sdk-php`) peça por peça — mesmo `Config`, `Client`, facade, camada HTTP com retry e as mesmas exceções — para que quem já integra um consiga integrar o outro sem aprender nada novo.
- `nfse()`: `emit()` (cria e transmite numa chamada, **idempotente** por `external_reference`), `create()`, `transmit()` (manda `Idempotency-Key`), `find()`, `list()`, `cancel()`, `pdf()`, `xml()`, `events()`.
- `persons()`: `findByDocument()`, `create()`, `findOrCreate()`.
- `natureOperations()`: `nfseProfiles()` (só as naturezas prontas para NFS-e), `list()`, `find()`.
- `FiscalRejectionException`: o fisco rejeitou a nota. O Fiscal Hub responde 200 com a nota em `denied`, e sem esta exceção quem integra trataria a resposta como sucesso.
- `ForbiddenException` (403) com `getAbility()`: a permissão que faltou na chave.
- `PATCH` na camada HTTP (transmissão e cancelamento) e leitura de arquivo cru (PDF/XML).
- `ApiResponse` lê o bloco `pagination` desde o primeiro dia — a lição do SDK do DataHub, em que a paginação ficou vazia por nove versões sem ninguém notar.
- Timeout padrão de 60s, porque a transmissão é síncrona.
