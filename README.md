# Smallwork

A small footprint full-stack AI framework for PHP. Build AI-powered web applications with a unified multi-provider AI gateway, server-rendered templates, JSON APIs, vector search, and enterprise features — all without the overhead of a large framework.

## Requirements

- PHP 8.2+
- Composer
- cURL extension (for AI providers)
- Optional: Redis, Qdrant, PostgreSQL with pgvector

## Quick Start

```bash
# Install dependencies
composer install

# Copy environment config
cp .env.example .env

# Start development server
php smallwork serve

# Or use PHP directly
php -S localhost:8080 -t public
```

Visit `http://localhost:8080` to verify it's running.

## Project Structure

```
smallwork/
├── public/              # Web root (single entry point)
│   ├── index.php
│   └── .htaccess
├── config/
│   ├── app.php          # Application settings
│   ├── database.php     # Database connections
│   ├── auth.php         # JWT and RBAC config
│   ├── ai.php           # AI provider config
│   └── routes/
│       ├── api.php      # API route definitions
│       └── web.php      # Web route definitions
├── src/                 # Framework source code
│   ├── Core/            # Router, Request, Response, Container, Middleware
│   ├── Database/        # Query builder, migrations, adapters
│   ├── Auth/            # JWT, API keys, roles
│   ├── View/            # Template engine, HTMX helpers
│   ├── AI/              # Gateway, providers, chat, embeddings, search
│   ├── Console/         # CLI commands
│   └── Testing/         # Test helpers
├── app/                 # Your application code
│   ├── Controllers/
│   ├── Models/
│   ├── Middleware/
│   ├── Views/
│   └── Prompts/
├── database/
│   └── migrations/
├── storage/
│   ├── logs/
│   └── cache/
└── tests/
    ├── Unit/
    └── Integration/
```

## Configuration

Copy `.env.example` to `.env` and set your values:

```ini
APP_NAME=Smallwork
APP_ENV=local
APP_DEBUG=true

DB_DRIVER=sqlite
DB_DATABASE=storage/database.sqlite

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GROK_API_KEY=xai-...
```

Access environment variables anywhere with the `env()` helper:

```php
$debug = env('APP_DEBUG', false);    // Casts "true"/"false" to booleans
$name  = env('APP_NAME', 'Default');
```

---

## Routing

Define routes in `config/routes/web.php` or `config/routes/api.php`. The router is passed as `$router`.

### Basic Routes

```php
$router->get('/hello', function (Request $request) {
    return Response::json(['message' => 'Hello, world!']);
});

$router->post('/users', function (Request $request) {
    $data = $request->json();
    return Response::json(['created' => $data['name']], 201);
});

$router->put('/users/{id}', function (Request $request) {
    $id = $request->param('id');
    return Response::json(['updated' => $id]);
});

$router->delete('/users/{id}', function (Request $request) {
    return Response::empty();
});
```

### Route Parameters

```php
$router->get('/posts/{slug}/comments/{id}', function (Request $request) {
    $slug = $request->param('slug');
    $id   = $request->param('id');
    return Response::json(compact('slug', 'id'));
});
```

### Route Groups

Groups share a URL prefix and optional middleware:

```php
$router->group('/api/v1', function (Router $r) {
    $r->get('/users', [UserController::class, 'index']);
    $r->post('/users', [UserController::class, 'store']);

    $r->group('/admin', function (Router $r) {
        $r->get('/stats', [AdminController::class, 'stats']);
    }, middleware: ['AdminOnly']);

}, middleware: ['AuthMiddleware']);
```

### Per-Route Middleware

```php
$router->get('/dashboard', [DashController::class, 'index'], middleware: ['AuthMiddleware']);
```

---

## Request

The `Request` object wraps all HTTP input:

