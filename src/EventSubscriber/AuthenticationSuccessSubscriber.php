<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->getPathInfo() === '/api/login') {
            $response = $event->getResponse();

            if ($response) {
                $data = json_decode($response->getContent(), true);
            } else {
                $data = [];
            }

            $data['name'] = $user->getName();
            $data['email'] = $user->getEmail();
            $data['timezone'] = $user->getTimezone();

            $event->setResponse(new JsonResponse($data));
        }
    }
}

