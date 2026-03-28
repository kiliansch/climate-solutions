<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Entity\Calendar;
use App\Entity\Slot;
use App\Entity\User;
use App\Repository\CalendarRepository;
use App\Repository\SlotRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent')]
#[IsGranted('ROLE_AGENT')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly SlotRepository $slotRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/calendars', name: 'agent_calendar_list', methods: ['GET'])]
    public function list(): Response
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $calendars = $this->calendarRepository->findByAgent($agent);
        $clients = $this->userRepository->findByRole('ROLE_CLIENT');

        return $this->render('agent/calendar/index.html.twig', [
            'calendars' => $calendars,
            'clients' => $clients,
        ]);
    }

    #[Route('/calendars', name: 'agent_calendar_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        $displayMode = (string) $request->request->get('displayMode', 'dayslot');
        $clientId = (int) $request->request->get('clientId', 0);

        $client = $this->userRepository->find($clientId);

        if ($client === null) {
            $this->addFlash('error', 'Client not found.');

            return $this->redirectToRoute('agent_calendar_list');
        }

        /** @var User $agent */
        $agent = $this->getUser();

        $calendar = new Calendar();
        $calendar->setName($name);
        $calendar->setDisplayMode($displayMode);
        $calendar->setClient($client);
        $calendar->setAgent($agent);

        $this->entityManager->persist($calendar);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Calendar "%s" created.', $calendar->getName()));

        return $this->redirectToRoute('agent_calendar_show', ['id' => $calendar->getId()]);
    }

    #[Route('/calendars/{id}', name: 'agent_calendar_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $calendar = $this->findCalendarForCurrentAgent($id);

        if ($calendar === null) {
            throw $this->createNotFoundException('Calendar not found.');
        }

        $slots = $this->slotRepository->findOpenByCalendar($calendar);

        return $this->render('agent/calendar/show.html.twig', [
            'calendar' => $calendar,
            'slots' => $slots,
        ]);
    }

    #[Route('/calendars/{id}/slots', name: 'agent_calendar_slot_create', methods: ['POST'])]
    public function createSlot(int $id, Request $request): Response
    {
        $calendar = $this->findCalendarForCurrentAgent($id);

        if ($calendar === null) {
            throw $this->createNotFoundException('Calendar not found.');
        }

        $type = (string) $request->request->get('type', '');
        $startAtRaw = (string) $request->request->get('startAt', '');
        $endAtRaw = (string) $request->request->get('endAt', '');
        $locationRaw = $request->request->get('location');
        $location = (is_string($locationRaw) && $locationRaw !== '') ? $locationRaw : null;
        $continentRaw = $request->request->get('continent');
        $continent = (is_string($continentRaw) && $continentRaw !== '') ? $continentRaw : null;

        try {
            $startAt = new \DateTimeImmutable($startAtRaw);
            $endAt = new \DateTimeImmutable($endAtRaw);
        } catch (\Exception) {
            $this->addFlash('error', 'Invalid date/time format.');

            return $this->redirectToRoute('agent_calendar_show', ['id' => $id]);
        }

        if ($startAt >= $endAt) {
            $this->addFlash('error', 'Start date/time must be before end date/time.');

            return $this->redirectToRoute('agent_calendar_show', ['id' => $id]);
        }

        $slot = new Slot();
        $slot->setType($type);
        $slot->setStartAt($startAt);
        $slot->setEndAt($endAt);
        $slot->setLocation($location);
        $slot->setContinent($continent);
        $slot->setCalendar($calendar);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        $this->addFlash('success', 'Slot added successfully.');

        return $this->redirectToRoute('agent_calendar_show', ['id' => $id]);
    }

    #[Route('/calendars/{id}/share', name: 'agent_calendar_share', methods: ['GET'])]
    public function share(int $id): Response
    {
        $calendar = $this->findCalendarForCurrentAgent($id);

        if ($calendar === null) {
            throw $this->createNotFoundException('Calendar not found.');
        }

        return $this->redirectToRoute('agent_calendar_show', ['id' => $id]);
    }

    private function findCalendarForCurrentAgent(int $id): ?Calendar
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $calendar = $this->calendarRepository->find($id);

        if ($calendar === null || $calendar->getAgent()->getId() !== $agent->getId()) {
            return null;
        }

        return $calendar;
    }
}
