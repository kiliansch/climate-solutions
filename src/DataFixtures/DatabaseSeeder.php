<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\Calendar;
use App\Entity\Slot;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\CalendarRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsTaggedItem('app.seeder')]
class DatabaseSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly CalendarRepository $calendarRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function seed(): void
    {
        $tz = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable('now', $tz);

        // a. Admin user
        $this->upsertUser('admin@example.com', 'password', 'Admin User', ['ROLE_ADMIN']);

        // b. Agent users
        $agent1 = $this->upsertUser('agent1@example.com', 'password', 'Alice Agent', ['ROLE_AGENT']);
        $agent2 = $this->upsertUser('agent2@example.com', 'password', 'Bob Agent', ['ROLE_AGENT']);

        // c. Client users
        $client1 = $this->upsertUser('client1@example.com', 'password', 'Carol Client', ['ROLE_CLIENT']);
        $client2 = $this->upsertUser('client2@example.com', 'password', 'Dave Client', ['ROLE_CLIENT']);

        // d. Calendars
        $calendar1 = $this->findOrCreateCalendar("Alice's Calendar", 'dayslot', $agent1, $client1);
        $calendar2 = $this->findOrCreateCalendar("Bob's Calendar", 'timeslot', $agent2, $client2);

        // e. Slots for calendar 1 (2 day + 1 overnight-time)
        if (!$this->hasSlots($calendar1)) {
            // Multi-day block in Berlin
            $this->addSlot(
                $calendar1,
                'day',
                $now->modify('+5 days')->setTime(0, 0, 0),
                $now->modify('+6 days')->setTime(23, 59, 59),
                'Berlin',
            );
            // Daytime session in Paris
            $this->addSlot(
                $calendar1,
                'time',
                $now->modify('+10 days')->setTime(9, 0, 0),
                $now->modify('+10 days')->setTime(11, 0, 0),
                'Paris',
            );
            // Multi-day block in Amsterdam
            $this->addSlot(
                $calendar1,
                'day',
                $now->modify('+20 days')->setTime(0, 0, 0),
                $now->modify('+21 days')->setTime(23, 59, 59),
                'Amsterdam',
            );
        }

        // e. Slots for calendar 2 (1 day + 1 time + 1 overnight-time carry-over)
        if (!$this->hasSlots($calendar2)) {
            // Afternoon session in London
            $this->addSlot(
                $calendar2,
                'time',
                $now->modify('+7 days')->setTime(14, 0, 0),
                $now->modify('+7 days')->setTime(16, 0, 0),
                'London',
            );
            // Multi-day block in Rome
            $this->addSlot(
                $calendar2,
                'day',
                $now->modify('+15 days')->setTime(0, 0, 0),
                $now->modify('+16 days')->setTime(23, 59, 59),
                'Rome',
            );
            // Overnight carry-over: starts late evening, ends early next morning
            $this->addSlot(
                $calendar2,
                'time',
                $now->modify('+25 days')->setTime(23, 0, 0),
                $now->modify('+26 days')->setTime(2, 0, 0),
                'Madrid',
            );
        }

        // g. Solo agent with client-less calendar and activities
        $soloAgent = $this->upsertUser('solo@example.com', 'password', 'Solo Agent', ['ROLE_SOLO_AGENT']);
        $soloCalendar = $this->findOrCreateSoloCalendar('Solo Calendar', 'timeslot', $soloAgent);

        [$actConsultation, $actWorkshop] = $this->ensureActivities($soloCalendar, ['Consultation', 'Workshop']);

        if (!$this->hasSlots($soloCalendar)) {
            $this->addSlotWithActivity(
                $soloCalendar,
                'time',
                $now->modify('+8 days')->setTime(10, 0, 0),
                $now->modify('+8 days')->setTime(11, 30, 0),
                'Brussels',
                $actConsultation,
            );
            $this->addSlotWithActivity(
                $soloCalendar,
                'time',
                $now->modify('+12 days')->setTime(14, 0, 0),
                $now->modify('+12 days')->setTime(17, 0, 0),
                'Amsterdam',
                $actWorkshop,
            );
        }

        // h. Single flush at the end
        $this->entityManager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function upsertUser(string $email, string $plainPassword, string $name, array $roles): User
    {
        $existing = $this->userRepository->findOneByEmail($email);
        if ($existing !== null) {
            return $existing;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles($roles);
        $user->setStatus('active');
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);

        return $user;
    }

    private function findOrCreateCalendar(string $name, string $displayMode, User $agent, User $client): Calendar
    {
        $existing = $this->calendarRepository->findOneBy(['agent' => $agent, 'client' => $client]);
        if ($existing !== null) {
            return $existing;
        }

        $calendar = new Calendar();
        $calendar->setName($name);
        $calendar->setDisplayMode($displayMode);
        $calendar->setAgent($agent);
        $calendar->setClient($client);

        $this->entityManager->persist($calendar);

        return $calendar;
    }

    private function hasSlots(Calendar $calendar): bool
    {
        if ($calendar->getId() === null) {
            return false;
        }

        $result = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Slot::class, 's')
            ->where('s.calendar = :calendar')
            ->setParameter('calendar', $calendar)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    private function addSlot(
        Calendar $calendar,
        string $type,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        string $location,
    ): void {
        $slot = new Slot();
        $slot->setType($type);
        $slot->setStartAt($startAt);
        $slot->setEndAt($endAt);
        $slot->setStatus('open');
        $slot->setLocation($location);
        $slot->setContinent('Europe');
        $slot->setCalendar($calendar);

        $this->entityManager->persist($slot);
    }

    private function findOrCreateSoloCalendar(string $name, string $displayMode, User $agent): Calendar
    {
        $existing = $this->calendarRepository->findOneBy(['agent' => $agent, 'client' => null]);
        if ($existing !== null) {
            return $existing;
        }

        $calendar = new Calendar();
        $calendar->setName($name);
        $calendar->setDisplayMode($displayMode);
        $calendar->setAgent($agent);
        $calendar->setClient(null);

        $this->entityManager->persist($calendar);

        return $calendar;
    }

    /**
     * @param list<string> $names
     * @return list<Activity>
     */
    private function ensureActivities(Calendar $calendar, array $names): array
    {
        $existing = [];
        if ($calendar->getId() !== null) {
            foreach ($this->activityRepository->findByCalendar($calendar) as $act) {
                $existing[$act->getName()] = $act;
            }
        }

        $result = [];
        foreach ($names as $name) {
            if (isset($existing[$name])) {
                $result[] = $existing[$name];
            } else {
                $activity = new Activity();
                $activity->setName($name);
                $activity->setCalendar($calendar);
                $this->entityManager->persist($activity);
                $result[] = $activity;
            }
        }

        return $result;
    }

    private function addSlotWithActivity(
        Calendar $calendar,
        string $type,
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        string $location,
        Activity $activity,
    ): void {
        $slot = new Slot();
        $slot->setType($type);
        $slot->setStartAt($startAt);
        $slot->setEndAt($endAt);
        $slot->setStatus('open');
        $slot->setLocation($location);
        $slot->setContinent('Europe');
        $slot->setCalendar($calendar);
        $slot->setActivity($activity);

        $this->entityManager->persist($slot);
    }
}
