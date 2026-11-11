<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CvTemplates;
use App\Service\TemplateCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:templates:seed', description: 'Installe ou actualise les huit modèles du catalogue PerformCV.')]
class SeedTemplatesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (TemplateCatalog::MODELS as $slug => $model) {
            $template = $this->em->getRepository(CvTemplates::class)->findOneBy(['slug' => $slug]);
            // Keep the identity of the old default so existing CVs continue to work.
            if (!$template && $slug === 'classique') {
                $template = $this->em->getRepository(CvTemplates::class)->findOneBy(['name' => 'Classique', 'slug' => null]);
            }
            $template ??= new CvTemplates();
            $template->setSlug($slug)->setName($model['name'])->setDescription($model['description'])
                ->setPrice($model['price'])->setIsPremium($model['price'] > 0 ? 1 : 0)->setIsActive(true)->setIsAtsFriendly($model['ats']);
            $this->em->persist($template);
        }
        $this->em->flush();
        $output->writeln('<info>Les huit modèles sont disponibles : deux gratuits et six à 3,90 €.</info>');

        return Command::SUCCESS;
    }
}
