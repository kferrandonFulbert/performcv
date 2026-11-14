<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{ArrayInput, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lance les purges des consultations et de l’audit, y compris en mode simulation.
 *
 * Les deux purges sont tentées même si la première échoue.
 */
#[AsCommand(name: 'app:retention:nightly', description: 'Exécute les purges quotidiennes des consultations, compteurs et traces.')]
final class NightlyRetentionCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les éléments concernés sans les supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getOption('dry-run') ? 'simulation' : 'purge';
        $success = true;
        foreach (['app:views:purge', 'app:audit:purge'] as $name) {
            $child = new ArrayInput(['command' => $name] + ($mode === 'simulation' ? ['--dry-run' => true] : []));
            $child->setInteractive(false);
            try {
                $ok = $this->getApplication()->doRun($child, $output) === Command::SUCCESS;
            } catch (\Exception) {
                $ok = false;
            }
            $output->writeln(sprintf('%s %s %s %s', gmdate('c'), $mode, $name, $ok ? 'OK' : 'ECHEC'));
            $success = $success && $ok;
        }
        return $success ? Command::SUCCESS : Command::FAILURE;
    }
}
