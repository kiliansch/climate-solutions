<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistrationDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function registerAgent(RegistrationDTO $dto): User
    {
        if ($this->userRepository->findOneByEmail($dto->email) !== null) {
            throw new \DomainException('Email already in use');
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setName($dto->name);
        $user->setRoles(['ROLE_AGENT']);
        $user->setStatus('active');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
