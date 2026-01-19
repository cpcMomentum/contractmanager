<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\ForbiddenException;
use OCA\ContractManager\Service\NotFoundException;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ContractController extends Controller {

    public function __construct(
        IRequest $request,
        private ContractService $service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function index(): JSONResponse {
        return new JSONResponse($this->service->findAll($this->userId));
    }

    /**
     * @NoAdminRequired
     */
    public function archived(): JSONResponse {
        return new JSONResponse($this->service->findArchived($this->userId));
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): JSONResponse {
        try {
            return new JSONResponse($this->service->find($id));
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(
        string $name,
        string $vendor,
        string $startDate,
        string $endDate,
        string $cancellationPeriod,
        string $contractType,
        ?int $categoryId = null,
        ?string $renewalPeriod = null,
        ?string $cost = null,
        ?string $currency = null,
        ?string $costInterval = null,
        ?string $contractFolder = null,
        ?string $mainDocument = null,
        bool $reminderEnabled = true,
        ?int $reminderDays = null,
        ?string $notes = null,
    ): JSONResponse {
        try {
            // Validate input data
            $this->service->validate([
                'name' => $name,
                'vendor' => $vendor,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            $contract = $this->service->create(
                $name,
                $vendor,
                $startDate,
                $endDate,
                $cancellationPeriod,
                $contractType,
                $this->userId,
                $categoryId,
                $renewalPeriod,
                $cost,
                $currency,
                $costInterval,
                $contractFolder,
                $mainDocument,
                $reminderEnabled,
                $reminderDays,
                $notes,
            );

            return new JSONResponse($contract, Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['errors' => $e->getErrors()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(
        int $id,
        string $name,
        string $vendor,
        string $startDate,
        string $endDate,
        string $cancellationPeriod,
        string $contractType,
        ?int $categoryId = null,
        ?string $status = null,
        ?string $renewalPeriod = null,
        ?string $cost = null,
        ?string $currency = null,
        ?string $costInterval = null,
        ?string $contractFolder = null,
        ?string $mainDocument = null,
        bool $reminderEnabled = true,
        ?int $reminderDays = null,
        ?string $notes = null,
    ): JSONResponse {
        try {
            // Validate input data
            $this->service->validate([
                'name' => $name,
                'vendor' => $vendor,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'status' => $status,
                'notes' => $notes,
            ]);

            $contract = $this->service->update(
                $id,
                $this->userId,
                $name,
                $vendor,
                $startDate,
                $endDate,
                $cancellationPeriod,
                $contractType,
                $categoryId,
                $status,
                $renewalPeriod,
                $cost,
                $currency,
                $costInterval,
                $contractFolder,
                $mainDocument,
                $reminderEnabled,
                $reminderDays,
                $notes,
            );

            return new JSONResponse($contract);
        } catch (ValidationException $e) {
            return new JSONResponse(['errors' => $e->getErrors()], Http::STATUS_BAD_REQUEST);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        } catch (ForbiddenException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->delete($id, $this->userId);
            return new JSONResponse(['success' => true]);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        } catch (ForbiddenException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function archive(int $id): JSONResponse {
        try {
            $contract = $this->service->archive($id, $this->userId);
            return new JSONResponse($contract);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        } catch (ForbiddenException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function restore(int $id): JSONResponse {
        try {
            $contract = $this->service->restore($id, $this->userId);
            return new JSONResponse($contract);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        } catch (ForbiddenException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }
}
