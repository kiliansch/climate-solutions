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
- **Slot**: id, type ENUM('day','time'), startAt (DateTimeImmutable), endAt (DateTimeImmutable), status ENUM('open','closed','booked','overridden') default 'open', location (nullable string), continent (nullable string), calendar (ManyToOne → Calendar, not null), createdAt (set on prePersist); composite index on (calendar_id, start_at, status); allowChunkedBooking bool (default false), chunkCooldownMinutes nullable int — valid values 30/60/90/120/150/180 min; cooldown required when chunked booking is on; booking logic not yet wired (storage only).
- **Unavailability**: id, startAt (DateTimeImmutable), endAt (DateTimeImmutable), reason (nullable string), calendar (ManyToOne → Calendar, not null), client (ManyToOne → User, not null)
- **BookingRequest**: id, customerName (string), customerEmail (string), message (nullable string), status ENUM('pending','accepted','declined') default 'pending', slot (ManyToOne → Slot, not null), createdAt (DateTimeImmutable)
- **NotificationSetting**: id, user (OneToOne → User, not null), emailEnabled (bool default true), inAppEnabled (bool default true)
- **Notification**: id, user (ManyToOne → User, not null), message (string), readAt (nullable DateTimeImmutable), createdAt (DateTimeImmutable)

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

### Phase 3 / Prompt 3.1 ✅
- **BookingService::createRequest(Slot $slot, BookingRequestDTO $dto): BookingRequest** — throws `\DomainException` if slot status != 'open', persists BookingRequest with status 'pending', dispatches BookingRequestCreatedMessage
- **BookingService::acceptRequest(BookingRequest $request, User $agent): void** — validates agent owns calendar (throws `AccessDeniedException` if not), sets request status 'accepted', slot status 'booked', all other pending requests for same slot to 'declined', single flush
- **BookingService::declineRequest(BookingRequest $request, User $agent): void** — validates agent ownership, sets status 'declined', flush

### Phase 3 / Prompt 3.2 ✅
- **Public\CalendarController** (no auth required):
  - GET `/c/{token}` (`calendar_public_view`) → `CalendarRepository::findByPublicToken()`, 404 if not found; loads open slots via `SlotRepository::findOpenByCalendar()`; renders `templates/public/calendar/show.html.twig`
  - POST `/c/{token}/book` (`calendar_public_book`) → validates `BookingRequestDTO` via `#[MapRequestPayload]` (NotBlank on customerName+customerEmail, valid Email); reads `slotId` from request body; calls `BookingService::createRequest()`; redirects to `calendar_public_view` with flash success
- **Agent\BookingController** protected by `#[IsGranted('ROLE_AGENT')]`:
  - GET `/agent/bookings` (`agent_booking_list`) → lists all booking requests for agent via `BookingRequestRepository::findByAgent()`
  - PATCH `/agent/bookings/{id}/accept` (`agent_booking_accept`) → `BookingService::acceptRequest()`; redirects with flash success
  - PATCH `/agent/bookings/{id}/decline` (`agent_booking_decline`) → `BookingService::declineRequest()`; redirects with flash success

### Phase 5 / Prompt 5.1 ✅
- **Admin\UserController** protected by `#[IsGranted('ROLE_ADMIN')]`:
  - GET `/admin/agents` (`admin_agent_list`) → lists all ROLE_AGENT users via `UserRepository::findByRole('ROLE_AGENT')`; renders `templates/admin/users/agents.html.twig` with name, email, status, createdAt
  - PATCH `/admin/users/{id}/block` (`admin_user_block`) → sets `user.status = 'blocked'`, flush, flash success, redirect to `admin_agent_list`
  - PATCH `/admin/users/{id}/unblock` (`admin_user_unblock`) → sets `user.status = 'active'`, flush, flash success, redirect to `admin_agent_list`
  - POST `/admin/invite` (`admin_invite`) → validates `InviteUserDTO` (NotBlank + valid Email on `email`) via `#[MapRequestPayload]`; calls `InvitationService::createInvitation()` with `role = 'ROLE_AGENT'`; flash success, redirect to `admin_agent_list`
