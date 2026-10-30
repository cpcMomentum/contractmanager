<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Only the vCard UID, so no address book data is ever copied into a contract.
 */
class Version010015Date20261030000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');
			if (!$table->hasColumn('vendor_contact_uid')) {
				$table->addColumn('vendor_contact_uid', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
