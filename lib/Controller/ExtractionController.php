<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\AiExtractionService;
use OCA\ContractManager\Service\PdfTextService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ExtractionController extends Controller {

	public function __construct(
		IRequest $request,
		private PdfTextService $pdfTextService,
		private AiExtractionService $aiExtractionService,
		private SettingsService $settingsService,
		private IL10N $l,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Extract contract data from a PDF file
	 *
	 * @param string $filePath Path to the PDF file in user's Nextcloud files
	 */
	#[NoAdminRequired]
	public function extract(string $filePath): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(
				['error' => $this->l->t('Not authenticated')],
				Http::STATUS_UNAUTHORIZED
			);
		}

		if (!$this->settingsService->isAiConfigured()) {
			return new JSONResponse(
				['error' => $this->l->t('AI extraction is not configured. Please ask your administrator to set up an AI provider.')],
				Http::STATUS_PRECONDITION_FAILED
			);
		}

		try {
			// Step 1: Extract text from PDF
			$pdfResult = $this->pdfTextService->extractText($filePath, $this->userId);

			// Step 2: Send to AI for structured extraction
			$extracted = $this->aiExtractionService->extract(
				$pdfResult['text'],
				$pdfResult['isScanned'],
				$pdfResult['rawContent']
			);

			$this->logger->info('Contract data extracted from PDF', [
				'app' => Application::APP_ID,
				'filePath' => $filePath,
				'confidence' => $extracted['confidence'] ?? 0,
				'isScanned' => $pdfResult['isScanned'],
			]);

			return new JSONResponse([
				'success' => true,
				'data' => $extracted,
				'isScanned' => $pdfResult['isScanned'],
				'pageCount' => $pdfResult['pageCount'],
			]);

		} catch (\OCP\Files\NotFoundException $e) {
			return new JSONResponse(
				['error' => $this->l->t('File not found')],
				Http::STATUS_NOT_FOUND
			);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(
				['error' => $this->l->t('Only PDF files are supported')],
				Http::STATUS_BAD_REQUEST
			);
		} catch (\Exception $e) {
			$this->logger->error('Contract extraction failed: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'filePath' => $filePath,
				'exception' => $e,
			]);
			return new JSONResponse(
				['error' => $this->l->t('Extraction failed: %s', [$e->getMessage()])],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	/**
	 * Check if AI extraction is available (for frontend visibility)
	 */
	#[NoAdminRequired]
	public function status(): JSONResponse {
		return new JSONResponse([
			'configured' => $this->settingsService->isAiConfigured(),
			'provider' => $this->settingsService->getAiProvider(),
		]);
	}
}
