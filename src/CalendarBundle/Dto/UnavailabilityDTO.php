<?php

declare(strict_types=1);

namespace App\CalendarBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'this.startAt <= this.endAt',
    message: 'endAt must be on or after startAt.',
)]
final class UnavailabilityDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly \DateTimeImmutable $startAt = new \DateTimeImmutable(),
        #[Assert\NotBlank]
        public readonly \DateTimeImmutable $endAt = new \DateTimeImmutable(),
        public readonly ?string $reason = null,
    ) {
    }
}
