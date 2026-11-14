<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AuditEventsRepository;
use App\Service\SecurityAudit;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Affiche un extrait du journal métier, avec un filtre facultatif sur l’acteur.
 */
#[AsCommand(name: 'app:audit:list', description: 'Consulte les événements d’audit (accès réservé à l’exploitation).')]
class ListAuditCommand extends Command
{
    public function __construct(private AuditEventsRepository $events, private SecurityAudit $securityAudit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Identifiant du compte acteur.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre de lignes, de 1 à 100.', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actor = $input->getOption('actor');
        $limit = $input->getOption('limit');
        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100
            || ($actor !== null && (!is_string($actor) || !ctype_digit($actor) || (int) $actor < 1 || strlen($actor) > 10))) {
            $output->writeln('Filtres invalides : actor positif, limit entre 1 et 100.');
            return Command::INVALID;
        }
        $this->securityAudit->record('audit.read', null);
        foreach ($this->events->recent($actor === null ? null : (int) $actor, (int) $limit) as $event) {
            $output->writeln(json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
