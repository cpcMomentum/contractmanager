<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {
    private ?string $userId;

    public function __construct(
        IRequest $request,
        ?string $userId
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->userId = $userId;
    }

    /**
     * @NoAdminRequired
     */
    public function get(): JSONResponse {
        // TODO: Implement
        return new JSONResponse([
            'reminderDays' => 30,
            'emailNotifications' => true,
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function update(): JSONResponse {
        // TODO: Implement
        return new JSONResponse(['success' => true]);
    }
}
