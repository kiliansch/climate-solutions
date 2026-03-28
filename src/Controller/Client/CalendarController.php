<?php

declare(strict_types=1);

namespace App\Controller\Client;

use App\CalendarBundle\Dto\UnavailabilityDTO;
use App\CalendarBundle\Repository\SlotUnavailabilityRepository;
use App\CalendarBundle\Repository\UnavailabilityRepository;
use App\CalendarBundle\Service\UnavailabilityService;
use App\Entity\User;
use App\Repository\CalendarRepository;
use App\Repository\SlotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_CLIENT')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly UnavailabilityRepository $unavailabilityRepository,
        private readonly SlotRepository $slotRepository,
        private readonly UnavailabilityService $unavailabilityService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SlotUnavailabilityRepository $slotUnavailabilityRepository,
    ) {
    }

    #[Route('/calendar', name: 'client_calendar_show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var User $client */
        $client = $this->getUser();

        $calendar = $this->calendarRepository->findByClient($client);

        if ($calendar === null) {
            throw $this->createNotFoundException('No calendar found for this client.');
        }

        $unavailabilities = $this->unavailabilityRepository->findByCalendar($calendar);

        $blockedDatesByUnavailability = [];
        foreach ($unavailabilities as $unavailability) {
            $blockedDates = $this->slotUnavailabilityRepository->createQueryBuilder('su')
                ->select('su.blockedDate')
                ->andWhere('su.unavailability = :unavailability')
                ->setParameter('unavailability', $unavailability)
                ->orderBy('su.blockedDate', 'ASC')
                ->getQuery()
                ->getSingleColumnResult();
            $blockedDatesByUnavailability[$unavailability->getId()] = $blockedDates;
        }

        return $this->render('client/calendar/show.html.twig', [
            'calendar' => $calendar,
            'unavailabilities' => $unavailabilities,
            'hasOverriddenSlots' => $this->slotRepository->hasOverriddenSlots($calendar),
            'blockedDatesByUnavailability' => $blockedDatesByUnavailability,
        ]);
    }

    #[Route('/unavailability', name: 'client_unavailability_create', methods: ['POST'])]
    public function createUnavailability(#[MapRequestPayload] UnavailabilityDTO $dto): RedirectResponse
    {
        /** @var User $client */
        $client = $this->getUser();

        $calendar = $this->calendarRepository->findByClient($client);

        if ($calendar === null) {
            throw $this->createNotFoundException('No calendar found for this client.');
        }

        $startAt = $dto->startAt;
        $endAt = $dto->endAt;

        if ($startAt->format('Y-m-d') === $endAt->format('Y-m-d')) {
            $endAt = $endAt->setTime(23, 59, 59);
        }

        $this->unavailabilityService->markUnavailable(
            calendar: $calendar,
            client: $client,
            start: $startAt,
            end: $endAt,
            reason: $dto->reason,
        );

        $this->addFlash('success', 'Unavailability period added successfully.');

        return $this->redirectToRoute('client_calendar_show');
    }

    #[Route('/unavailability/{id}', name: 'client_unavailability_delete', methods: ['DELETE'])]
    public function deleteUnavailability(int $id): RedirectResponse
    {
        /** @var User $client */
        $client = $this->getUser();

        $unavailability = $this->unavailabilityRepository->find($id);

        if ($unavailability === null) {
            throw $this->createNotFoundException('Unavailability not found.');
        }

        $calendar = $this->calendarRepository->findByClient($client);

        if ($calendar === null || $unavailability->getCalendar()->getId() !== $calendar->getId()) {
            throw $this->createAccessDeniedException('This unavailability does not belong to your calendar.');
        }

        $this->entityManager->remove($unavailability);
        $this->entityManager->flush();

        $this->addFlash('success', 'Unavailability period removed successfully.');

        return $this->redirectToRoute('client_calendar_show');
    }
}
