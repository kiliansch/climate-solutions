<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\CalendarBundle\Repository\UnavailabilityRepository;
use App\Repository\CalendarRepository;
use App\Repository\SlotRepository;
use CalendarBundle\Entity\Event;
use CalendarBundle\Event\SetDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly SlotRepository $slotRepository,
        private readonly UnavailabilityRepository $unavailabilityRepository,
        private readonly SlotUnavailabilityRepository $slotUnavailabilityRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SetDataEvent::class => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(SetDataEvent $setDataEvent): void
    {
        $filters = $setDataEvent->getFilters();
        $start = $setDataEvent->getStart();
        $end = $setDataEvent->getEnd();

        $token = is_string($filters['token'] ?? null) ? $filters['token'] : null;
        $viewType = is_string($filters['viewType'] ?? null) ? $filters['viewType'] : null;

        if ($token === null) {
            return;
        }

        $calendar = $this->calendarRepository->findByPublicToken($token);
        if ($calendar === null) {
            return;
        }

        $startImmutable = \DateTimeImmutable::createFromMutable($start);
        $endImmutable = \DateTimeImmutable::createFromMutable($end);

        $slots = $this->slotRepository->findByCalendarAndDateRange($calendar, $startImmutable, $endImmutable);

        // Preload blocked dates for all open multi-day day-slots in a single query
        $multiDayOpenSlots = array_values(array_filter(
            $slots,
            static fn($slot): bool => $slot->getStatus() === 'open'
                && $slot->getType() === 'day'
                && $slot->getStartAt()->format('Y-m-d') !== $slot->getEndAt()->format('Y-m-d'),
        ));
        $blockedDateMap = $this->slotUnavailabilityRepository->findBlockedDateSetBySlots($multiDayOpenSlots);

        foreach ($slots as $slot) {
            if ($slot->getStatus() !== 'open') {
                if ($viewType === 'client' && $slot->getStatus() === 'overridden') {
                    $event = new Event(
                        'Overridden',
                        \DateTime::createFromImmutable($slot->getStartAt()),
                        \DateTime::createFromImmutable($slot->getEndAt()),
                        null,
                        [
                            'color' => '#9ca3af',
                            'extendedProps' => ['status' => 'overridden', 'slotId' => $slot->getId()],
                        ],
                    );
                    $setDataEvent->addEvent($event);
                }
                continue;
            }

            if (
                $slot->getType() === 'day'
                && $slot->getStartAt()->format('Y-m-d') !== $slot->getEndAt()->format('Y-m-d')
            ) {
                $current = $slot->getStartAt();
                $slotEnd = $slot->getEndAt();
                while ($current <= $slotEnd) {
                    $dayDate = $current->setTime(0, 0, 0);
                    $blocked = isset($blockedDateMap[$slot->getId()][$dayDate->format('Y-m-d')]);

                    $dayStart = \DateTime::createFromImmutable($current->setTime(0, 0, 0));
                    $dayEnd = \DateTime::createFromImmutable($current->setTime(23, 59, 59));

                    if ($blocked) {
                        if ($viewType === 'client') {
                            $event = new Event('Blocked', $dayStart, $dayEnd, null, [
                                'color' => '#fbbf24',
                                'textColor' => '#92400e',
                                'extendedProps' => [
                                    'status' => 'blocked',
                                    'slotId' => $slot->getId(),
                                    'date' => $current->format('Y-m-d'),
                                ],
                            ]);
                            $setDataEvent->addEvent($event);
                        }
                    } else {
                        $title = $slot->getLocation() !== null ? ('📍 ' . $slot->getLocation()) : 'Available';
                        $event = new Event($title, $dayStart, $dayEnd, null, [
                            'color' => '#2d6a4f',
                            'extendedProps' => [
                                'status' => 'open',
                                'slotId' => $slot->getId(),
                                'type' => 'day',
                                'date' => $current->format('Y-m-d'),
                                'location' => $slot->getLocation(),
                                'continent' => $slot->getContinent(),
                            ],
                        ]);
                        $setDataEvent->addEvent($event);
                    }
                    $current = $current->modify('+1 day');
                }
            } else {
                $allDay = $slot->getType() === 'day';
                $title = $slot->getLocation() !== null ? ('📍 ' . $slot->getLocation()) : 'Available';
                $event = new Event(
                    $title,
                    \DateTime::createFromImmutable($slot->getStartAt()),
                    $allDay ? null : \DateTime::createFromImmutable($slot->getEndAt()),
                    null,
                    [
                        'color' => '#2d6a4f',
                        'extendedProps' => [
                            'status' => 'open',
                            'slotId' => $slot->getId(),
                            'type' => $slot->getType(),
                            'date' => $slot->getStartAt()->format('Y-m-d'),
                            'location' => $slot->getLocation(),
                            'continent' => $slot->getContinent(),
                            'timeRange' => $slot->getType() === 'time'
                                ? ($slot->getStartAt()->format('H:i') . ' – ' . $slot->getEndAt()->format('H:i'))
                                : null,
                        ],
                    ],
                );
                $setDataEvent->addEvent($event);
            }
        }

        if ($viewType === 'client') {
            $unavailabilities = $this->unavailabilityRepository->findByCalendar($calendar);
            foreach ($unavailabilities as $unavailability) {
                $uStart = $unavailability->getStartAt();
                $uEnd = $unavailability->getEndAt();
                if ($uStart > $endImmutable || $uEnd < $startImmutable) {
                    continue;
                }
                $event = new Event(
                    $unavailability->getReason() ?? 'Unavailable',
                    \DateTime::createFromImmutable($uStart),
                    \DateTime::createFromImmutable($uEnd),
                    null,
                    [
                        'color' => '#dc2626',
                        'display' => 'background',
                        'extendedProps' => ['type' => 'unavailability'],
                    ],
                );
                $setDataEvent->addEvent($event);
            }
        }
    }
}
