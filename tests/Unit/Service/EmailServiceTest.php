<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Service\EmailService;
use PHPUnit\Framework\TestCase;

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
}
