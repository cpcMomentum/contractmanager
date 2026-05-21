<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010010Date20260521000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');

			if (!$table->hasColumn('cancelled_on')) {
				$table->addColumn('cancelled_on', Types::DATE, [
					'notnull' => false,
				]);
			}

			if (!$table->hasColumn('cancelled_to')) {
				$table->addColumn('cancelled_to', Types::DATE, [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}
}
