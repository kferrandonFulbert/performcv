<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\{ProfilePhoto, SecurityAudit};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Réencode les portraits stockés pour en retirer les métadonnées privées.
 *
 * Le mode --dry-run recense les fichiers concernés sans les modifier.
 */
#[AsCommand(name: 'app:photos:clean', description: 'Ré-encode les photos stockées pour supprimer leurs métadonnées privées.')]
final class CleanPhotosCommand extends Command
{
    public function __construct(private ProfilePhoto $photos, private SecurityAudit $audit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Vérifie les photos sans réécrire les fichiers.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->photos->cleanStored($dryRun);
        // File-only maintenance, like audit-file purges: no business DB write.
        $this->audit->record($result['failed'] ? 'photos.clean_failed' : ($dryRun ? 'photos.clean_simulated' : 'photos.cleaned'), null);
        $output->writeln(sprintf('%d photo(s) %s ; %d échec(s).', $result['processed'], $dryRun ? 'vérifiée(s), sans écriture' : 'nettoyée(s)', $result['failed']));

        return $result['failed'] ? Command::FAILURE : Command::SUCCESS;
    }
}
