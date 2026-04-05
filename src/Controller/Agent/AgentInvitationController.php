<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Dto\InviteUserDTO;
use App\Entity\User;
use App\Service\InvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent')]
#[IsGranted('ROLE_AGENT')]
class AgentInvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {
    }

    #[Route('/invite-client', name: 'agent_invite_client', methods: ['GET'])]
    public function showForm(): Response
    {
        return $this->render('agent/invite_client.html.twig');
    }

    #[Route('/invite-client', name: 'agent_invite_client_post', methods: ['POST'])]
    public function invite(#[MapRequestPayload] InviteUserDTO $dto): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        try {
            $this->invitationService->createInvitation($dto->email, 'ROLE_CLIENT', $agent);
            $this->addFlash('success', 'Client invitation sent.');

            return $this->redirectToRoute('agent_calendar_list');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->render('agent/invite_client.html.twig');
        }
    }
}
