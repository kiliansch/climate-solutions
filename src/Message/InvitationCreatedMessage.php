<?php

declare(strict_types=1);

namespace App\Message;

final readonly class InvitationCreatedMessage
{
    public function __construct(
        public string $email,
        public string $token,
        public string $role,
    ) {
    }
}
