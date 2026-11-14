<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SecurityAudit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Purge les événements d’audit en base et les anciens fichiers du journal de sécurité.
 */
#[AsCommand(name: 'app:audit:purge', description: 'Supprime les événements d’audit de plus de six mois.')]
class PurgeAuditCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, #[Autowire('%kernel.logs_dir%/audit/%kernel.environment%')] private string $logDir, private SecurityAudit $securityAudit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les événements sans les supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->em->getConnection();
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-6 months')->format('Y-m-d H:i:s');
        $dryRun = $input->getOption('dry-run');
        $count = $dryRun
            ? (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_events WHERE occurred_at < ?', [$cutoff])
            : $connection->executeStatement('DELETE FROM audit_events WHERE occurred_at < ?', [$cutoff]);
        $output->writeln(sprintf('%d événement(s) %s.', $count, $dryRun ? 'à supprimer (simulation)' : 'supprimé(s)'));
        $files = 0;
        foreach (glob($this->logDir.'/audit_security-*.log') ?: [] as $file) {
            if (!preg_match('/^audit_security-([0-9]{4}-[0-9]{2}-[0-9]{2})\.log$/D', basename($file), $match)
                || $match[1] >= substr($cutoff, 0, 10) || !is_file($file) || is_link($file)) {
                continue;
            }
            if (!$dryRun && !unlink($file)) {
                $output->writeln('Impossible de supprimer un ancien fichier de sécurité.');
                return Command::FAILURE;
            }
            ++$files;
        }
        $output->writeln(sprintf('%d fichier(s) de sécurité %s.', $files, $dryRun ? 'à supprimer (simulation)' : 'supprimé(s)'));
        $this->securityAudit->record($dryRun ? 'audit.purge_simulated' : 'audit.purged', null);

        return Command::SUCCESS;
    }
}
