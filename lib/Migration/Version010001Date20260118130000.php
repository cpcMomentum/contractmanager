<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add previous_status field to contracts table
 * This field stores the status before archiving for correct restore behavior
 */
class Version010001Date20260118130000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmanager_contracts')) {
			$table = $schema->getTable('contractmanager_contracts');

			// Add previous_status column if it doesn't exist
			if (!$table->hasColumn('previous_status')) {
				$table->addColumn('previous_status', Types::STRING, [
					'notnull' => false,
					'length' => 20,
				]);
			}
		}

		return $schema;
	}
}
