<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use OCA\ContractManager\Controller\CategoryController;
use OCA\ContractManager\Controller\ContractController;
use OCA\ContractManager\Controller\ExtractionController;
use OCA\ContractManager\Service\AiExtractionService;
use OCA\ContractManager\Service\CategoryService;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\PdfTextService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Reading requires a VertragsWerk role. The app is enabled for every account
 * by default, so an account without admin, editor or viewer role must get
 * nothing — and must not reach the services at all.
 */
class ReadAccessTest extends TestCase {

	private ContractService $service;
	private PermissionService $permissionService;
	private IL10N $l;

	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(ContractService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnArgument(0);
	}

	private function contractController(?string $userId = 'dave'): ContractController {
		return new ContractController(
			$this->createMock(IRequest::class),
			$this->service,
			$this->permissionService,
			$this->l,
			$this->createMock(IUserManager::class),
			$userId,
		);
	}

	/**
	 * @return array<string, array{0: string, 1: list<mixed>}>
	 */
	public static function readEndpoints(): array {
		return [
			'Vertragsliste' => ['index', []],
			'Archiv' => ['archived', []],
			'Papierkorb' => ['trash', []],
			'Vertragspartner' => ['vendors', []],
			'Einzelner Vertrag' => ['show', [1]],
			'Erinnerung abbestellt lesen' => ['getReminderOptOut', [1]],
			'Erinnerung abbestellen' => ['setReminderOptOut', [1, true]],
		];
	}

	/**
	 * @param list<mixed> $args
	 */
	#[DataProvider('readEndpoints')]
	public function testUserWithoutRoleIsRefused(string $method, array $args): void {
		$this->permissionService->method('hasAccess')->with('dave')->willReturn(false);

		$this->service->expects($this->never())->method($this->anything());

		$response = $this->contractController()->$method(...$args);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testUserWithRoleGetsTheList(): void {
		$this->permissionService->method('hasAccess')->willReturn(true);
		$this->service->expects($this->once())->method('findAllVisible')->willReturn([]);

		$response = $this->contractController()->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testCategoriesNeedARole(): void {
		$this->permissionService->method('hasAccess')->willReturn(false);
		$categories = $this->createMock(CategoryService::class);
		$categories->expects($this->never())->method('findAll');

		$controller = new CategoryController($this->createMock(IRequest::class), $categories, 'dave', $this->l, $this->permissionService);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->index()->getStatus());
	}

	public function testAiExtractionNeedsEditRight(): void {
		$this->permissionService->method('canEdit')->willReturn(false);
		$pdf = $this->createMock(PdfTextService::class);
		$pdf->expects($this->never())->method('extractText');
		$settings = $this->createMock(SettingsService::class);
		$settings->method('isAiConfigured')->willReturn(true);

		$controller = new ExtractionController(
			$this->createMock(IRequest::class),
			$pdf,
			$this->createMock(AiExtractionService::class),
			$settings,
			$this->l,
			$this->createMock(LoggerInterface::class),
			'dave',
			$this->permissionService,
		);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->extract('/Vertrag.pdf')->getStatus());
	}
}
