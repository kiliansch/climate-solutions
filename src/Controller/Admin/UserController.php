<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\InviteUserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly InvitationService $invitationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/agents', name: 'admin_agent_list', methods: ['GET'])]
    public function listAgents(): Response
    {
        $agents = $this->userRepository->findByRole('ROLE_AGENT');

        return $this->render('admin/users/agents.html.twig', [
            'agents' => $agents,
        ]);
    }

    #[Route('/users/{id}/block', name: 'admin_user_block', methods: ['PATCH'])]
    public function block(User $user): RedirectResponse
    {
        $user->setStatus('blocked');
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User "%s" has been blocked.', $user->getEmail()));

        return $this->redirectToRoute('admin_agent_list');
    }

    #[Route('/users/{id}/unblock', name: 'admin_user_unblock', methods: ['PATCH'])]
    public function unblock(User $user): RedirectResponse
    {
        $user->setStatus('active');
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('User "%s" has been unblocked.', $user->getEmail()));

        return $this->redirectToRoute('admin_agent_list');
    }

    #[Route('/invite', name: 'admin_invite', methods: ['POST'])]
    public function invite(#[MapRequestPayload] InviteUserDTO $dto): RedirectResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $this->invitationService->createInvitation(
            email: $dto->email,
            role: 'ROLE_AGENT',
            invitedBy: $admin,
        );

        $this->addFlash('success', sprintf('Invitation sent to "%s".', $dto->email));

        return $this->redirectToRoute('admin_agent_list');
    }
}
