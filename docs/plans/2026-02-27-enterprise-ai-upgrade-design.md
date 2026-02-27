# Smallwork Enterprise AI Upgrade - Design Document

**Date:** 2026-02-27
**Status:** Approved
**Approach:** Evolutionary Upgrade (phased)

## Overview

Transform Smallwork from a minimal PHP routing proof-of-concept into a full-stack AI platform with enterprise-grade features. The framework supports both server-rendered PHP templates (Blade-like + HTMX) and JSON APIs consumable by any JS framework (React, Vue, Angular, Svelte).

## Architecture

### Layered Design

- `src/` — Framework code (Core, Database, Auth, View, AI, Console)
- `app/` — User code (Controllers, Models, Middleware, Views, Prompts)
- `config/` — Configuration files
- `public/` — Web root (single entry point)
- `database/` — Migrations
- `storage/` — Logs, cache
- `tests/` — PHPUnit tests

### Directory Structure

```
smallwork/
├── composer.json
├── .env.example
├── smallwork                  # CLI entry point
├── public/
│   ├── index.php              # Single web entry point
│   └── .htaccess
├── config/
│   ├── app.php
│   ├── database.php
│   ├── ai.php
│   ├── auth.php
│   └── routes/
│       ├── api.php
│       └── web.php
├── src/
│   ├── Core/
│   │   ├── App.php            # Bootstrap & DI container
│   │   ├── Router.php         # Route matching & dispatch
│   │   ├── Request.php        # PSR-7-inspired request wrapper
│   │   ├── Response.php       # JSON, HTML, SSE response builder
│   │   ├── Middleware/
│   │   │   ├── Pipeline.php
│   │   │   ├── CorsMiddleware.php
│   │   │   ├── RateLimitMiddleware.php
│   │   │   └── AuthMiddleware.php
│   │   └── Container.php      # DI container
│   ├── Database/
│   │   ├── Connection.php     # Connection factory
│   │   ├── QueryBuilder.php   # Fluent query builder
│   │   ├── Migration.php      # Schema migrations
│   │   └── Adapters/
│   │       ├── PdoAdapter.php      # SQLite, MySQL, PostgreSQL
│   │       ├── RedisAdapter.php
│   │       └── QdrantAdapter.php
│   ├── Auth/
│   │   ├── JwtAuth.php
│   │   ├── ApiKeyAuth.php
│   │   └── RoleManager.php
│   ├── View/
│   │   ├── Engine.php         # Blade-like template engine
│   │   ├── HtmxHelper.php
│   │   └── JsonResponse.php
│   ├── AI/
│   │   ├── Gateway.php        # Unified AI provider interface
│   │   ├── Providers/
│   │   │   ├── ProviderInterface.php
│   │   │   ├── OpenAIProvider.php
│   │   │   ├── AnthropicProvider.php
│   │   │   └── GrokProvider.php
│   │   ├── Chat.php           # Chat/completion with history
│   │   ├── Embeddings.php     # Embedding generation
│   │   ├── SemanticSearch.php # Vector search
│   │   ├── Middleware/
│   │   │   ├── ContentModeration.php
│   │   │   ├── IntentClassifier.php
│   │   │   └── AutoSummarizer.php
│   │   └── Prompts/
│   │       ├── TemplateEngine.php
│   │       └── VersionManager.php
│   └── Console/
│       ├── CLI.php
│       ├── MigrateCommand.php
│       └── ServeCommand.php
├── app/
│   ├── Controllers/
│   │   ├── Api/
│   │   └── Web/
│   ├── Models/
│   ├── Middleware/
│   ├── Views/
│   │   └── layouts/
│   └── Prompts/
├── tests/
│   ├── Unit/
│   └── Integration/
├── database/
│   └── migrations/
└── storage/
    ├── logs/
    └── cache/
```

## Phase 1: Core Framework

### Router
- Expressive route definitions: `$router->get('/path', [Controller::class, 'method'])`
- HTTP method support: GET, POST, PUT, PATCH, DELETE
- Route groups with shared middleware
- Route parameters: `/users/{id}`
- Named routes for URL generation

### Request
- Wraps `$_SERVER`, `$_GET`, `$_POST`, raw body
- Auto-parses JSON request bodies
- Methods: `input()`, `json()`, `header()`, `method()`, `query()`, `file()`
- Supports all HTTP methods

### Response
- `Response::json($data, $status)` — JSON responses
- `Response::view($template, $data)` — Rendered HTML
- `Response::stream($callback)` — SSE for AI streaming
- `Response::htmx($html)` — HTMX partial responses
- Fluent header setting

### Middleware Pipeline
- Onion-style: each middleware receives Request and `$next` callable
- Global middleware (applied to all routes)
- Route-group middleware
- Per-route middleware

### DI Container
- Service binding with closures
- Constructor auto-wiring
- Singleton support
- Interface-to-implementation binding

## Phase 2: Database Layer

