<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ContractController extends Controller {
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
    public function index(): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }

    /**
     * @NoAdminRequired
     */
    public function create(): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }

    /**
     * @NoAdminRequired
     */
    public function update(int $id): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }

    /**
     * @NoAdminRequired
     */
    public function archive(int $id): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }

    /**
     * @NoAdminRequired
     */
    public function restore(int $id): JSONResponse {
        // TODO: Implement
        return new JSONResponse([]);
    }
}
