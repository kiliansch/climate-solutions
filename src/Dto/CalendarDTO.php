<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CalendarDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name = '',
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['timeslot', 'dayslot'])]
        public readonly string $displayMode = 'dayslot',
        #[Assert\NotNull]
        #[Assert\Positive]
        public readonly int $clientId = 0,
    ) {
    }
}
