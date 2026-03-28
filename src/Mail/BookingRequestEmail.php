<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\BookingRequest;
use Symfony\Component\Mime\Email;

final class BookingRequestEmail extends Email
{
    public function __construct(BookingRequest $bookingRequest)
    {
        parent::__construct();

        $slot = $bookingRequest->getSlot();
        $agent = $slot->getCalendar()->getAgent();

        $this
            ->to($agent->getEmail())
            ->subject('New booking request received')
            ->text(sprintf(
                "Hello %s,\n\nYou have received a new booking request from %s (%s)."
                . "\n\nSlot: %s – %s\n\nMessage: %s\n\nPlease log in to review and respond.",
                $agent->getName(),
                $bookingRequest->getCustomerName(),
                $bookingRequest->getCustomerEmail(),
                $slot->getStartAt()->format('Y-m-d H:i'),
                $slot->getEndAt()->format('Y-m-d H:i'),
                $bookingRequest->getMessage() ?? '(no message)',
            ));
    }
}
