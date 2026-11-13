<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{CvInstances, CvShares, CvTemplates, CvViews, Experiences, Profiles, Users};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApplicationTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $ownerId;
    private int $profileId;
    private int $cvId;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->catchExceptions(false);
        $this->client->setServerParameter('REMOTE_ADDR', '192.0.2.1');
        $counter = static::$kernel->getCacheDir().'/attempts/'.hash('sha256', '192.0.2.1').'.json';
        if (is_file($counter)) {
            unlink($counter);
        }
        $em = $this->em();
        self::assertStringEndsWith('/.runtime/performcv_suite_test.db', str_replace('\\', '/', $em->getConnection()->getParams()['path']));
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        // SQLite needs no PostgreSQL Messenger trigger hooks. Generate its DDL directly.
        $connection = $em->getConnection();
        $platform = new SQLitePlatform();
        // Only this verified dedicated test database is rebuilt with constraints disabled.
        $connection->executeStatement('PRAGMA foreign_keys=OFF');
        try {
            foreach ($connection->createSchemaManager()->introspectSchema()->toDropSql($platform) as $sql) {
                $connection->executeStatement($sql);
            }
            foreach ($tool->getSchemaFromMetadata($metadata)->toSql($platform) as $sql) {
                $connection->executeStatement($sql);
            }
        } finally {
            $connection->executeStatement('PRAGMA foreign_keys=ON');
        }
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
        $user = (new Users())->setEmail('owner@example.test')->setFirstName('Camille')->setLastName('Martin');
        $user->setPasswordHash(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'Owner-password-123'));
        $em->persist($user);
        $other = (new Users())->setEmail('other@example.test')->setPasswordHash($user->getPasswordHash());
        $em->persist($other);
        $template = (new CvTemplates())->setName('Classique')->setIsPremium(0);
        $em->persist($template);
        $em->flush();
        $this->ownerId = $user->getId();
        $profile = (new Profiles())->setUser($user)->setTitle('Designer produit')->setSlug(str_repeat('a', 32));
        $em->persist($profile);
        $em->flush();
        $this->profileId = $profile->getId();
        $em->persist((new Experiences())->setProfile($profile)->setCompanyName('SecretCompany')->setPosition('Designer')->setDescription('<script>alert(1)</script>'));
        $cv = (new CvInstances())->setProfile($profile)->setTemplate($template)->setSectionsVisibility(['experiences' => false]);
        $em->persist($cv);
        $em->flush();
        $this->cvId = $cv->getId();
        $this->token = str_repeat('b', 64);
        $em->persist((new CvShares())->setCvInstance($cv)->setShareToken($this->token));
        $em->flush();
    }

    public function testHomeAndAnonymousProtection(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créez votre CV');
        self::assertSelectorTextContains('h1', 'gratuitement.');
        self::assertSelectorTextContains('title', 'Créer un CV en ligne gratuit et adapté aux ATS');
        self::assertSelectorExists('meta[name="description"][content*="gratuitement"]');
        self::assertSelectorTextContains('#cv-ats h2', 'lecture par les ATS');
        self::assertSelectorCount(4, '.home-faq details');
        self::assertSelectorExists('.hero a[href="/inscription"]');
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/app');
        self::assertResponseRedirects('/connexion');
    }

    public function testRegistrationAndRealLogin(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration[firstName]' => 'Alex', 'registration[lastName]' => 'Laurent', 'registration[email]' => 'alex@example.test',
            'registration[password][first]' => 'A-long-password-123', 'registration[password][second]' => 'A-long-password-123',
        ]));
        self::assertResponseRedirects('/connexion', 303);
        $created = $this->em()->getRepository(Users::class)->findOneBy(['email' => 'alex@example.test']);
        self::assertNotNull($created);
        self::assertNotSame('A-long-password-123', $created->getPasswordHash());
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'ALEX@example.test', '_password' => 'A-long-password-123']));
        self::assertResponseRedirects('/app');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bonjour Alex');
    }

    public function testInvalidRegistrationDoesNotPersist(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration[firstName]' => 'Alex', 'registration[lastName]' => 'Laurent', 'registration[email]' => 'alex@example.test',
            'registration[password][first]' => 'short', 'registration[password][second]' => 'short',
        ]));
        self::assertNull($this->em()->getRepository(Users::class)->findOneBy(['email' => 'alex@example.test']));
        self::assertSelectorExists('.form-card ul');
    }

    public function testProfileAndEntryLifecycle(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer le profil')->form(['profile[title]' => 'Mon second parcours']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Mon second parcours');
        $crawler = $this->client->click($crawler->filter('#experiences a')->link());
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['entry[companyName]' => 'Studio Test', 'entry[position]' => 'Développeur', 'entry[startDate]' => '2024-01-01']));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#experiences', 'Studio Test');
    }

    public function testOtherAccountCannotAccessOrMutateProfileOrCv(): void
    {
        $other = $this->em()->getRepository(Users::class)->findOneBy(['email' => 'other@example.test']);
        $this->client->loginUser($other);
        $this->client->catchExceptions(true);
        foreach (['/app/profils/'.$this->profileId, '/app/profils/'.$this->profileId.'/modifier', '/app/cv/'.$this->cvId, '/app/cv/'.$this->cvId.'/partager'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/app/profils/'.$this->profileId.'/supprimer');
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em()->find(Profiles::class, $this->profileId));
    }

    public function testCsrfRequiredForDeletionAndGetCannotDelete(): void
    {
        $this->loginOwner();
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/app/profils/'.$this->profileId.'/supprimer', ['_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/app/profils/'.$this->profileId.'/supprimer');
        self::assertResponseStatusCodeSame(405);
        self::assertNotNull($this->em()->find(Profiles::class, $this->profileId));
    }

    public function testPublicShareExcludesHiddenContentAndCountsEachDisplay(): void
    {
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cv-experiences');
        self::assertStringNotContainsString('SecretCompany', $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->client->request('GET', '/s/'.$this->token);
        self::assertSame(2, $this->em()->getRepository(CvViews::class)->count([]));
        $view = $this->em()->getRepository(CvViews::class)->findOneBy([]);
        self::assertNull($view->getIpAddress());
        self::assertNull($view->getUserAgent());
        self::assertNull($view->getReferrer());
    }

    public function testPrintableCvContainsReadableUrlsAndOnlyVisibleSections(): void
    {
        $profile = $this->em()->find(Profiles::class, $this->profileId);
        $profile->setWebsite('https://example.test/portfolio')->setLinkedinUrl('javascript:alert(1)');
        $this->em()->flush();

        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.cv-contact a', 'Site : https://example.test/portfolio');
        self::assertSelectorCount(1, '.cv-contact a');
        self::assertSelectorTextContains('.print-help', 'désactivez les en-têtes et pieds de page');
        self::assertSelectorNotExists('.cv-experiences');
        self::assertStringNotContainsString('SecretCompany', $this->client->getResponse()->getContent());

        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setSectionsVisibility(['experiences' => true]);
        $this->em()->flush();
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.cv-contact a', 'Site : https://example.test/portfolio');
        self::assertSelectorTextContains('.cv-experiences h2', 'Expériences professionnelles');
        self::assertSelectorTextContains('[data-print]', 'PDF (ATS)');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->client->getResponse()->getContent());
        self::assertSame(1, $crawler->filter('.cv-document')->count());
    }

    public function testProfileVisibilityAndHtmlEscaping(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/p/'.str_repeat('a', 32));
        self::assertResponseStatusCodeSame(404);
        $profile = $this->em()->find(Profiles::class, $this->profileId);
        $profile->setIsPublic(1);
        $this->em()->flush();
        $this->client->request('GET', '/p/'.str_repeat('a', 32));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->client->getResponse()->getContent());
    }

    public function testExpiredAndRevokedShareReturnNotFound(): void
    {
        $share = $this->em()->getRepository(CvShares::class)->findOneBy(['share_token' => $this->token]);
        $share->setExpiresAt(new \DateTime('-1 hour'));
        $this->em()->flush();
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->em()->getRepository(CvViews::class)->count([]));
    }

    public function testPasswordProtectedShareRequiresCorrectPassword(): void
    {
        $share = $this->em()->getRepository(CvShares::class)->findOneBy(['share_token' => $this->token]);
        $user = $this->em()->find(Users::class, $this->ownerId);
        $share->setPasswordProtected(1)->setPasswordHash($user->getPasswordHash());
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/s/'.$this->token);
        self::assertSelectorNotExists('.cv-document');
        $crawler = $this->client->submit($crawler->selectButton('Consulter le CV')->form(['unlock[password]' => 'wrong-password']));
        self::assertSelectorTextContains('.form-card ul', 'Mot de passe incorrect');
        self::assertSame(0, $this->em()->getRepository(CvViews::class)->count([]));
        $this->client->submit($crawler->selectButton('Consulter le CV')->form(['unlock[password]' => 'Owner-password-123']));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('.cv-document');
    }

    public function testCreateCvAndShareAndRevoke(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/cv/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer le CV')->form(['cv[colorScheme]' => 'emerald', 'cv[fontFamily]' => 'serif']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('.palette-emerald.font-serif');
        $crawler = $this->client->click($crawler->selectLink('Partager ↗')->link());
        $this->client->submit($crawler->selectButton('Créer le lien')->form(['share[password]' => 'Share-password-123']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        $share = $this->em()->getRepository(CvShares::class)->findOneBy(['password_protected' => 1]);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $share->getShareToken());
        $sharedToken = $share->getShareToken();
        $this->client->submit($crawler->selectButton('Révoquer le lien')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/s/'.$sharedToken);
        self::assertResponseStatusCodeSame(404);
    }

    public function testProfileDeletionRemovesAllChildren(): void
    {
        $this->client->request('GET', '/s/'.$this->token);
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId);
        $this->client->submit($crawler->selectButton('Supprimer le profil')->form());
        self::assertResponseRedirects('/app', 303);
        foreach ([Profiles::class, Experiences::class, CvInstances::class, CvShares::class, CvViews::class] as $class) {
            self::assertSame(0, $this->em()->getRepository($class)->count([]), $class);
        }
    }

    /** @dataProvider sectionInputs */
    public function testEverySectionCanBeCreated(string $section, array $values, string $expected): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/rubriques/'.$section.'/nouveau');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Enregistrer')->form($values));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#'.$section, $expected);
    }

    public function sectionInputs(): array
    {
        return [
            ['educations', ['entry[institution]' => 'Université Test', 'entry[degree]' => 'Master'], 'Université Test'],
            ['certifications', ['entry[name]' => 'Certification Test', 'entry[issueDate]' => '2024-01-01'], 'Certification Test'],
            ['projects', ['entry[title]' => 'Projet Test', 'entry[url]' => 'https://example.org'], 'Projet Test'],
            ['languages', ['entry[language]' => 'Français', 'entry[level]' => 'Natif'], 'Français'],
            ['skills', ['entry[skillName]' => 'Symfony', 'entry[level]' => 'Expert', 'entry[yearsExperience]' => '3'], 'Symfony'],
        ];
    }

    public function testInvalidChronologyAndDangerousUrlAreRejected(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/rubriques/experiences/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['entry[companyName]' => 'InvalidCompany', 'entry[startDate]' => '2025-01-01', 'entry[endDate]' => '2024-01-01']));
        self::assertSelectorTextContains('.form-card ul', 'La date de fin');
        self::assertSame(0, $this->em()->getRepository(Experiences::class)->count(['company_name' => 'InvalidCompany']));
        $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer le profil')->form(['profile[website]' => 'javascript:alert(1)']));
        self::assertSelectorExists('.form-card ul');
    }

    public function testForgedOwnerAndMissingCsrfAreRejected(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/nouveau');
        $form = $crawler->selectButton('Enregistrer le profil')->form(['profile[title]' => 'Forged']);
        $values = $form->getPhpValues();
        $values['profile']['userId'] = 9999;
        $this->client->request('POST', '/app/profils/nouveau', $values);
        self::assertSelectorExists('.form-card ul');
        self::assertSame(0, $this->em()->getRepository(Profiles::class)->count(['title' => 'Forged']));
        $this->client->request('POST', '/app/profils/nouveau', ['profile' => ['title' => 'No CSRF', 'isPublic' => '0']]);
        self::assertSame(0, $this->em()->getRepository(Profiles::class)->count(['title' => 'No CSRF']));
    }

    public function testAccountDeletionNeedsPasswordAndRemovesOnlyOwner(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/compte/supprimer');
        $crawler = $this->client->submit($crawler->selectButton('Supprimer définitivement mon compte')->form(['delete_account[currentPassword]' => 'wrong-password', 'delete_account[confirm]' => '1']));
        self::assertSelectorExists('.form-card ul');
        self::assertNotNull($this->em()->find(Users::class, $this->ownerId));
        $this->client->submit($crawler->selectButton('Supprimer définitivement mon compte')->form(['delete_account[currentPassword]' => 'Owner-password-123', 'delete_account[confirm]' => '1']));
        self::assertResponseRedirects('/', 303);
        self::assertNull($this->em()->find(Users::class, $this->ownerId));
        self::assertSame(1, $this->em()->getRepository(Users::class)->count([]));
        self::assertSame(0, $this->em()->getRepository(Profiles::class)->count([]));
    }

    public function testPasswordChangeRequiresCurrentPasswordAndLogsOut(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/compte/mot-de-passe');
        $values = ['change_password[currentPassword]' => 'wrong', 'change_password[newPassword][first]' => 'New-password-12345', 'change_password[newPassword][second]' => 'New-password-12345'];
        $crawler = $this->client->submit($crawler->selectButton('Modifier le mot de passe')->form($values));
        self::assertSelectorExists('.form-card ul');
        $values['change_password[currentPassword]'] = 'Owner-password-123';
        $this->client->submit($crawler->selectButton('Modifier le mot de passe')->form($values));
        self::assertResponseRedirects('/connexion', 303);
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/app');
        self::assertResponseRedirects('/connexion');
    }

    public function testRepeatedLoginAttemptsAreLimited(): void
    {
        $this->client->catchExceptions(true);
        for ($attempt = 0; $attempt < 21; ++$attempt) {
            $this->client->request('POST', '/connexion', ['_username' => 'nobody@example.test', '_password' => 'wrong']);
        }
        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    public function testEntryCannotBeAddressedThroughAnotherProfile(): void
    {
        $second = (new Profiles())->setUser($this->em()->find(Users::class, $this->ownerId))->setTitle('Autre profil');
        $this->em()->persist($second);
        $this->em()->flush();
        $secondId = $second->getId();
        $entry = $this->em()->getRepository(Experiences::class)->findOneBy(['profile' => $this->profileId]);
        $entryId = $entry->getId();
        $this->loginOwner();
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/app/profils/'.$secondId.'/rubriques/experiences/'.$entryId.'/modifier');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/app/profils/'.$secondId.'/rubriques/experiences/'.$entryId.'/supprimer');
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em()->find(Experiences::class, $entryId));
    }

    public function testDatabaseHtmlAndCssCannotBecomeExecutableTemplates(): void
    {
        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setCustomStyles('<script>alert("custom")</script>');
        $template = $this->em()->find(CvTemplates::class, $cv->getTemplateId());
        $template->setHtmlStructure('{{ 7 * 7 }}<script>alert("template")</script>')->setCssStyles('body{display:none}');
        $this->em()->flush();
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('alert("custom")', $html);
        self::assertStringNotContainsString('alert("template")', $html);
        self::assertStringNotContainsString('body{display:none}', $html);
        self::assertSelectorExists('.cv-document');
    }

    public function testShareCountsWithoutConsentAndDisplaysOwnerCounter(): void
    {
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cookie-preferences');
        self::assertSame(1, $this->em()->getRepository(CvViews::class)->count([]));
        $session = $this->client->getRequest()->getSession();
        self::assertSame([], array_filter(array_keys($session->all()), static fn (string $key): bool => str_starts_with($key, 'viewed_share_')));
        self::assertNull($this->client->getCookieJar()->get(\App\Service\CookieConsent::COOKIE_NAME));
        $this->loginOwner();
        $this->client->request('GET', '/app/cv/'.$this->cvId);
        self::assertSelectorTextContains('.view-count', '1 consultation(s)');
    }

    public function testShareCountsIndependentlyOfLegacyCookieChoice(): void
    {
        foreach ([false, true] as $analytics) {
            $cookie = (new \App\Service\CookieConsent('legacy-test-secret'))->cookie($analytics, false, false);
            $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie(\App\Service\CookieConsent::COOKIE_NAME, $cookie->getValue()));
            $this->client->request('GET', '/s/'.$this->token);
            self::assertResponseIsSuccessful();
        }
        self::assertSame(2, $this->em()->getRepository(CvViews::class)->count([]));
    }

    public function testShareDoesNotCountHeadOrPostRequests(): void
    {
        foreach (['HEAD', 'POST'] as $method) {
            $this->client->request($method, '/s/'.$this->token);
            self::assertResponseIsSuccessful();
        }
        self::assertSame(0, $this->em()->getRepository(CvViews::class)->count([]));
    }

    public function testPrivacyPurgeRemovesOnlyExpiredViewsAndCounters(): void
    {
        $em = $this->em();
        $share = $em->getRepository(CvShares::class)->findOneBy(['share_token' => $this->token]);
        $em->persist((new CvViews())->setCvShare($share)->setViewedAt(new \DateTime('-91 days')));
        $em->persist((new CvViews())->setCvShare($share)->setViewedAt(null));
        $em->persist((new CvViews())->setCvShare($share)->setViewedAt(new \DateTime('-1 day')));
        $em->flush();
        $directory = static::$kernel->getCacheDir().'/attempts';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $old = $directory.'/'.hash('sha256', '192.0.2.40').'.json';
        $recent = $directory.'/'.hash('sha256', '192.0.2.41').'.json';
        file_put_contents($old, '{}');
        file_put_contents($recent, '{}');
        touch($old, time() - 3600);
        clearstatcache();
        try {
            $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
            $tester = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:views:purge'));
            self::assertSame(0, $tester->execute([]));
            self::assertStringContainsString('2 consultation(s)', $tester->getDisplay());
            $em->clear();
            self::assertSame(1, $em->getRepository(CvViews::class)->count([]));
            self::assertFileDoesNotExist($old);
            self::assertFileExists($recent);
        } finally {
            foreach ([$old, $recent] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testTemplateCatalogueSeedIsIdempotentAndHasEightModels(): void
    {
        $this->seedCatalogue();
        $this->seedCatalogue();
        $templates = static::getContainer()->get(\App\Repository\WorkspaceRepository::class)->templates();
        self::assertCount(8, $templates);
        self::assertCount(2, array_filter($templates, static fn ($template) => $template->isFree()));
        self::assertCount(6, array_filter($templates, static fn ($template) => $template->getPrice() === 390));
        $this->loginOwner();
        $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(8, '[data-template-id]');
        self::assertSelectorTextContains('h1', 'Choisissez votre modèle');
    }

    public function testPaidPreviewDoesNotSaveAndPaidSelectionIsRejected(): void
    {
        $this->seedCatalogue();
        $premium = $this->em()->getRepository(CvTemplates::class)->findOneBy(['slug' => 'executive']);
        $premiumId = $premium->getId();
        $oldTemplate = $this->em()->find(CvInstances::class, $this->cvId)->getTemplateId();
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        $form = $crawler->selectButton('Prévisualiser')->form(['cv[templateId]' => $premiumId]);
        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.theme-executive.is-preview');
        self::assertStringNotContainsString('SecretCompany', $this->client->getResponse()->getContent());
        self::assertSame($oldTemplate, $this->em()->find(CvInstances::class, $this->cvId)->getTemplateId());
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer le CV')->form(['cv[templateId]' => $premiumId]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.template-controls', 'Achetez ce modèle');
        self::assertSame($oldTemplate, $this->em()->find(CvInstances::class, $this->cvId)->getTemplateId());
    }

    public function testFreeTemplateCanBeSavedAndPreviewRequiresValidCsrf(): void
    {
        $this->seedCatalogue();
        $id = $this->em()->getRepository(CvTemplates::class)->findOneBy(['slug' => 'moderne'])->getId();
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        $form = $crawler->selectButton('Prévisualiser')->form(['cv[templateId]' => $id]);
        $values = $form->getPhpValues();
        $values['cv']['_token'] = 'invalid';
        $this->client->request('POST', '/app/cv/'.$this->cvId.'/modifier', $values);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer le CV')->form(['cv[templateId]' => $id]));
        self::assertResponseRedirects('/app/cv/'.$this->cvId, 303);
        $this->client->followRedirect();
        self::assertSelectorExists('.theme-moderne:not(.is-preview)');
    }

    public function testPreviewAndCheckoutRejectOtherAccountAndInvalidCsrf(): void
    {
        $this->seedCatalogue();
        $id = $this->em()->getRepository(CvTemplates::class)->findOneBy(['slug' => 'executive'])->getId();
        $url = '/app/cv/'.$this->cvId.'/modeles/'.$id.'/acheter';
        $this->client->catchExceptions(true);
        $this->client->loginUser($this->em()->getRepository(Users::class)->findOneBy(['email' => 'other@example.test']));
        $this->client->request('POST', '/app/cv/'.$this->cvId.'/modifier', ['_preview' => 1]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url);
        self::assertResponseStatusCodeSame(403);
        $this->loginOwner();
        $this->client->request('POST', $url, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em()->getRepository(\App\Entity\TemplatePurchases::class)->count([]));
    }

    public function testCheckoutUsesServerPriceAndReusesOpenSessionWithoutGrantingAccess(): void
    {
        $this->seedCatalogue();
        $em = $this->em();
        $template = $em->getRepository(CvTemplates::class)->findOneBy(['slug' => 'executive']);
        $user = $em->find(Users::class, $this->ownerId);
        $stripe = $this->getMockBuilder(\App\Service\StripeCheckout::class)
            ->setConstructorArgs(['sk_test_fixture', 'whsec_fixture'])->onlyMethods(['create', 'retrieve'])->getMock();
        $session = ['id' => 'cs_test_fixture', 'url' => 'https://checkout.stripe.com/c/pay/fixture', 'status' => 'open'];
        $stripe->expects(self::once())->method('create')->with(self::callback(function (array $params) use ($template): bool {
            self::assertSame(390, $params['line_items'][0]['price_data']['unit_amount']);
            self::assertSame('eur', $params['line_items'][0]['price_data']['currency']);
            self::assertSame((string) $this->ownerId, $params['metadata']['user_id']);
            self::assertSame((string) $template->getId(), $params['metadata']['template_id']);
            self::assertStringStartsWith('https://performcv.fr/', $params['success_url']);
            return true;
        }), self::stringStartsWith('performcv-template-'))->willReturn($session);
        $stripe->expects(self::once())->method('retrieve')->with('cs_test_fixture')->willReturn($session);
        $access = static::getContainer()->get(\App\Service\TemplateAccess::class);
        $payments = new \App\Service\TemplatePayment($em, $stripe, $access, static::getContainer()->get(\App\Repository\TemplatePurchasesRepository::class), static::getContainer()->get(\App\Service\AuditTrail::class));
        self::assertSame($session['url'], $payments->checkout($template, $user, 'https://performcv.fr/app/cv/1/modifier'));
        self::assertSame($session['url'], $payments->checkout($template, $user, 'https://performcv.fr/app/cv/1/modifier'));
        self::assertSame(1, $em->getRepository(\App\Entity\TemplatePurchases::class)->count([]));
        self::assertFalse($access->allowed($template, $this->ownerId));
    }

    public function testSignedWebhookGrantsPermanentAccessIdempotentlyToPurchaserOnly(): void
    {
        [$templateId, $event] = $this->pendingTemplatePurchase();
        $this->sendStripeEvent($event, 'invalid');
        self::assertResponseStatusCodeSame(400);
        $this->sendStripeEvent($event);
        self::assertResponseIsSuccessful();
        $event['type'] = 'checkout.session.async_payment_succeeded';
        $this->sendStripeEvent($event);
        self::assertResponseIsSuccessful();
        $em = $this->em();
        self::assertSame(1, $em->getRepository(\App\Entity\TemplatePurchases::class)->count([]));
        $purchase = $em->getRepository(\App\Entity\TemplatePurchases::class)->findOneBy([]);
        self::assertNotNull($purchase->getPurchasedAt());
        $template = $em->find(CvTemplates::class, $templateId);
        $access = static::getContainer()->get(\App\Service\TemplateAccess::class);
        self::assertTrue($access->allowed($template, $this->ownerId));
        $other = $em->getRepository(Users::class)->findOneBy(['email' => 'other@example.test']);
        self::assertFalse($access->allowed($template, $other->getId()));
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer le CV')->form(['cv[templateId]' => $templateId]));
        self::assertResponseRedirects('/app/cv/'.$this->cvId, 303);
        $this->client->followRedirect();
        self::assertSelectorExists('.theme-executive:not(.is-preview)');
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.theme-executive');
    }

    public function testWebhookRejectsWrongAmountModeAndUnpaidSession(): void
    {
        [$templateId, $event] = $this->pendingTemplatePurchase();
        $unpaid = $event;
        $unpaid['data']['object']['payment_status'] = 'unpaid';
        $this->sendStripeEvent($unpaid);
        self::assertResponseIsSuccessful();
        foreach (['amount_total' => 1, 'currency' => 'usd', 'id' => 'cs_unrelated', 'client_reference_id' => '999', 'mode' => 'subscription'] as $key => $wrong) {
            $invalid = $event;
            $invalid['data']['object'][$key] = $wrong;
            $this->sendStripeEvent($invalid);
            self::assertResponseStatusCodeSame(400);
        }
        $event['livemode'] = true;
        $this->sendStripeEvent($event);
        self::assertResponseStatusCodeSame(400);
        $access = static::getContainer()->get(\App\Service\TemplateAccess::class);
        self::assertFalse($access->allowed($this->em()->find(CvTemplates::class, $templateId), $this->ownerId));
    }

    public function testForgedPaidCvCannotBeRenderedOrSharedWithoutEntitlement(): void
    {
        $this->seedCatalogue();
        $templateId = $this->em()->getRepository(CvTemplates::class)->findOneBy(['slug' => 'executive'])->getId();
        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setTemplate($this->em()->find(CvTemplates::class, $templateId));
        $this->em()->flush();
        $this->client->catchExceptions(true);
        $this->loginOwner();
        $this->client->request('GET', '/app/cv/'.$this->cvId);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em()->getRepository(CvViews::class)->count([]));
    }

    public function testEveryModelCanBePreviewedAndStudentPutsEducationFirst(): void
    {
        $this->seedCatalogue();
        $templates = static::getContainer()->get(\App\Repository\WorkspaceRepository::class)->templates();
        $ids = array_column(array_map(static fn ($template) => ['slug' => $template->getSlug(), 'id' => $template->getId()], $templates), 'id', 'slug');
        $this->loginOwner();
        foreach ($ids as $slug => $id) {
            $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
            $this->client->submit($crawler->selectButton('Prévisualiser')->form(['cv[templateId]' => $id]));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.theme-'.$slug.'.is-preview');
            self::assertSelectorNotExists('.cv-experiences');
        }
        $em = $this->em();
        $cv = $em->find(CvInstances::class, $this->cvId);
        $cv->setTemplate($em->find(CvTemplates::class, $ids['etudiant']))->setSectionsVisibility(null);
        $education = (new \App\Entity\Educations())->setProfile($this->em()->find(Profiles::class, $this->profileId))->setInstitution('École fictive')->setDegree('Diplôme fictif');
        $em->persist($education);
        $em->flush();
        $presentation = static::getContainer()->get(\App\Service\CvPresentation::class)->build($em->find(Profiles::class, $this->profileId), $cv, true);
        self::assertSame('educations', array_key_first($presentation['content']));
    }

    public function testInactiveTemplateIsNotSelectableAndSuccessReturnDoesNotGrantAccess(): void
    {
        $this->seedCatalogue();
        $template = $this->em()->getRepository(CvTemplates::class)->findOneBy(['slug' => 'executive']);
        $templateId = $template->getId();
        $template->setIsActive(false);
        $this->em()->flush();
        $this->loginOwner();
        $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier?payment=confirmation');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-template-id="'.$templateId.'"]');
        self::assertSame(0, $this->em()->getRepository(\App\Entity\TemplatePurchases::class)->count([]));
    }

    public function testNewCvUsesEditorAndCanSaveFreeModel(): void
    {
        $this->seedCatalogue();
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/cv/nouveau');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(8, '[data-template-id]');
        $this->client->submit($crawler->selectButton('Enregistrer le CV')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('.theme-classique:not(.is-preview)');
        self::assertSame(2, $this->em()->getRepository(CvInstances::class)->count(['profile' => $this->profileId]));
    }

    public function testDeletingAccountRemovesItsPurchasesAndLateWebhookDoesNotRestoreThem(): void
    {
        [, $event] = $this->pendingTemplatePurchase();
        static::getContainer()->get(\App\Service\WorkspaceWriter::class)->deleteAccount($this->em()->find(Users::class, $this->ownerId));
        self::assertSame(0, $this->em()->getRepository(\App\Entity\TemplatePurchases::class)->count([]));
        $this->sendStripeEvent($event);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->em()->getRepository(\App\Entity\TemplatePurchases::class)->count([]));
    }

    public function testProfilePhotoUploadVisibilityAndRemoval(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->loginOwner();
        $photos = static::getContainer()->get(\App\Service\ProfilePhoto::class);
        $name = null;
        try {
            $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/modifier');
            $form = $crawler->selectButton('Enregistrer le profil')->form();
            $form['profile[photo]']->upload($file);
            $this->client->submit($form);
            self::assertResponseStatusCodeSame(303);
            $name = $this->em()->find(Profiles::class, $this->profileId)->getProfilePictureUrl();
            self::assertNotNull($photos->dataUri($name));
            $this->client->request('GET', '/s/'.$this->token);
            self::assertSelectorNotExists('.cv-photo');
            self::assertStringNotContainsString('data:image/png;base64,', $this->client->getResponse()->getContent());
            $this->loginOwner();
            $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
            $this->client->submit($crawler->selectButton('Prévisualiser')->form(['cv[photo]' => true]));
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.cv-photo');
            $this->client->request('GET', '/s/'.$this->token);
            self::assertSelectorNotExists('.cv-photo');
            foreach ([true, false] as $visible) {
                $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
                $this->client->submit($crawler->selectButton('Enregistrer le CV')->form(['cv[photo]' => $visible]));
                self::assertResponseStatusCodeSame(303);
                $this->client->request('GET', '/s/'.$this->token);
                self::assertSelectorCount($visible ? 1 : 0, '.cv-photo');
            }
            $this->loginOwner();
            $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/modifier');
            $this->client->submit($crawler->selectButton('Enregistrer le profil')->form(['profile[removePhoto]' => true]));
            self::assertResponseStatusCodeSame(303);
            self::assertNull($photos->dataUri($name));
            self::assertNull($this->em()->find(Profiles::class, $this->profileId)->getProfilePictureUrl());
        } finally {
            unlink($file);
            $photos->delete($name);
        }
    }

    public function testProfilePhotoRejectsNonImagesAndOtherOwners(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($file, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        try {
            $this->loginOwner();
            $crawler = $this->client->request('GET', '/app/profils/'.$this->profileId.'/modifier');
            $form = $crawler->selectButton('Enregistrer le profil')->form();
            $form['profile[photo]']->upload($file);
            $this->client->submit($form);
            self::assertSelectorExists('.form-card ul');
            self::assertNull($this->em()->find(Profiles::class, $this->profileId)->getProfilePictureUrl());
            $this->client->catchExceptions(true);
            $this->client->loginUser($this->em()->getRepository(Users::class)->findOneBy(['email' => 'other@example.test']));
            $this->client->request('POST', '/app/profils/'.$this->profileId.'/modifier');
            self::assertResponseStatusCodeSame(403);
        } finally {
            unlink($file);
        }
    }

    public function testAuditTracksActorResourcesAndKeepsPayloadMinimal(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/profils/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer le profil')->form(['profile[title]' => 'Private audit fixture']));
        self::assertResponseStatusCodeSame(303);
        $event = $this->em()->getConnection()->fetchAssociative('SELECT * FROM audit_events');
        self::assertSame('profile.created', $event['action']);
        self::assertSame($this->ownerId, (int) $event['actor_id']);
        self::assertSame('profile', $event['resource_type']);
        self::assertGreaterThan($this->profileId, (int) $event['resource_id']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $event['request_id']);
        self::assertSame(['id', 'occurred_at', 'actor_id', 'action', 'resource_type', 'resource_id', 'request_id'], array_keys($event));
        self::assertStringNotContainsString('Private audit fixture', json_encode($event));
        self::assertStringNotContainsString('owner@example.test', json_encode($event));

        $profileId = (int) $event['resource_id'];
        $crawler = $this->client->request('GET', '/app/profils/'.$profileId.'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer le profil')->form(['profile[title]' => 'Changed private title']));
        $latest = $this->em()->getConnection()->fetchAssociative('SELECT * FROM audit_events ORDER BY id DESC');
        self::assertSame('profile.updated', $latest['action']);
        self::assertSame($profileId, (int) $latest['resource_id']);
        self::assertNotSame($event['request_id'], $latest['request_id']);
    }

    public function testAuditFailureRollsBackAlreadyFlushedBusinessWrite(): void
    {
        $em = $this->em();
        $connection = $em->getConnection();
        $original = $connection->fetchOne('SELECT title FROM profiles WHERE id = ?', [$this->profileId]);
        $profile = $em->find(Profiles::class, $this->profileId);
        $profile->setTitle('Must roll back');
        $connection->executeStatement("CREATE TRIGGER reject_audit BEFORE INSERT ON audit_events BEGIN SELECT RAISE(ABORT, 'Audit fixture failure'); END");
        try {
            static::getContainer()->get(\App\Service\WorkspaceWriter::class)->save($profile);
            self::fail('Write succeeded without its audit event.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertFalse($em->isOpen());
            self::assertSame($original, $connection->fetchOne('SELECT title FROM profiles WHERE id = ?', [$this->profileId]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_events'));
        }
    }

    public function testAuditSurvivesAccountDeletionAndSharesRequestCorrelation(): void
    {
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/compte/supprimer');
        $this->client->submit($crawler->selectButton('Supprimer définitivement mon compte')->form(['delete_account[currentPassword]' => 'Owner-password-123', 'delete_account[confirm]' => '1']));
        self::assertResponseRedirects('/', 303);
        $events = $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM audit_events ORDER BY id');
        self::assertSame(['account.deleted', 'profile.deleted'], array_column($events, 'action'));
        self::assertSame([$this->ownerId, $this->ownerId], array_map('intval', array_column($events, 'actor_id')));
        self::assertSame($events[0]['request_id'], $events[1]['request_id']);
        self::assertNull($this->em()->find(Users::class, $this->ownerId));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM cv_shares'));
    }

    public function testAuditIgnoresPublicViewsPreviewAndRejectedWrites(): void
    {
        $this->client->request('GET', '/s/'.$this->token);
        self::assertSame(1, $this->em()->getRepository(CvViews::class)->count([]));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM audit_events'));
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId.'/modifier');
        $this->client->submit($crawler->selectButton('Prévisualiser')->form());
        self::assertResponseIsSuccessful();
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/app/profils/'.$this->profileId.'/supprimer', ['_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM audit_events'));
    }

    public function testAuditWebhookReplayCreatesOneSystemEvent(): void
    {
        [, $event] = $this->pendingTemplatePurchase();
        $this->sendStripeEvent($event);
        self::assertResponseIsSuccessful();
        $this->sendStripeEvent($event);
        self::assertResponseIsSuccessful();
        $events = $this->em()->getConnection()->fetchAllAssociative('SELECT * FROM audit_events');
        self::assertCount(1, $events);
        self::assertSame('purchase.confirmed', $events[0]['action']);
        self::assertNull($events[0]['actor_id']);
        self::assertStringNotContainsString('cs_test', json_encode($events));
        self::assertStringNotContainsString('pi_fixture', json_encode($events));
    }

    public function testAuditCommandsFilterAndPurgeOnlyExpiredRecords(): void
    {
        $connection = $this->em()->getConnection();
        $base = ['actor_id' => $this->ownerId, 'action' => 'profile.updated', 'resource_type' => 'profile', 'resource_id' => $this->profileId, 'request_id' => str_repeat('a', 32)];
        $connection->insert('audit_events', $base + ['occurred_at' => (new \DateTimeImmutable('-7 months'))->format('Y-m-d H:i:s')]);
        $connection->insert('audit_events', $base + ['occurred_at' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s')]);
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $list = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:audit:list'));
        self::assertSame(0, $list->execute(['--actor' => (string) $this->ownerId, '--limit' => '1']));
        self::assertSame('profile.updated', json_decode(trim($list->getDisplay()), true, flags: JSON_THROW_ON_ERROR)['action']);
        self::assertSame(2, $list->execute(['--limit' => '1000']));
        $purge = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:audit:purge'));
        self::assertSame(0, $purge->execute(['--dry-run' => true]));
        self::assertStringContainsString('1 événement(s) à supprimer', $purge->getDisplay());
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_events'));
        self::assertSame(0, $purge->execute([]));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_events'));
    }

    public function testSecurityAuditLogsAuthenticationAndDenialsWithoutCredentials(): void
    {
        $file = static::$kernel->getLogDir().'/audit/test/audit_security-'.gmdate('Y-m-d').'.log';
        clearstatcache(true, $file);
        $offset = is_file($file) ? filesize($file) : 0;
        $this->client->setServerParameter('HTTP_X_REQUEST_ID', 'untrusted-client-id');
        $crawler = $this->client->request('GET', '/connexion');
        $crawler = $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'owner@example.test', '_password' => 'wrong-secret-fixture']));
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'owner@example.test', '_password' => 'Owner-password-123']));
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/app/profils/'.$this->profileId.'/supprimer', ['_token' => 'invalid-secret-fixture']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/app');
        $this->client->submit($crawler->selectButton('Déconnexion ↗')->form());
        self::assertResponseRedirects('/');
        $lines = substr(file_get_contents($file), $offset);
        $events = array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), array_filter(explode("\n", trim($lines))));
        self::assertSame(['auth.login_failed', 'auth.login_succeeded', 'access.denied', 'auth.logout'], array_column($events, 'message'));
        self::assertNull($events[0]['context']['actor_id']);
        self::assertSame($this->ownerId, $events[1]['context']['actor_id']);
        foreach ($events as $event) {
            self::assertSame('audit_security', $event['channel']);
            self::assertSame(['actor_id', 'request_id'], array_keys($event['context']));
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $event['context']['request_id']);
        }
        foreach (['owner@example.test', 'wrong-secret-fixture', 'Owner-password-123', 'invalid-secret-fixture', 'untrusted-client-id'] as $secret) {
            self::assertStringNotContainsString($secret, $lines);
        }
    }

    public function testAuditPurgeOnlyRemovesItsOwnExpiredSecurityFiles(): void
    {
        $directory = sys_get_temp_dir().'/performcv-audit-test-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $old = $directory.'/audit_security-2000-01-01.log';
        $recent = $directory.'/audit_security-'.gmdate('Y-m-d').'.log';
        $other = $directory.'/other.log';
        try {
            foreach ([$old, $recent, $other] as $file) { file_put_contents($file, 'fixture'); }
            $tester = new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\PurgeAuditCommand($this->em(), $directory, static::getContainer()->get(\App\Service\SecurityAudit::class)));
            self::assertSame(0, $tester->execute(['--dry-run' => true]));
            self::assertFileExists($old);
            self::assertSame(0, $tester->execute([]));
            self::assertFileDoesNotExist($old);
            self::assertFileExists($recent);
            self::assertFileExists($other);
        } finally {
            foreach ([$old, $recent, $other] as $file) { if (is_file($file)) { unlink($file); } }
            rmdir($directory);
        }
    }

    public function testDirectoryRequiresVoluntaryPublicationAndSupportsWithdrawal(): void
    {
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/annuaire-cv');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.directory-card');
        $this->client->request('GET', '/cv-public/'.$this->cvId);
        self::assertResponseStatusCodeSame(404);
        $this->loginOwner();
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId);
        $this->client->submit($crawler->selectButton('Publier ce CV dans l’annuaire et autoriser son référencement')->form());
        self::assertResponseRedirects('/app/cv/'.$this->cvId, 303);
        self::assertTrue($this->em()->find(CvInstances::class, $this->cvId)->isDirectoryListed());
        $this->client->request('GET', '/annuaire-cv');
        self::assertSelectorCount(1, '.directory-card');
        $crawler = $this->client->request('GET', '/app/cv/'.$this->cvId);
        $this->client->submit($crawler->selectButton('Retirer de l’annuaire')->form());
        $this->client->request('GET', '/cv-public/'.$this->cvId);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['cv.directory_published', 'cv.directory_withdrawn'], $this->em()->getConnection()->fetchFirstColumn('SELECT action FROM audit_events ORDER BY id'));
    }

    public function testDirectorySearchFindsNamesKeywordsAndOnlyVisibleSections(): void
    {
        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setDirectoryListed(true);
        $owner = $this->em()->find(Users::class, $this->ownerId);
        $owner->setFirstName('Élodie');
        $this->em()->flush();
        foreach (['elodie martin', 'MARTIN ELODIE', 'designer produit'] as $query) {
            $this->client->request('GET', '/annuaire-cv', ['q' => $query]);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, '.directory-card');
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, follow');
        }
        $this->client->request('GET', '/annuaire-cv', ['q' => 'SecretCompany']);
        self::assertSelectorCount(0, '.directory-card');
        foreach ([1, 'true', []] as $invalidVisibility) {
            $cv = $this->em()->find(CvInstances::class, $this->cvId);
            $cv->setSectionsVisibility(['experiences' => $invalidVisibility]);
            $this->em()->flush();
            $this->client->request('GET', '/annuaire-cv', ['q' => 'SecretCompany']);
            self::assertSelectorCount(0, '.directory-card');
        }
        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setSectionsVisibility(['experiences' => true]);
        $this->em()->flush();
        $this->client->request('GET', '/annuaire-cv', ['q' => 'SecretCompany']);
        self::assertSelectorCount(1, '.directory-card');
        foreach (['%', '_', "' OR 1=1 --"] as $query) {
            $this->client->request('GET', '/annuaire-cv', ['q' => $query]);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(0, '.directory-card');
        }
    }

    public function testDirectoryExcludesProtectedExpiredDisabledAndUnauthorizedCv(): void
    {
        $this->client->catchExceptions(true);
        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setDirectoryListed(true);
        $this->em()->flush();
        foreach (['protected', 'unknown_protection', 'hash', 'expired', 'disabled'] as $state) {
            $share = $this->em()->getRepository(CvShares::class)->findOneBy(['share_token' => $this->token]);
            $share->setPasswordProtected($state === 'protected' ? 1 : ($state === 'unknown_protection' ? null : 0))
                ->setPasswordHash($state === 'hash' ? 'synthetic-hash' : null)
                ->setExpiresAt($state === 'expired' ? new \DateTime('-1 day') : null)
                ->setIsPublic($state === 'disabled' ? 0 : 1);
            $this->em()->flush();
            $this->client->request('GET', '/annuaire-cv');
            self::assertSelectorCount(0, '.directory-card');
            $this->client->request('GET', '/cv-public/'.$this->cvId);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testDirectorySeoAndDeduplicationDoNotExposeShareTokens(): void
    {
        $cv = $this->em()->find(CvInstances::class, $this->cvId);
        $cv->setDirectoryListed(true);
        $this->em()->persist((new CvShares())->setCvInstance($cv)->setShareToken(str_repeat('c', 64)));
        $this->em()->flush();
        $this->client->request('GET', '/annuaire-cv');
        self::assertSelectorCount(1, '.directory-card');
        self::assertStringNotContainsString($this->token, $this->client->getResponse()->getContent());
        $this->client->request('GET', 'http://untrusted.example/cv-public/'.$this->cvId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="canonical"][href="https://performcv.fr/cv-public/'.$this->cvId.'"]');
        self::assertSelectorNotExists('.cv-experiences');
        self::assertStringNotContainsString('SecretCompany', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/sitemap-cv.xml');
        self::assertStringContainsString('https://performcv.fr/sitemap-cv/1.xml', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/sitemap-cv/1.xml');
        self::assertStringContainsString('https://performcv.fr/cv-public/'.$this->cvId, $this->client->getResponse()->getContent());
        self::assertStringNotContainsString($this->token, $this->client->getResponse()->getContent());
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function testDirectoryPublicationRejectsOtherAccountAndInvalidCsrf(): void
    {
        $this->client->catchExceptions(true);
        $this->client->loginUser($this->em()->getRepository(Users::class)->findOneBy(['email' => 'other@example.test']));
        $this->client->request('POST', '/app/cv/'.$this->cvId.'/annuaire', ['publish' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->loginOwner();
        $this->client->request('POST', '/app/cv/'.$this->cvId.'/annuaire', ['publish' => '1', '_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->em()->find(CvInstances::class, $this->cvId)->isDirectoryListed());
    }

    public function testDirectoryPremiumBonusNeedsPurchaseAndCannotOverrideExactName(): void
    {
        $em = $this->em();
        $free = $em->find(CvInstances::class, $this->cvId);
        $free->setDirectoryListed(true);
        $template = (new CvTemplates())->setName('Premium fixture')->setPrice(390)->setIsPremium(1);
        $em->persist($template);
        $paid = (new CvInstances())->setProfile($free->getProfile())->setTemplate($template)->setDirectoryListed(true)->setSectionsVisibility(['experiences' => false]);
        $em->persist($paid);
        $em->flush();
        $paidId = $paid->getId();
        $em->persist((new CvShares())->setCvInstance($paid)->setShareToken(str_repeat('d', 64)));
        $em->flush();
        $directory = static::getContainer()->get(\App\Repository\CvDirectoryRepository::class);
        self::assertSame([$this->cvId], array_map('intval', array_column($directory->search('designer', 1)['items'], 'id')));
        $purchase = new \App\Entity\TemplatePurchases($em->find(Users::class, $this->ownerId), $template, 390);
        $em->persist($purchase);
        $em->flush();
        $em->getConnection()->update('template_purchases', ['purchased_at' => gmdate('Y-m-d H:i:s')], ['id' => $purchase->getId()]);
        $items = $directory->search('designer', 1)['items'];
        self::assertSame([$paidId, $this->cvId], array_map('intval', array_column($items, 'id')));
        self::assertEqualsWithDelta(5, (float) $items[0]['ranking_score'] - (float) $items[1]['ranking_score'], 0.001);
        $other = $em->getRepository(Users::class)->findOneBy(['email' => 'other@example.test']);
        $other->setFirstName('Alex')->setLastName('Durand');
        $profile = (new Profiles())->setUser($other)->setTitle('Camille Martin');
        $em->persist($profile);
        $paid->setProfile($profile);
        $otherPurchase = new \App\Entity\TemplatePurchases($other, $template, 390);
        $em->persist($otherPurchase);
        $em->flush();
        $em->getConnection()->update('template_purchases', ['purchased_at' => gmdate('Y-m-d H:i:s')], ['id' => $otherPurchase->getId()]);
        self::assertSame($this->cvId, (int) $directory->search('Camille Martin', 1)['items'][0]['id']);
    }

    public function testDirectoryValidatesSearchAndPaginatesWithStableCanonical(): void
    {
        $em = $this->em();
        $base = $em->find(CvInstances::class, $this->cvId);
        $base->setDirectoryListed(true);
        for ($i = 0; $i < 20; ++$i) {
            $cv = (new CvInstances())->setProfile($base->getProfile())->setTemplate($base->getTemplate())->setSectionsVisibility(['experiences' => false])->setDirectoryListed(true);
            $em->persist($cv);
            $em->persist((new CvShares())->setCvInstance($cv)->setShareToken(hash('sha256', 'directory-fixture-'.$i)));
        }
        $em->flush();
        $this->client->request('GET', '/annuaire-cv');
        self::assertSelectorCount(20, '.directory-card');
        self::assertSelectorExists('a[href*="page=2"]');
        $this->client->request('GET', '/annuaire-cv?page=2');
        self::assertSelectorCount(1, '.directory-card');
        self::assertSelectorExists('link[rel="canonical"][href="https://performcv.fr/annuaire-cv?page=2"]');
        $this->client->request('GET', '/annuaire-cv', ['q' => 'un deux trois quatre cinq six']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/annuaire-cv', ['q' => 'un deux trois quatre cinq six sept']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/annuaire-cv?q='.str_repeat('a', 81));
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, follow');
    }

    public function testDirectoryCompletenessUsesVisibleContentAndViewsCannotBoostRanking(): void
    {
        $em = $this->em();
        $cv = $em->find(CvInstances::class, $this->cvId);
        $cv->setDirectoryListed(true);
        $em->flush();
        $directory = static::getContainer()->get(\App\Repository\CvDirectoryRepository::class);
        $before = (float) $directory->search('designer', 1)['items'][0]['ranking_score'];
        $cv->setSectionsVisibility(['experiences' => true]);
        $em->flush();
        $visible = (float) $directory->search('designer', 1)['items'][0]['ranking_score'];
        self::assertEqualsWithDelta(6.25, $visible - $before, 0.001);
        $share = $em->getRepository(CvShares::class)->findOneBy(['share_token' => $this->token]);
        for ($i = 0; $i < 10; ++$i) { $em->persist((new CvViews())->setCvShare($share)->setViewedAt(new \DateTime())); }
        $em->flush();
        self::assertEqualsWithDelta($visible, (float) $directory->search('designer', 1)['items'][0]['ranking_score'], 0.001);
    }

    private function seedCatalogue(): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:templates:seed'));
        self::assertSame(0, $tester->execute([]));
    }

    private function pendingTemplatePurchase(): array
    {
        $this->seedCatalogue();
        $template = $this->em()->getRepository(CvTemplates::class)->findOneBy(['slug' => 'executive']);
        $purchase = (new \App\Entity\TemplatePurchases($this->em()->find(Users::class, $this->ownerId), $template, 390))->setCheckoutSessionId('cs_test_fixture');
        $this->em()->persist($purchase);
        $this->em()->flush();
        return [$template->getId(), [
            'id' => 'evt_fixture', 'object' => 'event', 'type' => 'checkout.session.completed', 'livemode' => false,
            'data' => ['object' => ['id' => 'cs_test_fixture', 'object' => 'checkout.session', 'mode' => 'payment',
                'payment_status' => 'paid', 'amount_total' => 390, 'currency' => 'eur', 'payment_intent' => 'pi_fixture',
                'client_reference_id' => (string) $purchase->getId(),
                'metadata' => ['purchase_id' => (string) $purchase->getId(), 'user_id' => (string) $this->ownerId, 'template_id' => (string) $template->getId()],
            ]],
        ]];
    }

    private function sendStripeEvent(array $event, ?string $signature = null): void
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature ??= 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_performcv_fixture_not_a_real_secret');
        $this->client->request('POST', '/stripe/webhook', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload);
    }

    private function loginOwner(): void
    {
        $this->client->loginUser($this->em()->find(Users::class, $this->ownerId));
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
