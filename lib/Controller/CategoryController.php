<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\CategoryService;
use OCA\ContractManager\Service\NotFoundException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CategoryController extends Controller {

    public function __construct(
        IRequest $request,
        private CategoryService $service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function index(): JSONResponse {
        return new JSONResponse($this->service->findAll());
    }

    /**
     * Admin only - create category
     */
    public function create(string $name): JSONResponse {
        $category = $this->service->create($name);
        return new JSONResponse($category, Http::STATUS_CREATED);
    }

    /**
     * Admin only - update category
     */
    public function update(int $id, string $name, ?int $sortOrder = null): JSONResponse {
        try {
            $category = $this->service->update($id, $name, $sortOrder);
            return new JSONResponse($category);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Admin only - delete category
     */
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->delete($id);
            return new JSONResponse(['success' => true]);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
        }
    }
}
