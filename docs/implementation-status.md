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
