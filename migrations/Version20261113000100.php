<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\{AbstractMySQLPlatform, SQLitePlatform};
use Doctrine\Migrations\AbstractMigration;

final class Version20261113000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an explicit, initially disabled CV directory publication choice.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // Avoid rebuilding a referenced parent table while foreign keys are enabled.
            $this->addSql('ALTER TABLE cv_instances ADD COLUMN directory_listed BOOLEAN DEFAULT 0 NOT NULL');
            $this->addSql('CREATE INDEX idx_cv_directory ON cv_instances (directory_listed, id)');
            return;
        }
        $table = $schema->getTable('cv_instances');
        $table->addColumn('directory_listed', 'boolean', ['default' => false]);
        $table->addIndex(['directory_listed', 'id'], 'idx_cv_directory');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('DROP INDEX idx_cv_directory');
            $this->addSql('ALTER TABLE cv_instances DROP COLUMN directory_listed');
            return;
        }
        $table = $schema->getTable('cv_instances');
        $table->dropIndex('idx_cv_directory');
        $table->dropColumn('directory_listed');
    }

    public function isTransactional(): bool
    {
        return !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }
}
