<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Service\TalkService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TalkServiceTest extends TestCase {

	public function testReminderMessageUsesRecipientLanguage(): void {
		$user = $this->createMock(IUser::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('formal')->willReturn($user);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$factory = $this->createMock(IFactory::class);
		$factory->method('getUserLanguage')->with($user)->willReturn('de_DE');
		$factory->expects($this->once())->method('get')->with('contractmanager', 'de_DE')->willReturn($l);

		$service = new TalkService($this->createMock(IAppManager::class), $this->createMock(LoggerInterface::class), $factory, $userManager);

		try {
			$service->sendReminderMessage('token', 'Strom', '30.11.2026', 'final', 'auto_renewal', 'formal');
		} catch (\Throwable) {
			// Talk itself is not installed in the unit environment; only the language lookup matters here.
		}
	}
}
