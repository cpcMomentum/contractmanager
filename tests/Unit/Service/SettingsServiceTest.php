<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\SettingsService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {

	private IConfig $config;
	private SettingsService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->service = new SettingsService($this->config);
	}

	// ========================================
	// Per-User Talk Chat Token Tests
	// ========================================

	public function testGetUserTalkChatTokenReturnsNullWhenEmpty(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('testuser', Application::APP_ID, 'talk_chat_token', '')
			->willReturn('');

		$this->assertNull($this->service->getUserTalkChatToken('testuser'));
	}

	public function testGetUserTalkChatTokenReturnsValue(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('testuser', Application::APP_ID, 'talk_chat_token', '')
			->willReturn('abc123xyz');

		$this->assertEquals('abc123xyz', $this->service->getUserTalkChatToken('testuser'));
	}

	public function testSetUserTalkChatToken(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'talk_chat_token', 'newtoken');

		$this->service->setUserTalkChatToken('testuser', 'newtoken');
	}

	public function testSetUserTalkChatTokenWithNull(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'talk_chat_token', '');

		$this->service->setUserTalkChatToken('testuser', null);
	}

	// ========================================
	// Reminder Mode Tests
	// ========================================

	public function testGetUserReminderModeDefaultsToOwn(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('testuser', Application::APP_ID, 'reminder_mode', 'own')
			->willReturn('own');

		$this->assertEquals('own', $this->service->getUserReminderMode('testuser'));
	}

	public function testGetUserReminderModeReturnsAll(): void {
		$this->config->method('getUserValue')->willReturn('all');

		$this->assertEquals('all', $this->service->getUserReminderMode('testuser'));
	}

	public function testGetUserReminderModeFallsBackOnInvalidValue(): void {
		$this->config->method('getUserValue')->willReturn('garbage');

		$this->assertEquals('own', $this->service->getUserReminderMode('testuser'));
	}

	public function testSetUserReminderModeStoresValidValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'reminder_mode', 'all');

		$this->service->setUserReminderMode('testuser', 'all');
	}

	public function testSetUserReminderModeIgnoresInvalidValue(): void {
		$this->config->expects($this->never())
			->method('setUserValue');

		$this->service->setUserReminderMode('testuser', 'garbage');
	}

	// ========================================
	// Per-User Reminder Days Tests
	// ========================================

	public function testGetUserReminderDays1ReturnsNullWhenUnset(): void {
		$this->config->method('getUserValue')->willReturn('');

		$this->assertNull($this->service->getUserReminderDays1('testuser'));
	}

	public function testGetUserReminderDays1ReturnsConfiguredValue(): void {
		$this->config->method('getUserValue')->willReturn('30');

		$this->assertEquals(30, $this->service->getUserReminderDays1('testuser'));
	}

	public function testSetUserReminderDays1StoresValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'reminder_days_1', '30');

		$this->service->setUserReminderDays1('testuser', 30);
	}

	public function testSetUserReminderDays1NullClearsValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'reminder_days_1', '');

		$this->service->setUserReminderDays1('testuser', null);
	}

	public function testSetUserReminderDays2StoresValue(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'reminder_days_2', '5');

		$this->service->setUserReminderDays2('testuser', 5);
	}

	// ========================================
	// Reminder Days 1 Tests
	// ========================================

	public function testGetReminderDays1ReturnsDefault(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'reminder_days_1', '14')
			->willReturn('14');

		$result = $this->service->getReminderDays1();

		$this->assertEquals(14, $result);
	}

	public function testGetReminderDays1ReturnsConfiguredValue(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'reminder_days_1', '14')
			->willReturn('21');

		$result = $this->service->getReminderDays1();

		$this->assertEquals(21, $result);
	}

	public function testSetReminderDays1(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'reminder_days_1', '21');

		$this->service->setReminderDays1(21);
	}

	public function testSetReminderDays1EnforcesMinimum(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'reminder_days_1', '1');

		$this->service->setReminderDays1(0);
	}

	public function testSetReminderDays1EnforcesMinimumNegative(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'reminder_days_1', '1');

		$this->service->setReminderDays1(-5);
	}

	// ========================================
	// Reminder Days 2 Tests
	// ========================================

	public function testGetReminderDays2ReturnsDefault(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'reminder_days_2', '3')
			->willReturn('3');

		$result = $this->service->getReminderDays2();

		$this->assertEquals(3, $result);
	}

	public function testGetReminderDays2ReturnsConfiguredValue(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'reminder_days_2', '3')
			->willReturn('7');

		$result = $this->service->getReminderDays2();

		$this->assertEquals(7, $result);
	}

	public function testSetReminderDays2(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'reminder_days_2', '7');

		$this->service->setReminderDays2(7);
	}

	public function testSetReminderDays2EnforcesMinimum(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'reminder_days_2', '1');

		$this->service->setReminderDays2(0);
	}

	// ========================================
	// User Email Reminder Tests
	// ========================================

	public function testGetUserEmailReminderReturnsFalseByDefault(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('testuser', Application::APP_ID, 'email_reminder', '0')
			->willReturn('0');

		$result = $this->service->getUserEmailReminder('testuser');

		$this->assertFalse($result);
	}

	public function testGetUserEmailReminderReturnsTrueWhenEnabled(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('testuser', Application::APP_ID, 'email_reminder', '0')
			->willReturn('1');

		$result = $this->service->getUserEmailReminder('testuser');

		$this->assertTrue($result);
	}

	public function testSetUserEmailReminderEnabled(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'email_reminder', '1');

		$this->service->setUserEmailReminder('testuser', true);
	}

	public function testSetUserEmailReminderDisabled(): void {
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('testuser', Application::APP_ID, 'email_reminder', '0');

		$this->service->setUserEmailReminder('testuser', false);
	}

	// ========================================
	// AI API URL Validation Tests (Issue #123)
	// ========================================

	public function testIsValidAiApiUrlAcceptsHttps(): void {
		$this->assertTrue($this->service->isValidAiApiUrl('https://api.anthropic.com'));
		$this->assertTrue($this->service->isValidAiApiUrl('https://api.openai.com/v1'));
		$this->assertTrue($this->service->isValidAiApiUrl('https://example.com:8443/path'));
	}

	public function testIsValidAiApiUrlAcceptsHttpForLocalHosts(): void {
		$this->assertTrue($this->service->isValidAiApiUrl('http://localhost:11434'));
		$this->assertTrue($this->service->isValidAiApiUrl('http://127.0.0.1:11434'));
		$this->assertTrue($this->service->isValidAiApiUrl('http://[::1]:11434'));
		$this->assertTrue($this->service->isValidAiApiUrl('http://ollama.local'));
		$this->assertTrue($this->service->isValidAiApiUrl('http://server.localhost'));
	}

	public function testIsValidAiApiUrlRejectsHttpForRemoteHosts(): void {
		$this->assertFalse($this->service->isValidAiApiUrl('http://api.openai.com'));
		$this->assertFalse($this->service->isValidAiApiUrl('http://example.com'));
	}

	public function testIsValidAiApiUrlRejectsOtherSchemes(): void {
		$this->assertFalse($this->service->isValidAiApiUrl('file:///etc/passwd'));
		$this->assertFalse($this->service->isValidAiApiUrl('ftp://example.com'));
		$this->assertFalse($this->service->isValidAiApiUrl('javascript:alert(1)'));
		$this->assertFalse($this->service->isValidAiApiUrl('gopher://example.com'));
	}

	public function testIsValidAiApiUrlRejectsMalformed(): void {
		$this->assertFalse($this->service->isValidAiApiUrl('not a url'));
		$this->assertFalse($this->service->isValidAiApiUrl('http://'));
		$this->assertFalse($this->service->isValidAiApiUrl(''));
	}

	public function testSetAiApiUrlSavesValidUrl(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'ai_api_url', 'https://api.anthropic.com');

		$this->service->setAiApiUrl('https://api.anthropic.com');
	}

	public function testSetAiApiUrlSavesLocalHttp(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'ai_api_url', 'http://localhost:11434');

		$this->service->setAiApiUrl('http://localhost:11434');
	}

	public function testSetAiApiUrlClearsWithEmpty(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'ai_api_url', '');

		$this->service->setAiApiUrl('');
	}

	public function testSetAiApiUrlIgnoresInvalidScheme(): void {
		$this->config->expects($this->never())
			->method('setAppValue');

		$this->service->setAiApiUrl('file:///etc/passwd');
	}

	public function testSetAiApiUrlIgnoresHttpForRemote(): void {
		$this->config->expects($this->never())
			->method('setAppValue');

		$this->service->setAiApiUrl('http://api.openai.com');
	}

	public function testSetAiApiUrlIgnoresMalformed(): void {
		$this->config->expects($this->never())
			->method('setAppValue');

		$this->service->setAiApiUrl('not a url');
	}

	// ========================================
	// getCliUrl Tests
	// ========================================

	public function testGetCliUrlReturnsEmptyWhenNotSet(): void {
		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('overwrite.cli.url', '')
			->willReturn('');

		$this->assertSame('', $this->service->getCliUrl());
	}

	public function testGetCliUrlReturnsConfiguredValue(): void {
		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('overwrite.cli.url', '')
			->willReturn('https://cloud.example.com');

		$this->assertSame('https://cloud.example.com', $this->service->getCliUrl());
	}

	// ========================================
	// Filter-Einstellungen (#332)
	// ========================================

	public function testSetUserFiltersNeverPersistsOwnerMissing(): void {
		$stored = null;
		$this->config->expects($this->once())
			->method('setUserValue')
			->willReturnCallback(function ($user, $app, $key, $value) use (&$stored) {
				$stored = json_decode($value, true);
			});

		$this->service->setUserFilters('testuser', [
			'vendor' => 'Stadtwerke',
			'statuses' => ['active'],
			'contractType' => 'fixed',
			'responsible' => 'alice',
			'ownerMissing' => true,
		]);

		$this->assertFalse($stored['ownerMissing'], 'Der Diagnosefilter darf nie gespeichert werden');
		$this->assertSame('Stadtwerke', $stored['vendor'], 'Die uebrigen Filter bleiben erhalten');
		$this->assertSame(['active'], $stored['statuses']);
		$this->assertSame('fixed', $stored['contractType']);
		$this->assertSame('alice', $stored['responsible']);
	}

	public function testGetUserFiltersIgnoresStoredOwnerMissing(): void {
		$this->config->expects($this->once())
			->method('getUserValue')
			->willReturn(json_encode([
				'vendor' => 'Telekom',
				'statuses' => ['cancelled'],
				'contractType' => '',
				'responsible' => '',
				'ownerMissing' => true,
			]));

		$filters = $this->service->getUserFilters('testuser');

		$this->assertFalse($filters['ownerMissing'], 'Ein Altwert aus fruheren Versionen bleibt wirkungslos');
		$this->assertSame('Telekom', $filters['vendor']);
		$this->assertSame(['cancelled'], $filters['statuses']);
	}

	// ========================================
	// Custom Field Active Flag Tests (#368)
	// ========================================

	public function testGetCustomFieldEnabledReturnsStoredTrue(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'custom_field_enabled_1', '')
			->willReturn('1');

		$this->assertTrue($this->service->getCustomFieldEnabled(1));
	}

	public function testGetCustomFieldEnabledReturnsStoredFalse(): void {
		// Explicitly stored "0" wins even if a label exists — an active field may
		// be blank, and a deactivated one may still keep its name.
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'custom_field_enabled_2', '')
			->willReturn('0');

		$this->assertFalse($this->service->getCustomFieldEnabled(2));
	}

	public function testGetCustomFieldEnabledFallsBackToLabelWhenNeverSet(): void {
		// Pre-#368 data: no flag stored, derive from "label not empty".
		$this->config->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'custom_field_enabled_3', '', ''],
				[Application::APP_ID, 'custom_field_label_3', '', 'Kostenstelle'],
			]);

		$this->assertTrue($this->service->getCustomFieldEnabled(3));
	}

	public function testGetCustomFieldEnabledFallbackIsFalseForEmptyLabel(): void {
		$this->config->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'custom_field_enabled_1', '', ''],
				[Application::APP_ID, 'custom_field_label_1', '', ''],
			]);

		$this->assertFalse($this->service->getCustomFieldEnabled(1));
	}

	public function testGetCustomFieldEnabledOutOfRange(): void {
		$this->config->expects($this->never())->method('getAppValue');
		$this->assertFalse($this->service->getCustomFieldEnabled(0));
		$this->assertFalse($this->service->getCustomFieldEnabled(4));
	}

	public function testSetCustomFieldEnabledStoresFlag(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'custom_field_enabled_2', '1');

		$this->service->setCustomFieldEnabled(2, true);
	}

	public function testSetCustomFieldEnabledStoresFalse(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'custom_field_enabled_3', '0');

		$this->service->setCustomFieldEnabled(3, false);
	}

	public function testGetCustomFieldLabelsGatesDeactivatedFields(): void {
		// A named-but-deactivated field is gated out of the contract-form labels
		// (empty), while an active named field passes through.
		$this->config->method('getAppValue')
			->willReturnMap([
				[Application::APP_ID, 'custom_field_enabled_1', '', '1'],
				[Application::APP_ID, 'custom_field_label_1', '', 'Vertragsnummer'],
				[Application::APP_ID, 'custom_field_enabled_2', '', '0'],
				[Application::APP_ID, 'custom_field_label_2', '', 'Kostenstelle'],
				[Application::APP_ID, 'custom_field_enabled_3', '', '0'],
				[Application::APP_ID, 'custom_field_label_3', '', ''],
			]);

		$labels = $this->service->getCustomFieldLabels();

		$this->assertSame('Vertragsnummer', $labels['customFieldLabel1']);
		$this->assertSame('', $labels['customFieldLabel2'], 'Deaktiviert -> ausgeblendet');
		$this->assertSame('', $labels['customFieldLabel3']);
	}
}
