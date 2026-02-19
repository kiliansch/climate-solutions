# GitHub Copilot Instructions

## Project overview

This is a **Symfony** web application for climate solutions, running in Docker with PHP 8.3, Nginx (HTTPS), and PostgreSQL 16. Use Symfony conventions and Doctrine ORM for all backend code.

---

## General rules

- Write clean, readable PHP 8.3+ code using strict types (`declare(strict_types=1)`).
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style.
- Prefer constructor injection for all dependencies (no service locator or `$container->get()`).
- Never put business logic in controllers — keep controllers thin.
- Use `readonly` properties where possible (PHP 8.1+).
- Prefer named arguments for clarity when calling functions with multiple parameters.
- Always add return types and parameter types to every method and function.

---

## Symfony conventions

### Controllers

- Extend `AbstractController` only when you need its helper methods (`render`, `redirectToRoute`, `json`, etc.).
- Use `#[Route]` attributes directly on controller methods, not in `config/routes/`.
- Return `Response` objects explicitly; use `JsonResponse` for API endpoints.
- Validate user input with Symfony Validator constraints — never manually validate in controllers.
- Use `#[MapRequestPayload]` or `#[MapQueryString]` (Symfony 6.3+) to deserialize and validate request data automatically.

```php
#[Route('/api/projects', name: 'api_project_list', methods: ['GET'])]
public function list(ProjectRepository $repository): JsonResponse
{
    return $this->json($repository->findAllActive());
}
```

### Services

- Register services using autowiring and autoconfiguration (Symfony defaults in `config/services.yaml`).
- Tag services explicitly only when autoconfiguration is insufficient.
- Use `#[AsTaggedItem]`, `#[Autoconfigure]`, or `#[AsEventListener]` attributes instead of YAML where possible.
- Define interfaces for services that may have multiple implementations.

### Forms

- Create dedicated `FormType` classes under `src/Form/`.
- Use DTOs (Data Transfer Objects) as the form data model — do not bind forms directly to entities.

### Events & Messaging

- Use Symfony Messenger for async processing; dispatch messages from services, not controllers.
- Use Symfony EventDispatcher for domain events within the request lifecycle.

### Templates (Twig)

- Keep templates in `templates/`, following the `controller/action.html.twig` naming convention.
- No PHP logic in Twig — only presentation logic. Move any conditional data prep into the controller or a Twig extension.
- Use Twig `{% block %}` inheritance from `templates/base.html.twig`.

### Configuration & Environment

- Use `.env` for defaults and `.env.local` for overrides (never commit secrets).
- Access config via injected parameters or typed config classes — avoid `$_ENV` directly in services.

---

## Doctrine ORM

### Entities

- Place entities in `src/Entity/`.
- Use PHP attributes for all mappings (`#[ORM\Entity]`, `#[ORM\Column]`, etc.) — no XML or YAML mapping.
- Always define a `#[ORM\GeneratedValue]` strategy explicitly.
- Use `#[ORM\HasLifecycleCallbacks]` only for lightweight timestamp updates; defer heavy logic to event listeners/subscribers.
- Prefer `private` properties with typed getters/setters; avoid `public` entity properties.

```php
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    // getters & setters ...
}
```

### Repositories

- Place repositories in `src/Repository/`; extend `ServiceEntityRepository`.
- Write named query methods (`findAllActive()`, `findByCategory(string $category)`) — avoid using `findBy` patterns in controllers.
- Use QueryBuilder for dynamic queries; use DQL for complex static queries.
- Never fetch more data than needed — add `select()` projections and `setMaxResults()` where appropriate.
- Use `toIterable()` for batch processing large result sets to avoid memory exhaustion.

```php
public function findAllActive(): array
{
    return $this->createQueryBuilder('p')
        ->andWhere('p.active = :active')
        ->setParameter('active', true)
        ->orderBy('p.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

### Migrations

- Always generate migrations with `php bin/console doctrine:migrations:diff`.
- Never edit an already-executed migration; create a new one instead.
- Migration files live in `migrations/` and are committed to version control.
- Do not use `doctrine:schema:update --force` outside of local development / testing.

### Relationships

- Define the owning side and inverse side explicitly.
- Use `#[ORM\JoinColumn(nullable: false)]` when a relation is required.
- Prefer `fetch: 'EXTRA_LAZY'` on collections that may be large.
- Avoid bidirectional relationships unless the inverse side is actually needed.

---

## Database (PostgreSQL 16)

- Use PostgreSQL-specific features via Doctrine's platform abstractions where possible.
- Use `uuid` or `bigint` for primary keys depending on the aggregate's scale.
- Index columns used in `WHERE`, `ORDER BY`, and `JOIN` clauses with `#[ORM\Index]`.
- Use database-level constraints (`NOT NULL`, `UNIQUE`, foreign keys) in addition to application-level validation.

---

## Testing

- Unit test services and domain logic with **PHPUnit**; mock only direct dependencies.
- Use Symfony's `KernelTestCase` for integration tests that need the container.
- Use `WebTestCase` for controller/HTTP tests; do not spin up a real HTTP server.
- Use separate `.env.test` or `.env.test.local` for test database config.
- Reset database state between tests using `doctrine/doctrine-fixtures-bundle` and transactions.

---

## Docker / development environment

- The application runs at **https://localhost** (self-signed cert).
- Database connection string: `postgresql://app:app@database:5432/app`.
- Run Symfony and Composer commands inside the `php` container:
  ```bash
  docker compose exec php php bin/console <command>
  docker compose exec php composer <command>
  ```
  Or use the `make` shortcuts (`make console CMD="..."`, `make migrate`, etc.).
- Never install PHP packages on the host — always use the container.

---

## What to avoid

- Do not use annotations (the deprecated `@` doc-block style) — use PHP 8 attributes only.
- Do not call `$entityManager->flush()` inside a loop.
- Do not use `SELECT *` queries or load entire collections when only IDs are needed.
- Do not put environment-specific configuration in committed files.
- Do not use static methods or global state in services.
- Do not catch and silently swallow exceptions — log them or re-throw.
