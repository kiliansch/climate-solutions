<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Calendar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * @return Activity[]
     */
    public function findByCalendar(Calendar $calendar): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.calendar = :calendar')
            ->setParameter('calendar', $calendar)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPublicToken(string $token): ?Activity
    {
        return $this->findOneBy(['publicToken' => $token]);
    }
}
