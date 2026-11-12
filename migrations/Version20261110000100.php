<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// Initial schema. Review/baseline explicitly when connecting an existing database.
final class Version20261110000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the PerformCV domain tables with portable Doctrine schema definitions.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('certifications');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('name', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('issuing_organization', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('issue_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('expiration_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('credential_id', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('credential_url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_certifications_profile_id');

        $table = $schema->createTable('cv_instances');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('template_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('custom_styles', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('sections_visibility', 'json', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('color_scheme', 'string', ['notnull' => false, 'length' => 50, 'autoincrement' => false]);
        $table->addColumn('font_family', 'string', ['notnull' => false, 'length' => 100, 'autoincrement' => false]);
        $table->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_cv_instances_profile_id');
        $table->addIndex(['template_id'], 'idx_cv_instances_template_id');

        $table = $schema->createTable('cv_shares');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('cv_instance_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('share_token', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('is_public', 'integer', ['default' => 1, 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('password_protected', 'integer', ['default' => 0, 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('password_hash', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('expires_at', 'datetime', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['cv_instance_id'], 'idx_cv_shares_cv_instance_id');
        $table->addUniqueIndex(['share_token'], 'share_token');

        $table = $schema->createTable('cv_templates');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('name', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('description', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('preview_image_url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('css_styles', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('html_structure', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('is_premium', 'integer', ['default' => 0, 'notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);

        $table = $schema->createTable('cv_views');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('cv_share_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('ip_address', 'string', ['notnull' => false, 'length' => 45, 'autoincrement' => false]);
        $table->addColumn('user_agent', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('viewed_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('referrer', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['cv_share_id'], 'idx_cv_views_cv_share_id');

        $table = $schema->createTable('educations');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('institution', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('degree', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('field_of_study', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('start_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('end_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('description', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('order_index', 'integer', ['notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_educations_profile_id');

        $table = $schema->createTable('experiences');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('company_name', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('position', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('description', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('start_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('end_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('is_current', 'integer', ['default' => 0, 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('location', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('order_index', 'integer', ['notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_experiences_profile_id');

        $table = $schema->createTable('languages');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('language', 'string', ['notnull' => true, 'length' => 100, 'autoincrement' => false]);
        $table->addColumn('level', 'string', ['notnull' => false, 'length' => 10, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_languages_profile_id');

        $table = $schema->createTable('profiles');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('title', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('summary', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('location', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('website', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('linkedin_url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('github_url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('profile_picture_url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('is_public', 'integer', ['default' => 0, 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('slug', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id'], 'idx_profiles_user_id');
        $table->addUniqueIndex(['slug'], 'slug');

        $table = $schema->createTable('profile_skills');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('skill_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('level', 'string', ['notnull' => false, 'length' => 50, 'autoincrement' => false]);
        $table->addColumn('years_experience', 'integer', ['notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_profile_skills_profile_id');
        $table->addIndex(['skill_id'], 'idx_profile_skills_skill_id');

        $table = $schema->createTable('projects');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('profile_id', 'integer', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('title', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('description', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('image_url', 'string', ['notnull' => false, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('technologies_used', 'text', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('start_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('end_date', 'date', ['notnull' => false, 'autoincrement' => false]);
        $table->addColumn('order_index', 'integer', ['notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['profile_id'], 'idx_projects_profile_id');

        $table = $schema->createTable('skills');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('name', 'string', ['notnull' => true, 'length' => 100, 'autoincrement' => false]);
        $table->addColumn('category', 'string', ['notnull' => false, 'length' => 100, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);

        $table = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('email', 'string', ['notnull' => true, 'length' => 255, 'autoincrement' => false]);
        $table->addColumn('password_hash', 'text', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('first_name', 'string', ['notnull' => false, 'length' => 100, 'autoincrement' => false]);
        $table->addColumn('last_name', 'string', ['notnull' => false, 'length' => 100, 'autoincrement' => false]);
        $table->addColumn('phone', 'string', ['notnull' => false, 'length' => 20, 'autoincrement' => false]);
        $table->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'notnull' => false, 'autoincrement' => false]);
        $table->addColumn('subscription_type', 'string', ['default' => 'free', 'notnull' => false, 'length' => 50, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'email');

        $table = $schema->createTable('messenger_messages');
        $table->addColumn('id', 'bigint', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('body', 'text', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('headers', 'text', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('queue_name', 'string', ['notnull' => true, 'length' => 190, 'autoincrement' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('available_at', 'datetime_immutable', ['notnull' => true, 'autoincrement' => false]);
        $table->addColumn('delivered_at', 'datetime_immutable', ['notnull' => false, 'autoincrement' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_name', 'available_at', 'delivered_at', 'id'], 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');

    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('messenger_messages');
        $schema->dropTable('users');
        $schema->dropTable('skills');
        $schema->dropTable('projects');
        $schema->dropTable('profile_skills');
        $schema->dropTable('profiles');
        $schema->dropTable('languages');
        $schema->dropTable('experiences');
        $schema->dropTable('educations');
        $schema->dropTable('cv_views');
        $schema->dropTable('cv_templates');
        $schema->dropTable('cv_shares');
        $schema->dropTable('cv_instances');
        $schema->dropTable('certifications');
    }
}
