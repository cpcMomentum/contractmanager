<?php

declare(strict_types=1);

namespace OCA\ContractManager\UserMigration;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ContractExportService;
use OCA\ContractManager\Service\ContractImportService;
use OCA\ContractManager\Service\ValidationException;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exports and imports a user's own contract data through the standard Nextcloud
 * user_migration flow ("Personal settings > Data migration" / occ user:export|import).
 *
 * Only data OWNED by the exporting user is included (created_by). Attachments are
 * kept as path references only — the actual files travel with the standard files
 * migrator. Import runs through ContractImportService.
 */
class ContractMigrator implements IMigrator {

	use TMigratorBasicVersionHandling;

	private const PATH_CONTRACTS = Application::APP_ID . '/contracts.json';

	public function __construct(
		private ContractExportService $exportService,
		private ContractImportService $importService,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getDisplayName(): string {
		return $this->l10n->t('Verträge');
	}

	public function getDescription(): string {
		return $this->l10n->t('Deine Verträge samt Kategorien sowie Kündigungs- und Erinnerungseinstellungen');
	}

	public function export(IUser $user, IExportDestination $exportDestination, OutputInterface $output): void {
		$output->writeln('Exporting contracts…');

		// The serialization lives in ContractExportService, shared with the
		// periodic auto-backup and the occ export command so the formats can
		// never drift (#296).
		$document = $this->exportService->buildExportDocument($user->getUID());

		$exportDestination->addFileContents(
			self::PATH_CONTRACTS,
			json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		);
		$output->writeln('Exported ' . count($document['contracts']) . ' contract(s).');
	}

	public function import(IUser $user, IImportSource $importSource, OutputInterface $output): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No contracts to import, skipping…');
			return;
		}
		if (!$importSource->pathExists(self::PATH_CONTRACTS)) {
			return;
		}

		try {
			$data = $this->importService->parse($importSource->getFileContents(self::PATH_CONTRACTS));
		} catch (ValidationException $e) {
			$output->writeln('contracts.json skipped: ' . implode(' ', $e->getErrors()));
			return;
		}

		$summary = $this->importService->import($user->getUID(), $data);
		$output->writeln('Imported ' . $summary['contracts'] . ' contract(s), skipped ' . $summary['duplicates'] . ' duplicate(s).');
	}
}
