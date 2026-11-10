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
        foreach ($connection->createSchemaManager()->introspectSchema()->toDropSql($platform) as $sql) {
            $connection->executeStatement($sql);
        }
        foreach ($tool->getSchemaFromMetadata($metadata)->toSql($platform) as $sql) {
            $connection->executeStatement($sql);
        }
        $user = (new Users())->setEmail('owner@example.test')->setFirstName('Camille')->setLastName('Martin');
        $user->setPasswordHash(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'Owner-password-123'));
        $em->persist($user);
        $other = (new Users())->setEmail('other@example.test')->setPasswordHash($user->getPasswordHash());
        $em->persist($other);
        $template = (new CvTemplates())->setName('Classique')->setIsPremium(0);
        $em->persist($template);
        $em->flush();
        $this->ownerId = $user->getId();
        $profile = (new Profiles())->setUserId($user->getId())->setTitle('Designer produit')->setSlug(str_repeat('a', 32));
        $em->persist($profile);
        $em->flush();
        $this->profileId = $profile->getId();
        $em->persist((new Experiences())->setProfileId($profile->getId())->setCompanyName('SecretCompany')->setPosition('Designer')->setDescription('<script>alert(1)</script>'));
        $cv = (new CvInstances())->setProfileId($profile->getId())->setTemplateId($template->getId())->setSectionsVisibility(['experiences' => false]);
        $em->persist($cv);
        $em->flush();
        $this->cvId = $cv->getId();
        $this->token = str_repeat('b', 64);
        $em->persist((new CvShares())->setCvInstanceId($cv->getId())->setShareToken($this->token));
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

    public function testPublicShareExcludesHiddenContentAndCountsOnce(): void
    {
        $this->client->request('GET', '/s/'.$this->token);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cv-experiences');
        self::assertStringNotContainsString('SecretCompany', $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->client->request('GET', '/s/'.$this->token);
        self::assertSame(1, $this->em()->getRepository(CvViews::class)->count([]));
        $view = $this->em()->getRepository(CvViews::class)->findOneBy([]);
        self::assertNull($view->getIpAddress());
        self::assertNull($view->getUserAgent());
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
        $second = (new Profiles())->setUserId($this->ownerId)->setTitle('Autre profil');
        $this->em()->persist($second);
        $this->em()->flush();
        $secondId = $second->getId();
        $entry = $this->em()->getRepository(Experiences::class)->findOneBy(['profile_id' => $this->profileId]);
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

    private function loginOwner(): void
    {
        $this->client->loginUser($this->em()->find(Users::class, $this->ownerId));
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
