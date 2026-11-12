<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Users, Profiles, Experiences};
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2).'/migrations/Version20261110000100.php';
require_once dirname(__DIR__, 2).'/migrations/Version20261111000200.php';
require_once dirname(__DIR__, 2).'/migrations/Version20261112000100.php';

final class EntityAssociationsTest extends TestCase
{
    private array $databases = [];

    protected function tearDown(): void
    {
        foreach ($this->databases as [$admin, $name, $connection]) {
            $connection->close();
            $admin->executeStatement('DROP DATABASE '.$admin->quoteIdentifier($name));
            $admin->close();
        }
        parent::tearDown();
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        if ($url = getenv('PERFORMCV_MYSQL_TEST_SERVER_URL')) {
            $admin = DriverManager::getConnection(['url' => $url]);
            $name = 'performcv_associations_test_'.bin2hex(random_bytes(8));
            $admin->executeStatement('CREATE DATABASE '.$admin->quoteIdentifier($name));
            $params = $admin->getParams();
            unset($params['url']);
            $params['dbname'] = $name;
            $connection = DriverManager::getConnection($params);
            $this->databases[] = [$admin, $name, $connection];

            return $connection;
        }

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function oldSchema($connection): Schema
    {
        $schema = new Schema();
        (new \DoctrineMigrations\Version20261110000100($connection, new NullLogger()))->up($schema);
        (new \DoctrineMigrations\Version20261111000200($connection, new NullLogger()))->up($schema);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $schema;
    }

    public function testMigrationEnforcesAllMappedReferencesAndPreservesData(): void
    {
        $connection = $this->connection();
        $before = $this->oldSchema($connection);
        $after = clone $before;
        $migration = new \DoctrineMigrations\Version20261112000100($connection, new NullLogger());
        $migration->up($after);
        $platform = $connection->getDatabasePlatform();
        $diff = $connection->createSchemaManager()->createComparator()->compareSchemas($before, $after);
        foreach ($platform->getAlterSchemaSQL($diff) as $sql) {
            $connection->executeStatement($sql);
        }
        if (!$this->databases) {
            $connection->executeStatement('PRAGMA foreign_keys=ON');
        }
        foreach (['users', 'skills', 'cv_templates', 'profiles', 'certifications', 'educations', 'experiences', 'languages', 'projects', 'profile_skills', 'cv_instances', 'cv_shares', 'cv_views', 'template_purchases'] as $name) {
            $values = ['id' => 1];
            foreach ($after->getTable($name)->getColumns() as $column) {
                if ($column->getName() === 'id' || !$column->getNotnull() || $column->getDefault() !== null) {
                    continue;
                }
                $values[$column->getName()] = match ($column->getType()->getName()) {
                    'integer', 'boolean' => 1,
                    'datetime' => '2026-11-12 00:00:00',
                    default => substr('fixture', 0, $column->getLength() ?? 7),
                };
            }
            $connection->insert($name, $values);
        }
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager($connection, $config);
        self::assertSame([], (new SchemaValidator($em))->validateMapping());
        $mapped = (new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata());
        $count = 0;
        foreach ($mapped->getTables() as $table) {
            foreach ($table->getForeignKeys() as $fk) {
                ++$count;
                $installed = $after->getTable($table->getName())->getForeignKey($fk->getName());
                self::assertSame($fk->getForeignTableName(), $installed->getForeignTableName());
                self::assertSame($fk->getLocalColumns(), $installed->getLocalColumns());
                $child = $table->getName();
                $column = $fk->getLocalColumns()[0];
                try {
                    $connection->update($child, [$column => 99999], ['id' => 1]);
                    self::fail('Orphan accepted: '.$child.'.'.$column);
                } catch (ForeignKeyConstraintViolationException) {
                    self::assertSame(1, (int) $connection->fetchOne('SELECT '.$column.' FROM '.$child.' WHERE id = 1'));
                }
                try {
                    $connection->delete($fk->getForeignTableName(), ['id' => 1]);
                    self::fail('Referenced parent deleted.');
                } catch (ForeignKeyConstraintViolationException) {
                    self::assertTrue(true);
                }
            }
        }
        self::assertSame(14, $count);
        self::assertSame(1, $em->find(Experiences::class, 1)->getProfile()->getUser()->getId());
        $connection->beginTransaction();
        $connection->delete('cv_views', ['id' => 1]);
        $connection->delete('cv_shares', ['id' => 1]);
        $connection->rollBack();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cv_views'));
        $migration->down($after);
        foreach ($after->getTables() as $table) {
            self::assertCount(0, $table->getForeignKeys());
        }
        $connection->close();
    }

    public function testMigrationRefusesOrphansWithoutDeletingThem(): void
    {
        $connection = $this->connection();
        $schema = $this->oldSchema($connection);
        $connection->insert('experiences', ['profile_id' => 99999, 'company_name' => 'Fixture']);
        try {
            (new \DoctrineMigrations\Version20261112000100($connection, new NullLogger()))->up($schema);
            self::fail('Orphan accepted.');
        } catch (AbortMigration $exception) {
            self::assertStringContainsString('experiences.profile_id', $exception->getMessage());
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM experiences'));
            foreach ($schema->getTables() as $table) {
                self::assertCount(0, $table->getForeignKeys());
            }
        }
        $connection->close();
    }

    public function testOrmOrdersInsertsForNewAssociatedObjects(): void
    {
        $connection = $this->connection();
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager($connection, $config);
        $schema = (new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata());
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
        if (!$this->databases) {
            $connection->executeStatement('PRAGMA foreign_keys=ON');
        }
        $user = (new Users())->setEmail('association@example.test')->setPasswordHash('fixture');
        $profile = (new Profiles())->setUser($user);
        $experience = (new Experiences())->setProfile($profile)->setCompanyName('Fixture');
        $template = (new \App\Entity\CvTemplates())->setName('Fixture');
        $cv = (new \App\Entity\CvInstances())->setProfile($profile)->setTemplate($template);
        $share = (new \App\Entity\CvShares())->setCvInstance($cv)->setShareToken('fixture-token');
        $view = (new \App\Entity\CvViews())->setCvShare($share);
        $purchase = new \App\Entity\TemplatePurchases($user, $template, 390);
        foreach ([$view, $share, $cv, $purchase, $experience, $profile, $user, $template] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $id = $experience->getId();
        $em->clear();
        $lazyUser = $em->find(Experiences::class, $id)->getProfile()->getUser();
        self::assertTrue((new \ReflectionClass(Users::class))->isUninitializedLazyObject($lazyUser));
        $serialized = serialize($lazyUser);
        self::assertStringNotContainsString('fixture', $serialized);
        $restored = unserialize($serialized);
        self::assertSame('association@example.test', $restored->getUserIdentifier());
        self::assertSame(hash('crc32c', 'fixture'), $restored->getPassword());
        // This standalone ORM test has no Symfony ManagerRegistry/repository factory.
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            $metadata->setCustomRepositoryClass(null);
        }
        // Load the dependent graph before bulk deletion to exercise UnitOfWork cleanup.
        $em->find(\App\Entity\CvInstances::class, $cv->getId());
        $em->find(\App\Entity\CvShares::class, $share->getId());
        $em->find(\App\Entity\CvViews::class, $view->getId());
        $em->find(\App\Entity\TemplatePurchases::class, $purchase->getId());
        (new \App\Service\WorkspaceWriter($em, new \App\Service\ProfilePhoto(dirname(__DIR__, 2), 'test')))->deleteAccount($lazyUser);
        foreach (['users', 'profiles', 'experiences', 'cv_instances', 'cv_shares', 'cv_views', 'template_purchases'] as $table) {
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cv_templates'));
        $connection->close();
    }
}
