<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add permission and trash fields to contracts table
 * - is_private: marks contracts as private (only visible to creator + admin)
 * - deleted_at: soft-delete timestamp for trash functionality
 */
class Version010004Date20260121000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');

			// Add is_private field (0 = public, 1 = private)
			if (!$table->hasColumn('is_private')) {
				$table->addColumn('is_private', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
			}

			// Add deleted_at field for soft-delete (NULL = not deleted)
			if (!$table->hasColumn('deleted_at')) {
				$table->addColumn('deleted_at', Types::DATETIME, [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
