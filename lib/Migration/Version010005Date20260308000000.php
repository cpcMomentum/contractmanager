<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010005Date20260308000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');

			$column = $table->getColumn('cancellation_period');
			$column->setNotnull(false);
			$column->setDefault(null);
		}

		return $schema;
	}
}
