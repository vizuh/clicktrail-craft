English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/craft-clicktrail**

Veja qual campanha, palavra-chave, click ID e página de destino gerou cada envio de formulário, cliente e pedido do Commerce no Craft CMS.

</div>

[![CI](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/craft-clicktrail)](https://packagist.org/packages/vizuh/craft-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Índice

- [Por quê](#por-quê)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Mapeamento de eventos](#mapeamento-de-eventos)
- [Configurações](#configurações)
- [Consentimento](#consentimento)
- [Entrega](#entrega)
- [Como é diferente](#como-é-diferente)
- [Testes](#testes)
- [Licença](#licença)

## Por quê

Não é mais um script de analytics. O ClickTrail anexa atribuição determinística de primeiro/último toque a cada lead e venda que seu site Craft produz e envia server-side ao seu endpoint ClickTrail — assim a resposta para "de onde veio este cliente?" fica junto do registro, não num painel separado.

A lógica de atribuição nunca é reimplementada aqui; o núcleo compartilhado [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) calcula cada payload.

Requer Craft CMS 5.0+ e PHP 8.2+. Opcional: o plugin Craft Forms (envios de formulário) e o Craft Commerce (pedidos).

## Instalação

```bash
composer require vizuh/craft-clicktrail
```

Depois instale em Configurações → Plugins, ou:

```bash
php craft plugin/install clicktrail
```

## Início rápido

Leia a atribuição diretamente em qualquer template do site:

```twig
{{ clicktrail.attribution.first.source }}
{# "google" logo após uma landing de pesquisa paga —
   e continua "google" depois de quantas visitas diretas forem #}

<pre>{{ clicktrail.payload('page_view') | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
{# payload canônico e plano: com schema_version, chaves attribution.* pontilhadas,
   snapshot de consentimento incluído. Renderiza [] quando o consentimento
   de analytics está unknown/denied. #}
```

Um visitante chega por um anúncio do Google Ads, se registra e conclui um pedido no Commerce. Seu endpoint ClickTrail recebe três eventos canônicos — `lead.submitted`, `lead.submitted`, `sale.completed` — cada um carimbado com o mesmo primeiro toque imutável (`attribution.first.source === 'google'`, click ID preservado) mais o último toque no momento do evento.

## Mapeamento de eventos

Eventos nativos da plataforma são mapeados para eventos canônicos ClickTrail:

| Evento Craft | Evento ClickTrail |
|---|---|
| Envio de formulário (plugin Forms) | `lead.submitted` |
| Registro de usuário | `lead.submitted` |
| Pedido Commerce concluído | `sale.completed` |
| Pedido Commerce reembolsado | `sale.refunded` |

Cada mapeamento pode ser desligado individualmente nas configurações.

## Configurações

Todas as opções ficam na página de configurações do plugin (Configurações → ClickTrail):

| Opção | Padrão | Finalidade |
|---|---|---|
| Site ID | vazio | Identifica este site para sua conta ClickTrail |
| URL do endpoint | vazia | Para onde os payloads são enviados via POST |
| Classe resolvedora de consentimento | vazia | Implementação customizada de `ConsentResolverInterface` que retorna o snapshot normalizado; vazia = todos os sinais "unknown" |
| Persistência exige `analytics_storage` | ligado | Não armazenar nada sem consentimento de analytics concedido |
| Click IDs exigem `advertising_storage` | ligado | Remover gclid/fbclid/... do armazenamento sem consentimento de publicidade |
| Enviar dados de lead com hash a destinos de anúncios (`ad_user_data`) | desligado | Portão extra para encaminhamento de leads com hash; ainda exige `ad_user_data` concedido |
| Proxy first-party | desligado | Servir o loader ClickTrail pelo seu próprio domínio |
| Mapear envios de formulário | ligado | Emitir `lead.submitted` em envios de formulário |
| Mapear registros de usuário | ligado | Emitir `lead.submitted` no registro |
| Mapear pedidos do Commerce | ligado | Emitir `sale.completed` na conclusão do pedido |
| Mapear reembolsos | ligado | Emitir `sale.refunded` |

## Consentimento

O ClickTrail não substitui sua plataforma de consentimento — ele a obedece. O contrato normalizado de consentimento (capacidades, formato do snapshot, matriz de comportamento) está em [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Provedor: implemente `ClickTrail\Craft\Services\Consent\ConsentResolverInterface` (retorna o `ClickTrail\Consent\ConsentSnapshot` atual) e aponte a configuração do plugin para ela. Adaptadores reais de CMP estão adiados; o plugin WordPress lê a WP Consent API diretamente.
- Com consentimento desconhecido: **não armazenar nem enviar**. Ações suprimidas são registradas com `suppressionReason()` nos diagnósticos.
- O snapshot resolvido é persistido junto ao estado de atribuição e viaja com cada evento (chave `consent` em cada payload).

## Entrega

Os payloads são enviados como JSON para `<endpoint>/events`. Falhas de entrega são registradas como warnings para que nada desapareça silenciosamente. O transporte completo (retries com backoff, idempotency keys) pertence ao client do SDK compartilhado assim que a integração entrar.

## Como é diferente

| Configuração típica de analytics | ClickTrail para Craft |
|---|---|
| Sessões e páginas num dashboard | Campanha, palavra-chave, click ID e landing page **em cada envio, cliente e pedido** |
| Tags client-side mantidas por você | Uma variável Twig, um loader first-party |
| Lógica de atribuição duplicada por plataforma | Um motor determinístico, testado por fixtures no WordPress, GTM e integrações PHP |

## Testes

O CI no GitHub Actions faz lint de todos os arquivos PHP a cada push ([workflow](https://github.com/vizuh/clicktrail-craft/blob/main/.github/workflows/ci.yml)).

## Licença

MIT — Copyright (c) 2026 Vizuh OÜ. Consulte [LICENSE](LICENSE).
