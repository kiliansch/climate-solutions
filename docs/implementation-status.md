# Calendar Booking System — Implementation Status

## Stack
- Symfony 7, PHP 8.3, PostgreSQL 16, Docker
- Doctrine ORM, Symfony Messenger, Twig
- PSR-12, strict_types, constructor injection, PHP 8 attributes only

## Roles
- ROLE_ADMIN, ROLE_AGENT, ROLE_CLIENT
- Customers are unauthenticated (public token access)

## Business Rules
- All slots are closed by default
- Client unavailability takes precedence over agent-opened slots → set slot status to 'overridden' (do NOT delete)
- Slot stays open (pending) until agent accepts one request → then slot = 'booked', all other pending requests = 'declined'
- Booking requests are always possible on open slots until one is accepted
- Admin can manage everything (including inviting clients directly)
- Notifications (email + in-app) are user-configurable per agent

## Entities
<!-- Updated by each prompt -->

### Phase 1 / Prompt 1.1 ✅
- **User**: id, email, password, roles (JSON), status, name, createdAt, invitedBy (self ManyToOne)

## Services
<!-- Updated by each prompt -->

## Controllers & Routes
<!-- Updated by each prompt -->

## Messages (Messenger)
<!-- Updated by each prompt -->

## Pending / Open Questions
- Calendar concept (person vs. event vs. location) — TBD
- Multi-calendar per client — TBD