- **UserRepository::findByRole(string $role): User[]** — queries users whose JSON roles column contains the given role

### Bug Fix / Prompt BF4 ✅ — Invitation flow & client scoping
- **GET/POST /agent/invite-client** → `Agent\AgentInvitationController` (`agent_invite_client` / `agent_invite_client_post`)
  Agents invite clients standalone (no calendar context); calls `InvitationService::createInvitation($dto->email, 'ROLE_CLIENT', $agent)`; flashes success and redirects to `agent_calendar_list` on success; catches `\DomainException` and re-renders with error flash.
- **UserRepository::findClientsByAgentUser(User $agent): array** — returns ROLE_CLIENT users whose `invitedBy = $agent`, ordered by name ASC (native SQL, mirrors `findByRole` pattern).
- **Agent\CalendarController::list()** updated: `clients` variable now scoped to `findClientsByAgentUser($agent)` instead of `findByRole('ROLE_CLIENT')` — only clients the current agent invited appear in the dropdown.
- **templates/agent/invite_client.html.twig** updated: no `calendarId`, back-link always to `agent_calendar_list`, form posts to `agent_invite_client_post`, submit "Send Invitation".
- **templates/agent/calendar/show.html.twig** updated: "Invite Client" sidebar link removed — a calendar already has exactly one client assigned at creation.
- **templates/agent/calendar/index.html.twig** updated: "Invite New Client" secondary ghost link added next to the "New Calendar" section heading, linking to `agent_invite_client`.

### Phase 6 / Prompt 6.4 ✅
- **Client\CalendarController** protected by `#[IsGranted('ROLE_CLIENT')]`:
  - GET `/client/calendar` (`client_calendar_show`) → `CalendarRepository::findByClient()`, 404 if not found; loads unavailability records via `UnavailabilityRepository::findByCalendar()`; renders `templates/client/calendar/show.html.twig` with `calendar` + `unavailabilities`
  - POST `/client/unavailability` (`client_unavailability_create`) → validates `UnavailabilityDTO` via `#[MapRequestPayload]` (NotBlank on startAt+endAt, endAt must be after startAt, nullable reason); calls `UnavailabilityService::markUnavailable()`; flash success, redirect to `client_calendar_show`
  - DELETE `/client/unavailability/{id}` (`client_unavailability_delete`) → verifies unavailability belongs to current user's calendar (403 if not); removes entity, flush; flash success, redirect to `client_calendar_show`
- **CalendarRepository::findByClient(User $client): ?Calendar** — returns most recent calendar for the given client or null
- **UnavailabilityRepository::findByCalendar(Calendar $calendar): Unavailability[]** — returns all unavailabilities for a calendar ordered by startAt ASC
- **UnavailabilityDTO**: startAt (DateTimeImmutable, NotBlank), endAt (DateTimeImmutable, NotBlank, must be after startAt via Expression constraint), reason (nullable string)
- **UnavailabilityService::markUnavailable()** updated to accept optional `?string $reason` parameter

## Messages (Messenger)

- **InvitationCreatedMessage**: `{ email: string, token: string, role: string }`
- **BookingRequestCreatedMessage**: `{ bookingRequestId: int }`

### Phase 1 / Prompt 1.2 ✅
- **InvitationCreatedMessage** { email: string, token: string, role: string }

### Phase 3 / Prompt 3.1 ✅
- **BookingRequestCreatedMessage** { bookingRequestId: int }

### Gap / Prompt R2 ✅
- **InvitationCreatedHandler** handles `InvitationCreatedMessage` → generates absolute `app_invite_accept` URL via `UrlGeneratorInterface`; sends `TemplatedEmail` to invited user with HTML template `emails/invitation.html.twig` and plain-text fallback `emails/invitation.txt.twig`; context: `{ acceptUrl, role, expiresInDays: 7 }`

