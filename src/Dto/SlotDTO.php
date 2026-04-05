<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'this.startAt < this.endAt',
    message: 'startAt must be before endAt.',
)]
#[Assert\When(
    expression: 'this.allowChunkedBooking === true',
    constraints: [new Assert\Expression(
        expression: 'this.chunkCooldownMinutes in [30, 60, 90, 120, 150, 180]',
        message: 'chunkCooldownMinutes must be one of [30, 60, 90, 120, 150, 180] when chunked booking is enabled.',
    )],
)]
#[Assert\When(
    expression: '!this.allowChunkedBooking',
    constraints: [new Assert\Expression(
        expression: 'this.chunkCooldownMinutes === null',
        message: 'chunkCooldownMinutes must be null when chunked booking is disabled.',
    )],
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
        #[Assert\Choice(choices: ['Europe'])]
        public readonly ?string $continent = null,
        public readonly bool $allowChunkedBooking = false,
        public readonly ?int $chunkCooldownMinutes = null,
    ) {
    }
}
