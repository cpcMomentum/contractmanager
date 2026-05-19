<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010010Date20260519000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');

			if (!$table->hasIndex('cm_contract_archived_idx')) {
				$table->addIndex(['archived'], 'cm_contract_archived_idx');
			}

			if (!$table->hasIndex('cm_contract_deleted_idx')) {
				$table->addIndex(['deleted_at'], 'cm_contract_deleted_idx');
			}
		}

		return $schema;
	}
}
