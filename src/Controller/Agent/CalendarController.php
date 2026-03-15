<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Dto\CalendarDTO;
use App\Dto\SlotDTO;
use App\Entity\Calendar;
use App\Entity\Slot;
use App\Entity\User;
use App\Repository\CalendarRepository;
use App\Repository\SlotRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    public function list(): JsonResponse
    {
        /** @var User $agent */
        $agent = $this->getUser();

        $calendars = $this->calendarRepository->findByAgent($agent);

        return $this->json(array_map(
            static fn(Calendar $c): array => [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'displayMode' => $c->getDisplayMode(),
                'publicToken' => $c->getPublicToken(),
                'createdAt' => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $calendars,
        ));
    }

    #[Route('/calendars', name: 'agent_calendar_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CalendarDTO $dto): JsonResponse
    {
        $client = $this->userRepository->find($dto->clientId);

        if ($client === null) {
            return $this->json(['error' => 'Client not found.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $agent */
        $agent = $this->getUser();

        $calendar = new Calendar();
        $calendar->setName($dto->name);
        $calendar->setDisplayMode($dto->displayMode);
        $calendar->setClient($client);
        $calendar->setAgent($agent);

        $this->entityManager->persist($calendar);
        $this->entityManager->flush();

        return $this->json([
            'id' => $calendar->getId(),
            'name' => $calendar->getName(),
            'displayMode' => $calendar->getDisplayMode(),
            'publicToken' => $calendar->getPublicToken(),
            'createdAt' => $calendar->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/calendars/{id}', name: 'agent_calendar_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $calendar = $this->findCalendarForCurrentAgent($id);

        if ($calendar === null) {
            return $this->json(['error' => 'Calendar not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $slots = $this->slotRepository->findOpenByCalendar($calendar);

        return $this->json([
            'id' => $calendar->getId(),
            'name' => $calendar->getName(),
            'displayMode' => $calendar->getDisplayMode(),
            'publicToken' => $calendar->getPublicToken(),
            'createdAt' => $calendar->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'slots' => array_map(
                static fn(Slot $s): array => [
                    'id' => $s->getId(),
                    'type' => $s->getType(),
                    'startAt' => $s->getStartAt()->format(\DateTimeInterface::ATOM),
                    'endAt' => $s->getEndAt()->format(\DateTimeInterface::ATOM),
                    'status' => $s->getStatus(),
                    'location' => $s->getLocation(),
                    'continent' => $s->getContinent(),
                ],
                $slots,
            ),
        ]);
    }

    #[Route('/calendars/{id}/slots', name: 'agent_calendar_slot_create', methods: ['POST'])]
    public function createSlot(int $id, #[MapRequestPayload] SlotDTO $dto): JsonResponse
    {
        $calendar = $this->findCalendarForCurrentAgent($id);

        if ($calendar === null) {
            return $this->json(['error' => 'Calendar not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $slot = new Slot();
        $slot->setType($dto->type);
        $slot->setStartAt($dto->startAt);
        $slot->setEndAt($dto->endAt);
        $slot->setLocation($dto->location);
        $slot->setContinent($dto->continent);
        $slot->setCalendar($calendar);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $this->json([
            'id' => $slot->getId(),
            'type' => $slot->getType(),
            'startAt' => $slot->getStartAt()->format(\DateTimeInterface::ATOM),
            'endAt' => $slot->getEndAt()->format(\DateTimeInterface::ATOM),
            'status' => $slot->getStatus(),
            'location' => $slot->getLocation(),
            'continent' => $slot->getContinent(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/calendars/{id}/share', name: 'agent_calendar_share', methods: ['GET'])]
    public function share(int $id): JsonResponse
    {
        $calendar = $this->findCalendarForCurrentAgent($id);

        if ($calendar === null) {
            return $this->json(['error' => 'Calendar not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $url = $this->generateUrl(
            'calendar_public_view',
            ['token' => $calendar->getPublicToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->json(['url' => $url]);
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
