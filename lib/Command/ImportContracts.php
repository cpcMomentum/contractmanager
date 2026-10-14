<?php

declare(strict_types=1);

namespace OCA\ContractManager\Command;

use OCA\ContractManager\Service\ContractImportService;
use OCA\ContractManager\Service\ValidationException;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ contractmanager:import --user=<uid> --file=<file> [--dry-run]
 *
 * Counterpart to contractmanager:export: reads a backup JSON into a user's account.
 */
class ImportContracts extends Command {

	public function __construct(
		private ContractImportService $importService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('contractmanager:import')
			->setDescription('Import contract data from a backup JSON into a user\'s account')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'The user ID that will own the imported contracts')
			->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to the backup JSON on the server')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be imported');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = (string)$input->getOption('user');
		if ($uid === '') {
			$output->writeln('<error>--user is required</error>');
			return 1;
		}
		if (!$this->userManager->userExists($uid)) {
			$output->writeln('<error>Unknown user: ' . $uid . '</error>');
			return 1;
		}
		$file = (string)$input->getOption('file');
		if ($file === '' || !is_readable($file)) {
			$output->writeln('<error>File not readable: ' . $file . '</error>');
			return 1;
		}

		try {
			$data = $this->importService->parse((string)file_get_contents($file));
		} catch (ValidationException $e) {
			$output->writeln('<error>' . implode(' ', $e->getErrors()) . '</error>');
			return 1;
		}

		$dryRun = (bool)$input->getOption('dry-run');
		$summary = $dryRun
			? $this->importService->preview($uid, $data)
			: $this->importService->import($uid, $data);

		$output->writeln(($dryRun ? 'Would import' : 'Imported') . ' for ' . $uid . ':');
		$output->writeln('  contracts:              ' . $summary['contracts']);
		$output->writeln('  skipped (duplicates):   ' . $summary['duplicates']);
		$output->writeln('  skipped (invalid):      ' . $summary['invalid']);
		$output->writeln('  new categories:         ' . $summary['categories']);
		$output->writeln('  file paths not found:   ' . $summary['missingFiles']);
		$output->writeln('  unknown responsible:    ' . $summary['unknownUsers']);
		return 0;
	}
}
