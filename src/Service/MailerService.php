<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use App\Repository\ClientRepository;
use App\Entity\Status;
use App\Entity\Metrics;

class MailerService
{
    private $mailer;
    private $clientRepository;
    private $fromEmail;

    public function __construct(MailerInterface $mailer, ClientRepository $clientRepository, string $fromEmail)
    {
        $this->mailer = $mailer;
        $this->clientRepository = $clientRepository;
        $this->fromEmail = $fromEmail;
    }
    
    public function sendRegister($user, $confirmationUrl): void
    {

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Welcome!')
            ->htmlTemplate('emails/register.html.twig')
            ->context([
                'user' => $user,
                'confirmationUrl' => $confirmationUrl
            ]);

        $this->mailer->send($email);
    }

    public function sendInvite($user, $confirmationUrl): void
    {

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Welcome!')
            ->htmlTemplate('emails/invite.html.twig')
            ->context([
                'user' => $user,
                'organization' => $user->getClientId()->getName(),
                'confirmationUrl' => $confirmationUrl
            ]);

        $this->mailer->send($email);
    }

    public function restorePassword($user, $resetUrl): void
    {

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Recover your password')
            ->htmlTemplate('emails/restore.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl
            ]);

        $this->mailer->send($email);
    }


    public function sendAlert(object $entity, string $kind): void
    {
        $website = $entity instanceof Status ? $entity->getWebsiteId() : $entity->getWebsiteid();
        $client = $website->getClientId();
        $emails = $this->clientRepository->getNotificationEmails($client->getId());

        if (empty($emails)) {
            $this->logger->warning("No notification emails found for client ID: " . $client->getId());
            return;
        }

        $template = $entity instanceof Status 
        ? 'emails/status_alert.html.twig' 
        : 'emails/metrics_alert.html.twig';

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to(...$emails)
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Alert detected for host: ' . $website->getUrl() . ' at ' . $entity->getCheckedAt()->format('Y-m-d H:i:s'))
            ->htmlTemplate($template)
            ->context([
                'status' => $entity instanceof Status ? $entity : null,
                'metrics' => !$entity instanceof Status ? $entity : null,
                'website' => $website,
                'kind' => $kind,
            ]);

        $this->mailer->send($email);
    }

    public function sendRecoverAlert(object $entity, string $kind): void
    {
        $website = $entity instanceof Status ? $entity->getWebsiteId() : $entity->getWebsiteid();
        $client = $website->getClientId();
        $emails = $this->clientRepository->getNotificationEmails($client->getId());

        if (empty($emails)) {
            $this->logger->warning("No notification emails found for client ID: " . $client->getId());
            return;
        }

        $template = $entity instanceof Status 
        ? 'emails/status_restored.html.twig' 
        : 'emails/metrics_restored.html.twig';

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to(...$emails)
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Alert restored for host: ' . $website->getUrl() . ' at ' . $entity->getCheckedAt()->format('Y-m-d H:i:s'))
            ->htmlTemplate($template)
            ->context([
                'status' => $entity instanceof Status ? $entity : null,
                'metrics' => !$entity instanceof Status ? $entity : null,
                'website' => $website,
                'kind' => $kind,
            ]);

        $this->mailer->send($email);
    }

}