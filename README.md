# Smallwork

A small footprint full-stack AI framework for PHP.

## Requirements

- PHP 8.2+
- Composer

## Quick Start

```bash
composer install
php -S localhost:8000 -t public
```

## Project Structure

- `src/` — Framework source code
- `app/` — Your application code (controllers, models, views)
- `config/` — Configuration and route definitions
- `public/` — Web root (entry point)
- `tests/` — PHPUnit tests
- `storage/` — Logs, cache, database files

## Running Tests

```bash
vendor/bin/phpunit
```

## Configuration

Copy `.env.example` to `.env` and configure your settings.
