<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_agent_list');
        }

        if ($this->isGranted('ROLE_AGENT')) {
            return $this->redirectToRoute('agent_calendar_list');
        }

        if ($this->isGranted('ROLE_CLIENT')) {
            return $this->redirectToRoute('client_calendar_show');
        }

        return $this->redirectToRoute('app_login');
    }
}
