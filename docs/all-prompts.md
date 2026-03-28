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

## End of Archive

21 prompts total. To use: copy the prompt block into VS Code with Copilot Agent active.
