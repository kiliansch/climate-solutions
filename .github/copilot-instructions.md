# GitHub Copilot Instructions

## Project Overview

Symfony 7 web app for climate solutions — PHP 8.3, Docker, Nginx (HTTPS), PostgreSQL 16.
**Before starting any task:** read `docs/implementation-status.md`. Do not recreate anything already listed as completed.

***

## Engineering Standards

- Be elegant. Clean, simple, readable code is not optional.
- Make every change as small as possible — minimal blast radius, zero side effects.
- Find root causes. No temporary fixes.
- Only touch what's necessary.

***

## Context & Planning

- Read `docs/implementation-status.md` and `copilot-instructions.md` before every task.
- When gathering context or planning: be precise and concise — capture all key facts, no verbosity.
- State your plan in bullet points before writing code.

***

## Mandatory Task Completion Checklist

Every task ends with these two steps, in order. Do not mark a task complete until both are done.

1. **Verify** — Prove the implementation works. Run the relevant command (tests, console, HTTP request, linter) and show the output.
2. **Code Review** — Review every line you wrote. Check for: correctness, edge cases, unintended side effects, violations of any rule in these instructions, and elegance. Fix anything that doesn't meet the standard.

***

## Code Style

- `declare(strict_types=1)` on every file.
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/).
- PHP 8 attributes only — never `@`-style annotations.
- Always declare return types and parameter types.
- Use `readonly` properties where applicable (PHP 8.1+).
- Prefer named arguments for multi-parameter calls.

***

## Architecture Rules

- **Thin controllers** — no business logic; constructor injection only.
- Extend `AbstractController` only when its helpers (`render`, `redirectToRoute`, `json`) are needed.
- Use `#[Route]` attributes on methods, not `config/routes/`.
- Use `#[MapRequestPayload]` / `#[MapQueryString]` for request deserialization.
- Validate with Symfony Validator constraints — never manually in controllers.
- DTOs in `src/Dto/` as form/request data models — never bind forms directly to entities.
- No `JsonResponse` on non-API routes — all page routes return `render()` or `redirectToRoute()`.
- POST → redirect on success (PRG pattern); re-render on validation error.
- PATCH / DELETE routes must `redirectToRoute()` with a flash message.
- Dispatch async work via Symfony Messenger from services, not controllers.

***

## Services

- Autowire everything; explicit tags only when autoconfiguration is insufficient.
- Use `#[AsTaggedItem]`, `#[Autoconfigure]`, `#[AsEventListener]`, `#[AsMessageHandler]` attributes.
- Define interfaces for services with multiple implementations.
- Never use static methods or global state.
- Never catch and silently swallow exceptions — log or re-throw.

***

## Doctrine ORM

- Entities in `src/Entity/` (or bundle namespace — see implementation-status.md).
- PHP attributes for all mappings — no XML/YAML.
- Explicit `#[ORM\GeneratedValue]` strategy on every entity.
- Private properties with typed getters/setters; no public entity properties.
- Repositories extend `ServiceEntityRepository`; named query methods only — no raw `findBy` in controllers.
- Use QueryBuilder for dynamic queries; DQL for complex static ones.
- Add `select()` projections and `setMaxResults()` — never over-fetch.
- `toIterable()` for large batch result sets.
- **Never** call `$entityManager->flush()` inside a loop — single flush after all mutations.
- All datetime fields use `datetimetz_immutable` column type (TIMESTAMP WITH TIME ZONE in PostgreSQL).
- All `DateTimeImmutable` values created with `new \DateTimeImmutable('...', new \DateTimeZone('UTC'))`.

***

## Migrations

- Generate with `php bin/console doctrine:migrations:diff`.
- Never edit an already-executed migration — create a new one.
- Never use `doctrine:schema:update --force` outside local dev/test.
- Commit all migrations.

***

## Twig Templates

- `templates/` directory, `controller/action.html.twig` convention.
- No PHP logic in Twig — presentation only.
- Inherit from `templates/base.html.twig` via `{% block %}`.
- Public-facing pages that require no auth use a standalone layout (no base extension).

***

## Testing

- PHPUnit for unit tests (services, domain logic) — mock direct dependencies only.
- `KernelTestCase` for integration tests needing the container.
- `WebTestCase` for HTTP/controller tests — no real HTTP server.
- Separate `.env.test` / `.env.test.local` for test database config.
- Reset state between tests via fixtures and transactions.

***

## Docker / Environment

- App: **https://localhost** (self-signed cert).
- DB: `postgresql://app:app@database:5432/app`.
- All Symfony / Composer commands run **inside** the `php` container:
  ```bash
  docker compose exec php php bin/console <command>
  docker compose exec php composer <command>
  ```
  Or use `make` shortcuts: `make console CMD="..."`, `make migrate`.
- Never install PHP packages on the host.

***

## Updating Docs

After completing any task, append to `docs/implementation-status.md`:
- New entities under `## Entities` (key fields + relationships).
- New/changed services under `## Services` (public method signatures).
- New routes under `## Controllers & Routes`.
- New Messenger messages/handlers under `## Messages`.
- New templates under `## Templates`.
- Do not remove existing entries — only append or update.