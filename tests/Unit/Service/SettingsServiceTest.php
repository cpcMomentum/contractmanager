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
	// Talk Chat Token Tests
	// ========================================

	public function testGetTalkChatTokenReturnsNullWhenEmpty(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'talk_chat_token', '')
			->willReturn('');

		$result = $this->service->getTalkChatToken();

		$this->assertNull($result);
	}

	public function testGetTalkChatTokenReturnsValue(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'talk_chat_token', '')
			->willReturn('abc123xyz');

		$result = $this->service->getTalkChatToken();

		$this->assertEquals('abc123xyz', $result);
	}

	public function testSetTalkChatToken(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'talk_chat_token', 'newtoken');

		$this->service->setTalkChatToken('newtoken');
	}

	public function testSetTalkChatTokenWithNull(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'talk_chat_token', '');

		$this->service->setTalkChatToken(null);
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
}
