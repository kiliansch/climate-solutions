<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Dto\RegistrationDTO;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function showRegistrationForm(): Response
    {
        return $this->render('auth/register.html.twig');
    }

    #[Route('/register', name: 'app_register_post', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegistrationDTO $dto,
    ): Response {
        try {
            $this->registrationService->registerAgent($dto);
        } catch (\DomainException $e) {
            return $this->render('auth/register.html.twig', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->addFlash('success', 'Account created. Please log in.');

        return $this->redirectToRoute('app_login');
    }
}