### Phase 4 / Prompt 4.1 ✅
- **BookingRequestCreatedHandler** — handles `BookingRequestCreatedMessage`; loads `BookingRequest` by id; loads agent's `NotificationSetting` (defaults to emailEnabled=true, inAppEnabled=true if not set); if emailEnabled: sends `BookingRequestEmail` to agent via `MailerInterface`; if inAppEnabled: persists a new `Notification` for the agent

### Gap / Prompt R1 ✅
- **RegistrationController**: GET+POST `/register`
  - GET → renders `templates/auth/register.html.twig`
  - POST → validates `RegistrationDTO` via `#[MapRequestPayload]`; calls `RegistrationService::registerAgent()`; flash success + redirect to `/login`; catches `\DomainException` (duplicate email) and re-renders form with error
- **RegistrationService::registerAgent(RegistrationDTO $dto): User** — checks `UserRepository::findOneByEmail()` and throws `\DomainException('Email already in use')` if found; creates User with `roles=['ROLE_AGENT']`, `status='active'`, hashed password; persists and flushes
- **templates/auth/register.html.twig** — extends `base.html.twig`; form with name, email, password, password confirmation (client-side match check); "Create Account" submit button; link to `/login`; displays flash messages and `DomainException` errors
- **UserRepository::findOneByEmail(string $email): ?User** — added explicit method delegating to `findOneBy`

### Gap / Prompt R2 ✅
- **templates/emails/invitation.html.twig** — standalone HTML email (no base.html.twig); shows role label (Agent or Client), prominent CTA button linking to `acceptUrl`, 7-day expiry note, plain-text URL fallback below the button
- **templates/emails/invitation.txt.twig** — plain-text version with role, `acceptUrl`, and expiry note

## Pending / Open Questions
- Multi-calendar per client — TBD

## Templates

### Phase 6 / Prompt 6.1 ✅
- **templates/base.html.twig** — HTML5 boilerplate; role-aware nav (ROLE_AGENT: My Calendars + Booking Requests, ROLE_ADMIN: Agents, ROLE_CLIENT: My Calendar); user name + role badge (Admin/Agent/Client) in header; logout link; `{% block stylesheets %}`, `{% block body %}`, `{% block javascripts %}`; flash message display (success / error / info styles)
- **templates/auth/login.html.twig** — extends base; email + password fields; submit "Sign In"; link to `/register`; displays `error.messageKey|trans` authentication error if present
- **templates/auth/accept_invitation.html.twig** — extends base; invited email shown as read-only field; password + confirm password fields with client-side match validation; submit "Activate Account"; 7-day expiry notice; posts to `app_invite_accept_post`

### Phase 6 / Prompt 6.2 ✅
- **templates/agent/calendar/index.html.twig** — extends base; table of agent's calendars (name, displayMode badge, client name, createdAt); "View" button → `agent_calendar_show`; "Share" button → copies public URL (`calendar_public_view`) to clipboard via JS Clipboard API; form to create new calendar (name text, displayMode select timeslot/dayslot, clientId select from `clients` variable); expects `calendars` and `clients` template variables
- **templates/agent/calendar/show.html.twig** — extends base; two-column layout (main + sidebar); shows calendar name, client, displayMode badge, createdAt; read-only public share URL input with copy-to-clipboard button; slots table (type badge, startAt, endAt, status badge colour-coded open/closed/booked/overridden, location, continent); add-slot form (type select day/time, startAt datetime-local, endAt datetime-local, location text, continent select from 7 continents); sidebar with calendar info and "Invite Client" link → `agent_invite_client`; expects `calendar` and `slots` template variables
- **templates/agent/booking/index.html.twig** — extends base; table of booking requests (customerName, customerEmail, message truncated, slot date/time, status badge pending/accepted/declined); for pending requests: inline Accept and Decline forms with `_method=PATCH` override; expects `bookingRequests` template variable
- **templates/agent/invite_client.html.twig** — extends base; back-link to calendar show (if `calendarId` provided) or calendar list; form with email input + hidden `role=ROLE_CLIENT`; optional `calendarId` hidden field; submit "Send Invite"; posts to `agent_invite_client` route

