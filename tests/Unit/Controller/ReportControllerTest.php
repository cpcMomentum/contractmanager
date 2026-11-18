<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use DateTime;
use OCA\ContractManager\Controller\ReportController;
use OCA\ContractManager\Service\EntitlementService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ReportCsvWriter;
use OCA\ContractManager\Service\ReportFormatter;
use OCA\ContractManager\Service\ReportPdfWriter;
use OCA\ContractManager\Service\ReportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ReportControllerTest extends TestCase {

	private ReportService $reportService;
	private ReportPdfWriter $pdfWriter;
	private ReportCsvWriter $csvWriter;
	private EntitlementService $entitlement;
	private PermissionService $permissions;

	protected function setUp(): void {
		parent::setUp();
		$this->reportService = $this->createMock(ReportService::class);
		$this->pdfWriter = $this->createMock(ReportPdfWriter::class);
		$this->csvWriter = $this->createMock(ReportCsvWriter::class);
		$this->entitlement = $this->createMock(EntitlementService::class);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->permissions->method('hasAccess')->willReturn(true);
	}

	private function controller(?string $userId = 'alice'): ReportController {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		return new ReportController(
			$this->createMock(IRequest::class),
			$this->reportService,
			new ReportFormatter($l),
			$this->pdfWriter,
			$this->csvWriter,
			$this->entitlement,
			$this->permissions,
			$l,
			$userId,
		);
	}

	public function testWithoutTheFlagTheServerRefusesEveryDownload(): void {
		$this->entitlement->method('hasFeature')->with('vw_reports')->willReturn(false);
		$this->reportService->expects($this->never())->method($this->anything());

		foreach (ReportService::TYPES as $type) {
			foreach (['pdf', 'csv'] as $format) {
				$response = $this->controller()->download($type, $format, [1]);
				$this->assertInstanceOf(JSONResponse::class, $response);
				$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
				$this->assertTrue($response->getData()['werkplusLimit']);
			}
		}
	}

	public function testStatusTellsTheInterfaceWhetherReportsAreAvailable(): void {
		$this->entitlement->method('hasFeature')->willReturn(false);
		$this->assertSame(['available' => false], $this->controller()->status()->getData());
	}

	public function testWithoutRoleNothingIsRevealed(): void {
		$this->permissions = $this->createMock(PermissionService::class);
		$this->permissions->method('hasAccess')->willReturn(false);
		$this->entitlement->method('hasFeature')->willReturn(true);
		$this->reportService->expects($this->never())->method($this->anything());

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->status()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->download('costs')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(null)->download('costs')->getStatus());
	}

	public function testUnknownTypeFormatOrPeriodIsRejected(): void {
		$this->entitlement->method('hasFeature')->willReturn(true);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->download('everything')->getStatus());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->download('costs', 'xlsx')->getStatus());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->download('deadlines', 'pdf', [], 5)->getStatus());
	}

	public function testDownloadsTheContractSheetForTheListIds(): void {
		$this->entitlement->method('hasFeature')->willReturn(true);
		$this->permissions->method('isAdmin')->willReturn(false);
		$report = ['today' => new DateTime('2026-11-18'), 'groups' => []];
		$this->reportService->expects($this->once())->method('contractSheet')->with('alice', false, [3, 1])->willReturn($report);
		$this->csvWriter->method('contracts')->with($report)->willReturn('csv');

		$response = $this->controller()->download('contracts', 'csv', ['3', 1]);

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame('csv', $response->render());
		$headers = (new \ReflectionProperty(Http\Response::class, 'headers'))->getValue($response);
		$this->assertStringContainsString('Vertragsspiegel_2026-11-18.csv', $headers['Content-Disposition']);
		$this->assertStringStartsWith('text/csv', $headers['Content-Type']);
	}
}
