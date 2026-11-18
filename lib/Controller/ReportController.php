<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\EntitlementService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ReportCsvWriter;
use OCA\ContractManager\Service\ReportFormatter;
use OCA\ContractManager\Service\ReportPdfWriter;
use OCA\ContractManager\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IRequest;

class ReportController extends Controller {

	public const FLAG = 'vw_reports';

	public function __construct(
		IRequest $request,
		private ReportService $reportService,
		private ReportFormatter $formatter,
		private ReportPdfWriter $pdfWriter,
		private ReportCsvWriter $csvWriter,
		private EntitlementService $entitlementService,
		private PermissionService $permissionService,
		private IL10N $l,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		if ($denied = $this->denyWithoutRole()) {
			return $denied;
		}
		return new JSONResponse(['available' => $this->entitlementService->hasFeature(self::FLAG)]);
	}

	/** @param list<int|string> $ids */
	#[NoAdminRequired]
	public function download(string $type, string $format = 'pdf', array $ids = [], int $months = 3): Response {
		if ($denied = $this->denyWithoutRole()) {
			return $denied;
		}
		// The server decides, not the interface: without the flag nothing leaves.
		if (!$this->entitlementService->hasFeature(self::FLAG)) {
			return new JSONResponse([
				'error' => $this->l->t('Berichte gehören zu WerkPlus.'),
				'werkplusLimit' => true,
			], Http::STATUS_FORBIDDEN);
		}
		if (!in_array($type, ReportService::TYPES, true) || !in_array($format, ['pdf', 'csv'], true)
			|| ($type === ReportService::TYPE_DEADLINES && !in_array($months, ReportService::PERIODS, true))) {
			return new JSONResponse(['error' => $this->l->t('Unbekannter Bericht')], Http::STATUS_BAD_REQUEST);
		}

		$isAdmin = $this->permissionService->isAdmin($this->userId);
		$report = match ($type) {
			ReportService::TYPE_CONTRACTS => $this->reportService->contractSheet($this->userId, $isAdmin, array_map('intval', $ids)),
			ReportService::TYPE_COSTS => $this->reportService->costOverview($this->userId, $isAdmin),
			ReportService::TYPE_DEADLINES => $this->reportService->deadlinePreview($this->userId, $isAdmin, $months),
		};

		$writer = $format === 'pdf' ? $this->pdfWriter : $this->csvWriter;
		$content = match ($type) {
			ReportService::TYPE_CONTRACTS => $writer->contracts($report),
			ReportService::TYPE_COSTS => $writer->costs($report),
			ReportService::TYPE_DEADLINES => $writer->deadlines($report),
		};
		return new DataDownloadResponse(
			$content,
			$this->formatter->fileName($type, $report['today'], $format),
			$format === 'pdf' ? 'application/pdf' : 'text/csv; charset=utf-8',
		);
	}

	private function denyWithoutRole(): ?JSONResponse {
		if ($this->userId === null || !$this->permissionService->hasAccess($this->userId)) {
			return new JSONResponse(['error' => $this->l->t('Kein Zugriff')], Http::STATUS_FORBIDDEN);
		}
		return null;
	}
}
