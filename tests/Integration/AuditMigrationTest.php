<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AuditEvents;
use Doctrine\DBAL\{Configuration, DriverManager};
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\{DefaultSchemaManagerFactory, Schema};
use Doctrine\Common\EventManager;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2).'/migrations/Version20261112000200.php';

final class AuditMigrationTest extends TestCase
{
    public function testMigrationMatchesMappingAndWorksWithoutAccountForeignKeys(): void
    {
        $dbalConfig = new Configuration();
        $dbalConfig->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $dbalConfig);
        try {
            $schema = new Schema();
            $migration = new \DoctrineMigrations\Version20261112000200($connection, new NullLogger());
            $migration->up($schema);
            $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
            $config->enableNativeLazyObjects(true);
            $em = new EntityManager($connection, $config, new EventManager());
            $mapped = (new SchemaTool($em))->getSchemaFromMetadata([$em->getClassMetadata(AuditEvents::class)]);
            $diff = $connection->createSchemaManager()->createComparator()->compareSchemas($schema, $mapped);
            self::assertSame([], $connection->getDatabasePlatform()->getAlterSchemaSQL($diff));
            self::assertCount(0, $schema->getTable('audit_events')->getForeignKeys());
            $mysqlSql = implode("\n", $schema->toSql(new MySQLPlatform()));
            self::assertStringContainsString('InnoDB', $mysqlSql);
            self::assertStringContainsString('idx_audit_actor_date', $mysqlSql);
            foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) { $connection->executeStatement($sql); }
            $connection->insert('audit_events', ['occurred_at' => '2026-11-12 00:00:00', 'actor_id' => 999, 'action' => 'account.deleted', 'resource_type' => 'account', 'resource_id' => 999, 'request_id' => str_repeat('a', 32)]);
            $event = $em->find(AuditEvents::class, 1);
            self::assertSame(999, $event->getActorId());
            self::assertSame('account.deleted', $event->getAction());
            $migration->down($schema);
            self::assertFalse($schema->hasTable('audit_events'));
        } finally {
            $connection->close();
        }
    }
}