```php
// Query string: /search?q=php&page=2
$query = $request->query('q');          // "php"
$page  = $request->query('page', 1);   // "2"
$all   = $request->query();            // ['q' => 'php', 'page' => '2']

// POST / form data
$name = $request->input('name');

// JSON body (auto-parsed)
$data  = $request->json();             // Full decoded array
$email = $request->json('email');      // Specific key

// Headers (case-insensitive)
$token = $request->header('Authorization');
$all   = $request->headers();

// Route parameters
$id = $request->param('id');

// Raw body
$raw = $request->rawBody();

// Method helpers
$request->isGet();
$request->isPost();

// Custom attributes (set by middleware)
$user = $request->getAttribute('user');
```

---

## Response

Build responses with static factory methods:

```php
// JSON
return Response::json(['users' => $users]);
return Response::json(['error' => 'Not found'], 404);

// HTML
return Response::html('<h1>Hello</h1>');

// Empty (204 No Content)
return Response::empty();

// Redirect
return Response::redirect('/login');
return Response::redirect('/new-location', 301);

// Server-Sent Events (SSE) streaming
return Response::stream(function () {
    echo "data: chunk 1\n\n";
    flush();
    echo "data: chunk 2\n\n";
    flush();
});
```

Responses are immutable — `withHeader` and `withCookie` return new instances:

```php
return Response::json($data)
    ->withHeader('X-Request-Id', $requestId)
    ->withHeader('Cache-Control', 'no-store')
    ->withCookie('session', $token, maxAge: 3600);
```

---

## Middleware

Middleware follows the onion model. Each middleware receives the `Request` and a `$next` callable:

```php
class TimingMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        return $response->withHeader('X-Response-Time', "{$elapsed}ms");
    }
}
```

### Registering Middleware

```php
// Global middleware (all routes)
$app->addMiddleware(new CorsMiddleware());
$app->addMiddleware(new RateLimitMiddleware(maxRequests: 100, windowSeconds: 60));

// Route group middleware
$router->group('/api', function (Router $r) {
    // routes here
}, middleware: ['AuthMiddleware']);

// Per-route middleware
$router->get('/admin', $handler, middleware: ['AdminOnly']);
```

### Built-in Middleware

**CORS**

```php
$cors = new CorsMiddleware(
    allowedOrigins: ['https://myapp.com', 'https://staging.myapp.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization'],
    maxAge: 86400,
);
$app->addMiddleware($cors);
```

**Rate Limiting**

```php
$limiter = new RateLimitMiddleware(
    maxRequests: 60,   // requests per window
    windowSeconds: 60, // window size
);
$app->addMiddleware($limiter);
// Returns 429 Too Many Requests when exceeded, with X-RateLimit-* headers
```