### Connection Factory
- Creates correct adapter based on config
- Supports: SQLite, MySQL, MariaDB, PostgreSQL (via PDO)
- Redis adapter for caching, rate limiting, sessions
- Qdrant adapter for vector storage
- pgvector adapter as alternative vector store

### Query Builder
- Fluent interface: `DB::table('x')->where()->orderBy()->get()`
- Insert, update, delete, select
- Joins, aggregates
- Raw query escape hatch
- Transaction support

### Migrations
- PHP-based migration files with `up()` and `down()`
- CLI command: `php smallwork migrate`
- Migration tracking table
- Rollback support

### Vector Store Interface
- Unified API: `upsert()`, `search()`, `delete()`, `createCollection()`
- Implemented by QdrantAdapter and PgvectorAdapter
- Configurable distance metric (cosine, euclidean, dot product)

## Phase 3: Authentication & Security

### JWT Authentication
- Token issuance on login
- Token verification middleware
- Refresh token support
- Configurable expiry

### API Key Authentication
- Keys stored hashed in database
- Sent via `Authorization: Bearer` or `X-API-Key` header
- Per-key rate limits and permissions

### RBAC
- Roles: configurable (default: admin, user, service)
- Permissions: granular (e.g., `chat:create`, `embeddings:write`)
- Role-permission mapping in config
- Middleware: `RoleMiddleware::require('admin')`

### Security Middleware
- CORS with configurable origins
- Rate limiting (Redis-backed, sliding window)
- CSRF protection for web forms
- Input validation layer

## Phase 4: View & Frontend Layer

### Template Engine (Blade-like)
- `{{ $var }}` — escaped output
- `{!! $var !!}` — raw output
- `@if / @elseif / @else / @endif`
- `@foreach / @endforeach`
- `@extends('layout')` / `@section('name')` / `@yield('name')`
- `@include('partial')`
- Template file extension: `.sw.php`

### HTMX Integration
- Response helpers for HTMX swaps
- SSE streaming helpers for AI chat
- Trigger headers

### JS Framework Support
- API-first design: all endpoints return JSON
- CORS configured for SPA consumption
- No framework-specific integrations needed — just consume the API

## Phase 5: AI Layer

### AI Gateway
- Unified interface across providers (OpenAI, Anthropic, Grok)
- Leverages OpenAI-compatible API format where possible
- Thin adapters for provider-specific differences
- Features: streaming (SSE), token counting, error standardization, retries with backoff
- Provider selection per request: `$ai->chat($messages, provider: 'anthropic')`

### Chat/Completion
- Conversation history management
- System prompt injection
- Streaming responses via SSE
- Token usage tracking
- Configurable model parameters (temperature, max_tokens, etc.)

### Embeddings + Semantic Search
- Generate embeddings via any provider
- Store in Qdrant or pgvector via VectorStore interface
- Semantic search: query embedding -> vector search -> ranked results
- RAG support: auto-inject search results into chat context

### AI Middleware
- **ContentModeration** — classify and block harmful content before controller
- **IntentClassifier** — adds `$request->intent()` with classified user intent
- **AutoSummarizer** — summarizes long inputs, adds `$request->summary()`

### Prompt Management
- Template syntax: `{{variable}}` substitution
- File convention: `app/Prompts/{name}.v{version}.prompt`
- API: `Prompt::render('name', $vars)`, `Prompt::version('name', 2)`, `Prompt::latest('name')`
- Versioning for A/B testing and rollback

## Phase 6: Enterprise Features

### Logging
- PSR-3-compatible logger
- JSON-structured log output
- Log levels: debug, info, warning, error, critical
- Output to `storage/logs/`

### Health Checks
- `GET /health` endpoint
- Checks: database connectivity, Redis, AI provider availability
- Returns JSON status with component details

### OpenAPI Spec Generation
- Auto-generates from route definitions
- PHPDoc annotation support for descriptions and schemas
- Serves spec at `/api/docs`

### CLI Tool
- `php smallwork serve` — built-in dev server
- `php smallwork migrate` — run migrations
- `php smallwork make:controller` — scaffolding
- `php smallwork make:migration`
- `php smallwork make:model`

### Testing
- PHPUnit configuration
- Test helpers for mocking AI responses
- Database test traits (transactions, refresh)
- HTTP test client for integration tests

## Technology Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Package management | Composer | PHP standard, required for autoloading and dependencies |
| PHP version | 8.2+ | Enums, fibers, readonly properties, named args |
| AI provider format | OpenAI-compatible | De facto standard, minimizes adapter code |
| Vector DB primary | Qdrant | Purpose-built, excellent API, production-ready |
| Vector DB alt | pgvector | For teams already on PostgreSQL |
| Cache/sessions | Redis | Industry standard for ephemeral data |
| Template syntax | Blade-like | Familiar to PHP developers, minimal learning curve |
| Testing | PHPUnit | PHP standard testing framework |

## Non-Goals (YAGNI)

- WebSocket server (use SSE instead, simpler)
- GraphQL (REST/JSON is sufficient)
- ORM with active record (query builder is sufficient)
- Admin panel UI (out of scope for framework)
- Multi-tenancy (can be built by users)
