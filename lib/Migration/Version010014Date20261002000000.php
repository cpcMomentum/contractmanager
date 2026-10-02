<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Record who moved a contract to the trash (#438).
 *
 * Until now only `deleted_at` was stored, so a contract deleted by someone other
 * than its owner could neither be traced nor told apart from a self-deletion —
 * and the 30-day cleanup purged both alike.
 *
 * Nullable without a backfill: NULL means "unknown", which is exactly the state
 * of everything that was already in the trash before this version. The cleanup
 * job treats it conservatively and leaves such contracts to an admin.
 */
class Version010014Date20261002000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');
			if (!$table->hasColumn('deleted_by')) {
				$table->addColumn('deleted_by', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
