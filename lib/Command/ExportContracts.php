<?php

declare(strict_types=1);

namespace OCA\ContractManager\Command;

use OCA\ContractManager\Service\ContractExportService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ contractmanager:export --user=<uid> [--output=<file>]
 *
 * Writes a user's own contract data as JSON (same format as the user_migration
 * export and the auto-backup) to a file or stdout. Useful for admin/automation
 * scripting (#296).
 */
class ExportContracts extends Command {

	public function __construct(
		private ContractExportService $exportService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('contractmanager:export')
			->setDescription('Export a user\'s contract data as JSON')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'The user ID whose contracts to export')
			->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'File path to write to (defaults to stdout)');
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

		$json = $this->exportService->exportJson($uid);

		$target = $input->getOption('output');
		if ($target === null || $target === '') {
			$output->writeln($json);
			return 0;
		}

		if (file_put_contents((string)$target, $json) === false) {
			$output->writeln('<error>Could not write to ' . $target . '</error>');
			return 1;
		}
		$output->writeln('<info>Exported contracts for ' . $uid . ' to ' . $target . '</info>');
		return 0;
	}
}
