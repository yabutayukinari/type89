# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 13 project with PHP 8.4+ that demonstrates Laravel best practices. The project serves as an API backend (Sanctum SPA auth + Reverb broadcasting) for a Next.js frontend located in `frontend/`. It includes a user management API with admin controllers and comprehensive static analysis tools.

## Common Commands

The project runs entirely inside Laravel Sail containers — local PHP / Composer / Node are not required. Most commands below execute inside the `laravel.test` container, prefixed with `./vendor/bin/sail`. The repository's `Makefile` wraps the most common ones.

### Setup
- `make setup` - Idempotent bootstrap: installs Composer deps via a one-shot Docker container, copies `.env`, starts Sail, waits for MySQL, runs `key:generate` + `migrate`, installs npm packages. Safe to re-run.
- `make help` - List all available Make targets.

### Make Targets (wrappers around Sail)
- `make up` / `make down` / `make restart` - Sail container lifecycle
- `make shell` - Shell into the app container
- `make ps` / `make logs` - Container status / tail logs
- `make test` - Run the test suite (`sail composer test`)
- `make build` - All static analysis (csf + cs + sa + md)
- `make fix` - Auto-fix code style (PHP CS Fixer + PHP CodeSniffer)
- `make migrate` - Run pending migrations

### Frontend
The Next.js frontend lives in a separate directory (`frontend/`) and is run independently from Sail. See `frontend/CLAUDE.md` for its commands.

### Testing and Quality Assurance
- `./vendor/bin/sail composer test` - Run all tests (or `make test`)
- `./vendor/bin/sail bin phpunit --filter=TestClassName` - Run specific test class
- `./vendor/bin/sail bin phpunit tests/Feature/...` - Run specific test file

### Code Analysis
- `./vendor/bin/sail composer csf` - Check PHP coding style (PHP CS Fixer, dry-run mode)
- `./vendor/bin/sail composer csf-fix` - Fix PHP coding style issues (PHP CS Fixer)
- `./vendor/bin/sail composer cs` - Check code style with PHP CodeSniffer
- `./vendor/bin/sail composer cs-fix` - Fix code style issues with PHP CodeSniffer
- `./vendor/bin/sail composer sa` - Run static analysis (Larastan/PHPStan)
- `./vendor/bin/sail composer md` - Run mess detection (PHPMD)
- `./vendor/bin/sail composer build` - Run all analysis tools without tests (csf, cs, sa, md) — also `make build`
- `./vendor/bin/sail composer tests` - Run all checks including tests (csf, cs, sa, md, test)

### Database Setup for Tests
Tests run against the dedicated `mysql.test` Sail container (MySQL 8 on tmpfs for speed). `make setup` generates `.env.testing` from `.env.example` automatically — no manual database setup is required.

`.env` and `.env.testing` are git-ignored because they contain freshly-generated `APP_KEY` values. The CI workflow regenerates them from `.env.example` on each run.

## Architecture and Structure

### Directory Layout
- `app/` - Application source code
  - `Http/Controllers/` - Request handlers (including Admin controllers for user management)
  - `Http/Requests/` - Form request validation classes
  - `Http/Middleware/` - HTTP middleware
  - `Models/` - Eloquent models (User, Admin, PasswordReset)
  - `Providers/` - Service providers for bootstrapping
  - `Console/` - Artisan commands
  - `Exceptions/` - Exception handlers
- `tests/` - Test suite
  - `Feature/` - Feature/integration tests
  - `Unit/` - Unit tests
- `database/` - Database migrations, seeders, and factories
- `routes/` - Route definitions
- `resources/` - Localization files (`lang/`); the frontend lives in `frontend/`

### Code Quality Standards

**PHP Coding Standards:**
- PSR-2 with custom extensions via PHP-CS-Fixer configuration
- Strict types enabled (`declare(strict_types=1)`)
- Global namespace imports enabled (classes, constants, functions)

**Static Analysis:**
- Larastan (PHPStan) for strict type checking on Laravel code
- PHPMD for complexity and code smell detection with custom rules
- PHP CodeSniffer for PSR-2 compliance

**Testing:**
- PHPUnit 12 for unit and feature tests
- Dedicated `mysql.test` Sail container (MySQL 8 on tmpfs) for test isolation
- Base test case in `tests/TestCase.php` with Laravel testing utilities
- Laravel test assertions included via `jasonmccreary/laravel-test-assertions`

### Key Technology Stack
- **Framework:** Laravel 13
- **Language:** PHP 8.4+
- **Authentication:** Laravel Sanctum 4 (SPA / API token authentication)
- **Realtime:** Laravel Reverb 1 (WebSocket broadcasting)
- **Database:** MySQL (dev) / dedicated MySQL container (test)
- **Dependencies:** Guzzle HTTP client
- **Frontend:** Next.js (separate `frontend/` directory)

### Admin and User Management
The application exposes an admin user management API:
- `app/Http/Controllers/Api/Admin/` - Admin user CRUD operations (API)
- `app/Http/Requests/Api/` - API form request validation
- `app/Models/User.php`, `app/Models/Admin.php` - User models with multi-guard authentication (`web` / `admin`)
- Role-based access control configured in middleware and route middleware

### Development Workflow

1. **Before committing:**
   - Run `make build` (or `./vendor/bin/sail composer build`) to check code quality
   - Run `make test` (or `./vendor/bin/sail composer test`) to verify tests pass
   - Address any issues reported by static analysis tools
   - Lefthook hooks (pre-commit / pre-push) also run these via Sail automatically

2. **Working with database:**
   - Migrations in `database/migrations/`
   - Use model factories for seeding test data (in `database/factories/`)
   - Configure `.env` file for local development
   - Apply migrations with `make migrate`

3. **Frontend changes:**
   - Edit the Next.js app under `frontend/`
   - See `frontend/CLAUDE.md` for dev / build commands

4. **Testing approach:**
   - Feature tests for HTTP endpoints and workflows
   - Unit tests for isolated business logic
   - Use `tests/TestCase.php` base class for common test setup

## Important Notes

- The project enforces strict coding standards. All code must pass static analysis before being merged.
- Tests run against the dedicated `mysql.test` Sail container (MySQL 8 on tmpfs for speed and isolation).
- PHP 8.4+ type declarations are expected throughout the codebase.
- The application uses Laravel Sanctum for API authentication; refer to `laravel/sanctum` documentation for API token management.
