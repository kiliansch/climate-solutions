<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BookingRequest;
use App\Entity\Slot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingRequest>
 */
class BookingRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingRequest::class);
    }

    /**
     * @return BookingRequest[]
     */
    public function findPendingBySlot(Slot $slot): array
    {
        return $this->createQueryBuilder('br')
            ->andWhere('br.slot = :slot')
            ->andWhere('br.status = :status')
            ->setParameter('slot', $slot)
            ->setParameter('status', 'pending')
            ->orderBy('br.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return BookingRequest[]
     */
    public function findByAgent(User $agent): array
    {
        return $this->createQueryBuilder('br')
            ->join('br.slot', 's')
            ->join('s.calendar', 'c')
            ->andWhere('c.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('br.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
