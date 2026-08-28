<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\CalendarFeedService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Serves the read-only iCalendar feed of a user's contract deadlines (#68).
 *
 * The endpoint is public (calendar clients send no Nextcloud session) and is
 * authenticated solely by the unguessable token in the URL. The token resolves
 * to exactly one user; the feed then contains only that user's own deadlines.
 */
class CalendarController extends Controller {

	public function __construct(
		IRequest $request,
		private SettingsService $settingsService,
		private CalendarFeedService $calendarFeedService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * GET /feed/{token}/contracts.ics
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function feed(string $token): Response {
		$userId = $this->settingsService->getUserByCalendarFeedToken($token);
		if ($userId === null) {
			// Unknown/empty token — do not reveal whether the app is installed.
			return new NotFoundResponse();
		}

		$ics = $this->calendarFeedService->buildIcs($userId);
		return new DataDisplayResponse($ics, Http::STATUS_OK, ['Content-Type' => 'text/calendar; charset=UTF-8']);
	}
}
