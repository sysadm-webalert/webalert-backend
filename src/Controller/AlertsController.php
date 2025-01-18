<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\AlertsRepository;
use App\Service\TimezoneConverter;
use OpenApi\Attributes as OA;
use App\Enum\ErrorType;

class AlertsController extends AbstractController
{
    private $timezoneConverter;

    public function __construct(TimezoneConverter $timezoneConverter)
    {
        $this->timezoneConverter = $timezoneConverter;
    }
    
    #[OA\Get(
        summary: 'Get Alerts.',
        tags: ['Alerts'],
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'website', type: 'string', example: 'https://example.com'),
                    new OA\Property(property: 'status', type: 'integer', nullable: true, example: null),
                    new OA\Property(property: 'responseTime', type: 'integer', nullable: true, example: null),
                    new OA\Property(property: 'isUp', type: 'boolean', nullable: true, example: null),
                    new OA\Property(property: 'cpuUsage', type: 'number', format: 'float', example: 1.409282139114),
                    new OA\Property(property: 'memoryUsage', type: 'number', format: 'float', example: 12.438707561675),
                    new OA\Property(property: 'diskUsage', type: 'number', format: 'float', example: 41.113437517169),
                    new OA\Property(property: 'type', type: 'string', example: 'max_disk'),
                    new OA\Property(property: 'isResolved', type: 'boolean', example: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-12-22 12:18:48'),
                    new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', nullable: true, example: '2024-12-23 15:30:00')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Client not found or user not authenticated.'),
            ]
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
    #[Route('/api/v1/alerts/getbyclient', name: 'api_alerts_getbyclient', methods: ['GET'])]
    public function AllgetByClient(AlertsRepository $alertsRepository): JsonResponse {
        $user = $this->getUser();
        $client = $user->getClientId();
        $userTimezone = $user->getTimezone();
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found or user not authenticated.'], JsonResponse::HTTP_FORBIDDEN);
        }
        $alerts = $alertsRepository->findByClient($client);
        if (empty($alerts)) {
            return new JsonResponse([], JsonResponse::HTTP_OK);
        }

        $groupedAlerts = $this->alertsAgroupation($alerts);
        $alertData = $this->alertsFormat($groupedAlerts, $userTimezone);

        return new JsonResponse($alertData, JsonResponse::HTTP_OK);
    }

    private function alertsAgroupation(array $alerts): array
    {
        usort($alerts, function($a, $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        $groupedAlerts = [];
        $currentGroup = null;
        $lastResolvedAt = null;

        foreach ($alerts as $alert) {
            $websiteId = $alert->getWebsite()->getId();
            $kind = $alert->getKind();
            $groupKey = "{$websiteId}-{$kind}";

            if (!isset($groupedAlerts[$groupKey])) {
                $groupedAlerts[$groupKey] = [];
            }

            if ($lastResolvedAt === null || $alert->getCreatedAt() > $lastResolvedAt) {
                $groupedAlerts[$groupKey][] = [
                    'first' => $alert,
                    'last' => $alert
                ];
                $currentGroup = &$groupedAlerts[$groupKey][count($groupedAlerts[$groupKey]) - 1];
            } else {
                $currentGroup['last'] = $alert;
            }

            $lastResolvedAt = $alert->getResolvedAt();
        }

        return $groupedAlerts;
    }

    private function alertsFormat(array $groupedAlerts, ?string $userTimezone): array
    {
        $alertData = [];

        foreach ($groupedAlerts as $intervals) {
            foreach ($intervals as $interval) {
                $firstAlert = $interval['first'];
                $lastAlert = $interval['last'];

                $alertData[] = $this->alertFormat($firstAlert, $lastAlert, $userTimezone);
            }
        }

        return $alertData;
    }

    private function alertFormat($firstAlert, $lastAlert, ?string $userTimezone): array
    {
        $convertedCreatedAt = $this->timezoneConverter->convertToUserTimezone(
            $firstAlert->getCreatedAt(),
            $userTimezone
        );

        $convertedResolvedAt = $lastAlert->getResolvedAt()
            ? $this->timezoneConverter->convertToUserTimezone($lastAlert->getResolvedAt(), $userTimezone)
            : null;

        return [
            'id' => $lastAlert->getId(),
            'website' => $lastAlert->getWebsite()->getUrl(),
            'status' => $lastAlert->getStatus() ? $lastAlert->getStatus()->getStatusCode() : null,
            'responseTime' => $lastAlert->getStatus() ? $lastAlert->getStatus()->getResponseTime() : null,
            'isUp' => $lastAlert->getStatus() ? $lastAlert->getStatus()->isUp() : null,
            'cpuUsage' => $lastAlert->getMetrics() ? $lastAlert->getMetrics()->getCpuUsage() : null,
            'memoryUsage' => $lastAlert->getMetrics() ? $lastAlert->getMetrics()->getMemoryUsage() : null,
            'diskUsage' => $lastAlert->getMetrics() ? $lastAlert->getMetrics()->getDiskUsage() : null,
            'type' => $lastAlert->getKind(),
            'isResolved' => $lastAlert->isResolved(),
            'createdAt' => $convertedCreatedAt->format('Y-m-d H:i:s'),
            'resolvedAt' => $convertedResolvedAt ? $convertedResolvedAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
