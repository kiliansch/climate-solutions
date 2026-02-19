<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use CalendarBundle\Entity\Event;
use CalendarBundle\Event\SetDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CalendarSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SetDataEvent::class => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(SetDataEvent $setDataEvent): void
    {
        $start = $setDataEvent->getStart();
        $end = $setDataEvent->getEnd();

        // TODO: replace with a real Doctrine query, e.g.:
        //   $events = $this->solutionRepository->findByDateRange($start, $end);
        //   foreach ($events as $solution) { ... }

        // Example placeholder events
        $setDataEvent->addEvent(new Event(
            'Climate Summit',
            new \DateTime('Monday this week 09:00'),
            new \DateTime('Monday this week 17:00'),
            null,
            ['color' => '#2d6a4f']
        ));

        $setDataEvent->addEvent(new Event(
            'Tree Planting Drive',
            new \DateTime('Wednesday this week')
        ));

        $setDataEvent->addEvent(new Event(
            'Renewable Energy Workshop',
            new \DateTime('Friday this week 14:00'),
            new \DateTime('Friday this week 16:00'),
            null,
            ['color' => '#1b4332']
        ));
    }
}
