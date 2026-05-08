# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 13 project with PHP 8.4+ that demonstrates Laravel best practices. The project includes a user management system with admin controllers (including a Filament admin panel) and comprehensive static analysis tools.

## Common Commands

### Development
- `npm install` - Install JavaScript dependencies
- `npm run dev` - Start Vite dev server (with HMR)
- `npm run build` - Production build of assets

### Testing and Quality Assurance
- `composer test` - Run all tests
- `php vendor/bin/phpunit --filter=TestClassName` - Run specific test class
- `php vendor/bin/phpunit tests/Feature/...` - Run specific test file

### Code Analysis
- `composer csf` - Check PHP coding style (PHP CS Fixer, dry-run mode)
- `composer csf-fix` - Fix PHP coding style issues (PHP CS Fixer)
- `composer cs` - Check code style with PHP CodeSniffer
- `composer cs-fix` - Fix code style issues with PHP CodeSniffer
- `composer sa` - Run static analysis (Larastan/PHPStan)
- `composer md` - Run mess detection (PHPMD)
- `composer build` - Run all analysis tools without tests (csf, cs, sa, md)
- `composer tests` - Run all checks including tests (csf, cs, sa, md, test)

### Database Setup for Tests
PHPUnit is configured in `phpunit.xml` to use a SQLite in-memory database by default, so no setup is required for the standard test run.

If you need to run integration tests against MySQL, align the database with `.env.testing` (default: database `testing`, user `sail`):

```sql
CREATE SCHEMA testing;
CREATE USER 'sail'@'%' IDENTIFIED BY 'password';
GRANT ALL ON testing.* TO 'sail'@'%';
```

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
   - Run `composer build` to check code quality
   - Run `composer test` to verify tests pass
   - Address any issues reported by static analysis tools

2. **Working with database:**
   - Migrations in `database/migrations/`
   - Use model factories for seeding test data (in `database/factories/`)
   - Configure `.env` file for local development

3. **Frontend asset changes:**
   - Edit assets in `resources/`
   - Run `npm run dev` during development
   - Run `npm run build` before deployment

4. **Testing approach:**
   - Feature tests for HTTP endpoints and workflows
   - Unit tests for isolated business logic
   - Use `tests/TestCase.php` base class for common test setup

## Important Notes

- The project enforces strict coding standards. All code must pass static analysis before being merged.
- Tests are configured to run against an in-memory SQLite database for speed and isolation.
- PHP 8.4+ type declarations are expected throughout the codebase.
- The application uses Laravel Sanctum for API authentication; refer to `laravel/sanctum` documentation for API token management.