**Authentication** (see [Authentication](#authentication) section)

**Role/Permission** (see [Authorization](#authorization) section)

---

## Dependency Injection Container

The container supports binding, singletons, instances, and auto-wiring:

```php
$container = $app->container();

// Bind a factory (new instance each time)
$container->bind(Mailer::class, fn() => new Mailer(env('SMTP_HOST')));

// Singleton (created once, reused)
$container->singleton(Gateway::class, function () {
    $gw = new Gateway('openai');
    $gw->register('openai', new OpenAIProvider(
        baseUrl: 'https://api.openai.com/v1',
        apiKey: env('OPENAI_API_KEY'),
    ));
    return $gw;
});

// Register an existing instance
$container->instance('config', $configArray);

// Resolve
$mailer = $container->resolve(Mailer::class);

// Auto-wire (resolves constructor dependencies via type hints)
$controller = $container->make(UserController::class);
```

---

## Database

### Connecting

Configure in `config/database.php` or `.env`:

```php
// SQLite
$adapter = Connection::create([
    'driver'   => 'sqlite',
    'database' => 'storage/database.sqlite',
]);

// MySQL
$adapter = Connection::create([
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => '',
]);

// PostgreSQL
$adapter = Connection::create([
    'driver'   => 'pgsql',
    'host'     => '127.0.0.1',
    'port'     => 5432,
    'database' => 'myapp',
    'username' => 'postgres',
    'password' => '',
]);
```

### Query Builder

Fluent interface for building SQL queries:

```php
$db = Connection::create($config);
$qb = new QueryBuilder($db, 'users');

// SELECT
$users = $qb->select('id', 'name', 'email')
    ->where('active', '=', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20)
    ->get();

// Single row
$user = (new QueryBuilder($db, 'users'))
    ->where('id', '=', 42)
    ->first();

// Count
$total = (new QueryBuilder($db, 'users'))
    ->where('active', '=', 1)
    ->count();

// INSERT (returns last insert ID)
$id = (new QueryBuilder($db, 'users'))->insert([
    'name'  => 'Alice',
    'email' => 'alice@example.com',
]);

// UPDATE (returns affected row count)
$affected = (new QueryBuilder($db, 'users'))
    ->where('id', '=', 42)
    ->update(['name' => 'Bob']);

// DELETE
$deleted = (new QueryBuilder($db, 'users'))
    ->where('active', '=', 0)
    ->delete();

// JOIN
$posts = (new QueryBuilder($db, 'posts'))
    ->select('posts.title', 'users.name')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->get();

// GROUP BY
$counts = (new QueryBuilder($db, 'orders'))
    ->select('status')
    ->groupBy('status')
    ->get();
```

### Transactions

```php
$db->beginTransaction();
try {
    (new QueryBuilder($db, 'accounts'))
        ->where('id', '=', 1)
        ->update(['balance' => 900]);

    (new QueryBuilder($db, 'accounts'))
        ->where('id', '=', 2)
        ->update(['balance' => 1100]);

    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    throw $e;
}
```

### Migrations

Create migration files in `database/migrations/`:

```php
<?php
// database/migrations/2026_02_27_000001_create_users.php

use Smallwork\Database\Migration;
use Smallwork\Database\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Schema $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('active')->nullable();
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('users');
    }
};
```

Run migrations:

```bash
php smallwork migrate
php smallwork migrate --rollback
```

Or programmatically:

```php
$migrator = new Migrator($db, __DIR__ . '/database/migrations');
$count = $migrator->migrate();   // Run pending
$count = $migrator->rollback();  // Revert last batch
```

### Redis

```php
// Production (real Redis via RESP protocol)
$redis = RedisAdapter::create([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

// Testing (in-memory, no Redis needed)
$redis = RedisAdapter::createInMemory();

$redis->set('session:abc', json_encode($data), ttl: 3600);
$value = $redis->get('session:abc');
$redis->exists('session:abc');  // true
$redis->delete('session:abc');

$redis->increment('page:views');
$redis->decrement('stock:item:42');

$redis->flush();  // Clear everything
```

---

## Authentication

### JWT

```php
$jwt = new JwtAuth(secret: env('JWT_SECRET'));

// Create token
$token = $jwt->encode(
    payload: ['user_id' => 42, 'role' => 'admin'],
    expiresIn: 3600,
);

// Validate and decode
try {
    $payload = $jwt->decode($token);
    // ['user_id' => 42, 'role' => 'admin', 'iat' => ..., 'exp' => ...]
} catch (\RuntimeException $e) {
    // Invalid or expired token
}

// Refresh (issues new token with same payload)
$newToken = $jwt->refresh($oldToken, expiresIn: 3600);
```

### API Keys

```php
$apiKeys = new ApiKeyAuth($db);
$apiKeys->createTable();  // One-time setup

// Generate a new key
$result = $apiKeys->generate('My Service', permissions: ['chat:create', 'embeddings:read']);
// ['id' => 1, 'key' => 'sw_a1b2c3d4e5f6...']  (show key to user once)

// Verify incoming key
$info = $apiKeys->verify($key);
// ['id' => 1, 'name' => 'My Service', 'permissions' => [...], 'created_at' => '...']
// Returns null if invalid

// Manage keys
$apiKeys->revoke(1);
$all = $apiKeys->list();
```

### Auth Middleware

Protect routes with JWT or API key authentication:

```php
// JWT strategy
$authMiddleware = AuthMiddleware::jwt($jwt);

// API key strategy
$authMiddleware = AuthMiddleware::apiKey($apiKeys);

// Register
$app->container()->instance('auth', $authMiddleware);

$router->group('/api', function (Router $r) {
    $r->get('/profile', function (Request $request) {
        $user = $request->getAttribute('user');
        return Response::json($user);
    });
}, middleware: ['auth']);
```

The middleware reads `Authorization: Bearer <token>` or `X-Api-Key: <key>` headers and sets the `user` attribute on the request.

---

## Authorization

### Role-Based Access Control (RBAC)

Configure roles and permissions in `config/auth.php`:

```php
return [
    'roles' => [
        'admin'   => ['chat:create', 'chat:read', 'users:manage', 'embeddings:write'],
        'user'    => ['chat:create', 'chat:read'],
        'service' => ['embeddings:write', 'embeddings:read'],
    ],
];
```

Use in code:

```php
$roles = new RoleManager($config['roles']);

$roles->hasPermission('admin', 'users:manage');  // true
$roles->hasPermission('user', 'users:manage');   // false
$roles->getPermissions('admin');                  // ['chat:create', ...]
$roles->roleExists('admin');                      // true
```

### Role and Permission Middleware

```php
// Require a specific role
$adminOnly = new RoleMiddleware($roles, 'admin');

// Require a specific permission
$canManage = RoleMiddleware::requirePermission($roles, 'users:manage');

// Apply to routes
$router->group('/admin', function (Router $r) {
    $r->get('/users', [AdminController::class, 'users']);
}, middleware: [$adminOnly]);
```

The middleware reads `$request->getAttribute('user')['role']` (set by AuthMiddleware) and returns 403 if unauthorized.

---

## Input Validation

```php
$validator = Validator::make($request->json(), [
    'name'     => 'required|string|min:2|max:100',
    'email'    => 'required|email',
    'age'      => 'required|numeric|min:18|max:120',
    'role'     => 'required|in:admin,user,editor',
    'tags'     => 'array',
]);

if ($validator->fails()) {
    return Response::json(['errors' => $validator->errors()], 422);
}
```

**Available rules:**

| Rule | Description |
|------|-------------|
| `required` | Field must be present and non-empty |
| `string` | Must be a string |
| `numeric` | Must be numeric |
| `email` | Must be a valid email format |
| `array` | Must be an array |
| `min:N` | Minimum length (string) or value (numeric) |
| `max:N` | Maximum length (string) or value (numeric) |
| `in:a,b,c` | Must be one of the listed values |

---

## Views & Templates

### Blade-like Template Engine

Templates use `.sw.php` extension and live in `app/Views/`:

**Layout** (`app/Views/layouts/app.sw.php`):

```html
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title') - My App</title>
    @yield('head')
</head>
<body>
    @yield('content')
    @yield('scripts')
</body>
</html>
```

**Page** (`app/Views/home.sw.php`):

```html
@extends('layouts.app')

@section('title')Home@endsection

@section('content')
    <h1>Welcome, {{ $name }}</h1>

    @if($isAdmin)
        <p>Admin panel: <a href="/admin">Go</a></p>
    @else
        <p>You are a regular user.</p>
    @endif

    <ul>
    @foreach($items as $item)
        <li>{{ $item }}</li>
    @endforeach
    </ul>

    @include('partials.footer')
@endsection
```

**Rendering:**

```php
$engine = new Engine(
    viewsPath: __DIR__ . '/app/Views',
    cachePath: __DIR__ . '/storage/cache',
);

// In a route handler
$html = $engine->render('home', ['name' => 'Alice', 'isAdmin' => true, 'items' => ['A', 'B']]);
return Response::html($html);

// Or use ViewResponse
$view = new ViewResponse($engine);
return $view->make('home', ['name' => 'Alice']);
```

**Template syntax reference:**

| Syntax | Description |
|--------|-------------|
| `{{ $var }}` | Escaped output (htmlspecialchars) |
| `{!! $var !!}` | Raw/unescaped output |
| `@if($cond)...@elseif($cond)...@else...@endif` | Conditionals |
| `@foreach($items as $item)...@endforeach` | Loop |
| `@for($i=0; $i<10; $i++)...@endfor` | For loop |
| `@while($cond)...@endwhile` | While loop |
| `@extends('layout')` | Inherit layout |
| `@section('name')...@endsection` | Define section |
| `@yield('name')` | Output section |
| `@include('partial')` | Include partial (dot notation for paths) |

### HTMX Integration

Build interactive UIs without JavaScript using HTMX:

```php
// Check if request came from HTMX
if (HtmxHelper::isHtmxRequest($request)) {
    return HtmxHelper::partial('<li>New item added</li>');
}

// Trigger client-side events
$response = HtmxHelper::partial('<p>Saved!</p>');
$response = HtmxHelper::trigger($response, 'itemAdded');
$response = HtmxHelper::trigger($response, ['itemAdded', 'refreshList']);

// Navigation
$response = HtmxHelper::redirect('/dashboard');
$response = HtmxHelper::refresh();

// Retarget the swap
$response = HtmxHelper::retarget($response, '#notifications');
$response = HtmxHelper::reswap($response, 'beforeend');
$response = HtmxHelper::pushUrl($response, '/items/42');
```

### JSON API Mode

For React/Vue/Angular/Svelte apps, use the JSON API — no special integration needed:

```php
$router->group('/api/v1', function (Router $r) {
    $r->get('/posts', function (Request $request) {
        return Response::json(['posts' => $posts]);
    });
});
```

Configure CORS for your SPA origin and consume the API from any frontend framework.

---

## AI

### Gateway Setup

The AI Gateway provides a unified interface across OpenAI, Anthropic (Claude), and Grok:

```php
$gateway = new Gateway(defaultProvider: 'openai');

$gateway->register('openai', new OpenAIProvider(
    baseUrl: 'https://api.openai.com/v1',
    apiKey: env('OPENAI_API_KEY'),
    defaultModel: 'gpt-4o',
));

$gateway->register('anthropic', new AnthropicProvider(
    baseUrl: 'https://api.anthropic.com',
    apiKey: env('ANTHROPIC_API_KEY'),
    defaultModel: 'claude-sonnet-4-6',
));

$gateway->register('grok', new GrokProvider(
    baseUrl: 'https://api.x.ai/v1',
    apiKey: env('GROK_API_KEY'),
    defaultModel: 'grok-2',
));
```

### Chat Completions

```php
// One-shot
$result = $gateway->chat([
    ['role' => 'user', 'content' => 'Explain PHP in one sentence.'],
]);
echo $result['content'];
// $result['usage'] = ['prompt_tokens' => ..., 'completion_tokens' => ..., 'total_tokens' => ...]

// Choose provider per request
$result = $gateway->chat($messages, provider: 'anthropic');

// With options
$result = $gateway->chat($messages, options: [
    'temperature' => 0.7,
    'max_tokens'  => 500,
    'model'       => 'gpt-4o-mini',
]);
```

### Conversations (Chat Service)

Manage multi-turn conversations with history tracking:

```php
$chat = new Chat(
    gateway: $gateway,
    systemPrompt: 'You are a helpful coding assistant.',
    provider: 'openai',
    options: ['temperature' => 0.7],
);

$response1 = $chat->send('What is dependency injection?');
echo $response1['content'];

$response2 = $chat->send('Show me an example in PHP.');
echo $response2['content'];
// History is maintained — the AI sees both messages

// Streaming
$chat->stream('Explain closures.', function (string $chunk) {
    echo $chunk;
    flush();
});

// Track token usage across the conversation
$usage = $chat->getTotalUsage();
// ['prompt_tokens' => 450, 'completion_tokens' => 320, 'total_tokens' => 770]

// Inspect conversation history
$messages = $chat->getMessages();

// Manually add context
$chat->addMessage('user', 'Previous context...');
```

### Streaming (SSE)

Stream AI responses to the browser in real time:

```php
$router->post('/api/chat/stream', function (Request $request) use ($gateway) {
    $message = $request->json('message');

    return Response::stream(function () use ($gateway, $message) {
        $gateway->streamChat(
            [['role' => 'user', 'content' => $message]],
            function (string $chunk) {
                echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                flush();
            },
        );
        echo "data: [DONE]\n\n";
    });
});
```

### Embeddings

Generate vector embeddings from text:

```php
$embeddings = new Embeddings($gateway, maxChunkLength: 8000);

// Single text
$vectors = $embeddings->embed('The quick brown fox jumps over the lazy dog.');
// Returns array of float arrays (one per chunk if text is long)

// Batch
$vectors = $embeddings->embedBatch([
    'First document.',
    'Second document.',
    'Third document.',
]);
// Returns one embedding vector per input text

// Long text is automatically chunked at word boundaries
$vectors = $embeddings->embed($veryLongText);
// Returns one vector per chunk

// Use a specific provider
$vectors = $embeddings->embed($text, provider: 'openai', options: ['model' => 'text-embedding-3-large']);
```

### Semantic Search & RAG

Build retrieval-augmented generation (RAG) pipelines:

```php
$search = new SemanticSearch(
    gateway: $gateway,
    vectorStore: $qdrantAdapter,  // or $pgvectorAdapter
    collection: 'documents',
    provider: 'openai',
);

// Index documents
$search->index('doc-1', 'PHP is a server-side scripting language.', ['source' => 'wiki']);
$search->index('doc-2', 'Laravel is a PHP framework.', ['source' => 'docs']);

// Batch index
$search->indexBatch([
    ['id' => 'doc-3', 'text' => 'Composer manages PHP dependencies.', 'payload' => ['source' => 'docs']],
    ['id' => 'doc-4', 'text' => 'PHPUnit is a testing framework.', 'payload' => ['source' => 'docs']],
]);

// Search
$results = $search->search('What framework is used for PHP?', limit: 5);
// [['id' => 'doc-2', 'score' => 0.92, 'payload' => ['source' => 'docs', 'text' => '...']], ...]

// RAG: format search results as context for chat
$context = $search->formatRagContext('What frameworks exist?', $results);
$response = $gateway->chat([
    ['role' => 'system', 'content' => "Answer using this context:\n$context"],
    ['role' => 'user', 'content' => 'What frameworks exist for PHP?'],
]);
```

### Vector Stores

Two backends are supported. Both implement `VectorStoreInterface`:

**Qdrant** (recommended for production):

```php
$qdrant = new QdrantAdapter(
    host: 'http://localhost',
    port: 6333,
    apiKey: null, // optional
);

$qdrant->createCollection('documents', dimensions: 1536, distance: 'cosine');
$qdrant->upsert('documents', [
    ['id' => 'doc-1', 'vector' => [0.1, 0.2, ...], 'payload' => ['text' => '...']],
]);
$results = $qdrant->search('documents', $queryVector, limit: 10);
$qdrant->delete('documents', ['doc-1']);
```

**pgvector** (for teams already on PostgreSQL):

```php
$pgvector = new PgvectorAdapter($pdoAdapter);

$pgvector->createCollection('documents', dimensions: 1536, distance: 'cosine');
// Same interface as Qdrant: upsert(), search(), delete()
```

Distance metrics: `cosine`, `euclidean`, `dot`.

### AI Middleware

Add AI-powered processing to your request pipeline:

**Content Moderation** — Block harmful content before it reaches your controllers:

```php
$moderation = new ContentModeration(
    gateway: $gateway,
    fields: ['message', 'content', 'text'],  // JSON fields to check
    provider: 'openai',
);
$app->addMiddleware($moderation);
// Returns 422 if content is classified as unsafe
```

**Intent Classification** — Automatically classify user intent:

```php
$intent = new IntentClassifier(
    gateway: $gateway,
    categories: ['question', 'command', 'feedback', 'complaint', 'other'],
);
$app->addMiddleware($intent);

// In your controller
$router->post('/chat', function (Request $request) {
    $intent = $request->getAttribute('intent');  // e.g., "question"
    // Route to different handlers based on intent
});
```

**Auto-Summarizer** — Summarize long inputs:

```php
$summarizer = new AutoSummarizer(
    gateway: $gateway,
    threshold: 500,  // Only summarize if text exceeds 500 chars
);
$app->addMiddleware($summarizer);

// In your controller
$router->post('/submit', function (Request $request) {
    $summary = $request->getAttribute('summary');
    // Short inputs: summary equals the original text (no API call)
    // Long inputs: AI-generated summary
});
```

### Prompt Management

Manage versioned prompt templates for A/B testing and iteration:

**Template files** (`app/Prompts/greeting.v1.prompt`):

```
Hello {{name}}, welcome to {{app}}! How can I help you today?
```

**Template Engine:**

```php
$engine = new TemplateEngine();

// Render inline template
$prompt = $engine->render(
    'Summarize this {{type}} in {{language}}: {{content}}',
    ['type' => 'article', 'language' => 'English', 'content' => $text],
);

// Render from file
$prompt = $engine->renderFile('app/Prompts/greeting.v1.prompt', [
    'name' => 'Alice',
    'app'  => 'Smallwork',
]);
// Throws RuntimeException if any {{placeholder}} is left unsubstituted
```

**Version Manager:**

```php
$versions = new VersionManager('app/Prompts');

// Discover available versions
$available = $versions->versions('greeting');  // [1, 2, 3]

// Get latest version content
$template = $versions->latest('greeting');

// Get specific version
$template = $versions->version('greeting', 2);

// Combine with template engine
$prompt = $engine->render($versions->latest('greeting'), ['name' => 'Bob', 'app' => 'MyApp']);
```

---

## CLI

The `smallwork` executable provides command-line tools:

```bash
# List all commands
php smallwork list

# Start development server
php smallwork serve
php smallwork serve --host=0.0.0.0 --port=3000

# Run database migrations
php smallwork migrate
php smallwork migrate --rollback

# Generate boilerplate
php smallwork make:controller User     # -> app/Controllers/UserController.php
php smallwork make:model Post          # -> app/Models/Post.php
php smallwork make:migration create_posts  # -> database/migrations/2026_02_27_120000_create_posts.php
```

### Custom Commands

Create commands by extending `Command`:

```php
<?php
namespace App\Console;

use Smallwork\Console\Command;

class SeedCommand extends Command
{
    public function getName(): string { return 'db:seed'; }
    public function getDescription(): string { return 'Seed the database'; }

    public function execute(array $args): int
    {
        // Seeding logic...
        echo "Database seeded.\n";
        return 0; // exit code
    }
}
```

Register in the CLI entry point:

```php
$cli->register('db:seed', new SeedCommand());
```

---

## Enterprise Features

### Logging

PSR-3-compatible JSON logger:

```php
$logger = new Logger(
    logDir: 'storage/logs',
    minLevel: 'info',  // Ignores debug messages
);

$logger->info('User logged in', ['user_id' => 42, 'ip' => '1.2.3.4']);
$logger->warning('Rate limit approaching', ['remaining' => 5]);
$logger->error('Payment failed', ['order_id' => 99, 'reason' => 'declined']);

// Message interpolation
$logger->info('User {user_id} performed {action}', ['user_id' => 42, 'action' => 'login']);
// Logs: "User 42 performed login"
```

Each log entry is a single JSON line in `storage/logs/app.log`:

```json
{"timestamp":"2026-02-27T14:30:00+00:00","level":"info","message":"User 42 performed login","context":{"user_id":42,"action":"login"}}
```

**Log levels** (in order of severity): `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`. Setting `minLevel` filters out lower-severity messages.

### Health Checks

Monitor your application's dependencies:

```php
$health = new HealthCheck();

$health->addCheck('database', function () use ($db) {
    try {
        $db->fetchOne('SELECT 1');
        return ['status' => 'ok', 'message' => 'Connected'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
});

$health->addCheck('redis', function () use ($redis) {
    try {
        $redis->set('health', '1', ttl: 5);
        return ['status' => 'ok', 'message' => 'Connected'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
});

// Register as route
$router->get('/health', function () use ($health) {
    return $health->toResponse();
    // Returns 200 if all checks pass, 503 if any fail
});
```

Response format:

```json
{
  "status": "healthy",
  "checks": {
    "database": { "status": "ok", "message": "Connected", "latency_ms": 1.23 },
    "redis": { "status": "ok", "message": "Connected", "latency_ms": 0.45 }
  },
  "timestamp": "2026-02-27T14:30:00+00:00"
}
```

### OpenAPI Spec Generation

Auto-generate OpenAPI 3.0 documentation from your routes:

```php
$generator = new OpenApiGenerator(
    router: $app->router(),
    title: 'My API',
    version: '1.0.0',
    description: 'API documentation',
);

$router->get('/api/docs', function () use ($generator) {
    return Response::json($generator->generate());
});

// Or export as JSON string
file_put_contents('openapi.json', $generator->toJson());
```

Route parameters like `{id}` are automatically extracted as OpenAPI path parameters.

---

## Testing

### Test Helpers

Smallwork provides a base test class and AI mocking utilities:

```php
<?php
namespace Tests\Feature;

use Smallwork\Testing\TestCase;
use Smallwork\Testing\AIMock;
use Smallwork\Core\Response;

class ChatControllerTest extends TestCase
{
    public function test_homepage_returns_200(): void
    {
        $app = $this->createApp();
        $app->router()->get('/', fn() => Response::json(['ok' => true]));

        $response = $this->get('/');
        $this->assertEquals(200, $response->status());
    }

    public function test_create_post(): void
    {
        $app = $this->createApp();
        $app->router()->post('/posts', function ($request) {
            return Response::json($request->json(), 201);
        });

        $response = $this->json('POST', '/posts', ['title' => 'Hello']);
        $this->assertEquals(201, $response->status());
    }

    public function test_chat_endpoint_with_mocked_ai(): void
    {
        $gateway = AIMock::chat('Mocked AI response', [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ]);

        $result = $gateway->chat([['role' => 'user', 'content' => 'Hello']]);
        $this->assertEquals('Mocked AI response', $result['content']);
    }

    public function test_embeddings_with_mocked_ai(): void
    {
        $gateway = AIMock::embed([[0.1, 0.2, 0.3]]);
        $vectors = $gateway->embed('test text');
        $this->assertEquals([0.1, 0.2, 0.3], $vectors[0]);
    }
}
```

**TestCase methods:**

| Method | Description |
|--------|-------------|
| `createApp()` | Creates a fresh App instance with test fixtures |
| `get(string $path)` | Sends a GET request, returns Response |
| `post(string $path, array $data)` | Sends a POST request with form data |
| `json(string $method, string $path, array $data)` | Sends a JSON request |

**AIMock methods:**

| Method | Description |
|--------|-------------|
| `AIMock::chat(string $content, array $usage)` | Returns Gateway that always responds with given content |
| `AIMock::embed(array $vectors)` | Returns Gateway that returns given embedding vectors |

---

## Development

### Running Tests

```bash
# Full test suite
vendor/bin/phpunit

# Specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration

# Specific test file
vendor/bin/phpunit tests/Unit/Core/RouterTest.php

# With error display
vendor/bin/phpunit --display-errors
```

### Adding New Components

1. Create source file in the appropriate `src/` subdirectory
2. Write tests in the matching `tests/Unit/` subdirectory
3. Follow PSR-4 autoloading (`Smallwork\` maps to `src/`)
4. Use `declare(strict_types=1)` in all PHP files

### Code Conventions

- PHP 8.2+ features: enums, readonly properties, named arguments, match expressions
- Constructor property promotion where appropriate
- Immutable-style objects (Request, Response) with `with*` methods returning clones
- Static factory methods (`::create()`, `::json()`, `::make()`) for expressive construction
- Mock HTTP clients via callable injection for testing external API integrations
- In-memory adapters (`RedisAdapter::createInMemory()`, SQLite `:memory:`) for fast tests

## License

MIT
