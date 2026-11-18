<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use TCPDF;

/** A4 landscape tables drawn directly with TCPDF; rows are never split across pages. */
class ReportPdfWriter {

	private const FONT = 'freesans';
	private const SIZE = 9;
	private const PAD = 1.2;
	private const LINE = 4.2;

	private TCPDF $pdf;
	/** @var list<array{0: string, 1: float, 2: string}> */
	private array $columns = [];

	public function __construct(
		private ReportFormatter $f,
	) {
	}

	/** @param array<string, mixed> $report */
	public function contracts(array $report): string {
		$l = $this->f->l10n();
		$this->start(ReportService::TYPE_CONTRACTS, $this->f->asOf($report['today']));
		$this->columns = [
			[$l->t('Vertrag'), 55, 'L'],
			[$l->t('Vertragspartner'), 64, 'L'],
			[$l->t('Läuft bis'), 24, 'L'],
			[$l->t('Nächste Kündigungsfrist'), 34, 'L'],
			[$l->t('Status'), 30, 'L'],
			[$l->t('Zahlungsweise'), 26, 'L'],
			[$l->t('Betrag'), 44, 'R'],
		];
		$this->tableHeader();
		if ($report['groups'] === []) {
			$this->empty();
		}
		foreach ($report['groups'] as $group) {
			$first = true;
			foreach ($group['rows'] as $row) {
				$partner = implode("\n", array_filter(array_merge([$row['vendor']], $row['contact'])));
				$cells = [
					$row['name'],
					$partner,
					$this->f->date($row['runsUntil']),
					$this->f->date($row['deadline']),
					$this->f->status($row['status']),
					$this->f->interval($row['costInterval']),
					$this->amount($row['cost'], $row['currency'], $row['amountType']),
				];
				$this->row($cells, $first ? $this->f->category($group['category']) : null);
				$first = false;
			}
		}
		return $this->finish();
	}

	/** @param array<string, mixed> $report */
	public function costs(array $report): string {
		$l = $this->f->l10n();
		$this->start(ReportService::TYPE_COSTS, $this->f->asOf($report['today']));
		$this->note($l->t('Laufende Verträge, umgerechnet auf Monat und Jahr. Netto, Brutto und Währungen werden getrennt summiert.'));

		$sums = [
			[$l->t('Nach Kategorie'), $l->t('Kategorie'), $report['byCategory'], fn (?string $label): string => $this->f->category($label)],
			[$l->t('Nach Vertragspartner'), $l->t('Vertragspartner'), $report['byVendor'], fn (?string $label): string => $label ?? $l->t('Ohne Vertragspartner')],
			[$l->t('Gesamt'), $l->t('Verträge'), $report['totals'], fn (?string $label): string => $l->t('Alle Verträge')],
		];
		foreach ($sums as [$heading, $first, $list, $label]) {
			$this->heading($heading);
			$this->columns = [
				[$first, 97, 'L'],
				[$l->t('Netto/Brutto'), 30, 'L'],
				[$l->t('Anzahl Verträge'), 30, 'R'],
				[$l->t('Monatlich'), 60, 'R'],
				[$l->t('Jährlich'), 60, 'R'],
			];
			$this->tableHeader();
			if ($list === []) {
				$this->empty();
			}
			foreach ($list as $sum) {
				$this->row([
					$label($sum['label']),
					$this->f->amountType($sum['amountType']),
					(string)$sum['count'],
					$this->f->money($sum['monthly'], $sum['currency']),
					$this->f->money($sum['yearly'], $sum['currency']),
				]);
			}
		}

		$this->heading($l->t('Ohne festen Rhythmus'));
		$this->note($l->t('Diese Verträge haben keinen Monatswert und fließen in keine Summe ein.'));
		$this->columns = [
			[$l->t('Vertrag'), 80, 'L'],
			[$l->t('Vertragspartner'), 80, 'L'],
			[$l->t('Kategorie'), 47, 'L'],
			[$l->t('Zahlungsweise'), 30, 'L'],
			[$l->t('Betrag'), 40, 'R'],
		];
		$this->tableHeader();
		if ($report['irregular'] === []) {
			$this->empty();
		}
		foreach ($report['irregular'] as $row) {
			$this->row([
				$row['name'],
				$row['vendor'],
				$this->f->category($row['category']),
				$this->f->interval($row['costInterval']),
				$this->amount($row['cost'], $row['currency'], $row['amountType']),
			]);
		}
		return $this->finish();
	}

	/** @param array<string, mixed> $report */
	public function deadlines(array $report): string {
		$l = $this->f->l10n();
		$this->start(ReportService::TYPE_DEADLINES, $this->f->asOf($report['today']));
		$this->note($l->t('Kündigungsfristen und automatische Verlängerungen vom %1$s bis %2$s', [
			$this->f->date($report['today']),
			$this->f->date($report['until']),
		]));
		$this->columns = [
			[$l->t('Kündigung bis'), 30, 'L'],
			[$l->t('Verlängert sich am'), 34, 'L'],
			[$l->t('Verlängerung um'), 30, 'L'],
			[$l->t('Vertrag'), 70, 'L'],
			[$l->t('Vertragspartner'), 63, 'L'],
			[$l->t('Betrag'), 50, 'R'],
		];
		$this->tableHeader();
		if ($report['rows'] === []) {
			$this->empty();
		}
		foreach ($report['rows'] as $row) {
			$this->row([
				$this->f->date($row['deadline']),
				$this->f->date($row['renews']),
				$row['renews'] === null ? '' : $this->f->period($row['renewalPeriod']),
				$row['name'],
				$row['vendor'],
				$this->amount($row['cost'], $row['currency'], $row['amountType'], $row['costInterval']),
			]);
		}
		return $this->finish();
	}

