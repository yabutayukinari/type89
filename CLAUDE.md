# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 13 project with PHP 8.4+ that demonstrates Laravel best practices. The project includes a user management system with admin controllers (including a Filament admin panel) and comprehensive static analysis tools.

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

### Development
- `./vendor/bin/sail npm install` - Install JavaScript dependencies
- `./vendor/bin/sail npm run dev` - Start Vite dev server (with HMR)
- `./vendor/bin/sail npm run build` - Production build of assets

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
- `resources/` - Blade views and frontend assets

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
- SQLite in-memory database for test isolation
- Base test case in `tests/TestCase.php` with Laravel testing utilities
- Laravel test assertions included via `jasonmccreary/laravel-test-assertions`

### Key Technology Stack
- **Framework:** Laravel 13
- **Language:** PHP 8.4+
- **Admin Panel:** Filament 5
- **Authentication:** Laravel Sanctum 4 for API token authentication
- **Frontend Build:** Vite 6 (with `laravel-vite-plugin` and Tailwind CSS 4)
- **Database:** MySQL (configured) with SQLite for testing
- **Dependencies:** Guzzle HTTP client, Laravel UI for scaffolding

### Admin and User Management
The application includes an admin user management system:
- `app/Http/Controllers/Admin/UserController.php` - Admin user CRUD operations
- `app/Http/Requests/UserUpdateRequest.php` - User update validation
- `app/Models/User.php`, `app/Models/Admin.php` - User models with authentication
- Role-based access control configured in middleware and route middleware
- Filament panel for admin operations alongside the Blade-based admin views

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

3. **Frontend asset changes:**
   - Edit assets in `resources/`
   - Run `./vendor/bin/sail npm run dev` during development
   - Run `./vendor/bin/sail npm run build` before deployment

4. **Testing approach:**
   - Feature tests for HTTP endpoints and workflows
   - Unit tests for isolated business logic
   - Use `tests/TestCase.php` base class for common test setup

## Important Notes

- The project enforces strict coding standards. All code must pass static analysis before being merged.
- Tests are configured to run against an in-memory SQLite database for speed and isolation.
- PHP 8.4+ type declarations are expected throughout the codebase.
- The application uses Laravel Sanctum for API authentication; refer to `laravel/sanctum` documentation for API token management.
