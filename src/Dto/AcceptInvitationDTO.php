<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AcceptInvitationDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public readonly string $password = '',
    ) {
    }
}
