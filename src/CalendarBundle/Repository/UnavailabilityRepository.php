<?php

declare(strict_types=1);

namespace App\CalendarBundle\Repository;

use App\CalendarBundle\Entity\Unavailability;
use App\Entity\Calendar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unavailability>
 */
class UnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unavailability::class);
    }

    /**
     * @return Unavailability[]
     */
    public function findByCalendar(Calendar $calendar): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.calendar = :calendar')
            ->setParameter('calendar', $calendar)
            ->orderBy('u.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
