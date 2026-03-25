# Changelog

All notable changes to `ez-php/testing-application` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `ApplicationTestCase` — framework-coupled test base that boots the full `ez-php/framework` application; configures service providers, container bindings, and route loading from the module under test
- `DatabaseTestCase` — extends `ApplicationTestCase`; wraps each test in a database transaction rolled back on teardown; provides `migrate()` and `seed()` helpers against the test database
- `HttpTestCase` — extends `ApplicationTestCase`; dispatches HTTP requests through the complete application middleware and routing stack; returns `TestResponse` for assertion chaining