### Phase 6 / Prompt 6.3 ✅
- **templates/admin/users/agents.html.twig** — extends base; "Invite New Agent" form at top (email field + submit); table of agents (name, email, status badge active=green/blocked=red, createdAt); Block/Unblock toggle button per row via form POST with `_method=PATCH` override; expects `agents` template variable
- **templates/client/calendar/show.html.twig** — extends base; heading with calendar name; warning banner when `hasOverriddenSlots` is true; table of unavailability blocks (startAt, endAt, reason, delete button via `_method=DELETE`); add-unavailability form (startAt date, endAt date, reason text optional); posts to `client_unavailability_create`; delete posts to `client_unavailability_delete`; expects `calendar`, `unavailabilities`, `hasOverriddenSlots` template variables
- **templates/public/calendar/show.html.twig** — standalone layout (no base.html.twig); minimal branded header with 🌱 Climate Solutions; calendar name + client name as heading; flash success message; open slots grouped by date with date-group headings; each slot shows type badge (Day/Time block), time range, continent badge, location; "Request Booking" button expands inline form (customerName, customerEmail, message optional, hidden slotId); submit "Send Request"; no auth required

### Gap / Dashboard ✅
- **DashboardController**: GET `/` (`app_home`) — no auth guard; redirects to `admin_agent_list` for ROLE_ADMIN, `agent_calendar_list` for ROLE_AGENT, `client_calendar_show` for ROLE_CLIENT, `app_login` for unauthenticated users
- **config/packages/security.yaml**: `form_login.default_target_path: app_home`, `always_use_default_target_path: false` — post-login redirect hits the role-based dashboard
- **templates/base.html.twig** nav updated: logo `🌱 Climate Solutions` links to `app_home`; client "My Calendar" link uses `client_calendar_show` route (was hardcoded `/client/calendar`); unauthenticated users see "Sign In" → `app_login` and "Register" → `app_register`

## Code Quality ✅

- PHPStan level 10 — phpstan.dist.neon with Symfony + Doctrine + strict-rules extensions
- PHPCS PSR-12 — phpcs.xml.dist covering src/
- Composer scripts: `composer phpstan`, `composer cs-check`, `composer cs-fix`, `composer quality`

## Infrastructure

### Docker / Prompt D1 ✅
- PHP container runs as `appuser` (UID/GID 1000), matching host developer user
- Build ARGs `UID` + `GID` passed via `docker-compose.yml` from shell environment (`${UID:-1000}`, `${GID:-1000}`)
- `compose.override.yaml` can be used for per-developer UID/GID overrides (ideally via a local, untracked override file so the shared override stays focused on common settings)
- No changes needed for Nginx (volumes mounted `:ro`) or entrypoint.sh (runs as `appuser` via Dockerfile `USER` directive)

## Bug Fixes & Feature Gaps — Prompt BF1 ✅

