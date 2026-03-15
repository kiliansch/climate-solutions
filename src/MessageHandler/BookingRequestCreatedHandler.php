<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Mail\BookingRequestEmail;
use App\Message\BookingRequestCreatedMessage;
use App\Repository\BookingRequestRepository;
use App\Repository\NotificationSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingRequestCreatedHandler
{
    public function __construct(
        private readonly BookingRequestRepository $bookingRequestRepository,
        private readonly NotificationSettingRepository $notificationSettingRepository,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(BookingRequestCreatedMessage $message): void
    {
        $bookingRequest = $this->bookingRequestRepository->find($message->bookingRequestId);

        if ($bookingRequest === null) {
            return;
        }

        $agent = $bookingRequest->getSlot()->getCalendar()->getAgent();
        $settings = $this->notificationSettingRepository->findByUser($agent);

        $emailEnabled = $settings !== null ? $settings->isEmailEnabled() : true;
        $inAppEnabled = $settings !== null ? $settings->isInAppEnabled() : true;

        if ($emailEnabled) {
            $this->mailer->send(new BookingRequestEmail($bookingRequest));
        }

        if ($inAppEnabled) {
            $notification = new Notification();
            $notification->setUser($agent);
            $notification->setMessage(sprintf(
                'New booking request from %s (%s) for slot on %s.',
                $bookingRequest->getCustomerName(),
                $bookingRequest->getCustomerEmail(),
                $bookingRequest->getSlot()->getStartAt()->format('Y-m-d H:i'),
            ));
            $this->entityManager->persist($notification);
            $this->entityManager->flush();
        }
    }
}
