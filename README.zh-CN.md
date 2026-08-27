English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/craft-clicktrail**

看清是哪个广告系列、哪个关键词、哪个点击 ID 和哪个落地页带来了 Craft CMS 的每一次表单提交、每一位客户和每一个 Commerce 订单。

</div>

[![CI](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-craft/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/craft-clicktrail)](https://packagist.org/packages/vizuh/craft-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## 目录

- [为什么](#为什么)
- [安装](#安装)
- [快速上手](#快速上手)
- [事件映射](#事件映射)
- [设置](#设置)
- [同意状态](#同意状态)
- [投递](#投递)
- [有何不同](#有何不同)
- [测试](#测试)
- [许可协议](#许可协议)

## 为什么

不是又一个分析脚本。ClickTrail 会为你的 Craft 站点产生的每一条线索和每一笔销售附加确定性的首次/末次触点归因，并以服务端方式发送到你的 ClickTrail 端点——“这位客户从哪里来？”的答案就落在记录旁边，而不是另一个面板里。

这里从不重复实现归因逻辑；每个 payload 都由共享内核 [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) 计算。

需要 Craft CMS 5.0+ 和 PHP 8.2+。可选：Craft Forms 插件（表单提交）与 Craft Commerce（订单）。

## 安装

```bash
composer require vizuh/craft-clicktrail
```

然后在 设置 → 插件 中安装，或执行：

```bash
php craft plugin/install clicktrail
```

## 快速上手

在任意站点模板中直接读取归因数据：

```twig
{{ clicktrail.attribution.first.source }}
{# 付费搜索落地后立即是 "google" ——
   之后无论多少次直接访问，依然是 "google" #}

<pre>{{ clicktrail.payload('page_view') | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
{# 规范化的扁平 payload：带 schema_version 标记、点分式 attribution.* 键，
   包含同意快照。当分析类同意为 unknown/denied 时渲染 []。 #}
```

一位访客从 Google Ads 广告进入，完成注册，然后下了 Commerce 订单。你的 ClickTrail 端点会收到三个规范化事件——`lead_created`、`lead_created`、`sale`——每个都盖有同一个不可变的首次触点（`attribution.first.source === 'google'`，点击 ID 保留），并附带事件发生时的末次触点。

## 事件映射

平台原生事件映射到规范化的 ClickTrail 事件：

| Craft 事件 | ClickTrail 事件 |
|---|---|
| 表单提交（Forms 插件） | `lead_created` |
| 用户注册 | `lead_created` |
| Commerce 订单完成 | `sale` |
| Commerce 订单退款 | `refund` |

每个映射都可以在设置中单独关闭。

## 设置

所有选项位于插件设置页（设置 → ClickTrail）：

| 设置项 | 默认值 | 用途 |
|---|---|---|
| Site ID | 空 | 向你的 ClickTrail 账户标识本站点 |
| 端点 URL | 空 | Payload 的 POST 目标 |
| 同意解析器类 | 空 | 自定义的 `ConsentResolverInterface` 实现，返回规范化快照；留空 = 所有信号均为 "unknown" |
| 归因持久化需要 `analytics_storage` | 开启 | 未获得分析类同意时不存储任何数据 |
| 广告点击 ID 存储需要 `advertising_storage` | 开启 | 未获得广告类同意时，将 gclid/fbclid/... 从存储中剔除 |
| 向广告目标发送哈希线索数据（`ad_user_data`） | 关闭 | 哈希线索转发的额外闸门；仍需获得 `ad_user_data` 授权 |
| 第一方代理 | 关闭 | 从你自己的域名提供 ClickTrail 加载器 |
| 映射表单提交 | 开启 | 表单提交时发出 `lead_created` |
| 映射用户注册 | 开启 | 注册时发出 `lead_created` |
| 映射 Commerce 订单 | 开启 | 订单完成时发出 `sale` |
| 映射退款 | 开启 | 发出 `refund` |

## 同意状态

ClickTrail 不取代你的同意管理平台——它服从该平台。规范化的同意契约（能力、快照结构、行为矩阵）见 [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md)。

- 提供方：实现 `ClickTrail\Craft\Services\Consent\ConsentResolverInterface`（返回当前 `ClickTrail\Consent\ConsentSnapshot`），并将插件设置指向该实现。真正的 CMP 适配器暂缓；WordPress 插件直接读取 WP Consent API。
- 同意状态未知时：**不存储、不发送**。被抑制的操作会通过 `suppressionReason()` 记录到诊断信息中。
- 解析出的快照与归因状态一同持久化，并随每个事件传递（每个 payload 中的 `consent` 键）。

## 投递

Payload 以 JSON 形式 POST 到 `<endpoint>/events`。投递失败会被记录为警告，确保没有任何数据悄无声息地丢失。完整传输通道（带退避的重试、幂等键）将在共享 SDK 客户端的接线完成后接入。

## 有何不同

| 常见的分析方案 | ClickTrail for Craft |
|---|---|
| 会话和页面躺在面板里 | 广告系列、关键词、点击 ID 和落地页落在**每条提交、每位客户和每个订单上** |
| 自己维护的客户端标签 | 一个 Twig 变量，一个第一方加载器 |
| 各平台各自重复实现归因逻辑 | 一台确定性引擎，在 WordPress、GTM 和 PHP 集成间以样例统一验证 |

## 测试

GitHub Actions CI 在每次推送时对所有 PHP 文件执行 lint（[workflow](https://github.com/vizuh/clicktrail-craft/blob/main/.github/workflows/ci.yml)）。

## 许可协议

MIT — Copyright (c) 2026 Vizuh OÜ。详见 [LICENSE](LICENSE)。
