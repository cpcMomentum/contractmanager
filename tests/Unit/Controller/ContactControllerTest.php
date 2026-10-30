<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use OCA\ContractManager\Controller\ContactController;
use OCA\ContractManager\Service\ContactService;
use OCA\ContractManager\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ContactControllerTest extends TestCase {

	private ContactService $contacts;
	private PermissionService $permissions;

	protected function setUp(): void {
		parent::setUp();
		$this->contacts = $this->createMock(ContactService::class);
		$this->permissions = $this->createMock(PermissionService::class);
	}

	private function controller(): ContactController {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		return new ContactController($this->createMock(IRequest::class), $this->contacts, $this->permissions, $l, 'dave');
	}

	public function testSearchNeedsEditRight(): void {
		$this->permissions->method('hasAccess')->willReturn(true);
		$this->permissions->method('canEdit')->willReturn(false);
		$this->contacts->expects($this->never())->method('search');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->search('x')->getStatus());
	}

	public function testStatusNeedsARole(): void {
		$this->permissions->method('hasAccess')->willReturn(false);
		$this->contacts->expects($this->never())->method('isAvailable');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->status()->getStatus());
	}

	public function testResolveNeedsARole(): void {
		$this->permissions->method('hasAccess')->willReturn(false);
		$this->contacts->expects($this->never())->method('resolve');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->resolve('abc')->getStatus());
	}

	public function testEmptyUidResolvesNothing(): void {
		$this->permissions->method('hasAccess')->willReturn(true);
		$this->contacts->expects($this->never())->method('resolve');

		$this->assertSame(['contact' => null], $this->controller()->resolve('  ')->getData());
	}

	public function testContactThatViewerCannotReadIsNull(): void {
		$this->permissions->method('hasAccess')->willReturn(true);
		$this->contacts->method('resolve')->with('abc')->willReturn(null);

		$this->assertSame(['contact' => null], $this->controller()->resolve(' abc ')->getData());
	}
}
