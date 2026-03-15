<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Dto\AcceptInvitationDTO;
use App\Service\InvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class InvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {
    }

    #[Route('/invite/accept/{token}', name: 'app_invite_accept', methods: ['GET'])]
    public function showAcceptForm(string $token): Response
    {
        return $this->render('auth/invite_accept.html.twig', [
            'token' => $token,
        ]);
    }

    #[Route('/invite/accept/{token}', name: 'app_invite_accept_post', methods: ['POST'])]
    public function acceptInvitation(
        string $token,
        #[MapRequestPayload] AcceptInvitationDTO $dto,
    ): Response {
        try {
            $this->invitationService->acceptInvitation($token, $dto->password);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_invite_accept', ['token' => $token]);
        }

        return $this->redirectToRoute('app_login');
    }
}
