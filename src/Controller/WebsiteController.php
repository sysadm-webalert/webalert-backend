<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\WebsiteRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\Website;
use App\Entity\Threshold;
use App\Entity\Agent;
use App\Service\TimezoneConverter;
use App\Service\SnapshotService;
use App\Service\ValidateEntityService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Enum\EventsType;
use App\Service\EventsService;
use OpenApi\Attributes as OA;

class WebsiteController extends AbstractController
{
    private $entityManager;
    private $websiteRepository;
    private $timezoneConverter;
    private $snapshotService;
    private $validateEntitytService;
    private $eventsService;

    public function __construct(EntityManagerInterface $entityManager, WebsiteRepository $websiteRepository, TimezoneConverter $timezoneConverter, SnapshotService $snapshotService, ValidateEntityService $validateEntitytService, EventsService $eventsService)
    {
        $this->websiteRepository = $websiteRepository;
        $this->entityManager = $entityManager;
        $this->timezoneConverter = $timezoneConverter;
        $this->snapshotService = $snapshotService;
        $this->validateEntitytService = $validateEntitytService;
        $this->eventsService = $eventsService;
    }

    #[OA\Get(
        summary: 'Get Websites.',
        tags: ['Website'],
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'url', type: 'string', example: 'https://example.com')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'JWT Token not found.')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Access Denied.')
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]
    #[Route('/api/v1/website/getall', name: 'api_website_getall', methods: ['GET'])]
    public function getAll(): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_OPERATOR');
        $websites = $this->websiteRepository->findBy(['isActive' => true]);
        
        $filteredData = array_map(function ($website) {
            return [
                'id' => $website->getId(),
                'url' => $website->getUrl()
            ];
        }, $websites);
        
        return new JsonResponse($filteredData, Response::HTTP_OK);
    }

    #[OA\Get(
        summary: 'Get websites.',
        tags: ['Website'],
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'url', type: 'string', example: 'https://example.com'),
                    new OA\Property(property: 'name', type: 'string', example: 'Mysite'),
                    new OA\Property(property: 'Threshold', type: 'object', nullable: true,
                        properties: [
                            new OA\Property(property: 'codes', type: 'integer', example: 200),
                            new OA\Property(property: 'maxResponse', type: 'integer', example: 5000),
                            new OA\Property(property: 'maxCPU', type: 'integer', example: 90),
                            new OA\Property(property: 'maxRAM', type: 'integer', example: 90),
                            new OA\Property(property: 'maxDISK', type: 'integer', example: 90),
                        ]
                    ),
                    new OA\Property(property: 'agent', type: 'object', nullable: true,
                        properties: [
                            new OA\Property(property: 'isInstalled', type: 'boolean', example: true),
                            new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                            new OA\Property(property: 'lastCheckedAt', type: 'string', format: 'date-time', example: '2024-12-27 21:45:32'),
                        ]
                    ),
                    new OA\Property(property: 'lastMetric', type: 'object', nullable: true,
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2188),
                            new OA\Property(property: 'cpu_usage', type: 'integer', example: 50),
                            new OA\Property(property: 'memory_usage', type: 'integer', example: 45),
                            new OA\Property(property: 'disk_usage', type: 'integer', example: 45),
                            new OA\Property(property: 'checkedAt', type: 'string', format: 'date-time', example: '2024-12-27 21:45:32'),
                        ]
                    ),
                    new OA\Property(property: 'snapshotUrl', type: 'string', example: 'http://api.webalert.digital/uploads/snapshots/Mycompany-Mysite.jpg'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'JWT Token not found.'),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Client not found or user not authenticated.')
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]
    #[Route('/api/v1/website/getbyclient', name: 'api_website_getbyclient', methods: ['GET'])]
    public function getByClient(UrlGeneratorInterface $urlGenerator): JsonResponse {
        $user = $this->getUser();
        $userTimezone = $user->getTimezone();
        if (!$user || !$user->getClientId()) {
            return new JsonResponse(['error' => 'Client not found or user not authenticated'], Response::HTTP_NOT_FOUND);
        }

        $client = $user->getClientId();

        // Getting sites by client
        $websites = $this->websiteRepository->findBy(['client' => $client]);

        // Filtering data with agent and metrics
        $filteredData = array_map(function ($website) use ($userTimezone, $client, $urlGenerator) {
            $agent = $website->getAgent();
            $threshold = $website->getThreshold();
            $metrics = $website->getMetrics();

            // Getting last metric
            $lastMetric = null;
            if (!$metrics->isEmpty()) {
                $lastMetric = $metrics->last();
            }

            // Generate snapshot URL
            $sanitizedUrl = preg_replace('/[^a-zA-Z0-9-_]/', '_', parse_url($website->getUrl(), PHP_URL_HOST));
            $fileName = sprintf('%s-%s.jpg', $sanitizedUrl, $client->getName());
            $snapshotUrl = $urlGenerator->generate('app_snapshot_public', ['filename' => $fileName,], UrlGeneratorInterface::ABSOLUTE_URL);

            return [
                'id' => $website->getId(),
                'url' => $website->getUrl(),
                'name' => $website->getName(),
                'Threshold' => $threshold ? [
                    'codes' => $threshold->getHttpCode(),
                    'maxResponse' => $threshold->getMaxResponse(),
                    'maxCPU' => $threshold->getMaxCPU(),
                    'maxRAM' => $threshold->getMaxRAM(),
                    'maxDISK' => $threshold->getMaxDISK(),
                ] : null, // If no Threshold, give null
                'agent' => $agent ? [
                    'isInstalled' => $agent->isInstalled(),
                    'version' => $agent->getVersion(),
                    'lastCheckedAt' => $agent->getLastCheckedAt() ? $this->timezoneConverter->convertToUserTimezone($agent->getLastCheckedAt(), $userTimezone)->format('Y-m-d H:i:s') : null,
                ] : null, // If no agent, give null
                'lastMetric' => $lastMetric ? [
                    'id' => $lastMetric->getId(),
                    'cpu_usage' => $lastMetric->getCpuUsage(),
                    'memory_usage' => $lastMetric->getMemoryUsage(),
                    'disk_usage' => $lastMetric->getDiskUsage(),
                    'checkedAt' => $this->timezoneConverter->convertToUserTimezone($lastMetric->getCheckedAt(), $userTimezone)->format('Y-m-d H:i:s'),
            ] : null, // If no metrics, give null
            'snapshotUrl' => $snapshotUrl, // URL of the snapshot
            ];
        }, $websites);

        return new JsonResponse($filteredData, Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'Set site.',
        tags: ['Website'],
    )]
    #[OA\RequestBody(
        description: 'Add new site.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'url', type: 'string', example: 'https://example.com'),
                new OA\Property(property: 'name', type: 'string', example: 'ExampleSite'),
                new OA\Property(property: 'maxResponse', type: 'integer', example: 5000),
                new OA\Property(property: 'codes', type: 'string', example: '200')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Response successfully.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Website added successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'Must provide all valid values'),
                new OA\Property(property: 'error2', type: 'string', example: 'The value \"Example Site\" is not a valid site name. It contains invalid characters.'),
                new OA\Property(property: 'error3', type: 'string', example: 'The value \"example.com\" is not a valid URL. It cannot contain paths.'),
                new OA\Property(property: 'error4', type: 'string', example: 'The value \"-1\" is not a valid response time.'),
                new OA\Property(property: 'error5', type: 'string', example: 'The value \"50\" is not a valid HTTP code or range.')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Unauthorized access')
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]
    #[Route('/api/v1/website/add', name: 'api_website_add', methods: ['POST'])]
    public function addWebsite(Request $request, ValidatorInterface $validator): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        if ( !$data || !isset($data['url']) || !is_string($data['url']) || !isset($data['name']) || !is_string($data['name']) || !isset($data['maxResponse']) || !is_numeric($data['maxResponse']) || empty($data['codes']) || !is_string($data['codes'])) {
            return new JsonResponse(['error' => "Must provide all valid values"], Response::HTTP_BAD_REQUEST);
        }

        $website = new Website();
        $website->setUrl($data['url']);
        $website->setName($data['name']);
        $website->setClientId($client = $user->getClientId());
        $website->setActive(true);

        $errors = $this->validateEntitytService->validateEntity($website, $validator);
        if (!empty($errors)) {
            return new JsonResponse(['error' => implode("\n", $errors)], Response::HTTP_BAD_REQUEST);
        }

        $agent = new Agent();
        $agent->setInstalled(false);
        $agent->setVersion(null);
        $agent->setLastCheckedAt(null);
        $website->setAgent($agent);

        $threshold = new Threshold();
        $threshold->setWebsite($website);
        $threshold->setClient($client);
        $threshold->setHttpCode($data['codes']);
        $threshold->setMaxResponse($data['maxResponse']);
        $threshold->setCreatedAt(new \DateTimeImmutable());

        $errors = $this->validateEntitytService->validateEntity($threshold, $validator);
        if (!empty($errors)) {
            return new JsonResponse(['error' => implode("\n", $errors)], Response::HTTP_BAD_REQUEST);
        }

        $website->setThreshold($threshold);

        $this->entityManager->persist($website);
        $this->entityManager->flush();

        $eventMessage = "The site " . $website->getName() . " has been added by ". $user->getEmail();
        $this->eventsService->createEvent($user, $eventMessage, EventsType::SITE_CREATED);

        return new JsonResponse(['message' => 'Website added successfully'], Response::HTTP_CREATED);
    }

    #[OA\Put(
        summary: 'Update a website.',
        tags: ['Website'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID of the website.',
        schema: new OA\Schema(type: 'integer', example: 8)
    )]
    #[OA\RequestBody(
        description: 'Website to update.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'url', type: 'string', example: 'https://example.com'),
                new OA\Property(property: 'name', type: 'string', example: 'ExampleSite'),
                new OA\Property(property: 'maxResponse', type: 'integer', example: 5000),
                new OA\Property(property: 'codes', type: 'string', example: '200')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Website updated successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'Must provide all valid values'),
                new OA\Property(property: 'error2', type: 'string', example: 'The value \"Example Site\" is not a valid site name. It contains invalid characters.'),
                new OA\Property(property: 'error3', type: 'string', example: 'The value \"example.com\" is not a valid URL. It cannot contain paths.'),
                new OA\Property(property: 'error4', type: 'string', example: 'The value \"-1\" is not a valid response time.'),
                new OA\Property(property: 'error5', type: 'string', example: 'The value \"50\" is not a valid HTTP code or range.')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Unauthorized access')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Website not found')
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]    
    #[Route('/api/v1/website/update/{id}', name: 'api_website_update', methods: ['PUT'])]
    public function updateWebsite($id, Request $request, ValidatorInterface $validator): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $website = $this->websiteRepository->find($id);
        $user = $this->getUser();

        if (!$website) {
            return new JsonResponse(['error' => 'Website not found'], Response::HTTP_NOT_FOUND);
        }

        if ($website->getClientId() !== $user->getClientId()) {
            return new JsonResponse(['error' => 'Unauthorized access'], Response::HTTP_FORBIDDEN);
        }

        if (isset($data['url'])) {
            $website->setUrl($data['url']);
        }

        if (isset($data['name'])) {
            $website->setName($data['name']);
        }

        $errors = $this->validateEntitytService->validateEntity($website, $validator);
        if (!empty($errors)) {
            return new JsonResponse(['error' => implode("\n", $errors)], Response::HTTP_BAD_REQUEST);
        }
        
        $website->setUpdatedAt(new \DateTimeImmutable());

        $threshold = $website->getThreshold();
        if ($threshold) {
            if (isset($data['codes'])) {
                $threshold->setHttpCode($data['codes']);
            }
            if (isset($data['maxResponse'])) {
                if (!is_numeric($data['maxResponse'])) {
                    return new JsonResponse(['error' => 'Invalid maxResponse value'], Response::HTTP_BAD_REQUEST);
                }
                $threshold->setMaxResponse((float)$data['maxResponse']);
            }
            if (isset($data['maxCPU'])) {
                $threshold->setMaxCPU((float)$data['maxCPU']);
            }
            if (isset($data['maxRAM'])) {
                $threshold->setMaxRAM((float)$data['maxRAM']);
            }
            if (isset($data['maxDISK'])) {
                $threshold->setMaxDISK((float)$data['maxDISK']);
            }

            $threshold->setUpdatedAt(new \DateTimeImmutable());

            $errors = $this->validateEntitytService->validateEntity($threshold, $validator);
            if (!empty($errors)) {
                return new JsonResponse(['error' => implode("\n", $errors)], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($threshold);
        }
        $this->entityManager->flush();

        $eventMessage = "The site " . $website->getName() . " has been updated by ". $user->getEmail();
        $this->eventsService->createEvent($user, $eventMessage, EventsType::SITE_UPDATED);

        return new JsonResponse(['message' => 'Website updated successfully'], Response::HTTP_OK);
    }

    #[OA\Delete(
        summary: 'Delete website.',
        tags: ['Website'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID of the site.',
        schema: new OA\Schema(type: 'integer', example: 8)
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Website deleted successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Unauthorized access')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Website not found')
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]
    #[Route('/api/v1/website/delete/{id}', name: 'api_website_delete', methods: ['DELETE'])]
    public function deleteWebsite($id): JsonResponse {
        $website = $this->websiteRepository->find($id);

        if (!$website) {
            return new JsonResponse(['error' => 'Website not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();

        if ($website->getClientId() !== $user->getClientId()) {
            return new JsonResponse(['error' => 'Unauthorized access'], Response::HTTP_FORBIDDEN);
        }
        $snapshotDeleted = $this->snapshotService->deleteSnapshot($website->getUrl(), $user->getClientId()->getName());

        $eventMessage = "The site " . $website->getName() . " has been deleted by ". $user->getEmail();
        $this->eventsService->createEvent($user, $eventMessage, EventsType::SITE_DELETED);

        $this->entityManager->remove($website);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Website deleted successfully'], Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'Create a website snapshot.',
        tags: ['Website'],
    )]
    #[OA\RequestBody(
        description: 'Website URL to capture a snapshot.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'url', type: 'string', example: 'https://example.com')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Snapshot created successfully'),
                new OA\Property(property: 'imagePath', type: 'string', example: '/snapshots/example-client/example_com.jpg')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'URL is required')
            ]
        )
    )]
    #[OA\Response(
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal Server Error.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Failed to capture snapshot.')
            ]
        )
    )]
    #[Route('/api/v1/website/snapshot', name: 'create_snapshot', methods: ['POST'])]
    public function createSnapshot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? null;
        $user = $this->getUser();

        if (!$url) {
            return new JsonResponse(['error' => 'URL is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $imagePath = $this->snapshotService->captureSnapshot($url, $user->getClientId()->getName());
            if (!$imagePath) {
                return new JsonResponse(['error' => 'Failed to capture snapshot.',], Response::HTTP_INTERNAL_SERVER_ERROR);}
                
            return new JsonResponse(['message' => 'Snapshot created successfully','imagePath' => $imagePath], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Exception: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
