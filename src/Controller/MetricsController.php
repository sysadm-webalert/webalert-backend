<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\WebsiteRepository;
use App\Repository\MetricsRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\Metrics;
use App\Entity\Agent;
use App\Service\TimezoneConverter;
use App\Service\DateRangeFilterService;
use App\Service\AlertService;
use App\Service\ThresholdService;
use App\Enum\MetricType;
use OpenApi\Attributes as OA;
use App\Enum\ErrorType;

class MetricsController extends AbstractController
{
    private $entityManager;
    private $websiteRepository;
    private $metricsRepository;
    private $timezoneConverter;
    private $dateRangeFilterService;
    private $alertService;
    private $thresholdService;

    public function __construct(EntityManagerInterface $entityManager, WebsiteRepository $websiteRepository, MetricsRepository $metricsRepository, TimezoneConverter $timezoneConverter, DateRangeFilterService $dateRangeFilterService, AlertService $alertService, ThresholdService $thresholdService)
    {
        $this->entityManager = $entityManager;
        $this->websiteRepository = $websiteRepository;
        $this->metricsRepository = $metricsRepository;
        $this->timezoneConverter = $timezoneConverter;
        $this->dateRangeFilterService = $dateRangeFilterService;
        $this->alertService = $alertService;
        $this->thresholdService = $thresholdService;
    }
    #[OA\Post(
        summary: 'Send metrics from agent.',
        tags: ['Metrics'],
    )]
    #[OA\RequestBody(
        description: 'Post Metrics',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'cpu_usage', type: 'integer', example: 85),
                new OA\Property(property: 'memory_usage', type: 'integer', example: 8),
                new OA\Property(property: 'disk_usage', type: 'integer', example: 10),
                new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                new OA\Property(property: 'sitename', type: 'string', example: 'example')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::OK->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Metrics added successful.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Lack some data.')
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
    #[Route('/api/v1/metrics', name: 'api_metrics', methods: ['POST'])]
    public function index(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['cpu_usage'], $data['memory_usage'], $data['disk_usage'], $data['sitename'])) {
            return new JsonResponse(['error' => 'Lack some data.'], Response::HTTP_BAD_REQUEST);
        }
        $user = $this->getUser();
        $website = $this->websiteRepository->findOneBy(['name' => $data['sitename'],'client' => $user->getClientId()]);

        if (!$website) {
            return new JsonResponse(['error' => 'Site not found.'], Response::HTTP_NOT_FOUND);
        }
        if ($website->getClientId() !== $user->getClientId()) {
            return new JsonResponse(['error' => 'Unauthorized access.'], Response::HTTP_FORBIDDEN);
        }
        $metrics = new Metrics();
        $metrics->setCpuUsage($data['cpu_usage']);
        $metrics->setMemoryUsage($data['memory_usage']);
        $metrics->setDiskUsage($data['disk_usage']);
        $metrics->setCheckedAt(new \DateTimeImmutable());
        $metrics->setWebsiteId($website);

        $this->entityManager->persist($metrics);

        $agent = $website->getAgent();
        if ($agent) {
            $agent->setInstalled(true);
            $agent->setVersion($data['version']);
            $agent->setLastCheckedAt(new \DateTime());
            $this->entityManager->persist($agent);
        }

        $this->entityManager->flush();
        
        $threshold = $website->getThreshold();
        if ($threshold) {
            $violations = $this->thresholdService->checkThresholdForMetrics($metrics, $threshold);

            foreach (MetricType::cases() as $metricType) {
                $violationFound = false;

                foreach ($violations as $violation) {
                    if ($violation['kind'] === $metricType->value) {
                        $this->alertService->createAlert($metrics, $metricType->value);
                        $violationFound = true;
                        break;
                    }
                }

                if (!$violationFound) {
                    $this->alertService->resolveAlert($metrics, $metricType->value);
                }
            }
        }

   

        return new JsonResponse(['message' => 'Metrics added successful.'], Response::HTTP_CREATED);
    }

    #[OA\Get(
        summary: 'Get metrics by host.',
        tags: ['Metrics'],
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::OK->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'websiteId', type: 'integer', example: 1),
                new OA\Property(property: 'websiteName', type: 'string', example: 'mysite'),
                new OA\Property(property: 'checkedAt', type: 'string', format: 'date-time', example: '2024-12-22 12:18:48'),
                new OA\Property(property: 'cpuUsage', type: 'number', format: 'float', example: 1.409282139114),
                new OA\Property(property: 'memoryUsage', type: 'number', format: 'float', example: 12.438707561675),
                new OA\Property(property: 'diskUsage', type: 'number', format: 'float', example: 41.113437517169)
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
    #[Route('/api/v1/metrics/getbyhost/{websiteId}', name: 'api_metrics_getbyhost', methods: ['GET'])]
    public function getMetricsByClient(int $websiteId, UserInterface $user, Request $request): JsonResponse
    {
        $client = $user->getClientId();
        $userTimezone = $user->getTimezone();
        $filter = $request->query->get('filter', '7d');

        $websites = $this->websiteRepository->findBy([
            'id' => $websiteId,
            'client' => $client
        ]);

        $metricses = [];
        foreach ($websites as $website) {
            $agent = $website->getAgent();
            if ($agent && $agent->isInstalled()) {
                $query = $this->entityManager->createQueryBuilder()
                    ->select('m')
                    ->from(Metrics::class, 'm')
                    ->where('m.website = :website')
                    ->setParameter('website', $website);

                $query = $this->dateRangeFilterService->applyDateRangeFilter($query, 'm', 'checkedAt', $filter);

                $filteredMetrics = $query->getQuery()->getResult();

                foreach ($filteredMetrics as $metrics) {
                    $checkedAt = $this->timezoneConverter->convertToUserTimezone(
                        $metrics->getCheckedAt(),
                        $userTimezone
                    );
                    $metricses[] = [
                        'websiteId' => $website->getId(),
                        'websiteName' => $website->getName(),
                        'checkedAt' => $checkedAt->format('Y-m-d H:i:s'),
                        'cpuUsage' => $metrics->getCpuUsage(),
                        'memoryUsage' => $metrics->getMemoryUsage(),
                        'diskUsage' => $metrics->getDiskUsage(),
                    ];
                }
            }
        }
        // Order results by checkedAt
        usort($metricses, function ($firstMetrics, $secondMetrics) {
            return strtotime($firstMetrics['checkedAt']) <=> strtotime($secondMetrics['checkedAt']);
        });

        return new JsonResponse($metricses, Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'Get metrics by multiple hosts.',
        tags: ['Metrics'],
    )]
    #[OA\RequestBody(
        description: 'List of website IDs and range.',
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
                    description: 'Date range filter (default 7d)',
                    example: '7d'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::OK->value,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'websiteId', type: 'integer', example: 1),
                    new OA\Property(property: 'websiteName', type: 'string', example: 'mysite'),
                    new OA\Property(property: 'checkedAt', type: 'string', format: 'date-time', example: '2024-12-22 12:18:48'),
                    new OA\Property(property: 'cpuUsage', type: 'number', format: 'float', example: 1.409282139114),
                    new OA\Property(property: 'memoryUsage', type: 'number', format: 'float', example: 12.438707561675),
                    new OA\Property(property: 'diskUsage', type: 'number', format: 'float', example: 41.113437517169)
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
                new OA\Property(property: 'error', type: 'string', example: 'No website IDs provided.')
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
    #[Route('/api/v1/metrics/getbymultiplehosts', name: 'api_metrics_getbymultiplehosts', methods: ['POST'])]
    public function getMetricsByMultipleHosts(Request $request, UserInterface $user): JsonResponse
    {
        $client = $user->getClientId();
        $userTimezone = $user->getTimezone();
        $data = json_decode($request->getContent(), true);
        $websiteIds = $data['websiteIds'] ?? [];
        $filter = $data['filter'] ?? '7d';

        if (empty($websiteIds) || !is_array($websiteIds)) {
            return new JsonResponse(['error' => 'No website IDs provided.'], Response::HTTP_BAD_REQUEST);
        }

        $metricses = [];
        foreach ($websiteIds as $websiteId) {
            $website = $this->websiteRepository->findOneBy([
                'id' => $websiteId,
                'client' => $client,
            ]);

            if (!$website) {
                continue;
            }

            $agent = $website->getAgent();
            if ($agent && $agent->isInstalled()) {
                $query = $this->entityManager->createQueryBuilder()
                    ->select('m')
                    ->from(Metrics::class, 'm')
                    ->where('m.website = :website')
                    ->setParameter('website', $website);

                $query = $this->dateRangeFilterService->applyDateRangeFilter($query, 'm', 'checkedAt', $filter);

                $filteredMetrics = $query->getQuery()->getResult();

                foreach ($filteredMetrics as $metrics) {
                    $checkedAt = $this->timezoneConverter->convertToUserTimezone(
                        $metrics->getCheckedAt(),
                        $userTimezone
                    );
                    $metricses[] = [
                        'websiteId' => $website->getId(),
                        'websiteName' => $website->getName(),
                        'checkedAt' => $checkedAt->format('Y-m-d H:i:s'),
                        'cpuUsage' => $metrics->getCpuUsage(),
                        'memoryUsage' => $metrics->getMemoryUsage(),
                        'diskUsage' => $metrics->getDiskUsage(),
                    ];
                }
            }
        }
        // Order results by checkedAt
        usort($metricses, function ($firstMetrics, $secondMetrics) {
            return strtotime($firstMetrics['checkedAt']) <=> strtotime($secondMetrics['checkedAt']);
        });

        return new JsonResponse($metricses, Response::HTTP_OK);
    }
}
