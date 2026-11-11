<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Service\EmailService;
use OCA\ContractManager\Service\SettingsService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class EmailServiceTest extends TestCase {

	// ========================================
	// Email Masking for Logs (Issue #125)
	// ========================================

	public function testMaskEmailKeepsFirstCharAndTld(): void {
		$this->assertSame('a***@e***.com', EmailService::maskEmail('axel@example.com'));
		$this->assertSame('j***@c***.de', EmailService::maskEmail('john.doe@company.de'));
		$this->assertSame('s***@s***.org', EmailService::maskEmail('support@some-org.org'));
	}

	public function testMaskEmailHandlesShortAddresses(): void {
		$this->assertSame('a***@b***.de', EmailService::maskEmail('a@b.de'));
	}

	public function testMaskEmailHandlesMultiLevelDomain(): void {
		// TLD is the last dotted segment; intermediate subdomains get collapsed
		$result = EmailService::maskEmail('axel@mail.bedethi.com');
		$this->assertStringStartsWith('a***@', $result);
		$this->assertStringEndsWith('.com', $result);
		$this->assertStringNotContainsString('bedethi', $result);
		$this->assertStringNotContainsString('mail.', $result);
	}

	public function testMaskEmailHandlesInvalidInput(): void {
		$this->assertSame('***', EmailService::maskEmail(''));
		$this->assertSame('***', EmailService::maskEmail('no-at-sign'));
		$this->assertSame('***', EmailService::maskEmail('@example.com'));
		$this->assertSame('***', EmailService::maskEmail('user@'));
	}

	public function testMaskEmailHandlesDomainWithoutTld(): void {
		$this->assertSame('u***@***', EmailService::maskEmail('user@localhost'));
	}

	public function testMaskEmailDoesNotLeakOriginal(): void {
		$original = 'sensitive.user@confidential-domain.example';
		$masked = EmailService::maskEmail($original);
		$this->assertStringNotContainsString('sensitive', $masked);
		$this->assertStringNotContainsString('confidential', $masked);
	}

	// ========================================
	// Deep-link URL building for reminder mails (Issue #187, #189)
	// ========================================

	/**
	 * Invoke the private buildContractUrl() with controlled front-controller
	 * settings. getAbsoluteURL() is stubbed to echo its argument behind a fixed
	 * host, so the assertion targets exactly the path buildContractUrl builds.
	 */
	private function callBuildContractUrl(bool $ignoreFrontController, ?string $frontControllerEnv): string {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')
			->with('htaccess.IgnoreFrontController', false)
			->willReturn($ignoreFrontController);

		$url = $this->createMock(IURLGenerator::class);
		$url->method('getAbsoluteURL')
			->willReturnCallback(static fn (string $path): string => 'https://nc.example.test' . $path);

		$service = new EmailService(
			$this->createMock(IMailer::class),
			$this->createMock(IUserManager::class),
			$url,
			$config,
			$this->createMock(IFactory::class),
			$this->createMock(SettingsService::class),
			$this->createMock(LoggerInterface::class),
		);

		$contract = new Contract();
		$contract->setId(42);

		$prev = getenv('front_controller_active');
		if ($frontControllerEnv === null) {
			putenv('front_controller_active');
		} else {
			putenv('front_controller_active=' . $frontControllerEnv);
		}
		try {
			$method = new ReflectionMethod(EmailService::class, 'buildContractUrl');
			return $method->invoke($service, $contract);
		} finally {
			if ($prev === false) {
				putenv('front_controller_active');
			} else {
				putenv('front_controller_active=' . $prev);
			}
		}
	}

	public function testBuildContractUrlUsesIndexPhpWhenFrontControllerInactive(): void {
		// Default instance (mod_rewrite off): path must include /index.php
		$this->assertSame(
			'https://nc.example.test/index.php/apps/contractmanager/?contract=42',
			$this->callBuildContractUrl(false, null),
		);
	}

	public function testBuildContractUrlOmitsIndexPhpWhenIgnoreFrontControllerSet(): void {
		// htaccess.IgnoreFrontController = true → no /index.php prefix
		$this->assertSame(
			'https://nc.example.test/apps/contractmanager/?contract=42',
			$this->callBuildContractUrl(true, null),
		);
	}

	public function testBuildContractUrlOmitsIndexPhpWhenFrontControllerEnvActive(): void {
		// front_controller_active=true (env) → no /index.php prefix
		$this->assertSame(
			'https://nc.example.test/apps/contractmanager/?contract=42',
			$this->callBuildContractUrl(false, 'true'),
		);
	}

	public function testReminderMailUsesRecipientLanguage(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('user@example.test');
		$user->method('getDisplayName')->willReturn('Test');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('formal')->willReturn($user);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$factory = $this->createMock(IFactory::class);
		$factory->method('getUserLanguage')->with($user)->willReturn('de_DE');
		$factory->expects($this->once())->method('get')->with('contractmanager', 'de_DE')->willReturn($l);

		$settings = $this->createMock(SettingsService::class);
		$settings->method('getUserEmailReminder')->willReturn(true);
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($this->createMock(IMessage::class));
		$mailer->expects($this->once())->method('send');

		$service = new EmailService($mailer, $userManager, $this->createMock(IURLGenerator::class), $this->createMock(IConfig::class), $factory, $settings, $this->createMock(LoggerInterface::class));
		$contract = new Contract();
		$contract->setId(1);
		$contract->setName('Strom');
		$contract->setVendor('Stadtwerke');

		$this->assertTrue($service->sendReminder($contract, 'formal', '30.11.2026', 'final'));
	}
}
