<?php

declare(strict_types=1);

namespace App\CalendarBundle\Service;

use App\CalendarBundle\Entity\SlotUnavailability;
use App\CalendarBundle\Entity\Unavailability;
use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\Entity\Calendar;
use App\Entity\User;
use App\Repository\SlotRepository;
use Doctrine\ORM\EntityManagerInterface;

class UnavailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SlotRepository $slotRepository,
        private readonly SlotUnavailabilityRepository $slotUnavailabilityRepository,
    ) {
    }

    public function markUnavailable(
        Calendar $calendar,
        User $client,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $reason = null,
    ): void {
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            $end = $end->setTime(23, 59, 59);
        }

        $unavailability = new Unavailability();
        $unavailability->setCalendar($calendar);
        $unavailability->setClient($client);
        $unavailability->setStartAt($start);
        $unavailability->setEndAt($end);
        $unavailability->setReason($reason);

        $this->entityManager->persist($unavailability);

        $slots = $this->slotRepository->findByCalendarAndDateRange($calendar, $start, $end);

        foreach ($slots as $slot) {
            if ($slot->getStatus() !== 'open') {
                continue;
            }

            if ($slot->getType() === 'day') {
                $this->handleDaySlotUnavailability($slot, $unavailability, $start, $end);
            } else {
                $slot->setStatus('overridden');
            }
        }

        $this->entityManager->flush();
    }

    private function handleDaySlotUnavailability(
        \App\Entity\Slot $slot,
        Unavailability $unavailability,
        \DateTimeImmutable $unavailStart,
        \DateTimeImmutable $unavailEnd,
    ): void {
        $slotStart = $slot->getStartAt()->setTime(0, 0, 0);
        $slotEnd = $slot->getEndAt()->setTime(0, 0, 0);

        $existingDates = $this->slotUnavailabilityRepository->findBlockedDatesForSlot($slot);
        $existingDateSet = array_flip(array_map(
            static fn(\DateTimeImmutable $d): string => $d->format('Y-m-d'),
            $existingDates,
        ));

        $current = $slotStart;
        $totalDays = 0;
        $blockedDays = 0;

        while ($current <= $slotEnd) {
            $totalDays++;

            $dayStart = $current->setTime(0, 0, 0);
            $dayEnd = $current->setTime(23, 59, 59);

            if ($dayStart <= $unavailEnd && $dayEnd >= $unavailStart) {
                $blockedDays++;

                if (!isset($existingDateSet[$current->format('Y-m-d')])) {
                    $slotUnavailability = new SlotUnavailability();
                    $slotUnavailability->setSlot($slot);
                    $slotUnavailability->setUnavailability($unavailability);
                    $slotUnavailability->setBlockedDate($current->setTime(0, 0, 0));
                    $this->entityManager->persist($slotUnavailability);
                }
            }

            $current = $current->modify('+1 day');
        }

        if ($blockedDays === $totalDays) {
            $slot->setStatus('overridden');
        }
    }
}
