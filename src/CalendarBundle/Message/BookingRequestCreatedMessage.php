<?php

declare(strict_types=1);

namespace App\CalendarBundle\Message;

final readonly class BookingRequestCreatedMessage
{
    public function __construct(
        public int $bookingRequestId,
    ) {
    }
}