- **BF1.1**: Dayslot no-time enforcement in DTO + form + public view — Agent slot creation coerces day-type startAt/endAt to midnight; agent form toggles between date/datetime-local inputs via JS; public view shows only date for day slots
- **BF1.2**: Multi-day dayslot expanded to individual bookable days — Public\CalendarController expands multi-day day-type slots into virtual per-day entries; BookingRequestDTO gains optional `selectedDate`; BookingRequest entity gains nullable `selectedDate` column (migration applied); public booking form sends hidden `selectedDate` field; BookingService stores selectedDate on day-type requests
- **BF1.3**: Agent booking list shows all statuses (pending/accepted/declined) — BookingRequestRepository::findByAgent() confirmed no status filter; template already shows all statuses with colour-coded badges; Accept/Decline buttons shown only for pending
- **BF1.4**: Calendar/slot editing with accepted-booking guard — PATCH `/agent/calendars/{id}` updates name/displayMode (blocked if accepted bookings exist); DELETE `/agent/calendars/{id}/slots/{slotId}` removes non-booked slots; BookingRequestRepository::hasAcceptedBookingsForCalendar() added; agent calendar show template gains edit form (disabled with note when locked) and per-slot delete button (hidden for booked)
- **BF1.5**: Public calendar shows full time range (startAt–endAt) — Template already renders `HH:MM – HH:MM` for time slots and "Full day" for day slots; confirmed correct in both bookableSlots and fallback paths
- **BF1.6**: Client view correctly loads agent-created calendars — Agent\CalendarController::create() correctly sets `calendar.client` from `clientId` request param; CalendarRepository::findByClient() confirmed correct query
- **BF1.7**: Unavailability normalisation fix (same-day start=end bug) + slot overlap query fix — UnavailabilityService::markUnavailable() normalises same-day endAt to 23:59:59; Client\CalendarController also normalises before passing to service; SlotRepository::findByCalendarAndDateRange() fixed to use proper overlap query (`startAt < :to AND endAt > :from`); `app:normalise-unavailability` console command created and run to fix existing broken records

## Bundle Refactor — Prompt BF2 ✅

- **BF2.1**: Partial dayslot unavailability — SlotUnavailability entity (`src/CalendarBundle/Entity/SlotUnavailability.php`) tracks per-day blocks with slot, unavailability, and blockedDate fields; UnavailabilityService iterates each day in a day-type slot's range and creates SlotUnavailability records for days overlapping the unavailability period; only fully-covered slots get 'overridden' status; Public\CalendarController excludes blocked dates when expanding multi-day slots into virtual per-day entries; BookingService checks `isDateBlockedForSlot()` and throws DomainException for blocked dates; Client calendar template shows "Affected Days" column per unavailability block
- **BF2.2**: CalendarBundle namespace created under `src/CalendarBundle/`; BookingRequest, Unavailability, SlotUnavailability entities moved to `App\CalendarBundle\Entity\`; BookingRequestRepository, UnavailabilityRepository, SlotUnavailabilityRepository moved to `App\CalendarBundle\Repository\`; BookingService, UnavailabilityService moved to `App\CalendarBundle\Service\`; BookingRequestCreatedMessage moved to `App\CalendarBundle\Message\`; BookingRequestCreatedHandler moved to `App\CalendarBundle\MessageHandler\`; BookingRequestDTO, UnavailabilityDTO moved to `App\CalendarBundle\Dto\`; Doctrine mapping updated in `config/packages/doctrine.yaml` with `AppCalendarBundle` mapping; all controllers and consumers updated to import from `App\CalendarBundle\*` namespace

## Timezone — Prompt TZ1 ✅

- All datetime entity fields changed to `datetimetz_immutable` Doctrine column type, storing `TIMESTAMP WITH TIME ZONE` in PostgreSQL 16 (affects: `User.createdAt`, `Invitation.expiresAt`/`acceptedAt`, `Calendar.createdAt`, `Slot.startAt`/`endAt`/`createdAt`, `Unavailability.startAt`/`endAt`, `BookingRequest.createdAt`/`selectedDate`, `Notification.readAt`/`createdAt`, `SlotUnavailability.blockedDate`)
- All `prePersist` hooks and service-level `DateTimeImmutable` constructions use `new \DateTimeImmutable('now', new \DateTimeZone('UTC'))` to ensure UTC storage
- DTOs normalise incoming date values to UTC before persistence: `Agent\CalendarController` applies `setTimezone(new \DateTimeZone('UTC'))` to slot `startAt`/`endAt`; `Client\CalendarController` normalises unavailability `startAt`/`endAt` to UTC before same-day coercion; `BookingService` normalises `selectedDate` to UTC before storing
- Single migration `Version20260403135219` applied to convert all affected columns from `TIMESTAMP WITHOUT TIME ZONE` to `TIMESTAMP WITH TIME ZONE` using explicit `USING ... AT TIME ZONE 'UTC'` cast to preserve existing data

## Bug Fixes — Prompt BF3 ✅

- **BF3**: Multi-day dayslot booking acceptance fix — accepting a booking for a specific `selectedDate` in a day-type multi-day slot no longer marks the entire slot as booked; instead, a `SlotUnavailability` record (with null `unavailability` FK) is created for the booked day; `SlotUnavailability.unavailability` made nullable (migration `Version20260403150010`); `SlotUnavailabilityRepository::areAllDaysBlockedForSlot(Slot)` added to iterate the slot date range and check each day via `isDateBlockedForSlot()`; slot status is set to `booked` only when all days in the range are covered; `BookingRequestRepository::findPendingBySlotAndDate(Slot, DateTimeImmutable)` added; only same-`selectedDate` pending requests are declined (not requests for other days of the same slot); public calendar and agent booking views require no changes — `CalendarSubscriber` already excludes `SlotUnavailability`-blocked dates (null FK rows included) and agent view already shows all requests regardless of status

## Developer Tooling ✅

- **DatabaseSeeder** `src/DataFixtures/DatabaseSeeder.php` — seeds 1 admin, 2 agents, 2 clients, 2 calendars, 6 slots; idempotent (checks existing users by email, existing calendars by agent+client, existing slots by COUNT query); UTC-aware dates via `new \DateTimeImmutable('now', new \DateTimeZone('UTC'))`; plain Symfony service (not a Doctrine Fixture) tagged `#[AsTaggedItem('app.seeder')]`; single flush at the end
- **app:db:fresh** `src/Command/DatabaseFreshCommand.php` — drops DB (`doctrine:database:drop --force --if-exists`), recreates (`doctrine:database:create`), runs all migrations (`doctrine:migrations:migrate`), calls `DatabaseSeeder::seed()`; aborts with `Command::FAILURE` and a clear error message if any sub-command returns non-zero; interactive confirmation prompt skipped when `--no-interaction` is passed

