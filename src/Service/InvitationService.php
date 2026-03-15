<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\User;
use App\Message\InvitationCreatedMessage;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class InvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationRepository $invitationRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function createInvitation(string $email, string $role, User $invitedBy): Invitation
    {
        $invitation = new Invitation();
        $invitation->setEmail($email);
        $invitation->setToken(Uuid::v4()->toRfc4122());
        $invitation->setRole($role);
        $invitation->setInvitedBy($invitedBy);
        $invitation->setExpiresAt(new \DateTimeImmutable('+7 days'));

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->bus->dispatch(new InvitationCreatedMessage(
            email: $invitation->getEmail(),
            token: $invitation->getToken(),
            role: $invitation->getRole(),
        ));

        return $invitation;
    }

    public function acceptInvitation(string $token, string $plainPassword): User
    {
        $invitation = $this->invitationRepository->findByToken($token);

        if ($invitation === null) {
            throw new \InvalidArgumentException('Invitation not found.');
        }

        if ($invitation->isExpired()) {
            throw new \RuntimeException('Invitation has expired.');
        }

        if ($invitation->isAccepted()) {
            throw new \RuntimeException('Invitation has already been accepted.');
        }

        $user = new User();
        $user->setEmail($invitation->getEmail());
        $user->setName($invitation->getEmail());
        $user->setRoles([$invitation->getRole()]);
        $user->setInvitedBy($invitation->getInvitedBy());

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $invitation->setAcceptedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
