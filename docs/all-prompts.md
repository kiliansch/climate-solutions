# Shared Calendar Booking System — All Prompts Archive

**Created:** 2026-03-26
**Purpose:** Complete archive of all Copilot Agent prompts for the calendar booking system project.
**Usage:** Copy individual prompts into VS Code with Copilot Agent active (Claude Sonnet 4.6).

---

## Phase 1 — Auth & User Management

### Prompt 1.1 — User Entity & Roles

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.

### TASK
Create a Symfony User entity in src/Entity/User.php implementing UserInterface and PasswordAuthenticatedUserInterface.

Fields:
- id: int (auto-generated)
- email: string, unique, not null
- password: string, not null
- roles: array (JSON column), values: ROLE_ADMIN, ROLE_AGENT, ROLE_CLIENT
- status: string ENUM('active', 'blocked'), default 'active'
- name: string
- createdAt: DateTimeImmutable (set via #[ORM\HasLifecycleCallbacks] on prePersist)
- invitedBy: nullable ManyToOne self-referencing to User

Use PHP 8 attributes for all ORM mappings. Add private properties with typed getters/setters.
Generate the migration after creating the entity.

### UPDATE DOCS
After completing the task, update docs/implementation-status.md:
- Add all new entities under ## Entities with their key fields and relationships
- Add all new services under ## Services with their public method signatures
- Add all new routes under ## Controllers & Routes
- Add any new Messenger messages under ## Messages
- Do not remove existing entries — only append or update
```

### Prompt 1.2 — Invitation System

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.

### TASK
Create an Invitation entity in src/Entity/Invitation.php with fields:
- id: int
- email: string
- token: string (UUID, unique)
- role: string (ROLE_AGENT or ROLE_CLIENT)
- invitedBy: ManyToOne to User, not null
- expiresAt: DateTimeImmutable
- acceptedAt: nullable DateTimeImmutable

Create an InvitationService in src/Service/InvitationService.php with:
- createInvitation(string $email, string $role, User $invitedBy): Invitation
  - Generates a UUID token, sets expiry to +7 days, persists via EntityManager
  - Dispatches an InvitationCreatedMessage via Symfony Messenger for async email sending
- acceptInvitation(string $token, string $plainPassword): User
  - Validates token exists, not expired, not already accepted
  - Creates and persists a new User with hashed password and correct role
  - Marks invitation as accepted

Do not flush inside a loop. Use constructor injection only. Follow PSR-12.

### UPDATE DOCS
Append to docs/implementation-status.md under ## Entities:
- Invitation: id, email, token (UUID), role, invitedBy (ManyToOne → User), expiresAt, acceptedAt
Mark Phase 1 / Prompt 1.2 as complete.
```

### Prompt 1.3 — Auth Controllers

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.

### TASK
Create two Symfony controllers following AbstractController:

1. src/Controller/Auth/LoginController.php
   - GET/POST /login — renders login form, handled by Symfony's security firewall
   - GET /logout — handled by firewall

2. src/Controller/Auth/InvitationController.php
   - GET /invite/accept/{token} — renders password-setup form
   - POST /invite/accept/{token} — calls InvitationService::acceptInvitation(), then redirects to login
   - Use a DTO class AcceptInvitationDTO in src/Dto/ with Symfony Validator constraints (NotBlank, Length min 8 on password)
   - Use #[MapRequestPayload] for POST deserialization

Use #[Route] attributes. Return Response objects. No business logic in controller.

### UPDATE DOCS
Append to docs/implementation-status.md under ## Controllers & Routes:
- POST /login, GET /logout
- GET+POST /invite/accept/{token} → InvitationController
Mark Phase 1 / Prompt 1.3 as complete.
```

---

## Phase 2 — Calendars & Slots

### Prompt 2.1 — Calendar & Slot Entities

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: User entity (Phase 1 / Prompt 1.1).

### TASK
Create Calendar entity (src/Entity/Calendar.php):
- id, name, displayMode ENUM('timeslot','dayslot') default 'dayslot'
- client: ManyToOne User (ROLE_CLIENT), #[ORM\JoinColumn(nullable:false)]
- agent: ManyToOne User (ROLE_AGENT), #[ORM\JoinColumn(nullable:false)]
- publicToken: string UUID unique, generated on prePersist
- slots: OneToMany to Slot, fetch: EXTRA_LAZY
- createdAt: DateTimeImmutable, set on prePersist
CalendarRepository: findByAgent(User $agent), findByPublicToken(string $token)

Create Slot entity (src/Entity/Slot.php):
- id, type ENUM('day','time'), startAt, endAt: DateTimeImmutable
- status ENUM('open','closed','booked','overridden') default 'open'
- location: nullable string, continent: nullable string
- calendar: ManyToOne Calendar, not null
- createdAt: DateTimeImmutable
- Add #[ORM\Index] on (calendar_id, start_at, status)
SlotRepository: findOpenByCalendar(Calendar $calendar),
findByCalendarAndDateRange(Calendar $calendar, DateTimeImmutable $from, DateTimeImmutable $to)

Generate Doctrine migration.

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Entities: Calendar (fields + relations), Slot (fields + relations, index noted)
- Under ## Services: CalendarRepository methods, SlotRepository methods
Mark Phase 2 / Prompt 2.1 as complete.
```

### Prompt 2.2 — Unavailability & Conflict Resolution

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: Calendar, Slot entities (Phase 2 / Prompt 2.1).

### TASK
Create Unavailability entity (src/Entity/Unavailability.php):
- id, startAt, endAt: DateTimeImmutable, reason: nullable string
- calendar: ManyToOne Calendar, client: ManyToOne User, both not null

Create UnavailabilityService (src/Service/UnavailabilityService.php):
- markUnavailable(Calendar $calendar, User $client, DateTimeImmutable $start, DateTimeImmutable $end): void
  → persists Unavailability
  → finds all 'open' slots in that calendar overlapping the date range
    via SlotRepository::findByCalendarAndDateRange()
  → sets each overlapping slot status to 'overridden' (never delete — preserves agent intent)
  → single flush after all updates, never inside loop

Business rule: client unavailability ALWAYS takes precedence over agent-opened slots.

Generate Doctrine migration.

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Entities: Unavailability (id, calendar, client, startAt, endAt, reason)
- Under ## Services: UnavailabilityService::markUnavailable()
Mark Phase 2 / Prompt 2.2 as complete.
```

### Prompt 2.3 — Agent Calendar Controller

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: Calendar, Slot, UnavailabilityService (Phase 2 / Prompts 2.1–2.2).

### TASK
Create src/Controller/Agent/CalendarController.php, all routes under /agent,
protected by #[IsGranted('ROLE_AGENT')] on the class:

- GET  /agent/calendars → list agent's calendars via CalendarRepository::findByAgent()
- POST /agent/calendars → create calendar using CalendarDTO (src/Dto/CalendarDTO.php)
- GET  /agent/calendars/{id} → view calendar + its open slots
- POST /agent/calendars/{id}/slots → open slot using SlotDTO (src/Dto/SlotDTO.php)
  Validator constraints on SlotDTO: startAt before endAt, type in ['day','time']
- GET  /agent/calendars/{id}/share → render template with public URL (do NOT return JsonResponse)

No business logic in controllers. Constructor inject repositories and services only.

### UPDATE DOCS
Append to docs/implementation-status.md under ## Controllers & Routes:
- GET/POST /agent/calendars
- GET /agent/calendars/{id}
- POST /agent/calendars/{id}/slots
- GET /agent/calendars/{id}/share
Mark Phase 2 / Prompt 2.3 as complete.
```

---

## Phase 3 — Public Calendar & Booking

### Prompt 3.1 — BookingRequest Entity & BookingService

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: Slot, Calendar, User entities (Phase 2).

### TASK
Create BookingRequest entity (src/Entity/BookingRequest.php):
- id, customerName: string, customerEmail: string, message: nullable string
- status ENUM('pending','accepted','declined') default 'pending'
- slot: ManyToOne Slot, not null; createdAt: DateTimeImmutable

BookingRequestRepository: findPendingBySlot(Slot $slot), findByAgent(User $agent)

Create BookingService (src/Service/BookingService.php):
- createRequest(Slot $slot, BookingRequestDTO $dto): BookingRequest
  → throw \DomainException if slot status != 'open'
  → persist BookingRequest with status 'pending'
  → dispatch BookingRequestCreatedMessage via Symfony Messenger
  → do NOT change slot status yet
- acceptRequest(BookingRequest $request, User $agent): void
  → validate agent owns calendar (throw \AccessDeniedException if not)
  → set request status 'accepted', slot status 'booked'
  → set all other pending requests for same slot to 'declined'
  → single flush after all updates
- declineRequest(BookingRequest $request, User $agent): void
  → validate agent ownership, set status 'declined', flush

Generate Doctrine migration.

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Entities: BookingRequest (id, slot, customerName, customerEmail, message, status, createdAt)
- Under ## Services: BookingService::createRequest(), ::acceptRequest(), ::declineRequest()
- Under ## Messages: BookingRequestCreatedMessage { bookingRequestId: int }
Mark Phase 3 / Prompt 3.1 as complete.
```

### Prompt 3.2 — Public Controller & Booking Controller

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: BookingService, CalendarRepository, SlotRepository (Phase 3 / Prompt 3.1).

### TASK
Create src/Controller/Public/CalendarController.php (no auth required):
- GET  /c/{token} → CalendarRepository::findByPublicToken(), 404 if not found
  Load only slots with status 'open' via SlotRepository::findOpenByCalendar()
  Render templates/public/calendar/show.html.twig
- POST /c/{token}/book → BookingRequestDTO with NotBlank on name+email, valid Email
  Call BookingService::createRequest(), redirect to GET /c/{token} with flash success

Create src/Controller/Agent/BookingController.php, protected by ROLE_AGENT:
- GET   /agent/bookings → list all booking requests via BookingRequestRepository::findByAgent()
- PATCH /agent/bookings/{id}/accept  → BookingService::acceptRequest(), redirect with flash
- PATCH /agent/bookings/{id}/decline → BookingService::declineRequest(), redirect with flash

### UPDATE DOCS
Append to docs/implementation-status.md under ## Controllers & Routes:
- GET+POST /c/{token} → Public\CalendarController
- GET /agent/bookings, PATCH /agent/bookings/{id}/accept, PATCH /agent/bookings/{id}/decline
Mark Phase 3 / Prompt 3.2 as complete.
```

---

## Phase 4 — Notifications

### Prompt 4.1 — Notification System

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: BookingRequestCreatedMessage (Phase 3 / Prompt 3.1), User entity (Phase 1).

### TASK
Create NotificationSetting entity (src/Entity/NotificationSetting.php):
- id, emailEnabled: bool default true, inAppEnabled: bool default true
- user: OneToOne User, not null

Create Notification entity (src/Entity/Notification.php):
- id, message: string, readAt: nullable DateTimeImmutable, createdAt: DateTimeImmutable
- user: ManyToOne User, not null

Create BookingRequestCreatedHandler (src/MessageHandler/BookingRequestCreatedHandler.php):
- Use #[AsMessageHandler]
- Loads BookingRequest by id from message
- Loads agent's NotificationSetting (or uses defaults if not set)
- If emailEnabled: send BookingRequestEmail Mailable to agent
- If inAppEnabled: persist a new Notification for the agent
- Constructor inject all dependencies

Generate Doctrine migration.

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Entities: NotificationSetting (user, emailEnabled, inAppEnabled), Notification (user, message, readAt, createdAt)
- Under ## Messages: BookingRequestCreatedHandler handles BookingRequestCreatedMessage
Mark Phase 4 / Prompt 4.1 as complete.
```

---

## Phase 5 — Admin

### Prompt 5.1 — Admin Controller

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: User entity, InvitationService (Phase 1).

### TASK
Create src/Controller/Admin/UserController.php, protected by #[IsGranted('ROLE_ADMIN')] on the class:

- GET  /admin/agents → list all ROLE_AGENT users via UserRepository::findByRole('ROLE_AGENT')
  Render name, email, status, createdAt in Twig template
- PATCH /admin/users/{id}/block   → set user.status = 'blocked', flush, flash + redirect
- PATCH /admin/users/{id}/unblock → set user.status = 'active', flush, flash + redirect
- POST  /admin/invite → call InvitationService::createInvitation() to invite a new agent
  Use InviteUserDTO (src/Dto/InviteUserDTO.php) with NotBlank + valid Email constraints

No business logic in controller. Constructor inject only.

### UPDATE DOCS
Append to docs/implementation-status.md under ## Controllers & Routes:
- GET /admin/agents
- PATCH /admin/users/{id}/block, PATCH /admin/users/{id}/unblock
- POST /admin/invite
Mark Phase 5 / Prompt 5.1 as complete.
```

---

## Gap Prompts — Registration & Email

### Prompt R1 — Agent Self-Registration

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: User entity (Phase 1 / Prompt 1.1), UserPasswordHasherInterface.

### TASK
Create src/Controller/Auth/RegistrationController.php:
- GET  /register → render templates/auth/register.html.twig
- POST /register → use RegistrationDTO (src/Dto/RegistrationDTO.php) via #[MapRequestPayload]
  Fields: name (NotBlank), email (NotBlank, Email), password (NotBlank, Length min 8)
  Call RegistrationService::registerAgent()
  On success: flash success, redirect to /login
  On duplicate email: catch \DomainException, re-render with error

Create src/Service/RegistrationService.php:
- registerAgent(RegistrationDTO $dto): User
  → throw \DomainException('Email already in use') if email exists
  → create User with roles=['ROLE_AGENT'], status='active', hashed password
  → persist and flush

Create templates/auth/register.html.twig extending base:
- Fields: name, email, password, password confirmation (client-side match check)
- Submit: "Create Account", link to login

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Controllers & Routes: GET+POST /register
- Under ## Services: RegistrationService::registerAgent()
- Under ## Templates: templates/auth/register.html.twig
Mark Gap / Prompt R1 as complete.
```

### Prompt R2 — Invitation Email Handler & Template

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
InvitationCreatedMessage { email, token, role } is already dispatched by InvitationService::createInvitation().

### TASK
Create src/MessageHandler/InvitationCreatedHandler.php:
- Use #[AsMessageHandler]
- Build absolute URL for route 'invitation_accept' with {token}
- Send TemplatedEmail via MailerInterface:
    - To: message.email
    - Subject: "You've been invited to the Calendar Booking System"
    - HtmlTemplate: 'emails/invitation.html.twig'
    - Context: { acceptUrl, role, expiresInDays: 7 }
- Constructor inject MailerInterface, UrlGeneratorInterface

Create templates/emails/invitation.html.twig (standalone, no base extension):
- Role label, CTA button → acceptUrl, 7-day expiry note, plain text URL fallback

Create templates/emails/invitation.txt.twig:
- Plain text with acceptUrl and expiry note

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Messages: InvitationCreatedHandler
- Under ## Templates: templates/emails/invitation.html.twig, invitation.txt.twig
Mark Gap / Prompt R2 as complete.
```

### Prompt R3 — Fix JSON-Returning Routes

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.

### TASK
Audit all controllers in src/Controller/ and fix any route that returns a JsonResponse
or $this->json(...) where it should render a Twig template or redirect.

Rules — no JsonResponse anywhere in this project:
- GET  /agent/calendars             → render templates/agent/calendar/index.html.twig
- GET  /agent/calendars/{id}        → render templates/agent/calendar/show.html.twig
- GET  /agent/calendars/{id}/share  → render templates/agent/calendar/show.html.twig
  (pass publicUrl as template variable; clipboard copy handled by JS — not a JSON response)
- GET  /agent/bookings              → render templates/agent/booking/index.html.twig
- GET  /admin/agents                → render templates/admin/users/agents.html.twig
- GET  /client/calendar             → render templates/client/calendar/show.html.twig

PATCH / DELETE routes must redirectToRoute() with a flash message.
POST routes must redirectToRoute() on success (PRG pattern) or re-render on validation error.

### UPDATE DOCS
Note under ## Controllers & Routes that all routes now return render() or redirectToRoute().
Mark Gap / Prompt R3 as complete.
```

### Prompt R4 — Dashboard & Navigation

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.

### TASK

1. Create src/Controller/DashboardController.php
   - Route: GET / named "app_home"
   - Role-based redirect (no template needed):
     - ROLE_ADMIN  → redirectToRoute('admin_agent_list')
     - ROLE_AGENT  → redirectToRoute('agent_calendar_list')
     - ROLE_CLIENT → redirectToRoute('client_calendar_show')
     - unauthenticated → redirectToRoute('app_login')
   - Use $this->isGranted() — no service injection needed.

2. Remove the default Symfony welcome page
   - Delete templates/home.html.twig if it exists
   - Remove any existing route mapped to GET / that is not DashboardController

3. Update config/packages/security.yaml
   - Set form_login.default_target_path: app_home
   - Set always_use_default_target_path: false (respect _target_path when present)

4. Update templates/base.html.twig (non-destructive, only add what's missing)
   - Logo/site name links to app_home
   - ROLE_ADMIN  nav: "Agents" → admin_agent_list
   - ROLE_AGENT  nav: "My Calendars" → agent_calendar_list | "Booking Requests" → agent_booking_list
   - ROLE_CLIENT nav: "My Calendar" → client_calendar_show
   - Authenticated: "Logout" link
   - Unauthenticated: "Sign In" → app_login | "Register" → app_register

### UPDATE DOCS
Append to docs/implementation-status.md:
- Under ## Controllers & Routes: GET / → DashboardController (app_home), role-based redirect
- Under ## Templates: base.html.twig updated with app_home logo link + unauthenticated nav
Mark Gap / Prompt R4 as complete.
```

---

## Phase 6 — Twig Templates

### Prompt 6.1 — Base Layout & Auth Templates

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.

### TASK
Create the following Twig templates. All authenticated templates extend templates/base.html.twig.

templates/base.html.twig:
- HTML5 boilerplate with {% block title %}, {% block body %}
- Nav bar: current user name + role badge (ADMIN / AGENT / CLIENT)
- Nav links by role:
    - ROLE_AGENT:  "My Calendars" (agent_calendar_list), "Booking Requests" (agent_booking_list)
    - ROLE_ADMIN:  "Agents" (admin_agent_list)
    - ROLE_CLIENT: "My Calendar" (client_calendar_show)
- Logout link for authenticated users
- Flash message display block (success / error / info)

templates/auth/login.html.twig:
- Email + password, submit "Sign In"
- Link to register; display authentication_error if present

templates/auth/accept_invitation.html.twig:
- Invited email (read-only), password + confirm, submit "Activate Account"
- 7-day expiry notice

### UPDATE DOCS
Append to docs/implementation-status.md under ## Templates:
- templates/base.html.twig
- templates/auth/login.html.twig
- templates/auth/accept_invitation.html.twig
Mark Phase 6 / Prompt 6.1 as complete.
```

### Prompt 6.2 — Agent Templates

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: Agent\CalendarController, Agent\BookingController routes (Phase 2+3).

### TASK
Create the following Twig templates (all extend base):

templates/agent/calendar/index.html.twig:
- Table of agent's calendars: name, displayMode badge, client name, createdAt
- "View" → agent_calendar_show, "Share" → copies public URL to clipboard via JS Clipboard API
- Create calendar form: name, displayMode select, clientId select

templates/agent/calendar/show.html.twig:
- Calendar name, client, displayMode; public share URL with copy-to-clipboard button
- Slots table: type badge, startAt, endAt, status badge (colour-coded), location, continent
- Add slot form: type, startAt/endAt (datetime-local), location, continent select

templates/agent/booking/index.html.twig:
- Booking requests table: customer name, email, message, slot datetime, status badge
- Pending rows: inline Accept / Decline forms (PATCH method override)

templates/agent/invite_client.html.twig:
- Email field, role hidden = ROLE_CLIENT, submit "Send Invite"

### UPDATE DOCS
Append under ## Templates. Mark Phase 6 / Prompt 6.2 as complete.
```

### Prompt 6.3 — Client, Admin & Public Templates

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: Admin\UserController (Phase 5), Public\CalendarController (Phase 3), Client routes.

### TASK
templates/admin/users/agents.html.twig (extends base):
- Agents table: name, email, status badge (active=green/blocked=red), createdAt
- Block/Unblock toggle (PATCH method override); Invite New Agent form at top

templates/client/calendar/show.html.twig (extends base):
- Calendar name; unavailability table with delete buttons (DELETE method override)
- Add unavailability form: startAt, endAt, reason (optional)
- Warning banner if hasOverriddenSlots is true

templates/public/calendar/show.html.twig (standalone — does NOT extend base):
- Minimal branded header; calendar + client name as heading
- Open slots grouped by date: type, time range, continent badge, location
- Per slot: "Request Booking" expands inline form (customerName, customerEmail, message, hidden slotId)
- Flash success at top; no auth required

### UPDATE DOCS
Append under ## Templates. Mark Phase 6 / Prompt 6.3 as complete.
```

### Prompt 6.4 — Client Controller

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: Unavailability entity + UnavailabilityService (Phase 2 / Prompt 2.2).

### TASK
Create src/Controller/Client/CalendarController.php protected by #[IsGranted('ROLE_CLIENT')]:

- GET /client/calendar
  → CalendarRepository::findByClient($user), 404 if not found
  → load Unavailability records via UnavailabilityRepository::findByCalendar()
  → render templates/client/calendar/show.html.twig with calendar, unavailabilities, hasOverriddenSlots

- POST /client/unavailability
  → UnavailabilityDTO: startAt (NotBlank), endAt (NotBlank, after startAt), reason (nullable)
  → call UnavailabilityService::markUnavailable(), redirect with flash success

- DELETE /client/unavailability/{id}
  → verify belongs to current user's calendar (403 if not)
  → remove, flush, redirect with flash

No business logic in controller. Constructor inject only.

### UPDATE DOCS
Append to docs/implementation-status.md under ## Controllers & Routes:
- GET /client/calendar, POST /client/unavailability, DELETE /client/unavailability/{id}
Mark Phase 6 / Prompt 6.4 as complete.
```

---

## Code Quality

### Prompt CQ1 — PHPStan & PHPCS Setup

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Stack: Symfony 7, PHP 8.3, Composer.

### TASK
Set up two code quality tools: PHPStan (highest level) and PHP_CodeSniffer (PSR-12).

#### 1. Install dependencies (require-dev)

composer require --dev phpstan/phpstan phpstan/extension-installer phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/phpstan-strict-rules squizlabs/php_codesniffer

#### 2. PHPStan — phpstan.dist.neon

Create phpstan.dist.neon at the project root:

includes:
    - vendor/phpstan/phpstan-symfony/extension.neon
    - vendor/phpstan/phpstan-doctrine/extension.neon
    - vendor/phpstan/phpstan-strict-rules/rules.neon

parameters:
    level: 10
    paths:
        - src
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        objectManagerLoader: tests/bootstrap_doctrine.php
    ignoreErrors: []
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true

#### 3. Doctrine bootstrap for PHPStan

Create tests/bootstrap_doctrine.php:

<?php

declare(strict_types=1);

use App\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();

#### 4. PHP_CodeSniffer — phpcs.xml.dist

Create phpcs.xml.dist at the project root:

<?xml version="1.0"?>
<ruleset name="Calendar Booking System">
    <description>PSR-12 coding standard</description>
    <file>src</file>
    <arg name="basepath" value="."/>
    <arg name="extensions" value="php"/>
    <arg name="colors"/>
    <arg value="sp"/>
    <rule ref="PSR12"/>
    <!-- Exclude generated files -->
    <exclude-pattern>src/Migrations/*</exclude-pattern>
</ruleset>

#### 5. Composer scripts

Add to the scripts section in composer.json:

"scripts": {
    "phpstan": "phpstan analyse --memory-limit=256M",
    "cs-check": "phpcs",
    "cs-fix": "phpcbf",
    "quality": [
        "@phpstan",
        "@cs-check"
    ]
}

#### 6. .gitignore additions

/.phpstan-cache/
/phpstan.dist.neon.cache

### RULES
- Do NOT change any existing src/ code — only add config files and dev dependencies.
- All tools analyse src/ only, not tests/ or vendor/.
- PSR-12 applies to all .php files in src/.
- PHPStan level must be 10 (maximum).
- Use phpstan.dist.neon (not phpstan.neon) and phpcs.xml.dist (not phpcs.xml) so local overrides remain gitignored.

### UPDATE DOCS
Append to docs/implementation-status.md under a new section ## Code Quality:
- PHPStan level 10 — phpstan.dist.neon with Symfony + Doctrine + strict-rules extensions
- PHPCS PSR-12 — phpcs.xml.dist covering src/
- Composer scripts: composer phpstan, composer cs-check, composer cs-fix, composer quality
Mark Code Quality / Prompt CQ1 as complete ✅.
```

---

## Docker — Infrastructure

### Prompt D1 — Fix UID/GID Mismatch (Container root vs Host 1000:1000)

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
The PHP container currently runs as root. The host developer user is UID=1000, GID=1000.
Any files written by the container (vendor/, var/cache/, migrations, etc.) are owned by
root on the host, requiring sudo to edit or delete them.

### PROBLEM
UID/GID mismatch between the container (root) and the host (1000:1000) causes permission
errors on bind-mounted volumes.

### TASK

1. Update docker/php/Dockerfile:
   - After installing system packages, declare build ARGs and create a matching user:
       ARG UID=1000
       ARG GID=1000
       RUN groupadd -g ${GID} appuser \
        && useradd -u ${UID} -g appuser -m appuser
   - Transfer ownership of the app directory:
       RUN mkdir -p /var/www/html && chown -R appuser:appuser /var/www/html
   - Switch to the non-root user BEFORE ENTRYPOINT/CMD:
       USER appuser

2. Update entrypoint.sh:
   - The script already runs composer install and symfony commands.
     Since USER appuser is set in the Dockerfile, these will now run as UID 1000 automatically.
   - If any step still requires root (e.g. apt installs), move those steps to the Dockerfile
     BEFORE the USER appuser instruction — never run apt inside entrypoint.sh.

3. Update docker-compose.yml — php service build section:
       build:
         context: .
         dockerfile: docker/php/Dockerfile
         args:
           UID: ${UID:-1000}
           GID: ${GID:-1000}
   - The ${UID:-1000} syntax reads the UID from the environment (for example, run
     `export UID=$(id -u) GID=$(id -g)` before `docker compose`) and falls back to 1000
     if not set, keeping it portable across developers.
   - Do NOT use the top-level `user:` key as an alternative — build ARGs in the Dockerfile
     are the correct approach so the user is baked into the image.

4. Nginx service — no changes needed (volume is mounted :ro).

5. Rebuild and verify:
       docker compose build --no-cache php
       docker compose up -d
       docker compose exec php id
       # Expected: uid=1000(appuser) gid=1000(appuser) groups=1000(appuser)
       ls -la var/
       # Files should now be owned by your host user (1000), not root

### NOTE
Keep UID/GID as build ARGs — never hard-code them in the Dockerfile.
For per-developer overrides, add to compose.override.yaml:
    services:
      php:
        build:
          args:
            UID: ${UID:-1000}
            GID: ${GID:-1000}

### UPDATE DOCS
Append to docs/implementation-status.md under a new section ## Infrastructure:
- PHP container runs as appuser (UID/GID 1000), matching host developer user
- Build ARGs UID + GID passed via docker-compose.yml from shell environment
Mark Docker / Prompt D1 as complete.
```

## Bug Fixes & Feature Gaps — Session 2026-03-28

### Prompt BF1 — Bug Fixes & Feature Gaps (7 issues)

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: All Phase 1–6 prompts completed.

### TASK
Address the following 7 issues in the calendar booking system:

---

#### 1. Dayslot — No time required
When creating a slot with type='day', do NOT require or save time values.
- In SlotDTO: if type='day', coerce startAt and endAt to midnight (00:00:00) before persisting.
- In the agent slot creation form (templates/agent/calendar/show.html.twig): when type='day' is selected, hide the time part of the datetime inputs (use date-only input or hide the time picker via JS).
- In the public calendar view (templates/public/calendar/show.html.twig): for day slots, display only the date (not the time).

---

#### 2. Dayslot spanning multiple days — Individual day booking
When a Slot has type='day' and its startAt–endAt spans multiple calendar days, customers must be able to book individual days within that range (not only the full span).

Implementation:
- In Public\CalendarController GET /c/{token}: for each open day-type Slot where startAt != endAt, expand it into virtual day entries (one per day in the range). Pass these to the template as bookable units — each unit carries the original slotId plus a selectedDate (Y-m-d).
- In BookingRequestDTO: add optional selectedDate (nullable DateTimeImmutable).
- In BookingService::createRequest(): if the slot is type='day' and a selectedDate is provided, store it on the BookingRequest (add selectedDate nullable DateTimeImmutable field to BookingRequest entity).
- In the public booking form: include a hidden selectedDate field when booking an individual day.
- Generate Doctrine migration for the new BookingRequest.selectedDate field.

---

#### 3. Agent view — Show accepted and declined bookings
In templates/agent/booking/index.html.twig:
- The table currently only shows pending bookings (or hides accepted/declined). Update it to show ALL booking requests regardless of status.
- Group or visually separate: Pending (yellow badge) / Accepted (green badge) / Declined (red badge).
- For accepted/declined rows, do NOT show Accept/Decline action buttons.
- BookingRequestRepository::findByAgent() must return all statuses — verify it has no status filter; if it does, remove it.

---

#### 4. Calendar/slot editing — Agent can edit unless bookings accepted
Agents must be able to edit calendar name/displayMode and delete open slots, subject to these rules:
- A calendar can be edited (name, displayMode) as long as it has no accepted BookingRequests.
- A slot can be deleted if its status is NOT 'booked'.

Add the following:
- PATCH /agent/calendars/{id} → update name and/or displayMode; throws DomainException (flash error) if any accepted BookingRequest exists for any slot in this calendar.
- DELETE /agent/calendars/{id}/slots/{slotId} → remove the slot unless status='booked' (403/flash error if booked). Use _method=DELETE override.
- In CalendarService (create if needed) or directly in controller: add canEditCalendar(Calendar $calendar): bool — checks BookingRequestRepository for any accepted request on any slot belonging to this calendar.
- Update templates/agent/calendar/show.html.twig:
  - Add edit form for calendar name + displayMode (disabled if canEdit=false, with explanatory note).
  - Add delete button per slot row (hidden if slot.status='booked').
- Update docs.

---

#### 5. Public calendar view — Show full slot timeframe
In templates/public/calendar/show.html.twig:
- For time-type slots: display the full range "HH:MM – HH:MM" (startAt to endAt), not only startAt.
- For day-type multi-day slots expanded into individual days (see issue 2): show the individual date.
- For day-type single-day slots: show just the date.
Ensure the Twig template renders endAt wherever a time slot is displayed.

---

#### 6. Client view — Show calendars added by agent
Currently Client\CalendarController::findByClient() only finds calendars where the client is set. Verify that Agent\CalendarController when creating a calendar assigns the current authenticated user as agent and the selected clientId as client — this should already work.

If the client dashboard (GET /client/calendar) returns 404 when the agent has created the calendar (because CalendarRepository::findByClient() is broken or uses the wrong field), fix the repository method:
- CalendarRepository::findByClient(User $client): ?Calendar must query WHERE calendar.client = :client ORDER BY createdAt DESC, LIMIT 1.
- Ensure the agent calendar creation (POST /agent/calendars) correctly sets calendar.client = the User with id=clientId (load user via UserRepository).
- If currently setting client incorrectly (e.g. setting agent as client), fix it.

---

#### 7. Unavailabilities must block booking requests
Bug: slots whose day falls within an unavailability period still show as 'open' and accept bookings.
Root cause: The database shows Unavailability.startAt = 2026-04-16 00:00:00 and endAt = 2026-04-16 00:00:00 (same timestamp), meaning a single-day unavailability is stored with identical start and end, causing range overlap queries to miss it.

Fix in two places:
a) UnavailabilityService::markUnavailable(): when startAt == endAt (same date, single-day), set endAt to startAt + 23:59:59 (i.e., end of that day) before persisting and before running the slot overlap query. Alternatively set endAt to start of next day (startAt + 1 day at 00:00:00).
b) UnavailabilityDTO / Client\CalendarController: when the client submits a single-day unavailability (startAt date == endAt date), normalise endAt to end-of-day (23:59:59) or next-day 00:00:00 before passing to the service.
c) SlotRepository::findByCalendarAndDateRange(): ensure the overlap query uses a proper range comparison:
   slot.startAt < :to AND slot.endAt > :from
   (not <=/>= which misses zero-length ranges). Adjust if currently using wrong operators.
d) After fix, re-run UnavailabilityService for any existing broken records (or provide a migration/command to normalise existing endAt=startAt rows by setting endAt = startAt + 1 day).

### UPDATE DOCS
Append to docs/implementation-status.md under a new section ## Bug Fixes & Feature Gaps:
- BF1.1: Dayslot no-time enforcement in DTO + form + public view
- BF1.2: Multi-day dayslot expanded to individual bookable days
- BF1.3: Agent booking list shows all statuses (pending/accepted/declined)
- BF1.4: Calendar/slot editing with accepted-booking guard
- BF1.5: Public calendar shows full time range (startAt–endAt)
- BF1.6: Client view correctly loads agent-created calendars
- BF1.7: Unavailability normalisation fix (same-day start=end bug) + slot overlap query fix
Mark Bug Fixes / Prompt BF1 as complete.
```

---
## Feature Requests — Session 2026-03-28 (Continued)

### Prompt BF2 — Partial Dayslot Unavailability & CalendarBundle Migration

```
### CONTEXT
Read docs/implementation-status.md and .github/copilot-instructions.md before starting.
Do not re-create anything already listed as completed.
Depends on: All Phase 1–6 + BF1 prompts completed.

### TASK
Address the following 2 requirements:

---

#### 1. Partial Unavailability — Block Only Affected Days Within a Dayslot

**Problem:**
When a client sets an unavailability that covers only a subset of a multi-day slot period,
the entire slot is currently set to 'overridden', blocking all days — including days
not covered by the unavailability.

**Required behaviour:**
Only the individual days that overlap with the unavailability period should be
blocked for booking. Days within the slot that fall outside the unavailability range
must remain bookable.

**Implementation:**

a) Do NOT change the slot status to 'overridden' for day-type slots when only a
   subset of the period is affected. Instead, track blocked days at a finer granularity.

b) Create a new entity `SlotUnavailability` (src/Entity/SlotUnavailability.php):
   - id: int
   - slot: ManyToOne -> Slot, not null
   - unavailability: ManyToOne -> Unavailability, not null
   - blockedDate: DateTimeImmutable  (the specific day that is blocked)
   - Generate Doctrine migration.

c) Update `UnavailabilityService::markUnavailable()`:
   - For day-type Slots overlapping the unavailability range:
     -> Iterate over each day in the slot's startAt-endAt range.
     -> If the day falls within the unavailability's startAt-endAt range, create a
        `SlotUnavailability` record for that day (do NOT change slot status to 'overridden').
     -> If ALL days in the slot are covered by the unavailability, THEN set slot status
        to 'overridden' (full block, preserving existing behaviour).
   - For time-type Slots: keep existing behaviour (set status to 'overridden' if overlapping).
   - Single flush after all updates.

d) Add `SlotUnavailabilityRepository` (src/Repository/SlotUnavailabilityRepository.php):
   - `findBlockedDatesForSlot(Slot $slot): array` — returns array of blocked DateTimeImmutable dates
   - `isDateBlockedForSlot(Slot $slot, DateTimeImmutable $date): bool`

e) Update `Public\CalendarController` GET `/c/{token}`:
   - When expanding multi-day day-type slots into virtual per-day entries (BF1.2),
     exclude any day that has a `SlotUnavailability` record (use `isDateBlockedForSlot()`).
   - Days with a SlotUnavailability record must NOT appear as bookable units.

f) Update `BookingService::createRequest()`:
   - For day-type slots with a selectedDate: call `isDateBlockedForSlot()` and throw
     `\DomainException('This date is not available for booking')` if blocked.

g) Update templates/client/calendar/show.html.twig:
   - In the unavailability table, add a column "Affected Days" that lists the specific
     blocked dates per SlotUnavailability record for each Unavailability (if any).

---

#### 2. Move Booking & Unavailability Logic into CalendarBundle

**Requirement:**
All booking request handling and unavailability logic must use the CalendarBundle
(src/CalendarBundle/ or equivalent bundle namespace) as the home for this logic.
No business logic for bookings or unavailabilities should live outside the bundle.

**Implementation:**

a) If `CalendarBundle` does not yet exist, create it:
   - `src/CalendarBundle/CalendarBundle.php` — standard Symfony bundle class
   - Register it in `config/bundles.php`

b) Move (or create if not yet in bundle) the following into the bundle:
   - Entities: `BookingRequest`, `Unavailability`, `SlotUnavailability` -> `src/CalendarBundle/Entity/`
   - Repositories: `BookingRequestRepository`, `UnavailabilityRepository`, `SlotUnavailabilityRepository` -> `src/CalendarBundle/Repository/`
   - Services: `BookingService`, `UnavailabilityService` -> `src/CalendarBundle/Service/`
   - Messages: `BookingRequestCreatedMessage` -> `src/CalendarBundle/Message/`
   - Message Handler: `BookingRequestCreatedHandler` -> `src/CalendarBundle/MessageHandler/`
   - DTOs: `BookingRequestDTO`, `UnavailabilityDTO` -> `src/CalendarBundle/Dto/`

c) Update all namespace references across the codebase:
   - Controllers (Public\CalendarController, Client\CalendarController, Agent\BookingController)
     must import from `App\CalendarBundle\*` (or the correct bundle namespace).
   - No class outside the bundle should directly instantiate bundle-internal services;
     use constructor injection throughout.

d) Update Doctrine mapping configuration if needed so Doctrine scans
   `src/CalendarBundle/Entity/` for entities.

e) Ensure all existing tests (if any) and routes continue to function after the move.

### UPDATE DOCS
Append to docs/implementation-status.md under a new section ## Bundle Refactor:
- BF2.1: Partial dayslot unavailability — SlotUnavailability entity tracks per-day blocks;
  UnavailabilityService iterates days; only fully-covered slots get 'overridden' status;
  Public controller and BookingService respect blocked dates
- BF2.2: CalendarBundle created; BookingRequest, Unavailability, SlotUnavailability entities,
  repositories, services, messages and handlers moved into src/CalendarBundle/
Mark Bundle Refactor / Prompt BF2 as complete.
```

---

## End of Archive

24 prompts total. To use: copy the prompt block into VS Code with Copilot Agent active.