## Bug Fixes / Feature Gaps — Continent Restriction ✅

- Continent selection restricted to Europe only: the `createSlot` controller action in `App\Controller\Agent\CalendarController` ignores any `continent` value from the POST request and always persists `'Europe'`; agent slot-creation form no longer has a continent input (just a static label); public calendar JS appends the continent label only when `props.continent` is a non-empty string — no default fallback to "Europe" in JS. No migration required — `Slot.continent` remains a nullable string column.

## Bug Fixes / Feature Gaps — Time Slot Rendering ✅

- **Time-type slots render as timed blocks in the shared FullCalendar view.** `CalendarSubscriber` passes real `startAt`/`endAt` `DateTime` values for time-type slots and explicitly includes `'allDay' => false` in the event options array; FullCalendar infers `allDay=false` from the non-midnight times and renders the event spanning the correct time range. Day-type slots remain all-day bars (`'allDay'` not set, end passed as `null`). `timeGridWeek`/`timeGridDay` views were added to the FullCalendar toolbar in `templates/public/calendar/show.html.twig`, so timed views are available even though the template still uses its existing default `initialView`. Note: any time-type slot accidentally stored with midnight `startAt`/`endAt` (prior to the BF1.1 guard being time-type-aware) would still render as an all-day event in older browsers without the explicit `allDay: false` flag; the explicit flag covers this edge case.

## ROLE_SOLO_AGENT — Activity Management & Filtered Share Links ✅

### Security
- `security.yaml` role hierarchy: `ROLE_SOLO_AGENT: [ROLE_AGENT]` — solo agents inherit all ROLE_AGENT routes

### Entities
- **Activity**: id (auto-increment), name (string 255, not null), description (nullable text), calendar (ManyToOne → Calendar, not null, onDelete CASCADE), createdAt (datetimetz_immutable, set on prePersist UTC) — composite index on (calendar_id, name)
- **Calendar.client** changed to nullable (ManyToOne → User, onDelete SET NULL); solo agent calendars have client = null
- **Slot.activity**: nullable ManyToOne → Activity (onDelete SET NULL) — migration `Version20260405175537`

