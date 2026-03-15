<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Calendar;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Calendar>
 */
class CalendarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calendar::class);
    }

    /**
     * @return Calendar[]
     */
    public function findByAgent(User $agent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPublicToken(string $token): ?Calendar
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.publicToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
