<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use OCA\ContractManager\Controller\SettingsController;
use OCA\ContractManager\Service\AutoBackupService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {

	private IRequest $request;
	private SettingsService $settingsService;
	private PermissionService $permissionService;
	private AutoBackupService $autoBackupService;
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private IL10N $l;
	private IURLGenerator $urlGenerator;
	private ISecureRandom $secureRandom;
	private SettingsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->autoBackupService = $this->createMock(AutoBackupService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnArgument(0);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->controller = new SettingsController(
			$this->request,
			'admin',
			$this->settingsService,
			$this->permissionService,
			$this->autoBackupService,
			$this->userManager,
			$this->groupManager,
			$this->l,
			$this->urlGenerator,
			$this->secureRandom,
		);
	}

	// ========================================
	// API Key Masking (Issue #126)
	// ========================================

	public function testGetAdminReturnsMaskWhenKeyIsSet(): void {
		$this->settingsService->method('getAiApiKey')->willReturn('sk-real-secret-123');

		/** @var JSONResponse $response */
		$response = $this->controller->getAdmin();
		$data = $response->getData();

		$this->assertSame(SettingsService::API_KEY_MASK, $data['aiApiKey']);
		$this->assertStringNotContainsString('sk-real-secret-123', json_encode($data));
	}

	public function testGetAdminReturnsEmptyWhenKeyIsNotSet(): void {
		$this->settingsService->method('getAiApiKey')->willReturn('');

		/** @var JSONResponse $response */
		$response = $this->controller->getAdmin();
		$data = $response->getData();

		$this->assertSame('', $data['aiApiKey']);
	}

	public function testUpdateAdminIgnoresMaskedKey(): void {
		$this->settingsService->expects($this->never())->method('setAiApiKey');

		$this->controller->updateAdmin(aiApiKey: SettingsService::API_KEY_MASK);
	}

	public function testUpdateAdminSavesRealKey(): void {
		$this->settingsService->expects($this->once())
			->method('setAiApiKey')
			->with('sk-new-key-456');

		$this->controller->updateAdmin(aiApiKey: 'sk-new-key-456');
	}

	public function testUpdateAdminIgnoresNullKey(): void {
		$this->settingsService->expects($this->never())->method('setAiApiKey');

		$this->controller->updateAdmin(aiApiKey: null);
	}

	public function testUpdateAdminClearsKeyWhenEmptyString(): void {
		$this->settingsService->expects($this->once())
			->method('setAiApiKey')
			->with('');

		$this->controller->updateAdmin(aiApiKey: '');
	}

	public function testApiKeyMaskConstantIsStable(): void {
		// Pinning the value here so any accidental rename/edit is caught.
		// The frontend echoes this exact string back; changing it silently
		// would break the unchanged-key detection.
		$this->assertSame('••••••••', SettingsService::API_KEY_MASK);
	}

	// ========================================
	// Reminder Link Diagnostics (Issue #194)
	// ========================================

	public function testGetAdminReminderLinkStatusOkWhenHostsMatch(): void {
		$this->settingsService->method('getCliUrl')->willReturn('https://cloud.example.com');
		$this->request->method('getServerHost')->willReturn('cloud.example.com');

		$data = $this->controller->getAdmin()->getData();

		$this->assertSame('ok', $data['reminderLink']['status']);
		$this->assertSame('https://cloud.example.com', $data['reminderLink']['cliUrl']);
		$this->assertSame('cloud.example.com', $data['reminderLink']['accessHost']);
	}

	public function testGetAdminReminderLinkStatusMissingWhenCliUrlEmpty(): void {
		$this->settingsService->method('getCliUrl')->willReturn('');
		$this->request->method('getServerHost')->willReturn('cloud.example.com');

		$data = $this->controller->getAdmin()->getData();

		$this->assertSame('missing', $data['reminderLink']['status']);
		$this->assertSame('', $data['reminderLink']['cliUrl']);
	}

	public function testGetAdminReminderLinkStatusMismatchWhenHostsDiffer(): void {
		$this->settingsService->method('getCliUrl')->willReturn('https://old.example.com');
		$this->request->method('getServerHost')->willReturn('cloud.example.com');

		$data = $this->controller->getAdmin()->getData();

		$this->assertSame('mismatch', $data['reminderLink']['status']);
	}

	public function testGetAdminReminderLinkHostComparisonIsCaseInsensitive(): void {
		$this->settingsService->method('getCliUrl')->willReturn('https://Cloud.Example.COM');
		$this->request->method('getServerHost')->willReturn('cloud.example.com');

		$data = $this->controller->getAdmin()->getData();

		$this->assertSame('ok', $data['reminderLink']['status']);
	}

	public function testGetAdminReminderLinkIgnoresPort(): void {
		$this->settingsService->method('getCliUrl')->willReturn('https://cloud.example.com:8443');
		$this->request->method('getServerHost')->willReturn('cloud.example.com');

		$data = $this->controller->getAdmin()->getData();

		$this->assertSame('ok', $data['reminderLink']['status']);
	}

	// ========================================
	// Custom Field Active Flag (#368)
	// ========================================

	public function testUpdateAdminSavesCustomFieldEnabled(): void {
		$this->settingsService->expects($this->once())
			->method('setCustomFieldEnabled')
			->with(2, false);
		// The label setter must NOT be touched — toggling keeps the name.
		$this->settingsService->expects($this->never())->method('setCustomFieldLabel');

		$this->controller->updateAdmin(customField2Enabled: false);
	}

	public function testUpdateAdminIgnoresNullCustomFieldEnabled(): void {
		$this->settingsService->expects($this->never())->method('setCustomFieldEnabled');

		$this->controller->updateAdmin(customField1Enabled: null);
	}

	public function testGetAdminExposesCustomFieldEnabledFlags(): void {
		$this->settingsService->method('getCustomFieldEnabled')
			->willReturnMap([[1, true], [2, false], [3, true]]);

		$data = $this->controller->getAdmin()->getData();

		$this->assertTrue($data['customField1Enabled']);
		$this->assertFalse($data['customField2Enabled']);
		$this->assertTrue($data['customField3Enabled']);
	}

	// ========================================
	// Calendar feed token (#68)
	// ========================================

	public function testResetCalendarFeedTokenGeneratesAndReturnsUrl(): void {
		$this->secureRandom->method('generate')->willReturn('FRESHTOKEN123');
		$this->settingsService->expects($this->once())
			->method('setCalendarFeedToken')
			->with('admin', 'FRESHTOKEN123');
		$this->urlGenerator->method('linkToRouteAbsolute')
			->with('contractmanager.calendar.feed', ['token' => 'FRESHTOKEN123'])
			->willReturn('https://cloud.example.com/apps/contractmanager/feed/FRESHTOKEN123/contracts.ics');

		$data = $this->controller->resetCalendarFeedToken()->getData();

		$this->assertSame(
			'https://cloud.example.com/apps/contractmanager/feed/FRESHTOKEN123/contracts.ics',
			$data['calendarFeedUrl'],
		);
	}

	public function testGetReturnsEmptyCalendarFeedUrlWhenNoToken(): void {
		$this->settingsService->method('getCalendarFeedToken')->willReturn('');
		// No token -> no URL is built (linkToRouteAbsolute must not be called).
		$this->urlGenerator->expects($this->never())->method('linkToRouteAbsolute');

		$data = $this->controller->get()->getData();

		$this->assertSame('', $data['calendarFeedUrl']);
	}

	// ========================================
	// Backup visibility + manual trigger (#397)
	// ========================================

	public function testGetExposesActualLastRunAndNextRun(): void {
		// Display shows the real last-success time, not the schedule anchor.
		$this->settingsService->method('getUserBackupLastSuccess')->willReturn(1_700_050_000);
		$this->settingsService->method('getUserBackupLastRun')->willReturn(1_700_000_000); // anchor
		$this->settingsService->method('getUserBackupInterval')->willReturn('daily');

		$data = $this->controller->get()->getData();

		$this->assertSame(1_700_050_000, $data['backupLastRun']);
		// next = anchor + one day
		$this->assertSame(1_700_000_000 + 86400, $data['backupNextRun']);
	}

	public function testGetReturnsZeroNextRunWhenNeverRun(): void {
		$this->settingsService->method('getUserBackupLastSuccess')->willReturn(0);
		$this->settingsService->method('getUserBackupLastRun')->willReturn(0);
		$this->settingsService->method('getUserBackupInterval')->willReturn('weekly');

		$data = $this->controller->get()->getData();

		$this->assertSame(0, $data['backupLastRun']);
		$this->assertSame(0, $data['backupNextRun']);
	}

	public function testBackupNowRunsBackupAndReturnsLastAndNextRun(): void {
		$this->settingsService->method('getUserBackupEnabled')->willReturn(true);
		$this->autoBackupService->expects($this->once())
			->method('backupNow')
			->with('admin')
			->willReturn(1_724_000_123);
		$this->settingsService->method('getUserBackupInterval')->willReturn('weekly');
		// After backupNow the anchor equals the run time.
		$this->settingsService->method('getUserBackupLastRun')->willReturn(1_724_000_123);

		$response = $this->controller->backupNow();
		$data = $response->getData();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(1_724_000_123, $data['backupLastRun']);
		$this->assertSame(1_724_000_123 + 604800, $data['backupNextRun']);
	}

	public function testBackupNowReturns400WhenBackupDisabled(): void {
		$this->settingsService->method('getUserBackupEnabled')->willReturn(false);
		// The backup must never run for a disabled user.
		$this->autoBackupService->expects($this->never())->method('backupNow');

		$response = $this->controller->backupNow();

		$this->assertSame(400, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
	}

	public function testBackupNowReturns500OnFailure(): void {
		$this->settingsService->method('getUserBackupEnabled')->willReturn(true);
		$this->autoBackupService->method('backupNow')
			->willThrowException(new \RuntimeException('disk full'));

		$response = $this->controller->backupNow();

		$this->assertSame(500, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
	}
}