### Services
- **ActivityRepository::findByCalendar(Calendar $calendar): Activity[]** — ordered by name ASC
- **SlotRepository::findOpenByCalendar** updated to accept `?Activity $activity = null`; when provided adds `WHERE s.activity = :activity` filter; existing callers (no second arg) unchanged
- **CalendarSubscriber** updated: reads optional `activityId` from FullCalendar event-source filters, validates it belongs to the calendar, filters event output to matching slots only

### Controllers / Routes
- **POST `/agent/calendars/{id}/activities`** (`agent_activity_create`) — validates ActivityDTO (name NotBlank), persists Activity, redirect with flash
- **DELETE `/agent/calendars/{id}/activities/{actId}`** (`agent_activity_delete`) — verifies activity.calendar === current calendar (403 if not), remove + flush, redirect with flash
- **PATCH `/agent/calendars/{id}/slots/{slotId}/activity`** (`agent_slot_update_activity`) — reads `activityId` (nullable int) from request body, loads + validates activity ownership, calls `slot.setActivity()`, flush, redirect with flash
- **GET `/c/{token}?activity={id}`** — same `calendar_public_view` route; reads optional `?activity` query param; 404 for unknown or foreign activity; passes `$activity` (nullable) to `CalendarSubscriber` via FullCalendar `extraParams.filters.activityId`; template heading shows activity name when filtered

### Templates
- **templates/agent/calendar/show.html.twig** updated (non-destructive additions): slots table gains "Activity" column with inline `<select>` that auto-submits a PATCH form to `agent_slot_update_activity`; Activities section added (table with name, description, activity-filtered share link, delete button; "No activities yet" empty state); "Add Activity" form (name, optional description); sidebar skips "Client" row when `calendar.client` is null; page header also null-safe on client
- **templates/agent/calendar/index.html.twig** updated: client cell shows "—" for null client
- **templates/public/calendar/show.html.twig** updated: heading appends "— {activity.name}" when activity filter active; client-name paragraph null-safe; FullCalendar `extraParams.filters` includes `activityId` when activity param present

### Developer Tooling
- **DatabaseSeeder** updated (single flush maintained): seeds `solo@example.com` (ROLE_SOLO_AGENT, active), "Solo Calendar" (agent=soloAgent, client=null), activities "Consultation" + "Workshop", 2 time-type open slots each tagged to one activity; idempotent via `findOrCreateSoloCalendar` + `ensureActivities` helpers

### Activity Share Links — multi-activity filter ✅
- `SlotRepository::findOpenByCalendar` signature changed to `array $activityIds = []`; when non-empty adds `WHERE s.activity IN (:activityIds)`; empty array returns all open slots (no regression).
- `CalendarSubscriber` reads `activityIds` (int array) from `extraParams.filters.activityIds`; validates each ID belongs to the calendar; filters slots via `in_array` in PHP.
- `GET /c/{token}?activityIds=1,3` parses comma-separated IDs; 404 on any unknown or foreign activity ID; empty/absent param returns all slots; old `?activityId` (singular) param silently ignored.
- Agent calendar show template: "Share Link" column removed; activity share box added with "Share All" copy button, per-activity checkboxes, and "Copy filtered link" button (disabled until at least one checkbox is checked; builds `?activityIds=id1,id2`).
- Public calendar heading lists all filtered activity names comma-joined when filter is active.

### Activity public tokens ✅
- `Activity.publicToken` added (VARCHAR 36, unique, UUID v4 generated on prePersist) — mirrors the `Calendar.publicToken` pattern; integer IDs are never exposed in public URLs.
- `ActivityRepository::findByPublicToken(string $token): ?Activity` — lookup by public token.
- Migration `Version20260406130111`: adds column nullable, backfills via PostgreSQL `gen_random_uuid()`, sets NOT NULL, adds unique index.
- `GET /c/{token}?activityTokens=uuid1,uuid2` — replaces `?activityIds=...`; 404 on unknown token or token belonging to a different calendar; old `?activityIds` param silently ignored.
