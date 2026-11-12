<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add trusted template catalogue metadata and Stripe purchase records.';
    }

    public function up(Schema $schema): void
    {
        $templates = $schema->getTable('cv_templates');
        $templates->addColumn('slug', 'string', ['length' => 40, 'notnull' => false]);
        $templates->addUniqueIndex(['slug'], 'uniq_cv_template_slug');
        $templates->addColumn('price', 'integer', ['default' => 0]);
        $templates->addColumn('is_active', 'boolean', ['default' => true]);
        $templates->addColumn('is_ats_friendly', 'boolean', ['default' => true]);
        $table = $schema->createTable('template_purchases');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer');
        $table->addColumn('template_id', 'integer');
        $table->addColumn('amount', 'integer');
        $table->addColumn('currency', 'string', ['length' => 3]);
        $table->addColumn('checkout_session_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('stripe_payment_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('purchased_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id', 'template_id'], 'uniq_purchase_user_template');
        $table->addUniqueIndex(['checkout_session_id'], 'uniq_purchase_checkout');
        $table->addUniqueIndex(['stripe_payment_id'], 'uniq_purchase_payment');
        $table->addIndex(['user_id', 'template_id', 'purchased_at'], 'idx_purchase_entitlement');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('template_purchases');
        $table = $schema->getTable('cv_templates');
        $table->dropIndex('uniq_cv_template_slug');
        foreach (['slug', 'price', 'is_active', 'is_ats_friendly'] as $column) {
            $table->dropColumn($column);
        }
    }
}
