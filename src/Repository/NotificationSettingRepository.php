<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationSetting;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationSetting>
 */
class NotificationSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationSetting::class);
    }

    public function findByUser(User $user): ?NotificationSetting
    {
        return $this->findOneBy(['user' => $user]);
    }
}
