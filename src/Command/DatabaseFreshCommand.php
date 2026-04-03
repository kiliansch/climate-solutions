<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\DatabaseSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:fresh',
    description: 'Drops the database, runs all migrations, and seeds the database with development fixtures.',
)]
class DatabaseFreshCommand extends Command
{
    public function __construct(
        private readonly DatabaseSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->isInteractive()) {
            if (!$io->confirm('This will drop and recreate the database. Continue?', false)) {
                $io->warning('Aborted.');

                return Command::SUCCESS;
            }
        }

        $application = $this->getApplication();
        if ($application === null) {
            $io->error('Application is not available.');

            return Command::FAILURE;
        }

        // 1. Drop the database
        $dropInput = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--if-exists' => true,
        ]);
        $dropInput->setInteractive(false);
        $exitCode = $application->find('doctrine:database:drop')->run($dropInput, $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error(sprintf('Command "doctrine:database:drop" failed with exit code %d.', $exitCode));

            return Command::FAILURE;
        }

        // 2. Create the database
        $createInput = new ArrayInput(['command' => 'doctrine:database:create']);
        $createInput->setInteractive(false);
        $exitCode = $application->find('doctrine:database:create')->run($createInput, $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error(sprintf('Command "doctrine:database:create" failed with exit code %d.', $exitCode));

            return Command::FAILURE;
        }

        // 3. Run all migrations
        $migrateInput = new ArrayInput(['command' => 'doctrine:migrations:migrate']);
        $migrateInput->setInteractive(false);
        $exitCode = $application->find('doctrine:migrations:migrate')->run($migrateInput, $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error(sprintf('Command "doctrine:migrations:migrate" failed with exit code %d.', $exitCode));

            return Command::FAILURE;
        }

        // 4. Seed the database
        $io->section('Seeding database...');
        $this->seeder->seed();

        $io->success('Database has been freshly set up and seeded.');

        return Command::SUCCESS;
    }
}
