<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Entity\User;
use App\Service\InvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/agent')]
#[IsGranted('ROLE_AGENT')]
class InviteClientController extends AbstractController
{
    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/invite-client', name: 'agent_invite_client', methods: ['GET', 'POST'])]
    public function invite(Request $request): Response
    {
        $calendarId = $request->query->getInt('calendarId') ?: null;

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $calendarId = $request->request->getInt('calendarId') ?: null;

            $violations = $this->validator->validate($email, [
                new Assert\NotBlank(),
                new Assert\Email(),
            ]);

            if (count($violations) > 0) {
                $this->addFlash('error', (string) $violations->get(0)->getMessage());

                return $this->render('agent/invite_client.html.twig', [
                    'calendarId' => $calendarId,
                    'prefillEmail' => $email,
                ]);
            }

            /** @var User $agent */
            $agent = $this->getUser();

            try {
                $this->invitationService->createInvitation($email, 'ROLE_CLIENT', $agent);
                $this->addFlash('success', sprintf('Invitation sent to %s.', $email));
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            if ($calendarId !== null) {
                return $this->redirectToRoute('agent_calendar_show', ['id' => $calendarId]);
            }

            return $this->redirectToRoute('agent_calendar_list');
        }

        return $this->render('agent/invite_client.html.twig', [
            'calendarId' => $calendarId,
        ]);
    }
}
