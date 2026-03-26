<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $name = '',

        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public readonly string $password = '',
    ) {
    }
}
