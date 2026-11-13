<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\{Configuration, DriverManager};
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\{DefaultSchemaManagerFactory, Schema};
use Doctrine\Common\EventManager;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2).'/migrations/Version20261113000100.php';

final class DirectoryMigrationTest extends TestCase
{
    public function testDirectoryMigrationPreservesExistingCvAndDisablesPublication(): void
    {
        $dbalConfig = new Configuration();
        $dbalConfig->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $dbalConfig);
        try {
            $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
            $config->enableNativeLazyObjects(true);
            $em = new EntityManager($connection, $config, new EventManager());
            $expected = (new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata());
            $before = clone $expected;
            $table = $before->getTable('cv_instances');
            $table->dropIndex('idx_cv_directory');
            $table->dropColumn('directory_listed');
            foreach ($before->toSql($connection->getDatabasePlatform()) as $sql) { $connection->executeStatement($sql); }
            $connection->insert('users', ['email' => 'directory@example.test', 'password_hash' => 'synthetic']);
            $connection->insert('profiles', ['user_id' => 1, 'title' => 'Fixture']);
            $connection->insert('cv_templates', ['name' => 'Fixture']);
            $connection->insert('cv_instances', ['profile_id' => 1, 'template_id' => 1]);
            $connection->insert('cv_shares', ['cv_instance_id' => 1, 'share_token' => 'synthetic-directory-token']);
            $connection->executeStatement('PRAGMA foreign_keys=ON');
            $after = clone $before;
            $migration = new \DoctrineMigrations\Version20261113000100($connection, new NullLogger());
            $migration->up($after);
            foreach ($migration->getSql() as $query) { $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes()); }
            self::assertSame(0, (int) $connection->fetchOne('SELECT directory_listed FROM cv_instances WHERE id = 1'));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cv_instances'));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cv_shares'));
            self::assertSame([], $connection->fetchAllAssociative('PRAGMA foreign_key_check'));
            $live = $connection->createSchemaManager()->introspectSchema();
            $mappingDiff = $connection->createSchemaManager()->createComparator()->compareSchemas(new Schema([$live->getTable('cv_instances')]), new Schema([$expected->getTable('cv_instances')]));
            self::assertSame([], $connection->getDatabasePlatform()->getAlterSchemaSQL($mappingDiff));

            $mysql = $this->createMock(\Doctrine\DBAL\Connection::class);
            $mysql->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
            $mysql->method('createSchemaManager')->willReturn($connection->createSchemaManager());
            $mysqlMigration = new \DoctrineMigrations\Version20261113000100($mysql, new NullLogger());
            $mysqlMigration->up($after);
            self::assertFalse($mysqlMigration->isTransactional());
            $diff = $connection->createSchemaManager()->createComparator()->compareSchemas($before, $after);
            self::assertStringContainsString('directory_listed', implode("\n", (new MySQLPlatform())->getAlterSchemaSQL($diff)));
        } finally {
            $connection->close();
        }
    }

}
