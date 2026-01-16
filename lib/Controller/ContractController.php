<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\NotFoundException;
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
        return new JSONResponse($this->service->findAll());
    }

    /**
     * @NoAdminRequired
     */
    public function archived(): JSONResponse {
        return new JSONResponse($this->service->findArchived());
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
            $contract = $this->service->update(
                $id,
                $name,
                $vendor,
                $startDate,
                $endDate,
                $cancellationPeriod,
                $contractType,
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

            return new JSONResponse($contract);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->delete($id);
            return new JSONResponse(['success' => true]);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function archive(int $id): JSONResponse {
        try {
            $contract = $this->service->archive($id);
            return new JSONResponse($contract);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function restore(int $id): JSONResponse {
        try {
            $contract = $this->service->restore($id);
            return new JSONResponse($contract);
        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
        }
    }
}
