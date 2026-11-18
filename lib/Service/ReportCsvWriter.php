<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

/** UTF-8 with BOM and semicolons, so Excel opens umlauts and columns correctly. */
class ReportCsvWriter {

	public function __construct(
		private ReportFormatter $f,
	) {
	}

	/** @param array<string, mixed> $report */
	public function contracts(array $report): string {
		$l = $this->f->l10n();
		$rows = [[
			$l->t('Kategorie'), $l->t('Vertrag'), $l->t('Vertragspartner'), $l->t('Kontakt'),
			$l->t('Läuft bis'), $l->t('Nächste Kündigungsfrist'), $l->t('Status'),
			$l->t('Zahlungsweise'), $l->t('Betrag'), $l->t('Währung'), $l->t('Netto/Brutto'),
		]];
		foreach ($report['groups'] as $group) {
			foreach ($group['rows'] as $row) {
				$rows[] = [
					$this->f->category($group['category']),
					$row['name'],
					$row['vendor'],
					implode("\n", $row['contact']),
					$this->f->date($row['runsUntil']),
					$this->f->date($row['deadline']),
					$this->f->status($row['status']),
					$this->f->interval($row['costInterval']),
					$this->f->number($row['cost'], false),
					$row['cost'] === null ? '' : $row['currency'],
					$row['cost'] === null ? '' : $this->f->amountType($row['amountType']),
				];
			}
		}
		return $this->csv($rows);
	}

	/** @param array<string, mixed> $report */
	public function costs(array $report): string {
		$l = $this->f->l10n();
		$rows = [[
			$l->t('Bereich'), $l->t('Bezeichnung'), $l->t('Anzahl Verträge'), $l->t('Währung'),
			$l->t('Netto/Brutto'), $l->t('Monatlich'), $l->t('Jährlich'), $l->t('Betrag'), $l->t('Zahlungsweise'),
		]];
		$sections = [
			[$l->t('Kategorie'), $report['byCategory'], fn (?string $label): string => $this->f->category($label)],
			[$l->t('Vertragspartner'), $report['byVendor'], fn (?string $label): string => $label ?? $l->t('Ohne Vertragspartner')],
			[$l->t('Gesamt'), $report['totals'], fn (?string $label): string => $l->t('Alle Verträge')],
		];
		foreach ($sections as [$section, $sums, $label]) {
			foreach ($sums as $sum) {
				$rows[] = [
					$section,
					$label($sum['label']),
					(string)$sum['count'],
					$sum['currency'],
					$this->f->amountType($sum['amountType']),
					$this->f->number($sum['monthly'], false),
					$this->f->number($sum['yearly'], false),
					'',
					'',
				];
			}
		}
		foreach ($report['irregular'] as $row) {
			$rows[] = [
				$l->t('Ohne festen Rhythmus'),
				$row['vendor'] === '' ? $row['name'] : $row['name'] . ' (' . $row['vendor'] . ')',
				'1',
				$row['currency'],
				$this->f->amountType($row['amountType']),
				'',
				'',
				$this->f->number($row['cost'], false),
				$this->f->interval($row['costInterval']),
			];
		}
		return $this->csv($rows);
	}

	/** @param array<string, mixed> $report */
	public function deadlines(array $report): string {
		$l = $this->f->l10n();
		$rows = [[
			$l->t('Kündigung bis'), $l->t('Verlängert sich am'), $l->t('Verlängerung um'), $l->t('Vertrag'),
			$l->t('Vertragspartner'), $l->t('Zahlungsweise'), $l->t('Betrag'), $l->t('Währung'), $l->t('Netto/Brutto'),
		]];
		foreach ($report['rows'] as $row) {
			$rows[] = [
				$this->f->date($row['deadline']),
				$this->f->date($row['renews']),
				$row['renews'] === null ? '' : $this->f->period($row['renewalPeriod']),
				$row['name'],
				$row['vendor'],
				$this->f->interval($row['costInterval']),
				$this->f->number($row['cost'], false),
				$row['cost'] === null ? '' : $row['currency'],
				$row['cost'] === null ? '' : $this->f->amountType($row['amountType']),
			];
		}
		return $this->csv($rows);
	}

	/** @param list<list<string>> $rows */
	private function csv(array $rows): string {
		$handle = fopen('php://temp', 'r+');
		foreach ($rows as $row) {
			fputcsv($handle, array_map([$this, 'neutralize'], $row), ';', '"', '');
		}
		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);
		return "\u{FEFF}" . str_replace("\n", "\r\n", (string)$csv);
	}

	// Cells starting with = + - @ would run as formulas in Excel.
	private function neutralize(string $value): string {
		if (preg_match('/^-?[\d.,]+$/', $value) === 1) {
			return $value;
		}
		return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
	}
}
