<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Status;
use App\Repository\WebsiteRepository;
use App\Service\AlertService;
use App\Service\ThresholdService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Service\TimezoneConverter;
use App\Service\DateRangeFilterService;
use App\Enum\StatusType;
use OpenApi\Attributes as OA;
use App\Enum\ErrorType;

class StatusController extends AbstractController
{
    private $entityManager;
    private $websiteRepository;
    private $alertService;
    private $thresholdService;
    private $timezoneConverter;
    private $dateRangeFilterService;
    private $logger;
    private const DATE_TIME = '2024-12-23 15:30:00';
    
    public function __construct(EntityManagerInterface $entityManager, WebsiteRepository $websiteRepository, AlertService $alertService, TimezoneConverter $timezoneConverter, DateRangeFilterService $dateRangeFilterService, ThresholdService $thresholdService, LoggerInterface $logger)
    {
        $this->websiteRepository = $websiteRepository;
        $this->entityManager = $entityManager;
        $this->alertService = $alertService;
        $this->thresholdService = $thresholdService;
        $this->timezoneConverter = $timezoneConverter;
        $this->dateRangeFilterService = $dateRangeFilterService;
        $this->logger = $logger;
    }

    #[OA\Post(
        summary: 'Set status.',
        tags: ['Status'],
    )]
    #[OA\RequestBody(
        description: 'Array of status data.',
        required: true,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'websiteId', type: 'integer', example: 5),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(property: 'responseTime', type: 'integer', example: 8000),
                    new OA\Property(property: 'pageLoad', type: 'integer', example: 127),
                    new OA\Property(property: 'pageSize', type: 'integer', example: 1500),
                    new OA\Property(property: 'isUp', type: 'boolean', example: true),
                    new OA\Property(property: 'checkedAt', type: 'string', format: 'date-time', example: self::DATE_TIME),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Response successfully.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Statuses registered successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'Invalid data format, expected an array'),
                new OA\Property(property: 'error2', type: 'string', example: 'All fields are required: statusCode, responseTime, isUp, checkedAt, websiteId')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: ErrorType::UNAUTHORIZED->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::UNAUTHORIZED->value)
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: ErrorType::FORBIDDEN->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::FORBIDDEN->value)
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: ErrorType::NOT_FOUND->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::NOT_FOUND->value)
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
    #[Route('/api/v1/status/setall', name: 'api_status_setall', methods: ['POST'])]
    public function setAll(Request $request): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_OPERATOR');
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid data format, expected an array'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data as $statusData) {
            if (!isset($statusData['statusCode'], $statusData['responseTime'], $statusData['pageLoad'], $statusData['pageSize'], $statusData['isUp'], $statusData['checkedAt'], $statusData['websiteId'])) {
                return new JsonResponse(['error' => 'All fields are required: statusCode, responseTime, isUp, checkedAt, websiteId'], Response::HTTP_BAD_REQUEST);
            }

            $website = $this->websiteRepository->find($statusData['websiteId']);
            if (!$website) {
                return new JsonResponse(['error' => 'Website not found for ID: ' . $statusData['websiteId']], Response::HTTP_NOT_FOUND);
            }

            $status = new Status();
            $status->setStatusCode($statusData['statusCode']);
            $status->setResponseTime($statusData['responseTime']);
            $status->setPageLoad($statusData['pageLoad']);
            $status->setpageSize($statusData['pageSize']);
            $status->setUp($statusData['isUp']);
            $status->setCheckedAt(new \DateTimeImmutable($statusData['checkedAt']));
            $status->setWebsiteId($website);

            $this->entityManager->persist($status);

            // Getting threshold per site
            $threshold = $website->getThreshold();
            if ($threshold) {
                $violations = $this->thresholdService->checkThreshold($status, $threshold);
    
                $this->logger->info('Violations:', ['violations' => $violations]);
    
                $this->statusViolations($violations, $status);
            }
        }
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Statuses registered successfully.'], Response::HTTP_CREATED);
    }

    #[OA\Get(
        summary: 'Get Status.',
        tags: ['Status'],
    )]
    #[OA\Parameter(
        name: 'filter',
        in: 'query',
        required: false,
        description: 'Date range.',
        schema: new OA\Schema(type: 'string', example: '7d')
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'websiteId', type: 'integer', example: 5),
                    new OA\Property(property: 'websiteName', type: 'string', example: 'example'),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(property: 'responseTime', type: 'integer', example: 800),
                    new OA\Property(property: 'pageLoad', type: 'integer', example: 120),
                    new OA\Property(property: 'pageSize', type: 'integer', example: 1500),
                    new OA\Property(property: 'isUp', type: 'boolean', example: true),
                    new OA\Property(property: 'checkedAt', type: 'string', format: 'date-time', example: self::DATE_TIME)
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
                new OA\Property(property: 'error', type: 'string', example: ErrorType::UNAUTHORIZED->value)
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
    #[Route('/api/v1/status/getbyhost/{websiteId}', name: 'api_status_getbyhost', methods: ['GET'])]
    public function getStatusesByHost(int $websiteId, UserInterface $user, Request $request): JsonResponse
    {
        $client = $user->getClientId();
        $userTimezone = $user->getTimezone();

        $filter = $this->dateRangeFilterService->getFilterFromRequest($request);

        $website = $this->websiteRepository->findOneBy([
            'id' => $websiteId,
            'client' => $client,
        ]);

        if (!$website) {
            return new JsonResponse(['error' => 'Website not found or not authorized'], Response::HTTP_NOT_FOUND);
        }

        $query = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Status::class, 's')
            ->where('s.website = :website')
            ->setParameter('website', $website);

        // Use datarange service
        $query = $this->dateRangeFilterService->applyDateRangeFilter($query, 's', 'checkedAt', $filter);

        $filteredStatuses = $query->getQuery()->getResult();

        $statuses = array_map(function ($status) use ($website, $userTimezone) {
            $checkedAt = $this->timezoneConverter->convertToUserTimezone(
                $status->getCheckedAt(),
                $userTimezone
            );

            return [
                'websiteId' => $website->getId(),
                'websiteName' => $website->getName(),
                'statusCode' => $status->getStatusCode(),
                'responseTime' => $status->getResponseTime(),
                'pageLoad' => $status->getPageLoad(),
                'pageSize' => $status->getPageSize(),
                'isUp' => $status->isUp(),
                'checkedAt' => $checkedAt->format('Y-m-d H:i:s'),
            ];
        }, $filteredStatuses);

        // Order by `checkedAt`
        usort($statuses, function ($firstStatus, $secondStatus) {
            return strtotime($firstStatus['checkedAt']) <=> strtotime($secondStatus['checkedAt']);
        });

        return new JsonResponse($statuses, Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'Get statuses by multiple hosts.',
        tags: ['Status'],
    )]
    #[OA\RequestBody(
        description: 'List of website IDs.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'websiteIds',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2, 3]
                ),
                new OA\Property(
                    property: 'filter',
                    type: 'string',
                    description: 'Date range filter.',
                    example: '7d'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'websiteId', type: 'integer', example: 5),
                    new OA\Property(property: 'websiteName', type: 'string', example: 'example'),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(property: 'responseTime', type: 'integer', example: 800),
                    new OA\Property(property: 'pageLoad', type: 'integer', example: 120),
                    new OA\Property(property: 'pageSize', type: 'integer', example: 1500),
                    new OA\Property(property: 'isUp', type: 'boolean', example: true),
                    new OA\Property(property: 'checkedAt', type: 'string', format: 'date-time', example: self::DATE_TIME)
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
                new OA\Property(property: 'error', type: 'string', example: 'No website IDs provided')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: ErrorType::UNAUTHORIZED->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::UNAUTHORIZED->value)
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
    #[Route('/api/v1/status/getbymultiplehosts', name: 'api_status_getbymultiplehosts', methods: ['POST'])]
    public function getStatusesByMultipleHosts(Request $request, UserInterface $user): JsonResponse
    {
        $client = $user->getClientId();
        $userTimezone = $user->getTimezone();
        $data = json_decode($request->getContent(), true);
        $websiteIds = $data['websiteIds'] ?? [];
        $filter = $this->dateRangeFilterService->getFilterFromRequest($request);

        if (empty($websiteIds) || !is_array($websiteIds)) {
            return new JsonResponse(['error' => 'No website IDs provided'], Response::HTTP_BAD_REQUEST);
        }

        $statuses = [];
        foreach ($websiteIds as $websiteId) {
            $website = $this->websiteRepository->findOneBy([
                'id' => $websiteId,
                'client' => $client,
            ]);

            if (!$website) {
                continue;
            }

            $query = $this->entityManager->createQueryBuilder()
                ->select('s')
                ->from(Status::class, 's')
                ->where('s.website = :website')
                ->setParameter('website', $website);

            // Use datarange service
            $query = $this->dateRangeFilterService->applyDateRangeFilter($query, 's', 'checkedAt', $filter);

            $filteredStatuses = $query->getQuery()->getResult();

            foreach ($filteredStatuses as $status) {
                $checkedAt = $this->timezoneConverter->convertToUserTimezone(
                    $status->getCheckedAt(),
                    $userTimezone
                );
                $statuses[] = [
                    'websiteId' => $website->getId(),
                    'websiteName' => $website->getName(),
                    'statusCode' => $status->getStatusCode(),
                    'responseTime' => $status->getResponseTime(),
                    'pageLoad' => $status->getPageLoad(),
                    'pageSize' => $status->getPageSize(),
                    'isUp' => $status->isUp(),
                    'checkedAt' => $checkedAt->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Order by `checkedAt`
        usort($statuses, function ($firstStatus, $secondStatus) {
            return strtotime($firstStatus['checkedAt']) <=> strtotime($secondStatus['checkedAt']);
        });

        return new JsonResponse($statuses, Response::HTTP_OK);
    }

    private function statusViolations(array $violations, Status $status): void
    {
        foreach (StatusType::cases() as $statusType) {
            $violationFound = $this->handleViolationStatus($violations, $statusType, $status);

            if (!$violationFound) {
                $this->alertService->resolveAlert($status, $statusType->value);
            }
        }
    }

    private function handleViolationStatus(array $violations, StatusType $statusType, Status $status): bool
    {
        foreach ($violations as $violation) {
            if ($violation['kind'] === $statusType->value) {
                $this->alertService->createAlert($status, $statusType->value);
                return true;
            }
        }
        return false;
    }

}
