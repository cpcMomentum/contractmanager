<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Controller;

use OCA\ContractManager\Controller\ImportController;
use OCA\ContractManager\Service\ContractImportService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImportControllerTest extends TestCase {

	private ContractImportService $importService;
	private PermissionService $permissionService;
	private Folder $userFolder;

	protected function setUp(): void {
		parent::setUp();
		$this->importService = $this->createMock(ContractImportService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->userFolder = $this->createMock(Folder::class);
	}

	private function controller(?string $userId = 'bob'): ImportController {
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($this->userFolder);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		return new ImportController(
			$this->createMock(IRequest::class),
			$userId,
			$this->importService,
			$this->permissionService,
			$rootFolder,
			$l,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function jsonFile(string $name = 'contracts-2026-10-08-120000.json', string $content = '{}'): File {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		$file->method('getSize')->willReturn(strlen($content));
		$file->method('getContent')->willReturn($content);
		return $file;
	}

	public function testWithoutEditRightNothingIsReadOrImported(): void {
		$this->permissionService->method('canEdit')->willReturn(false);
		$this->userFolder->expects($this->never())->method('get');
		$this->importService->expects($this->never())->method('import');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->import('/a.json')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->preview('/a.json')->getStatus());
	}

	public function testMissingFileGives404(): void {
		$this->permissionService->method('canEdit')->willReturn(true);
		$this->userFolder->method('get')->willThrowException(new NotFoundException());

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller()->preview('/weg.json')->getStatus());
	}

	public function testNonJsonFileIsRejected(): void {
		$this->permissionService->method('canEdit')->willReturn(true);
		$this->userFolder->method('get')->willReturn($this->jsonFile('vertrag.pdf'));
		$this->importService->expects($this->never())->method('parse');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->preview('/vertrag.pdf')->getStatus());
	}

	public function testFolderIsRejected(): void {
		$this->permissionService->method('canEdit')->willReturn(true);
		$this->userFolder->method('get')->willReturn($this->createMock(Folder::class));

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->preview('/Backup')->getStatus());
	}

	public function testUnknownSchemaReturnsMessage(): void {
		$this->permissionService->method('canEdit')->willReturn(true);
		$this->userFolder->method('get')->willReturn($this->jsonFile());
		$this->importService->method('parse')->willThrowException(new ValidationException(['file' => 'unbekanntes Format']));

		$response = $this->controller()->import('/x.json');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'unbekanntes Format'], $response->getData());
	}

	public function testPreviewDoesNotImport(): void {
		$this->permissionService->method('canEdit')->willReturn(true);
		$this->userFolder->method('get')->with('Backup/x.json')->willReturn($this->jsonFile());
		$this->importService->method('parse')->willReturn(['contracts' => []]);
		$this->importService->expects($this->never())->method('import');
		$this->importService->expects($this->once())->method('preview')->with('bob')->willReturn(['contracts' => 3]);

		$response = $this->controller()->preview('/Backup/x.json');
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['contracts' => 3], $response->getData());
	}

	public function testImportRunsForCurrentUser(): void {
		$this->permissionService->method('canEdit')->with('bob')->willReturn(true);
		$this->userFolder->method('get')->willReturn($this->jsonFile());
		$this->importService->method('parse')->willReturn(['contracts' => []]);
		$this->importService->expects($this->once())->method('import')->with('bob')->willReturn(['contracts' => 2]);

		$this->assertSame(['contracts' => 2], $this->controller()->import('/x.json')->getData());
	}
}
