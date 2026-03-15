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

- **User**: id, email, password, roles (JSON), status, name, createdAt, invitedBy (self ManyToOne)
- **Invitation**: id, email, token (UUID, unique), role (ROLE_AGENT|ROLE_CLIENT), invitedBy (ManyToOne → User, not null), expiresAt (DateTimeImmutable), acceptedAt (nullable DateTimeImmutable)
- **Calendar**: id, name, displayMode ENUM('timeslot','dayslot') default 'dayslot', client (ManyToOne → User, not null), agent (ManyToOne → User, not null), publicToken (UUID string, unique, generated on prePersist), slots (OneToMany → Slot, EXTRA_LAZY), createdAt (set on prePersist)
- **Slot**: id, type ENUM('day','time'), startAt (DateTimeImmutable), endAt (DateTimeImmutable), status ENUM('open','closed','booked','overridden') default 'open', location (nullable string), continent (nullable string), calendar (ManyToOne → Calendar, not null), createdAt (set on prePersist); composite index on (calendar_id, start_at, status)
- **Unavailability**: id, startAt (DateTimeImmutable), endAt (DateTimeImmutable), reason (nullable string), calendar (ManyToOne → Calendar, not null), client (ManyToOne → User, not null)

### Phase 1 / Prompt 1.1 ✅
- **User**: id, email, password, roles (JSON), status, name, createdAt, invitedBy (self ManyToOne)

### Phase 1 / Prompt 1.2 ✅
- **Invitation**: id, email, token (UUID, unique), role (ROLE_AGENT|ROLE_CLIENT), invitedBy (ManyToOne → User, not null), expiresAt (DateTimeImmutable), acceptedAt (nullable DateTimeImmutable)

## Services

- **InvitationService**: `createInvitation(string $email, string $role, User $invitedBy): Invitation`, `acceptInvitation(string $token, string $plainPassword): User`
- **UnavailabilityService**: `markUnavailable(Calendar $calendar, User $client, DateTimeImmutable $start, DateTimeImmutable $end): void`

### Phase 1 / Prompt 1.2 ✅
- **InvitationService::createInvitation(string $email, string $role, User $invitedBy): Invitation** — generates UUID token, sets expiry +7 days, persists, dispatches InvitationCreatedMessage
- **InvitationService::acceptInvitation(string $token, string $plainPassword): User** — validates token not expired/accepted, creates hashed User with correct role, marks invitation accepted

### Phase 2 / Prompt 2.1 ✅
- **CalendarRepository::findByAgent(User $agent): Calendar[]** — returns all calendars for a given agent ordered by createdAt DESC
- **CalendarRepository::findByPublicToken(string $token): ?Calendar** — returns calendar matching the public token or null
- **SlotRepository::findOpenByCalendar(Calendar $calendar): Slot[]** — returns all open slots for a calendar ordered by startAt ASC
- **SlotRepository::findByCalendarAndDateRange(Calendar $calendar, DateTimeImmutable $from, DateTimeImmutable $to): Slot[]** — returns slots within date range ordered by startAt ASC

### Phase 2 / Prompt 2.2 ✅
- **UnavailabilityService::markUnavailable(Calendar $calendar, User $client, DateTimeImmutable $start, DateTimeImmutable $end): void** — persists Unavailability, finds all open slots overlapping the date range via SlotRepository::findByCalendarAndDateRange(), sets each open slot status to 'overridden', single flush after all updates

## Controllers & Routes

- **LoginController**: `GET+POST /login`, `GET /logout`
- **InvitationController**: `GET+POST /invite/accept/{token}`

### Phase 1 / Prompt 1.3 ✅
- **LoginController**: GET+POST `/login` (firewall handles authentication), GET `/logout` (firewall intercepts)
- **InvitationController**: GET+POST `/invite/accept/{token}` → renders password-setup form / calls `InvitationService::acceptInvitation()`, redirects to `/login` on success
- **AcceptInvitationDTO**: `password` field with `NotBlank` + `Length(min:8)` constraints; mapped via `#[MapRequestPayload]`

### Phase 2 / Prompt 2.3 ✅
- **Agent\CalendarController** protected by `#[IsGranted('ROLE_AGENT')]`:
  - GET `/agent/calendars` → list agent's calendars via `CalendarRepository::findByAgent()`
  - POST `/agent/calendars` → create calendar using `CalendarDTO` (name, displayMode, clientId)
  - GET `/agent/calendars/{id}` → view calendar + its open slots via `SlotRepository::findOpenByCalendar()`
  - POST `/agent/calendars/{id}/slots` → open slot using `SlotDTO` (type, startAt, endAt, location, continent); constraints: `type` in `['day','time']`, `startAt` before `endAt`
  - GET `/agent/calendars/{id}/share` → returns absolute public URL built from `calendar.publicToken` via `calendar_public_view` route

## Messages (Messenger)

- **InvitationCreatedMessage**: `{ email: string, token: string, role: string }`

### Phase 1 / Prompt 1.2 ✅
- **InvitationCreatedMessage** { email: string, token: string, role: string }

## Pending / Open Questions
- Multi-calendar per client — TBD
