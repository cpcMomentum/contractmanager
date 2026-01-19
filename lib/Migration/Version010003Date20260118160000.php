<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add the archived column to contracts table
 */
class Version010003Date20260118160000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('contractmgr_contracts')) {
            $table = $schema->getTable('contractmgr_contracts');

            if (!$table->hasColumn('archived')) {
                $table->addColumn('archived', Types::SMALLINT, [
                    'notnull' => true,
                    'default' => 0,
                ]);
            }
        }

        return $schema;
    }
}
