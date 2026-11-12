<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112000100 extends AbstractMigration
{
    private const REFERENCES = [
        ['profiles', 'user_id', 'users'],
        ['certifications', 'profile_id', 'profiles'],
        ['educations', 'profile_id', 'profiles'],
        ['experiences', 'profile_id', 'profiles'],
        ['languages', 'profile_id', 'profiles'],
        ['projects', 'profile_id', 'profiles'],
        ['profile_skills', 'profile_id', 'profiles'],
        ['cv_instances', 'profile_id', 'profiles'],
        ['profile_skills', 'skill_id', 'skills'],
        ['cv_instances', 'template_id', 'cv_templates'],
        ['cv_shares', 'cv_instance_id', 'cv_instances'],
        ['cv_views', 'cv_share_id', 'cv_shares'],
        ['template_purchases', 'user_id', 'users'],
        ['template_purchases', 'template_id', 'cv_templates'],
    ];

    public function getDescription(): string
    {
        return 'Add restrictive foreign keys for the 14 mapped ManyToOne associations without changing existing columns.';
    }

    public function isTransactional(): bool
    {
        // MySQL DDL implicitly commits and cannot be rolled back atomically.
        return false;
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->abortIf((int) $this->connection->fetchOne('SELECT @@FOREIGN_KEY_CHECKS') !== 1, 'FOREIGN_KEY_CHECKS must be enabled.');
            foreach (array_unique(array_merge(array_column(self::REFERENCES, 0), array_column(self::REFERENCES, 2))) as $table) {
                $engine = $this->connection->fetchOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
                $this->abortIf(strtoupper((string) $engine) !== 'INNODB', 'Table '.$table.' must use InnoDB.');
            }
        }
        // Check every relation before preparing any DDL; never delete orphaned data.
        foreach (self::REFERENCES as [$child, $column, $parent]) {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$child.' c LEFT JOIN '.$parent.' p ON c.'.$column.' = p.id WHERE p.id IS NULL');
            $this->abortIf($count > 0, sprintf('%d orphaned rows in %s.%s referencing %s. Repair data before retrying.', $count, $child, $column, $parent));
        }
        foreach (self::REFERENCES as [$child, $column, $parent]) {
            $table = $schema->getTable($child);
            $name = 'fk_'.$child.'_'.$column;
            if (!$table->hasForeignKey($name)) {
                $table->addForeignKeyConstraint($parent, [$column], ['id'], ['onDelete' => 'RESTRICT'], $name);
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::REFERENCES as [$child, $column]) {
            $table = $schema->getTable($child);
            $name = 'fk_'.$child.'_'.$column;
            if ($table->hasForeignKey($name)) {
                $table->removeForeignKey($name);
            }
        }
    }
}
