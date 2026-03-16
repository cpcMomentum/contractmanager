<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;
use Smalot\PdfParser\Parser;

class PdfTextService {

	public function __construct(
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Extract text from a PDF file in the user's Nextcloud storage
	 *
	 * @param string $filePath Path relative to user's files (e.g. /Documents/contract.pdf)
	 * @param string $userId The user ID
	 * @return array{text: string, isScanned: bool, pageCount: int, rawContent: string}
	 * @throws NotFoundException
	 * @throws \Exception
	 */
	public function extractText(string $filePath, string $userId): array {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		if (!$userFolder->nodeExists($filePath)) {
			throw new NotFoundException('File not found: ' . $filePath);
		}

		$file = $userFolder->get($filePath);

		if ($file->getMimeType() !== 'application/pdf') {
			throw new \InvalidArgumentException('File is not a PDF');
		}

		$content = $file->getContent();
		$rawContent = base64_encode($content);

		try {
			$parser = new Parser();
			$pdf = $parser->parseContent($content);
			$text = $pdf->getText();
			$pages = $pdf->getPages();
			$pageCount = count($pages);

			// Heuristic: if extracted text is very short relative to page count,
			// it's likely a scanned document
			$isScanned = $this->isLikelyScanned($text, $pageCount);

			$this->logger->debug('PDF text extracted', [
				'app' => Application::APP_ID,
				'path' => $filePath,
				'textLength' => strlen($text),
				'pageCount' => $pageCount,
				'isScanned' => $isScanned,
			]);

			return [
				'text' => $text,
				'isScanned' => $isScanned,
				'pageCount' => $pageCount,
				'rawContent' => $rawContent,
			];
		} catch (\Exception $e) {
			$this->logger->warning('PDF text extraction failed, treating as scanned', [
				'app' => Application::APP_ID,
				'path' => $filePath,
				'error' => $e->getMessage(),
			]);

			return [
				'text' => '',
				'isScanned' => true,
				'pageCount' => 0,
				'rawContent' => $rawContent,
			];
		}
	}

	private function isLikelyScanned(string $text, int $pageCount): bool {
		$trimmed = trim($text);
		if ($trimmed === '') {
			return true;
		}
		// Less than 50 characters per page suggests a scan
		if ($pageCount > 0 && strlen($trimmed) / $pageCount < 50) {
			return true;
		}
		return false;
	}
}
