# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum` and
`opcache` → `OPCache` are existing exceptions the guess gets wrong).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks all ~40
  `CLAUDE.md` copies as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| **next free** | **3311** | **6383** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project is the one exception, since it has no host/container split and uses `REDIS_PORT` for both.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/testing-application

Framework-coupled PHPUnit base classes for ez-php applications — `ApplicationTestCase`, `DatabaseTestCase`, `HttpTestCase`.

This module is the framework-aware complement to `ez-php/testing`. It was split out to allow `ez-php/testing` (which contains `TestResponse` and `ModelFactory`) to exist without any dependency on `ez-php/framework`. Only applications that actually boot the full framework stack need this package.

This module is a **dev-time dependency**. Users add it to `require-dev` in their application or module.

---

## Source Structure

```
src/
├── ApplicationTestCase.php   — Abstract PHPUnit base; bootstraps a fresh Application per test
├── DatabaseTestCase.php      — Extends ApplicationTestCase; wraps each test in a DB transaction, rolls back on teardown
├── HttpTestCase.php          — Extends ApplicationTestCase; get/post/put/delete helpers that dispatch through the full stack
└── MigrationBootstrap.php    — Runs migration files up/down against a PDO connection; for suite-level schema setup/teardown

tests/
├── TestCase.php                    — Minimal PHPUnit base
├── ApplicationTestCaseTest.php     — Tests bootstrap, app() accessor, configureApplication() hook
├── DatabaseTestCaseTest.php        — Tests transaction start and PDO accessibility (SQLite :memory:)
└── HttpTestCaseTest.php            — Tests request helpers and 404 dispatch; uses an inline HttpTestRouteProvider
```

---

## Key Classes and Responsibilities

### ApplicationTestCase (`src/ApplicationTestCase.php`)

Abstract PHPUnit base class. Creates a new `Application` per test, calls `configureApplication()`, then calls `bootstrap()`.

| Method | Behaviour |
|---|---|
| `setUp()` | Creates Application, calls configureApplication(), calls bootstrap() |
| `getBasePath(): string` | Override to return your app root; default creates a temp dir with empty `config/` |
| `configureApplication(Application)` | Hook: override to register providers, middleware, or routes before bootstrap |
| `app(): Application` | Returns the bootstrapped Application instance |

**Default basePath** creates a temporary directory with an empty `config/` subdirectory. This satisfies `ConfigLoader` (which throws if the directory is missing) while keeping bindings lazy.

---

### DatabaseTestCase (`src/DatabaseTestCase.php`)

Extends `ApplicationTestCase`. Resolves `DatabaseInterface` from the container after bootstrap, begins a PDO transaction, and rolls it back unconditionally in `tearDown()`.

| Method | Behaviour |
|---|---|
| `setUp()` | parent::setUp() + resolves DatabaseInterface + beginTransaction() |
| `tearDown()` | rollBack() if inTransaction() + parent::tearDown() |
| `pdo(): PDO` | Returns the raw PDO connection for direct use in tests |

**Requires a configured database.** Override `getBasePath()` to point to an application with a working `config/db.php`. For in-process tests without MySQL, configure `driver=sqlite, database=:memory:`.

---

### HttpTestCase (`src/HttpTestCase.php`)

Extends `ApplicationTestCase`. All HTTP helpers construct a `Request` value object and call `$app->handle()` — no HTTP server is involved. The full middleware pipeline, router, and exception handler all run.

| Method | Behaviour |
|---|---|
| `get(uri, headers)` | GET request; returns TestResponse |
| `post(uri, body, headers)` | POST request with body array |
| `put(uri, body, headers)` | PUT request with body array |
| `delete(uri, headers)` | DELETE request |
| `request(method, uri, body, headers)` | Generic method; normalises header names to lowercase |

---

## Design Decisions and Constraints

- **Namespace stays `EzPhp\Testing\`** — The three classes keep their original namespace so that downstream modules (framework, auth, orm, …) do not need `use`-statement changes when migrating from `ez-php/testing` to `ez-php/testing-application`.
- **Split from `ez-php/testing`** — `TestResponse` and `ModelFactory` have no framework dependency and remain in `ez-php/testing`. Only the three Application-booting base classes live here, because they require `ez-php/framework`.
- **`getBasePath()` creates a temp dir by default** — Same behaviour as in `ez-php/testing` before the split. The config/ stub satisfies `ConfigLoader` while all service bindings remain lazy.
- **`DatabaseTestCase` uses transaction rollback, not table truncation** — Faster than truncation and avoids needing a separate test database.
- **`HttpTestCase` does not emit HTTP headers** — `ResponseEmitter` is never called. The `Response` is returned directly from `Application::handle()`.
- **No services resolved in `configureApplication()`** — Called before `bootstrap()`. Only `register()` and `middleware()` calls on the Application are safe here.

---

## Testing Approach

- **No external infrastructure required** — All module tests run in-process using SQLite `:memory:`. No MySQL or Redis service needed.
- **`DatabaseTestCaseTest` uses SQLite** — Overrides `getBasePath()` to create a temp dir with `config/db.php` returning `['driver' => 'sqlite', 'database' => ':memory:']`.
- **`HttpTestCaseTest` registers routes via `HttpTestRouteProvider`** — A `ServiceProvider` defined in the test file registers routes in `boot()`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| `TestResponse` assertion helpers | `ez-php/testing` |
| `ModelFactory` | `ez-php/testing` |
| Application lifecycle / bootstrap logic | `ez-php/framework` |
| ORM / Model logic | `ez-php/orm` |
| Fixture data for a specific application | Application's own test directory |
| HTTP fake client (for outgoing HTTP) | `ez-php/http-client` mock transport |
| Request / Response value objects | `ez-php/http` |
