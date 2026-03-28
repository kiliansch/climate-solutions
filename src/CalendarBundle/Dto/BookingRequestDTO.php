<?php

declare(strict_types=1);

namespace App\CalendarBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class BookingRequestDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $customerName = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public readonly string $customerEmail = '',
        public readonly ?string $message = null,
        public readonly ?\DateTimeImmutable $selectedDate = null,
    ) {
    }
}
