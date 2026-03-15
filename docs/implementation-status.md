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

### Phase 1 / Prompt 1.2 ✅
- **Invitation**: id, email, token (UUID, unique), role (ROLE_AGENT|ROLE_CLIENT), invitedBy (ManyToOne → User, not null), expiresAt (DateTimeImmutable), acceptedAt (nullable DateTimeImmutable)

## Services
<!-- Updated by each prompt -->

### Phase 1 / Prompt 1.2 ✅
- **InvitationService::createInvitation(string $email, string $role, User $invitedBy): Invitation** — generates UUID token, sets expiry +7 days, persists, dispatches InvitationCreatedMessage
- **InvitationService::acceptInvitation(string $token, string $plainPassword): User** — validates token not expired/accepted, creates hashed User with correct role, marks invitation accepted

## Controllers & Routes
<!-- Updated by each prompt -->

### Phase 1 / Prompt 1.3 ✅
- **LoginController**: GET+POST `/login` (firewall handles authentication), GET `/logout` (firewall intercepts)
- **InvitationController**: GET+POST `/invite/accept/{token}` → renders password-setup form / calls `InvitationService::acceptInvitation()`, redirects to `/login` on success
- **AcceptInvitationDTO**: `password` field with `NotBlank` + `Length(min:8)` constraints; mapped via `#[MapRequestPayload]`

## Messages (Messenger)
<!-- Updated by each prompt -->

### Phase 1 / Prompt 1.2 ✅
- **InvitationCreatedMessage** { email: string, token: string, role: string }

## Pending / Open Questions
- Calendar concept (person vs. event vs. location) — TBD
- Multi-calendar per client — TBD
