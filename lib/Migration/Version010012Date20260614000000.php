<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add the mutable "responsible user" field per contract (#174 / #173).
 *
 * createdBy stays as the immutable audit of who created the contract; this new
 * column points at the person currently responsible. The effective owner is
 * responsible_user when set, otherwise created_by.
 */
class Version010012Date20260614000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');
			if (!$table->hasColumn('responsible_user')) {
				$table->addColumn('responsible_user', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
			}
		}

		return $schema;
	}
}
