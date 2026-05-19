<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use OCA\ContractManager\Controller\SettingsController;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {

	private IRequest $request;
	private SettingsService $settingsService;
	private PermissionService $permissionService;
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private SettingsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->controller = new SettingsController(
			$this->request,
			'admin',
			$this->settingsService,
			$this->permissionService,
			$this->userManager,
			$this->groupManager,
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
}
