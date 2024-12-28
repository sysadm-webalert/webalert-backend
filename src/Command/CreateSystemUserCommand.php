<?php
//php bin/console make:command app:create-system-user
//php bin/console app:create-system-user
namespace App\Command;

use App\Entity\User;
use App\Entity\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-system-user',
    description: 'This command creates the built in user, used for check sites.',
)]
class CreateSystemUserCommand extends Command
{
    private $entityManager;
    private $passwordHasher;
    private $fromEmail;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, string $fromEmail)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->fromEmail = $fromEmail;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->fromEmail]);
        if ($existingUser) {
            $io->success('The system user already exists.');
            return Command::SUCCESS;
        }  
        $password = $this->generateRandomPassword();

        $client = new Client();
        $client->setName("system");
        $client->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($client);

        $user = new User();
        $user->setEmail($this->fromEmail);
        $user->setClientId($client);
        $user->setName("Operator");
        $user->setRoles(['ROLE_OPERATOR']);
        $user->setActive(true);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'System user created successfully. Use this user for the scraper validation. Email: %s Password: %s',
            $this->fromEmail,
            $password
        ));
        return Command::SUCCESS;
    }

    private function generateRandomPassword(int $length = 24): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $charactersLength = strlen($characters);
        $randomPassword = '';

        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomPassword;
    }
}
