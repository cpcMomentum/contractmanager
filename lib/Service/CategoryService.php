<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class CategoryService {

    private const DEFAULT_CATEGORIES = [
        'Software',
        'Telekommunikation',
        'Versicherung',
        'Miete/Leasing',
        'Dienstleistung',
        'Sonstige',
    ];

    public function __construct(
        private CategoryMapper $mapper,
    ) {
    }

    /**
     * @return Category[]
     */
    public function findAll(): array {
        $categories = $this->mapper->findAll();

        // Initialize default categories if none exist
        if (empty($categories)) {
            $this->initializeDefaults();
            $categories = $this->mapper->findAll();
        }

        return $categories;
    }

    /**
     * @throws NotFoundException
     */
    public function find(int $id): Category {
        try {
            return $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }
    }

    public function create(string $name): Category {
        $category = new Category();
        $category->setName($name);
        $category->setSortOrder($this->mapper->getMaxSortOrder() + 1);

        return $this->mapper->insert($category);
    }

    /**
     * @throws NotFoundException
     */
    public function update(int $id, string $name, ?int $sortOrder = null): Category {
        try {
            $category = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $category->setName($name);
        if ($sortOrder !== null) {
            $category->setSortOrder($sortOrder);
        }

        return $this->mapper->update($category);
    }

    /**
     * @throws NotFoundException
     */
    public function delete(int $id): Category {
        try {
            $category = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        return $this->mapper->delete($category);
    }

    private function initializeDefaults(): void {
        foreach (self::DEFAULT_CATEGORIES as $index => $name) {
            $category = new Category();
            $category->setName($name);
            $category->setSortOrder($index + 1);
            $this->mapper->insert($category);
        }
    }
}
