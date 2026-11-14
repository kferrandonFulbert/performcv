<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CvViews;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Supprime les anciennes consultations de CV et nettoie les compteurs de tentatives périmés.
 */
#[AsCommand(name: 'app:views:purge', description: 'Supprime les consultations de plus de 90 jours.')]
class PurgeViewsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, #[Autowire('%kernel.cache_dir%')] private string $cacheDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Compte sans supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dry = $input->getOption('dry-run');
        $query = $this->em->createQuery(($dry ? 'SELECT COUNT(v.id) FROM ' : 'DELETE FROM ').CvViews::class.' v WHERE v.viewed_at < :cutoff OR v.viewed_at IS NULL')
            ->setParameter('cutoff', new \DateTime('-90 days'));
        $count = $dry ? $query->getSingleScalarResult() : $query->execute();
        $output->writeln(sprintf('%d consultation(s) %s.', $count, $dry ? 'à supprimer' : 'supprimée(s)'));
        // Security counters are not statistics and are retained for at most a daily cycle.
        foreach (glob($this->cacheDir.'/attempts/*.json') ?: [] as $file) {
            if (is_file($file) && !is_link($file) && filemtime($file) < time() - 900) {
                if (!$dry && !unlink($file)) { return Command::FAILURE; }
            }
        }

        return Command::SUCCESS;
    }
}
