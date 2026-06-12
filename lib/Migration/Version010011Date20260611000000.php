<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use Closure;
use OCA\ContractManager\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Personal reminder model (#157 + #172):
 * - New per-(user, contract) opt-out table.
 * - Per-recipient dedup index on the reminders table.
 * - Migrate the formerly global admin Talk chat token to the personal
 *   token of each admin, then drop the global app value.
 */
class Version010011Date20260611000000 extends SimpleMigrationStep {

	private const KEY_TALK_CHAT_TOKEN = 'talk_chat_token';

	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Per-(user, contract) reminder opt-out
		if (!$schema->hasTable('contractmgr_optouts')) {
			$table = $schema->createTable('contractmgr_optouts');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('contract_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['contract_id', 'user_id'], 'cm_optout_contract_user_idx');
		}

		// Per-recipient dedup: existing index only covered (contract_id, reminder_type).
		if ($schema->hasTable('contractmgr_reminders')) {
			$table = $schema->getTable('contractmgr_reminders');
			if (!$table->hasIndex('cm_reminder_cts_idx')) {
				$table->addIndex(['contract_id', 'reminder_type', 'sent_to'], 'cm_reminder_cts_idx');
			}
		}

		return $schema;
	}

	/**
	 * Move the global Talk chat token to every admin's personal token,
	 * then remove the global value. Talk is now a per-user channel.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$globalToken = $this->config->getAppValue(Application::APP_ID, self::KEY_TALK_CHAT_TOKEN, '');
		if ($globalToken === '') {
			return;
		}

		$adminGroup = $this->groupManager->get('admin');
		if ($adminGroup !== null) {
			foreach ($adminGroup->getUsers() as $admin) {
				$existing = $this->config->getUserValue($admin->getUID(), Application::APP_ID, self::KEY_TALK_CHAT_TOKEN, '');
				if ($existing === '') {
					$this->config->setUserValue($admin->getUID(), Application::APP_ID, self::KEY_TALK_CHAT_TOKEN, $globalToken);
				}
			}
			$output->info('Migrated global Talk chat token to admin personal settings');
		}

		$this->config->deleteAppValue(Application::APP_ID, self::KEY_TALK_CHAT_TOKEN);
	}
}
