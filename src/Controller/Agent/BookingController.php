<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\CalendarBundle\Repository\BookingRequestRepository;
use App\CalendarBundle\Service\BookingService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent')]
#[IsGranted('ROLE_AGENT')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingRequestRepository $bookingRequestRepository,
        private readonly BookingService $bookingService,
    ) {
    }

    #[Route('/bookings', name: 'agent_booking_list', methods: ['GET'])]
    public function list(): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $bookingRequests = $this->bookingRequestRepository->findByAgent($agent);

        return $this->render('agent/booking/index.html.twig', [
            'bookingRequests' => $bookingRequests,
        ]);
    }

    #[Route('/bookings/{id}/accept', name: 'agent_booking_accept', methods: ['PATCH'])]
    public function accept(int $id): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $bookingRequest = $this->bookingRequestRepository->find($id);

        if ($bookingRequest === null) {
            $this->addFlash('error', 'Booking request not found.');

            return $this->redirectToRoute('agent_booking_list');
        }

        try {
            $this->bookingService->acceptRequest($bookingRequest, $agent);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('agent_booking_list');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('agent_booking_list');
        }

        $this->addFlash('success', 'Booking request accepted.');

        return $this->redirectToRoute('agent_booking_list');
    }

    #[Route('/bookings/{id}/decline', name: 'agent_booking_decline', methods: ['PATCH'])]
    public function decline(int $id): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $bookingRequest = $this->bookingRequestRepository->find($id);

        if ($bookingRequest === null) {
            $this->addFlash('error', 'Booking request not found.');

            return $this->redirectToRoute('agent_booking_list');
        }

        try {
            $this->bookingService->declineRequest($bookingRequest, $agent);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('agent_booking_list');
        }

        $this->addFlash('success', 'Booking request declined.');

        return $this->redirectToRoute('agent_booking_list');
    }
}
