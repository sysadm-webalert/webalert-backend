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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use OpenApi\Attributes as OA;
use App\Enum\ErrorType;

class UserController extends AbstractController
{
    private $entityManager;
    private MailerService $mailerservice;
    private UrlGeneratorInterface $urlGenerator;
    private const EXAMPLE_EMAIL = 'example@example.com';
    private const RESET_EMAIL = 'Reset user password.';

    public function __construct(EntityManagerInterface $entityManager, MailerService $mailerservice, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->mailerservice = $mailerservice;
        $this->urlGenerator = $urlGenerator;
    }

    #[OA\Post(
        summary: 'Set User.',
        tags: ['User'],
    )]
    #[OA\RequestBody(
        description: 'User registration data.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: self::EXAMPLE_EMAIL),
                new OA\Property(property: 'password', type: 'string', example: 'securepassword'),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'organization', type: 'string', example: 'Mycompany')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Response successfully.',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'User registered successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'Email, password, name, and organization are required'),
                new OA\Property(property: 'error2', type: 'string', example: 'Email already exists'),
                new OA\Property(property: 'error3', type: 'string', example: 'Organization already exists. Please contact with the owner to request an invitation.')
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
    #[Route('/api/v1/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'], $data['organization'])) {
            return new JsonResponse(['error' => 'Email, password, name, and organization are required'], Response::HTTP_BAD_REQUEST);
        }

        $userRepository = $entityManager->getRepository(User::class);
        $clientRepository = $entityManager->getRepository(Client::class);

        $userEmail = $data['email'];
        $organizationName = $data['organization'];

        // Check if user exist
        $user = $userRepository->findOneBy(['email' => $userEmail]);
        if ($user) {
            return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
        }

        // Check if Organization exist
        $client = $clientRepository->findOneBy(['name' => $organizationName]);
        if ($client) {
            return new JsonResponse(['error' => 'Organization already exists. Please contact with the ownwer to request an invitation.'], Response::HTTP_BAD_REQUEST);
        }

        $client = new Client();
        $client->setName($organizationName);
        $client->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($client);

        $user = new User();
        $user->setEmail($userEmail);
        $user->setName($data['name']);
        $user->setClientId($client);
        $user->setRoles(['ROLE_MANAGER']);
        $confirmationToken = Uuid::v4();
        $user->setConfirmationToken($confirmationToken);
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        $confirmationUrl = $this->urlGenerator->generate('user_confirm_email',['token' => $confirmationToken],UrlGeneratorInterface::ABSOLUTE_URL);
        
        $this->mailerservice->sendRegister($user, $confirmationUrl);

        return new JsonResponse(['message' => 'User registered successfully.'], Response::HTTP_CREATED);
    }

    #[OA\Get(
        summary: 'User email verification.',
        tags: ['User'],
    )]
    #[OA\Parameter(
        name: 'token',
        in: 'path',
        required: true,
        description: 'Confirmation token.',
        schema: new OA\Schema(type: 'string', example: 'token')
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Email confirmed successfully. You can now log in.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid or expired confirmation token.')
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
    #[Route('/api/v1/confirm-email/{token}', name: 'user_confirm_email', methods: ['GET'])]
    public function confirmEmail(string $token, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['confirmationToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid or expired confirmation token.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setActive(true);
        $user->setConfirmationToken(null);

        $entityManager->flush();

        return new JsonResponse(['message' => 'Email confirmed successfully. You can now log in.'], Response::HTTP_OK);
    }

    #[OA\Get(
        summary: 'User token validation.',
        tags: ['User'],
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'valid')
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
    #[Route('/api/v1/validate-token', name: 'api_validate_token', methods: ['GET'])]
    public function validateToken(): JsonResponse
    {
        return $this->json(['status' => 'valid']);
    }

    #[OA\Get(
        summary: 'Get user profile.',
        tags: ['User'],
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Madrid'),
                new OA\Property(property: 'notification_email', type: 'string', example: self::EXAMPLE_EMAIL)
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
        response: 405,
        description: ErrorType::METHOD_NOT_ALLOWED->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::METHOD_NOT_ALLOWED->value)
            ]
        )
    )]
    #[Route('/api/v1/profile/get', name: 'api_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_FORBIDDEN);
        }

        $profileData = [
            'timezone' => $user->getTimezone(),
            'notification_email' => $user->getNotificationEmail(),
        ];

        return new JsonResponse($profileData, Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'Update authenticated user profile.',
        tags: ['User'],
    )]
    #[OA\RequestBody(
        description: 'User profile data to update.',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Madrid'),
                new OA\Property(property: 'notification_email', type: 'string', example: self::EXAMPLE_EMAIL)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Profile updated successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'All fields are required: timezone, notification_email.'),
                new OA\Property(property: 'error2', type: 'string', example: 'Invalid timezone provided.')
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
        response: 405,
        description: ErrorType::METHOD_NOT_ALLOWED->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: ErrorType::METHOD_NOT_ALLOWED->value)
            ]
        )
    )]
    #[Route('/api/v1/profile/set', name: 'api_profile_add', methods: ['POST'])]
    public function setProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['timezone'], $data['notification_email'])) {
            return new JsonResponse(['error' => 'All fields are required: timezone, notification_email.'], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($data['timezone'], timezone_identifiers_list())) {
            return new JsonResponse(['error' => 'Invalid timezone provided.'], Response::HTTP_BAD_REQUEST);
        }
        $user->setTimezone($data['timezone']);
        $user->setNotificationEmail($data['notification_email']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Profile updated successfully.'], Response::HTTP_OK);
    }

    #[OA\Post(
        summary: 'User password.',
        tags: ['User'],
    )]
    #[OA\RequestBody(
        description: self::RESET_EMAIL,
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: self::EXAMPLE_EMAIL)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'An email has been sent to your account.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'All fields are required: email.')
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
    #[Route('/api/v1/request-password', name: 'api_request_password', methods: ['POST'])]
    public function requestPassword(Request $request): JsonResponse
    {
        $email = json_decode($request->getContent(), true);
      
        if (!isset($email)) {
            return new JsonResponse(['error' => 'All fields are required: email.'], Response::HTTP_BAD_REQUEST);
        }
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['error' => 'No user found with this email.'], Response::HTTP_NOT_FOUND);
        }

        $resetToken = Uuid::v4();
        $user->setResetToken($resetToken);
        $user->setResetExpiration((new \DateTime())->modify('+1 hour'));

        $frontendBaseUrl = $this->getParameter('frontend_base_url');
        $resetUrl = $frontendBaseUrl . '/restore?token=' . $resetToken;

        $this->mailerservice->restorePassword($user, $resetUrl);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'An email has been sent to your account.'], Response::HTTP_OK);
    }

    #[OA\Post(
        summary: self::RESET_EMAIL,
        tags: ['User'],
    )]
    #[OA\RequestBody(
        description: self::RESET_EMAIL,
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'password', type: 'string', example: 'newsecurepassword'),
                new OA\Property(property: 'token', type: 'string', example: 'token')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: ErrorType::SUCCESS->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Your password has been changed.')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: ErrorType::BAD_REQUEST->value,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'error1', type: 'string', example: 'All fields are required: password, token'),
                new OA\Property(property: 'error2', type: 'string', example: 'Your token has been expired, please request new one.')
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
    #[Route('/api/v1/restart-password', name: 'api_restart_password', methods: ['POST'])]
    public function restartPassword(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
      
        if (!isset($data['password'], $data['token'])) {
            return new JsonResponse(['error' => 'All fields are required: email, token'], Response::HTTP_BAD_REQUEST);
        }
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['resetToken' => $data['token']]);

        if ($user->getResetExpiration() < new \DateTime()) {
            return new JsonResponse(['error' => 'Your token has been expired, please request new one.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setResetToken(null);
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Your password has been changed.'], Response::HTTP_OK);
    }
}

