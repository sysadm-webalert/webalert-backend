<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\User;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Security;
use App\Service\MailerService;
use Symfony\Component\Uid\Uuid;
use App\Service\EventsService;
use App\Enum\EventsType;
use OpenApi\Attributes as OA;

class OrganizationController extends AbstractController
{
    private $entityManager;
    private MailerService $mailerservice;
    private $eventsService;

    public function __construct(EntityManagerInterface $entityManager, MailerService $mailerservice, EventsService $eventsService)
    {
        $this->entityManager = $entityManager;
        $this->mailerservice = $mailerservice;
        $this->eventsService = $eventsService;
    }

    #[OA\Get(
        summary: 'Get members.',
        tags: ['Organization'],
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 2),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', example: 'example@example.com'),
                new OA\Property(property: 'isActive', type: 'boolean', example: true),
                new OA\Property(property: 'roles', type: 'array', example: ["ROLE_MANAGER", "ROLE_USER"], items: new OA\Items(type: 'string')),
                new OA\Property(property: 'notification_email', type: 'string', example: 'example@example.com'),
                new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Madrid'),
            ]
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
        response: 405,
        description: 'Method not allowed.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'An Error Occurred: Method Not Allowed.')
            ]
        )
    )]
    #[Route('/api/v1/organization/members', name: 'api_get_members', methods: ['GET'])]
    public function getMembers(EntityManagerInterface $entityManager): JsonResponse {

        $user = $this->getUser();
        $client = $user->getClientId();

        $users = $this->entityManager->getRepository(User::class)->findBy(['client' => $client]);

        $membersData = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'isActive' => $user->isActive(),
                'roles' => $user->getRoles(),
                'notification_email' => $user->getNotificationEmail(),
                'timezone' => $user->getTimezone(),
            ];
        }, $users);

        return new JsonResponse($membersData, Response::HTTP_OK);    
    }

    #[OA\Post(
        summary: 'Change member.',
        tags: ['Organization'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID of the member.',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        description: 'Change member role or status',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'action', type: 'string', example: 'enable'),
                new OA\Property(property: 'role', type: 'string', example: 'ROLE_MANAGER')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Member updated successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid request data.')
            ]
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
                new OA\Property(property: 'error1', type: 'string', example: 'You do not have permission to change this member.'),
                new OA\Property(property: 'error2', type: 'string', example: 'You cannot disable yourself.'),
                new OA\Property(property: 'error3', type: 'string', example: 'You cannot change your own role.')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not found.')
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
    #[Route('/api/v1/organization/change_member/{id}', name: 'api_change_members', methods: ['POST'])]
    public function changeMember(int $id, Request $request, EntityManagerInterface $entityManager ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $data = json_decode($request->getContent(), true);
    
        $action = $data['action'] ?? null;
        $newRole = $data['role'] ?? null;
    
        if (!$id || (!$action && !$newRole)) {
            return new JsonResponse(['error' => 'Invalid request data.'], Response::HTTP_BAD_REQUEST);
        }
    
        $user = $this->entityManager->getRepository(User::class)->find($id);
    
        if (!$user) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }
        
        $currentUser = $this->getUser();

        if ($user->getClientId() !== $currentUser->getClientId()) {
            return new JsonResponse(['error' => 'You do not have permission to change this member.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->getId() === $currentUser->getId() && $action === 'disable') {
            return new JsonResponse(['error' => 'You cannot disable yourself.'], Response::HTTP_FORBIDDEN);
        }
    
        if ($user->getId() === $currentUser->getId() && $newRole) {
            return new JsonResponse(['error' => 'You cannot change your own role.'], Response::HTTP_FORBIDDEN);
        }

        if ($action) {
            if ($action === 'enable') {
                $user->setActive(true);
            } elseif ($action === 'disable') {
                $user->setActive(false);
            } else {
                return new JsonResponse(['error' => 'Invalid action.'], Response::HTTP_BAD_REQUEST);
            }
        }
    
        if ($newRole) {
            $validRoles = ['ROLE_USER', 'ROLE_MANAGER'];
            if (!in_array($newRole, $validRoles)) {
                return new JsonResponse(['error' => 'Invalid role.'], Response::HTTP_BAD_REQUEST);
            }
    
            $user->setRoles([$newRole]);
        }
    
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    
        return new JsonResponse(['message' => 'Member updated successfully.'], Response::HTTP_OK);
    }

    #[OA\Delete(
        summary: 'Delete member.',
        tags: ['Organization'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID of the member.',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'OK.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Member deleted successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'You cannot delete yourself.'),
                new OA\Property(property: 'error2', type: 'string', example: 'You do not have permission to delete this member.'),
            ]
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
        response: 404,
        description: 'Not found.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not found.')
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
    #[Route('/api/v1/organization/delete_members/{id}', name: 'api_delete_members', methods: ['DELETE'])]
    public function deleteMember(int $id, EntityManagerInterface $entityManager ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
    
        $user = $this->entityManager->getRepository(User::class)->find($id);
    
        if (!$user) {
            return new JsonResponse(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }
    
        $currentUser = $this->getUser();

        if ($user->getId() === $currentUser->getId()) {
            return new JsonResponse(['error' => 'You cannot delete yourself.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->getClientId() !== $currentUser->getClientId()) {
            return new JsonResponse(['error' => 'You do not have permission to delete this member.'], Response::HTTP_FORBIDDEN);
        }
        
        $eventMessage = "The user " . $user->getEmail() . " has been deleted by ". $currentUser->getName();
        $this->eventsService->createEvent($user, $eventMessage, EventsType::USER_DELETED);
        
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    
        return new JsonResponse(['message' => 'Member deleted successfully.'], Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'Invite member.',
        tags: ['Organization'],
    )]
    #[OA\RequestBody(
        description: 'Email and role of the member to invite.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'newmember@example.com'),
                new OA\Property(property: 'role', type: 'string', example: 'ROLE_USER')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Response successfully.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Member invited successfully.')
            ]
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
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'Email and role are required.'),
                new OA\Property(property: 'error2', type: 'string', example: 'Email already exists'),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'You are not associated with any organization.')
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
    #[Route('/api/v1/organization/invite', name: 'api_invite_member', methods: ['POST'])]
    public function inviteMember(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['role'])) {
            return new JsonResponse(['error' => 'Email and role are required'], Response::HTTP_BAD_REQUEST);
        }

        $userRepository = $entityManager->getRepository(User::class);

        // Check if the email is already registered
        $userEmail = $data['email'];
        $existingUser = $userRepository->findOneBy(['email' => $userEmail]);

        if ($existingUser) {
            return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
        }

        // Get the current user and their client (organization)
        $currentUser = $this->getUser();
        $client = $currentUser->getClientId();

        if (!$client) {
            return new JsonResponse(['error' => 'You are not associated with any organization.'], Response::HTTP_FORBIDDEN);
        }

        $role = ($data['role'] === 'ROLE_MANAGER') ? 'ROLE_MANAGER' : 'ROLE_USER';

        // Create the new user for the same client
        $user = new User();
        $user->setEmail($userEmail);
        $user->setClientId($client);
        $user->setActive(false);
        $user->setRoles([$role]);
        $confirmationToken = Uuid::v4();
        $user->setConfirmationToken($confirmationToken);

        $entityManager->persist($user);
        $entityManager->flush();

        $frontendBaseUrl = $this->getParameter('frontend_base_url');
        $confirmationUrl = $frontendBaseUrl . '/invitation?token=' . $confirmationToken;

        $this->mailerservice->sendInvite($user, $confirmationUrl);

        return new JsonResponse(['message' => 'Member invited successfully.'], Response::HTTP_CREATED);
    }

    #[OA\Post(
        summary: 'Register member.',
        tags: ['Organization'],
    )]
    #[OA\RequestBody(
        description: 'Register a new member using the invitation token.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'password', type: 'string', example: 'SecurePassword123'),
                new OA\Property(property: 'token', type: 'string', example: 'c5b5d06e-4c19-4f92-8f1e-55a5f1b1c6c8')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Member registered successfully.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Member registered successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Name, password, token, are required.')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'JWT Token not found.')
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
    #[Route('/api/v1/organization/register_member', name: 'api_register_member', methods: ['POST'])]
    public function registerMember(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['password'], $data['token'])) {
            return new JsonResponse(['error' => 'Name, password, token, are required.'], Response::HTTP_BAD_REQUEST);
        }
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['confirmationToken' => $data['token']]);

        $user->setName($data['name']);
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->setActive(true);
        $user->setConfirmationToken(null);
        $entityManager->flush();

        $eventMessage = "The user " . $user->getEmail() . " has been registered";
        $this->eventsService->createEvent($user, $eventMessage, EventsType::USER_JOINED);

        return new JsonResponse(['message' => 'Member registered successfully'], Response::HTTP_CREATED);
    }
}

