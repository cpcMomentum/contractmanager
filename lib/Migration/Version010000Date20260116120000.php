<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20260116120000 extends SimpleMigrationStep {

    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Categories table (short name to avoid index name length issues)
        if (!$schema->hasTable('contractmgr_categories')) {
            $table = $schema->createTable('contractmgr_categories');

            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('sort_order', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['sort_order'], 'cm_cat_sort_idx');
        }

        // Contracts table (short name to avoid index name length issues)
        if (!$schema->hasTable('contractmgr_contracts')) {
            $table = $schema->createTable('contractmgr_contracts');

            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('vendor', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 20,
                'default' => 'active',
            ]);
            $table->addColumn('category_id', Types::INTEGER, [
                'notnull' => false,
            ]);
            $table->addColumn('start_date', Types::DATE, [
                'notnull' => true,
            ]);
            $table->addColumn('end_date', Types::DATE, [
                'notnull' => true,
            ]);
            $table->addColumn('cancellation_period', Types::STRING, [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('contract_type', Types::STRING, [
                'notnull' => true,
                'length' => 30,
                'default' => 'fixed',
            ]);
            $table->addColumn('renewal_period', Types::STRING, [
                'notnull' => false,
                'length' => 50,
            ]);
            $table->addColumn('cost', Types::DECIMAL, [
                'notnull' => false,
                'precision' => 10,
                'scale' => 2,
            ]);
            $table->addColumn('currency', Types::STRING, [
                'notnull' => false,
                'length' => 3,
                'default' => 'EUR',
            ]);
            $table->addColumn('cost_interval', Types::STRING, [
                'notnull' => false,
                'length' => 20,
            ]);
            $table->addColumn('contract_folder', Types::STRING, [
                'notnull' => false,
                'length' => 1024,
            ]);
            $table->addColumn('main_document', Types::STRING, [
                'notnull' => false,
                'length' => 1024,
            ]);
            $table->addColumn('reminder_enabled', Types::SMALLINT, [
                'notnull' => true,
                'default' => 1,
            ]);
            $table->addColumn('reminder_days', Types::INTEGER, [
                'notnull' => false,
            ]);
            $table->addColumn('notes', Types::TEXT, [
                'notnull' => false,
            ]);
            $table->addColumn('created_by', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('updated_at', Types::DATETIME, [
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['status'], 'cm_contract_status_idx');
            $table->addIndex(['category_id'], 'cm_contract_cat_idx');
            $table->addIndex(['end_date'], 'cm_contract_end_idx');
            $table->addIndex(['created_by'], 'cm_contract_user_idx');
        }

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Insert default categories
        // Note: This is handled via the CategoryService on first access
        // to avoid issues with different DB connection states during migration
    }
}
