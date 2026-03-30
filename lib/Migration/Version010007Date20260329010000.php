<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fix custom field column names to match Entity property mapping.
 * NC Entity maps camelCase "customField1" to snake_case "custom_field1" (not "custom_field_1").
 */
class Version010007Date20260329010000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');

			// Drop old columns with wrong names
			if ($table->hasColumn('custom_field_1')) {
				$table->dropColumn('custom_field_1');
			}
			if ($table->hasColumn('custom_field_2')) {
				$table->dropColumn('custom_field_2');
			}
			if ($table->hasColumn('custom_field_3')) {
				$table->dropColumn('custom_field_3');
			}

			// Add new columns with correct names
			if (!$table->hasColumn('custom_field1')) {
				$table->addColumn('custom_field1', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('custom_field2')) {
				$table->addColumn('custom_field2', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('custom_field3')) {
				$table->addColumn('custom_field3', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
