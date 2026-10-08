<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ContractImportService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ImportController extends Controller {

	private const MAX_FILE_SIZE = 10 * 1024 * 1024;

	public function __construct(
		IRequest $request,
		private ?string $userId,
		private ContractImportService $importService,
		private PermissionService $permissionService,
		private IRootFolder $rootFolder,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function preview(string $path = ''): JSONResponse {
		return $this->handle($path, false);
	}

	#[NoAdminRequired]
	public function import(string $path = ''): JSONResponse {
		return $this->handle($path, true);
	}

	private function handle(string $path, bool $write): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l->t('Nicht angemeldet')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->permissionService->canEdit($this->userId)) {
			return new JSONResponse(['error' => $this->l->t('Keine Berechtigung')], Http::STATUS_FORBIDDEN);
		}

		try {
			$data = $this->importService->parse($this->readFile($path));
		} catch (NotFoundException) {
			return new JSONResponse(['error' => $this->l->t('Datei nicht gefunden')], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => implode(' ', $e->getErrors())], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->warning('Contract import file not readable', [
				'app' => Application::APP_ID,
				'user' => $this->userId,
				'exception' => $e,
			]);
			return new JSONResponse(['error' => $this->l->t('Die Datei konnte nicht gelesen werden.')], Http::STATUS_BAD_REQUEST);
		}

		try {
			$summary = $write
				? $this->importService->import($this->userId, $data)
				: $this->importService->preview($this->userId, $data);
		} catch (\Throwable $e) {
			$this->logger->error('Contract import failed', [
				'app' => Application::APP_ID,
				'user' => $this->userId,
				'exception' => $e,
			]);
			return new JSONResponse(['error' => $this->l->t('Einlesen fehlgeschlagen')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse($summary);
	}

	/**
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	private function readFile(string $path): string {
		$path = ltrim($path, '/');
		if ($path === '') {
			throw new NotFoundException();
		}
		$node = $this->rootFolder->getUserFolder($this->userId)->get($path);
		if (!$node instanceof File || strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION)) !== 'json') {
			throw new ValidationException(['file' => $this->l->t('Bitte eine JSON-Datei aus dem Backup wählen.')]);
		}
		if ($node->getSize() > self::MAX_FILE_SIZE) {
			throw new ValidationException(['file' => $this->l->t('Die Datei ist zu groß.')]);
		}
		return $node->getContent();
	}
}
