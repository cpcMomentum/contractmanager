<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use Exception;

/**
 * Exception thrown when input validation fails
 */
class ValidationException extends Exception {

	/** @var array<string, string> */
	private array $errors;

	/**
	 * @param array<string, string> $errors Field name => error message pairs
	 */
	public function __construct(array $errors) {
		parent::__construct('Validation failed');
		$this->errors = $errors;
	}

	/**
	 * Get all validation errors
	 *
	 * @return array<string, string>
	 */
	public function getErrors(): array {
		return $this->errors;
	}
}
