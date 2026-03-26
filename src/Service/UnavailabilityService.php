<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Calendar;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Repository\SlotRepository;
use Doctrine\ORM\EntityManagerInterface;

class UnavailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SlotRepository $slotRepository,
    ) {}

    public function markUnavailable(
        Calendar $calendar,
        User $client,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $reason = null,
    ): void {
        $unavailability = new Unavailability();
        $unavailability->setCalendar($calendar);
        $unavailability->setClient($client);
        $unavailability->setStartAt($start);
        $unavailability->setEndAt($end);
        $unavailability->setReason($reason);

        $this->entityManager->persist($unavailability);

        $slots = $this->slotRepository->findByCalendarAndDateRange($calendar, $start, $end);

        foreach ($slots as $slot) {
            if ($slot->getStatus() === 'open') {
                $slot->setStatus('overridden');
            }
        }

        $this->entityManager->flush();
    }
}
