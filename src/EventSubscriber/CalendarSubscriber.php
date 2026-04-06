<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\CalendarBundle\Repository\UnavailabilityRepository;
use App\Repository\ActivityRepository;
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
        private readonly ActivityRepository $activityRepository,
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
        $activityIdsRaw = $filters['activityIds'] ?? [];
        $activityIds = [];
        if (is_array($activityIdsRaw)) {
            foreach ($activityIdsRaw as $rawId) {
                $id = is_scalar($rawId) ? (int) $rawId : 0;
                if ($id > 0) {
                    $activityIds[] = $id;
                }
            }
        }

        if ($token === null) {
            return;
        }

        $calendar = $this->calendarRepository->findByPublicToken($token);
        if ($calendar === null) {
            return;
        }

        $filterActivityIds = [];
        foreach ($activityIds as $id) {
            $act = $this->activityRepository->find($id);
            if ($act === null || $act->getCalendar()->getId() !== $calendar->getId()) {
                return;
            }
            $filterActivityIds[] = $id;
        }

        $startImmutable = \DateTimeImmutable::createFromMutable($start)->setTimezone(new \DateTimeZone('UTC'));
        $endImmutable = \DateTimeImmutable::createFromMutable($end)->setTimezone(new \DateTimeZone('UTC'));

        $slots = $this->slotRepository->findByCalendarAndDateRange($calendar, $startImmutable, $endImmutable);

        if ($filterActivityIds !== []) {
            $slots = array_values(array_filter(
                $slots,
                static fn($slot): bool => in_array($slot->getActivity()?->getId(), $filterActivityIds, true),
            ));
        }

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
                    $slotEnd = $slot->getType() === 'day'
                        ? null
                        : \DateTime::createFromImmutable($slot->getEndAt());
                    $event = new Event(
                        'Overridden',
                        \DateTime::createFromImmutable($slot->getStartAt()),
                        $slotEnd,
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

                    if ($blocked) {
                        if ($viewType === 'client') {
                            $event = new Event('Blocked', $dayStart, null, null, [
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
                        $event = new Event($title, $dayStart, null, null, [
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
            } elseif ($slot->getType() === 'time') {
                // Split time-type slots at day boundaries so each segment renders as a
                // timed block in dayGridMonth rather than as a spanning all-day bar.
                $title = $slot->getLocation() !== null ? ('📍 ' . $slot->getLocation()) : 'Available';
                $segmentStart = $slot->getStartAt();
                $slotEnd = $slot->getEndAt();
                while ($segmentStart < $slotEnd) {
                    $nextMidnight = $segmentStart->setTime(0, 0, 0)->modify('+1 day');
                    $segmentEnd = $nextMidnight < $slotEnd ? $nextMidnight : $slotEnd;
                    $event = new Event(
                        $title,
                        \DateTime::createFromImmutable($segmentStart),
                        \DateTime::createFromImmutable($segmentEnd),
                        null,
                        ['color' => '#2d6a4f', 'allDay' => false, 'extendedProps' => [
                            'status' => 'open',
                            'slotId' => $slot->getId(),
                            'type' => 'time',
                            'date' => $segmentStart->format('Y-m-d'),
                            'location' => $slot->getLocation(),
                            'continent' => $slot->getContinent(),
                            'timeRange' => $segmentStart->format('H:i') . ' – ' . $segmentEnd->format('H:i'),
                        ]],
                    );
                    $setDataEvent->addEvent($event);
                    $segmentStart = $nextMidnight;
                }
            } else {
                // day-type single-day slot
                $title = $slot->getLocation() !== null ? ('📍 ' . $slot->getLocation()) : 'Available';
                $event = new Event(
                    $title,
                    \DateTime::createFromImmutable($slot->getStartAt()),
                    null,
                    null,
                    [
                        'color' => '#2d6a4f',
                        'extendedProps' => [
                            'status' => 'open',
                            'slotId' => $slot->getId(),
                            'type' => 'day',
                            'date' => $slot->getStartAt()->format('Y-m-d'),
                            'location' => $slot->getLocation(),
                            'continent' => $slot->getContinent(),
                            'timeRange' => null,
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
