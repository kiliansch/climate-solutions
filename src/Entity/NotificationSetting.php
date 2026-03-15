<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationSettingRepository::class)]
#[ORM\Table(name: 'notification_settings')]
class NotificationSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $emailEnabled = true;

    #[ORM\Column]
    private bool $inAppEnabled = true;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;

        return $this;
    }

    public function isInAppEnabled(): bool
    {
        return $this->inAppEnabled;
    }

    public function setInAppEnabled(bool $inAppEnabled): static
    {
        $this->inAppEnabled = $inAppEnabled;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