	private function amount(?float $cost, string $currency, string $amountType, ?string $interval = null): string {
		if ($cost === null) {
			return '';
		}
		$text = $this->f->money($cost, $currency) . ' ' . $this->f->amountType($amountType);
		$interval = $interval === null ? '' : $this->f->interval($interval);
		return $interval === '' ? $text : $text . "\n" . $interval;
	}

	private function start(string $type, string $subtitle): void {
		$pageLabel = $this->f->l10n()->t('Seite %1$s von %2$s', ['{page}', '{pages}']);
		$this->pdf = new class('L', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF {
			public string $pageLabel = '';

			public function Footer(): void {
				$this->SetY(-12);
				$this->SetFont('freesans', '', 8);
				$text = str_replace(['{page}', '{pages}'], [$this->getAliasNumPage(), $this->getAliasNbPages()], $this->pageLabel);
				$this->Cell(0, 6, $text, 0, 0, 'R');
			}
		};
		$this->pdf->pageLabel = $pageLabel;
		$this->pdf->SetCreator('VertragsWerk');
		$this->pdf->SetTitle($this->f->title($type));
		$this->pdf->setPrintHeader(false);
		$this->pdf->setPrintFooter(true);
		$this->pdf->SetMargins(10, 10, 10);
		$this->pdf->SetAutoPageBreak(true, 15);
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
		$this->pdf->AddPage();

		$this->pdf->SetFont(self::FONT, 'B', 15);
		$this->pdf->Cell(0, 8, $this->f->title($type), 0, 1);
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
		$this->pdf->Cell(0, 5, $subtitle, 0, 1);
		$this->pdf->Ln(3);
		$this->columns = [];
	}

	private function finish(): string {
		return $this->pdf->Output('', 'S');
	}

	private function heading(string $text): void {
		if (!$this->fits(20 + 2 * (self::LINE + 2 * self::PAD))) {
			$this->pdf->AddPage();
		}
		$this->pdf->Ln(2);
		$this->pdf->SetFont(self::FONT, 'B', 11);
		$this->pdf->Cell(0, 7, $text, 0, 1);
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
	}

	private function note(string $text): void {
		$this->pdf->SetFont(self::FONT, '', 8);
		$this->pdf->MultiCell(0, 4.5, $text, 0, 'L', false, 1);
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
		$this->pdf->Ln(1);
	}

	private function empty(): void {
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
		$this->pdf->Cell(0, 7, $this->f->l10n()->t('Keine Einträge.'), 0, 1);
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
	}

	private function tableHeader(): void {
		$this->pdf->SetFont(self::FONT, 'B', self::SIZE);
		$this->pdf->SetFillColor(235, 238, 242);
		$this->drawCells(array_map(fn (array $c): string => $c[0], $this->columns), true);
		$this->pdf->SetFont(self::FONT, '', self::SIZE);
	}

	/** @param list<string> $cells */
	private function row(array $cells, ?string $groupLabel = null): void {
		$height = $this->rowHeight($cells);
		$groupHeight = $groupLabel === null ? 0 : 7;
		if (!$this->fits($groupHeight + $height)) {
			$this->pdf->AddPage();
			$this->tableHeader();
		}
		if ($groupLabel !== null) {
			$this->pdf->SetFont(self::FONT, 'B', 10);
			$this->pdf->Cell(0, $groupHeight, $groupLabel, 0, 1);
			$this->pdf->SetFont(self::FONT, '', self::SIZE);
		}
		$this->drawCells($cells, false, $height);
	}

	private function fits(float $height): bool {
		return $this->pdf->GetY() + $height <= $this->pdf->getPageHeight() - $this->pdf->getBreakMargin();
	}

	/** @param list<string> $cells */
	private function rowHeight(array $cells): float {
		$lines = 1;
		foreach ($cells as $i => $text) {
			$lines = max($lines, $this->pdf->getNumLines($text, $this->columns[$i][1], false, true, ['L' => self::PAD, 'R' => self::PAD, 'T' => 0, 'B' => 0]));
		}
		return $lines * self::LINE + 2 * self::PAD;
	}

	/** @param list<string> $cells */
	private function drawCells(array $cells, bool $header, ?float $height = null): void {
		$height ??= $this->rowHeight($cells);
		$x = $this->pdf->GetX();
		$y = $this->pdf->GetY();
		$this->pdf->setCellPaddings(self::PAD, self::PAD, self::PAD, self::PAD);
		foreach ($cells as $i => $text) {
			[, $width, $align] = $this->columns[$i];
			$this->pdf->MultiCell($width, $height, $text, 'B', $align, $header, 0, $x, $y, true, 0, false, true, $height, 'T');
			$x += $width;
		}
		$this->pdf->SetXY($this->pdf->getMargins()['left'], $y + $height);
	}
}
