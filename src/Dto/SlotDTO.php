<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'this.startAt < this.endAt',
    message: 'startAt must be before endAt.',
)]
final class SlotDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['day', 'time'])]
        public readonly string $type = '',
        #[Assert\NotNull]
        public readonly \DateTimeImmutable $startAt = new \DateTimeImmutable(),
        #[Assert\NotNull]
        public readonly \DateTimeImmutable $endAt = new \DateTimeImmutable(),
        #[Assert\Length(max: 255)]
        public readonly ?string $location = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $continent = null,
    ) {
    }
}
