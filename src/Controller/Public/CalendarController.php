<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\CalendarBundle\Dto\BookingRequestDTO;
use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\CalendarBundle\Service\BookingService;
use App\Repository\CalendarRepository;
use App\Repository\SlotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class CalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly SlotRepository $slotRepository,
        private readonly BookingService $bookingService,
        private readonly SlotUnavailabilityRepository $slotUnavailabilityRepository,
    ) {
    }

    #[Route('/c/{token}', name: 'calendar_public_view', methods: ['GET'])]
    public function show(string $token): Response
    {
        $calendar = $this->calendarRepository->findByPublicToken($token);

        if ($calendar === null) {
            throw $this->createNotFoundException('Calendar not found.');
        }

        return $this->render('public/calendar/show.html.twig', [
            'calendar' => $calendar,
        ]);
    }

    #[Route('/c/{token}/book', name: 'calendar_public_book', methods: ['POST'])]
    public function book(
        string $token,
        Request $request,
        #[MapRequestPayload] BookingRequestDTO $dto,
    ): Response {
        $calendar = $this->calendarRepository->findByPublicToken($token);

        if ($calendar === null) {
            throw $this->createNotFoundException('Calendar not found.');
        }

        $slotId = $request->request->getInt('slotId');
        $slot = $this->slotRepository->find($slotId);

        if ($slot === null || $slot->getCalendar()->getId() !== $calendar->getId()) {
            $this->addFlash('error', 'Invalid slot selected.');

            return $this->redirectToRoute('calendar_public_view', ['token' => $token]);
        }

        try {
            $this->bookingService->createRequest($slot, $dto);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('calendar_public_view', ['token' => $token]);
        }

        $this->addFlash('success', 'Your booking request has been submitted successfully.');

        return $this->redirectToRoute('calendar_public_view', ['token' => $token]);
    }
}
