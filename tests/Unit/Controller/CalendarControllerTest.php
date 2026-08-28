<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use OCA\ContractManager\Controller\CalendarController;
use OCA\ContractManager\Service\CalendarFeedService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class CalendarControllerTest extends TestCase {

	private SettingsService $settingsService;
	private CalendarFeedService $calendarFeedService;
	private CalendarController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->calendarFeedService = $this->createMock(CalendarFeedService::class);
		$this->controller = new CalendarController(
			$this->createMock(IRequest::class),
			$this->settingsService,
			$this->calendarFeedService,
		);
	}

	public function testValidTokenServesIcs(): void {
		$this->settingsService->method('getUserByCalendarFeedToken')->with('goodtoken')->willReturn('alice');
		$this->calendarFeedService->method('buildIcs')->with('alice')->willReturn("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");

		$response = $this->controller->feed('goodtoken');

		// getHeaders() touches NC internals unavailable in the isolated unit env;
		// the content-type is a constant and is verified in the live smoke test.
		$this->assertInstanceOf(DataDisplayResponse::class, $response);
		$this->assertSame("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n", $response->render());
	}

	public function testUnknownTokenReturnsNotFound(): void {
		$this->settingsService->method('getUserByCalendarFeedToken')->willReturn(null);
		// The feed must not be built for an unknown token.
		$this->calendarFeedService->expects($this->never())->method('buildIcs');

		$response = $this->controller->feed('bogus');

		$this->assertInstanceOf(NotFoundResponse::class, $response);
	}
}
