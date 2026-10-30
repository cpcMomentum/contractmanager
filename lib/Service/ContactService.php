<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCP\App\IAppManager;
use OCP\Contacts\IManager;
use OCP\IAddressBook;
use OCP\IURLGenerator;

/**
 * Reads only the viewer's own and shared address books, never the system one with the accounts.
 */
class ContactService {

	private const SEARCH_LIMIT = 20;

	public function __construct(
		private IManager $contactsManager,
		private IAppManager $appManager,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function isAvailable(): bool {
		return $this->personalAddressBooks() !== [];
	}

	/**
	 * @return list<array{uid: string, name: string, organization: string, email: string}>
	 */
	public function search(string $query): array {
		$query = trim($query);
		if ($query === '' || !$this->isAvailable()) {
			return [];
		}

		$results = [];
		$raw = $this->contactsManager->search($query, ['FN', 'ORG', 'EMAIL'], [
			'limit' => self::SEARCH_LIMIT,
			'enumeration' => false,
		]);
		foreach ($raw as $entry) {
			$uid = $this->scalar($entry['UID'] ?? null);
			if ($uid === '' || !empty($entry['isLocalSystemBook']) || isset($results[$uid])) {
				continue;
			}
			$results[$uid] = [
				'uid' => $uid,
				'name' => $this->displayName($entry),
				'organization' => $this->organization($entry),
				'email' => $this->values($entry['EMAIL'] ?? null)[0] ?? '',
			];
			if (count($results) >= self::SEARCH_LIMIT) {
				break;
			}
		}
		return array_values($results);
	}

	/**
	 * @return array{uid: string, name: string, organization: string, emails: list<string>, phones: list<string>, addresses: list<string>, contactsUrl: ?string}|null null when this viewer cannot read it
	 */
	public function resolve(string $uid): ?array {
		$books = $this->personalAddressBooks();
		if ($uid === '' || $books === []) {
			return null;
		}

		$raw = $this->contactsManager->search($uid, ['UID'], [
			'strict_search' => true,
			'enumeration' => false,
		]);
		foreach ($raw as $entry) {
			if ($this->scalar($entry['UID'] ?? null) !== $uid || !empty($entry['isLocalSystemBook'])) {
				continue;
			}
			$book = $books[(string)($entry['addressbook-key'] ?? '')] ?? null;
			if ($book === null) {
				continue;
			}
			return [
				'uid' => $uid,
				'name' => $this->displayName($entry),
				'organization' => $this->organization($entry),
				'emails' => $this->values($entry['EMAIL'] ?? null),
				'phones' => $this->values($entry['TEL'] ?? null),
				'addresses' => array_values(array_filter(array_map(
					fn (string $adr): string => $this->formatAddress($adr),
					$this->values($entry['ADR'] ?? null),
				))),
				'contactsUrl' => $this->contactsUrl($uid, $book),
			];
		}
		return null;
	}

	/**
	 * @return array<string, IAddressBook> keyed by address book key
	 */
	private function personalAddressBooks(): array {
		$books = [];
		foreach ($this->contactsManager->getUserAddressBooks() as $book) {
			if (!$book->isSystemAddressBook()) {
				$books[(string)$book->getKey()] = $book;
			}
		}
		return $books;
	}

	private function contactsUrl(string $uid, IAddressBook $book): ?string {
		if (!$this->appManager->isEnabledForUser('contacts')) {
			return null;
		}
		return $this->urlGenerator->linkToRoute('contacts.contacts.direct', [
			'contact' => $uid . '~' . $book->getUri(),
		]);
	}

	private function displayName(array $entry): string {
		$name = $this->scalar($entry['FN'] ?? null);
		return $name !== '' ? $name : $this->organization($entry);
	}

	private function organization(array $entry): string {
		return trim(explode(';', $this->scalar($entry['ORG'] ?? null))[0]);
	}

	/**
	 * vCard ADR: PO box;extended;street;locality;region;postal code;country
	 */
	private function formatAddress(string $adr): string {
		$parts = array_map('trim', array_pad(explode(';', $adr), 7, ''));
		$lines = [
			trim($parts[0] . ' ' . $parts[1] . ' ' . $parts[2]),
			trim($parts[5] . ' ' . $parts[3]),
			$parts[4],
			$parts[6],
		];
		return implode("\n", array_filter($lines, static fn (string $line): bool => $line !== ''));
	}

	/**
	 * @return list<string>
	 */
	private function values(mixed $value): array {
		$list = is_array($value) ? $value : [$value];
		return array_values(array_filter(
			array_map(fn (mixed $v): string => $this->scalar($v), $list),
			static fn (string $v): bool => $v !== '',
		));
	}

	private function scalar(mixed $value): string {
		return is_scalar($value) ? trim((string)$value) : '';
	}
}
