<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CvTemplates;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:templates:seed', description: 'Installe les trois présentations de CV sans écraser les modèles existants.')]
class SeedTemplatesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['Classique' => 'Une présentation sobre, lisible et intemporelle.', 'Atelier' => 'Un format éditorial à la typographie affirmée.', 'Horizon' => 'Une composition moderne et structurée.'] as $name => $description) {
            if (!$this->em->getRepository(CvTemplates::class)->findOneBy(['name' => $name])) {
                $this->em->persist((new CvTemplates())->setName($name)->setDescription($description)->setIsPremium(0));
            }
        }
        $this->em->flush();
        $output->writeln('<info>Les présentations Classique, Atelier et Horizon sont disponibles.</info>');

        return Command::SUCCESS;
    }
}
