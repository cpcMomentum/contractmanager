<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010006Date20260329000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');

			if (!$table->hasColumn('custom_field_1')) {
				$table->addColumn('custom_field_1', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('custom_field_2')) {
				$table->addColumn('custom_field_2', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('custom_field_3')) {
				$table->addColumn('custom_field_3', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
