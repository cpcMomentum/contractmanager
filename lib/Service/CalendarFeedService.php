<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\Db\Contract;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;

/**
 * Builds a read-only iCalendar (RFC 5545) feed of a user's upcoming contract
 * deadlines, so they can subscribe to it in the Nextcloud Calendar app and see
 * the dates they would be reminded about as a passive overview (#68).
 *
 * The feed is hand-rolled rather than pulling in sabre/vobject: an all-day
 * VEVENT per contract is simple, keeps the service dependency-free (and thus
 * unit-testable without NC's 3rdparty autoloader), and gives full control over
 * escaping and line folding.
 */
class CalendarFeedService {

	private const PRODID = '-//cpcMomentum//VertragsWerk//DE';

	public function __construct(
		private ReminderService $reminderService,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
	) {
	}

	/**
	 * The full VCALENDAR document for a user, ready to serve as text/calendar.
	 */
	public function buildIcs(string $userId): string {
		$dtstamp = $this->timeFactory->getDateTime()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:' . self::PRODID,
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->escape($this->l10n->t('VertragsWerk – Fristen')),
		];

		foreach ($this->reminderService->getUpcomingDeadlinesForUser($userId) as $entry) {
			/** @var Contract $contract */
			$contract = $entry['contract'];
			/** @var \DateTime $deadline */
			$deadline = $entry['deadline'];

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:contract-' . $contract->getId() . '-deadline@contractmanager';
			$lines[] = 'DTSTAMP:' . $dtstamp;
			// All-day event on the deadline date (VALUE=DATE, no time component).
			$lines[] = 'DTSTART;VALUE=DATE:' . $deadline->format('Ymd');
			$lines[] = 'SUMMARY:' . $this->escape($this->summary($contract));
			$description = $this->description($contract);
			if ($description !== '') {
				$lines[] = 'DESCRIPTION:' . $this->escape($description);
			}
			// Passive marker — must not block free/busy time in the calendar.
			$lines[] = 'TRANSP:TRANSPARENT';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
	}

	/**
	 * Event title: the deadline kind plus the contract name. For auto-renewal
	 * contracts the date is the cancellation deadline; for fixed-term ones it is
	 * the contract end — matching exactly what the reminders are about.
	 */
	private function summary(Contract $contract): string {
		$name = $contract->getName() !== '' ? $contract->getName() : $this->l10n->t('Vertrag');
		if ($contract->getContractType() === 'auto_renewal') {
			return $this->l10n->t('Kündigungsfrist: %s', [$name]);
		}
		return $this->l10n->t('Vertragsende: %s', [$name]);
	}

	private function description(Contract $contract): string {
		$vendor = $contract->getVendor();
		if ($vendor !== null && $vendor !== '') {
			return $this->l10n->t('Vertragspartner: %s', [$vendor]);
		}
		return '';
	}

	/**
	 * Escape a text value per RFC 5545 §3.3.11 (backslash, semicolon, comma and
	 * newlines). Order matters: backslashes first.
	 */
	private function escape(string $value): string {
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
		$value = str_replace(';', '\\;', $value);
		$value = str_replace(',', '\\,', $value);
		return $value;
	}

	/**
	 * Fold content lines longer than 75 octets by inserting CRLF + a single
	 * space, as required by RFC 5545 §3.1. Folds on octet boundaries but never
	 * inside a multi-byte UTF-8 character, per §3.1's guidance — splitting mid-
	 * character (common with German umlauts) would leave an invalid byte
	 * sequence on either side of the fold.
	 */
	private function fold(string $line): string {
		if (strlen($line) <= 75) {
			return $line;
		}
		$folded = '';
		$current = '';
		foreach (mb_str_split($line) as $char) {
			if (strlen($current) + strlen($char) > 75) {
				$folded .= $current . "\r\n ";
				$current = '';
			}
			$current .= $char;
		}
		return $folded . $current;
	}
}
