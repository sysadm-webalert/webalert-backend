<?php

namespace App\Controller;

use App\Entity\Events;
use App\Entity\User;
use App\Entity\Clients;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\EventsRepository;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TimezoneConverter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Attributes as OA;
use App\Enum\ErrorType;

class EventsController extends AbstractController
{
    private $entityManager;
    private $timezoneConverter;
    private $eventsRepository;

    public function __construct(EntityManagerInterface $entityManager, TimezoneConverter $timezoneConverter, EventsRepository $eventsRepository)
    {
        $this->timezoneConverter = $timezoneConverter;
        $this->entityManager = $entityManager;
        $this->eventsRepository  = $eventsRepository;
    }

    #[OA\Get(
        summary: 'Get events.',
        tags: ['Events'],
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'message', type: 'string', example: 'The site example has been updated by user@example.com'),
                    new OA\Property(property: 'acknowledge', type: 'bool', nullable: true, example: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-12-22 12:18:48'),
                    new OA\Property(property: 'type', type: 'string', example: 'site_updated'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 401,
        description: ErrorType::UNAUTHORIZED->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::UNAUTHORIZED->value),
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: ErrorType::METHOD_NOT_ALLOWED->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::METHOD_NOT_ALLOWED->value)
            ]
        )
    )]
    #[Route('/api/v1/events', name: 'api_events', methods: ['GET'])]
    public function getEvents(Request $request): JsonResponse {
        $user = $this->getUser();
        $client = $user->getClientId();
        $userTimezone = $user->getTimezone();
        $ack = filter_var($request->query->get('ack', false), FILTER_VALIDATE_BOOLEAN);
        $events = $this->eventsRepository->findBy(['client' => $client],['createdAt' => 'DESC'], 100);
        
        $eventsData = array_map(function (Events $event) use ($userTimezone) {
            $createdAt = $this->timezoneConverter->convertToUserTimezone(
                $event->getCreatedAt(),
                $userTimezone
            );
            return [
                'id' => $event->getId(),
                'message' => $event->getMessage(),
                'acknowledge' => $event->isAcknowledge(),
                'createdAt' => $createdAt->format('Y-m-d H:i:s'),
                'type' => $event->getKind(),
            ];
        }, $events);
        if ($ack === true) {
            foreach ($events as $event) {
                $event->setAcknowledge(true);
                $this->entityManager->persist($event);
            }
            $this->entityManager->flush();
        }
        return new JsonResponse($eventsData, Response::HTTP_OK);
    }
}
