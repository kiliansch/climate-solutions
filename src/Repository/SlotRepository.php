<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Calendar;
use App\Entity\Slot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Slot>
 */
class SlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Slot::class);
    }

    /**
     * @return Slot[]
     */
    public function findOpenByCalendar(Calendar $calendar): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.calendar = :calendar')
            ->andWhere('s.status = :status')
            ->setParameter('calendar', $calendar)
            ->setParameter('status', 'open')
            ->orderBy('s.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasOverriddenSlots(Calendar $calendar): bool
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.calendar = :calendar')
            ->andWhere('s.status = :status')
            ->setParameter('calendar', $calendar)
            ->setParameter('status', 'overridden')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return Slot[]
     */
    public function findByCalendarAndDateRange(
        Calendar $calendar,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('s')
            ->andWhere('s.calendar = :calendar')
            ->andWhere('s.startAt < :to')
            ->andWhere('s.endAt > :from')
            ->setParameter('calendar', $calendar)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
