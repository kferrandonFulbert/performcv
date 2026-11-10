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

#[AsCommand(name: 'app:views:purge', description: 'Supprime les consultations de plus de 90 jours.')]
class PurgeViewsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, #[Autowire('%kernel.cache_dir%')] private string $cacheDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->em->createQuery('DELETE FROM '.CvViews::class.' v WHERE v.viewed_at < :cutoff OR v.viewed_at IS NULL')
            ->setParameter('cutoff', new \DateTime('-90 days'))->execute();
        $output->writeln(sprintf('%d consultation(s) supprimée(s).', $count));
        // Security counters are not statistics and are retained for at most a daily cycle.
        foreach (glob($this->cacheDir.'/attempts/*.json') ?: [] as $file) {
            if (filemtime($file) < time() - 900) {
                unlink($file);
            }
        }

        return Command::SUCCESS;
    }
}
