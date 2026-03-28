<?php

declare(strict_types=1);

namespace App\CalendarBundle\Repository;

use App\CalendarBundle\Entity\SlotUnavailability;
use App\Entity\Slot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SlotUnavailability>
 */
class SlotUnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SlotUnavailability::class);
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public function findBlockedDatesForSlot(Slot $slot): array
    {
        /** @var \DateTimeImmutable[] $results */
        $results = $this->createQueryBuilder('su')
            ->select('su.blockedDate')
            ->andWhere('su.slot = :slot')
            ->setParameter('slot', $slot)
            ->orderBy('su.blockedDate', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $results;
    }

    public function isDateBlockedForSlot(Slot $slot, \DateTimeImmutable $date): bool
    {
        return (int) $this->createQueryBuilder('su')
            ->select('COUNT(su.id)')
            ->andWhere('su.slot = :slot')
            ->andWhere('su.blockedDate = :date')
            ->setParameter('slot', $slot)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
