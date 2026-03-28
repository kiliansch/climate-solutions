<?php

declare(strict_types=1);

namespace App\Command;

use App\CalendarBundle\Entity\Unavailability;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:normalise-unavailability',
    description: 'Fix unavailability records where startAt == endAt by setting endAt to end-of-day (23:59:59).',
)]
class NormaliseUnavailabilityCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $repository = $this->entityManager->getRepository(Unavailability::class);

        /** @var Unavailability[] $records */
        $records = $repository->createQueryBuilder('u')
            ->andWhere('u.startAt = u.endAt')
            ->getQuery()
            ->getResult();

        $count = count($records);

        if ($count === 0) {
            $io->success('No broken unavailability records found.');

            return Command::SUCCESS;
        }

        foreach ($records as $record) {
            $record->setEndAt($record->getStartAt()->setTime(23, 59, 59));
        }

        $this->entityManager->flush();

        $io->success(sprintf('Normalised %d unavailability record(s).', $count));

        return Command::SUCCESS;
    }
}
