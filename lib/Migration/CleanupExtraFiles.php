<?php

declare(strict_types=1);

namespace OCA\ContractManager\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Remove extra files that were accidentally included in earlier releases
 * and cause integrity check failures.
 */
class CleanupExtraFiles implements IRepairStep {

	public function getName(): string {
		return 'Remove extra files from earlier releases';
	}

	public function run(IOutput $output): void {
		$appPath = \OC_App::getAppPath('contractmanager');
		if ($appPath === false) {
			return;
		}

		$filesToRemove = [
			$appPath . '/test-results/.last-run.json',
		];

		$dirsToRemove = [
			$appPath . '/test-results',
		];

		foreach ($filesToRemove as $file) {
			if (file_exists($file)) {
				@unlink($file);
				$output->info('Removed: ' . basename($file));
			}
		}

		foreach ($dirsToRemove as $dir) {
			if (is_dir($dir)) {
				@rmdir($dir);
				$output->info('Removed directory: ' . basename($dir));
			}
		}
	}
}
