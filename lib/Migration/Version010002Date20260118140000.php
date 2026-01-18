<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to create the reminders_sent table for tracking sent reminders
 */
class Version010002Date20260118140000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('contractmgr_reminders')) {
            $table = $schema->createTable('contractmgr_reminders');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('contract_id', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('reminder_type', Types::STRING, [
                'notnull' => true,
                'length' => 50,
            ]);
            $table->addColumn('sent_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('sent_to', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['contract_id', 'reminder_type'], 'cm_reminder_contract_type_idx');
        }

        return $schema;
    }
}
