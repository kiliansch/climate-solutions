<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\InvitationCreatedMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class InvitationCreatedHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(InvitationCreatedMessage $message): void
    {
        $acceptUrl = $this->urlGenerator->generate(
            'app_invite_accept',
            ['token' => $message->token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->to($message->email)
            ->subject("You've been invited to the Calendar Booking System")
            ->htmlTemplate('emails/invitation.html.twig')
            ->textTemplate('emails/invitation.txt.twig')
            ->context([
                'acceptUrl' => $acceptUrl,
                'role' => $message->role,
                'expiresInDays' => 7,
            ]);

        $this->mailer->send($email);
    }
}
