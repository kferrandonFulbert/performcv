<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\{Certifications, CvInstances, CvTemplates, Educations, Experiences, Languages, Profiles, ProfileSkills, Projects, Skills, Users};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un parcours fictif pour les démonstrations en environnement local ou de test.
 */
#[AsCommand(name: 'app:demo', description: 'Crée un parcours fictif, uniquement en environnement local ou test.')]
class DemoCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, #[Autowire('%kernel.environment%')] private string $environment)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->environment, ['local', 'test'], true)) {
            $output->writeln('<error>Cette commande est réservée au mode local ou test.</error>');

            return Command::FAILURE;
        }
        if ($this->em->getRepository(Users::class)->findOneBy(['email' => 'demo@performcv.test'])) {
            $output->writeln('Le compte de démonstration existe déjà. Aucune donnée modifiée.');

            return Command::SUCCESS;
        }
        $template = $this->em->getRepository(CvTemplates::class)->findOneBy(['name' => 'Horizon']);
        if (!$template) {
            $output->writeln('<error>Lancez app:templates:seed avant app:demo.</error>');

            return Command::FAILURE;
        }
        $this->em->wrapInTransaction(function () use ($template): void {
            $user = (new Users())->setEmail('demo@performcv.test')->setFirstName('Camille')->setLastName('Martin')->setCreatedAt(new \DateTime())->setUpdatedAt(new \DateTime());
            $user->setPasswordHash($this->hasher->hashPassword($user, 'Demo-performcv-2026'));
            $this->em->persist($user);
            $this->em->flush();
            $profile = (new Profiles())->setUser($user)->setTitle('Designer produit & expériences numériques')->setLocation('Lyon, France')
                ->setSummary('Je transforme les besoins des utilisateurs en expériences simples et accessibles. Mon fil conducteur : conjuguer recherche, créativité et attention aux détails.')
                ->setSlug(bin2hex(random_bytes(16)));
            $this->em->persist($profile);
            $skill = $this->em->getRepository(Skills::class)->findOneBy(['name' => 'Design produit']) ?? (new Skills())->setName('Design produit')->setCategory('Design');
            $this->em->persist($skill);
            $this->em->flush();
            foreach ([
                (new Experiences())->setProfile($profile)->setCompanyName('Atelier Exemple')->setPosition('Product designer')->setStartDate(new \DateTime('2022-09-01'))->setIsCurrent(1)->setOrderIndex(0)->setDescription('Conception de services numériques, entretiens utilisateurs et prototypage. Collaboration avec les équipes de développement pour des interfaces accessibles.'),
                (new Educations())->setProfile($profile)->setInstitution('École de design — Exemple')->setDegree('Master Design numérique')->setStartDate(new \DateTime('2018-09-01'))->setEndDate(new \DateTime('2020-06-30'))->setOrderIndex(0),
                (new Certifications())->setProfile($profile)->setName('Accessibilité numérique')->setIssuingOrganization('Organisme de démonstration')->setIssueDate(new \DateTime('2024-03-01')),
                (new Projects())->setProfile($profile)->setTitle('Un service public plus accessible')->setDescription('Refonte fictive d’un parcours de prise de rendez-vous : recherche, simplification et tests utilisateurs.')->setTechnologiesUsed('Figma, recherche utilisateur, design system')->setOrderIndex(0),
                (new Languages())->setProfile($profile)->setLanguage('Français')->setLevel('Natif'),
                (new Languages())->setProfile($profile)->setLanguage('Anglais')->setLevel('C1'),
                (new ProfileSkills())->setProfile($profile)->setSkill($skill)->setLevel('Expert')->setYearsExperience(6),
                (new CvInstances())->setProfile($profile)->setTemplate($template)->setColorScheme('emerald')->setFontFamily('sans')->setCreatedAt(new \DateTime())->setUpdatedAt(new \DateTime()),
            ] as $entity) {
                $this->em->persist($entity);
            }
        });
        $output->writeln('<info>Compte fictif créé : demo@performcv.test / Demo-performcv-2026</info>');

        return Command::SUCCESS;
    }
}
