<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add the "cancellation deadline type" field per contract (#159).
 *
 * Controls how the cancellation deadline is derived for auto-renewal contracts:
 * - 'normal'    : notice period counted back from the (effective) end date — the
 *                 previous and default behavior.
 * - 'month_end' : the resulting deadline is moved to the last calendar day of
 *                 its month (e.g. a notice landing on the 21st becomes the 31st).
 *
 * Default 'normal' keeps every existing contract byte-identical to before.
 */
class Version010013Date20260616000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');
			if (!$table->hasColumn('cancellation_deadline_type')) {
				$table->addColumn('cancellation_deadline_type', Types::STRING, [
					'notnull' => true,
					'length' => 20,
					'default' => 'normal',
				]);
			}
		}

		return $schema;
	}
}
