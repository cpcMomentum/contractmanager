<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCP\IL10N;

/** Labels, dates and numbers of a report in the reader's language. */
class ReportFormatter {

	public function __construct(
		private IL10N $l,
	) {
	}

	public function l10n(): IL10N {
		return $this->l;
	}

	public function title(string $type): string {
		return match ($type) {
			ReportService::TYPE_CONTRACTS => $this->l->t('Vertragsspiegel'),
			ReportService::TYPE_COSTS => $this->l->t('Kostenübersicht'),
			ReportService::TYPE_DEADLINES => $this->l->t('Fristenvorschau'),
		};
	}

	public function fileName(string $type, DateTime $today, string $extension): string {
		$name = match ($type) {
			ReportService::TYPE_CONTRACTS => $this->l->t('Vertragsspiegel'),
			ReportService::TYPE_COSTS => $this->l->t('Kostenübersicht'),
			ReportService::TYPE_DEADLINES => $this->l->t('Fristenvorschau'),
		};
		return str_replace(' ', '_', $name) . '_' . $today->format('Y-m-d') . '.' . $extension;
	}

	public function asOf(DateTime $today): string {
		return $this->l->t('Stand: %s', [$this->date($today)]);
	}

	public function date(?DateTime $date): string {
		return $date === null ? '' : (string)$this->l->l('date', $date, ['width' => 'medium']);
	}

	public function status(string $status): string {
		return match ($status) {
			'planned' => $this->l->t('Geplant'),
			'ending_soon' => $this->l->t('Kündigungsfrist endet'),
			'ending' => $this->l->t('endet'),
			'cancelled' => $this->l->t('Gekündigt'),
			'ended' => $this->l->t('Beendet'),
			default => $this->l->t('Laufend'),
		};
	}

	public function interval(?string $interval): string {
		return match ($interval) {
			'weekly' => $this->l->t('Wöchentlich'),
			'monthly' => $this->l->t('Monatlich'),
			'quarterly' => $this->l->t('Quartalsweise'),
			'semi_annual' => $this->l->t('Halbjährlich'),
			'yearly' => $this->l->t('Jährlich'),
			'one_time' => $this->l->t('Einmalig'),
			default => '',
		};
	}

	public function amountType(string $amountType): string {
		return $amountType === 'brutto' ? $this->l->t('Brutto') : $this->l->t('Netto');
	}

	public function period(?string $period): string {
		if ($period === null || !preg_match('/^(\d+)\s+(day|days|week|weeks|month|months|year|years)$/i', trim($period), $m)) {
			return (string)$period;
		}
		$unit = match (strtolower($m[2])) {
			'day' => $this->l->t('Tag'),
			'days' => $this->l->t('Tage'),
			'week' => $this->l->t('Woche'),
			'weeks' => $this->l->t('Wochen'),
			'month' => $this->l->t('Monat'),
			'months' => $this->l->t('Monate'),
			'year' => $this->l->t('Jahr'),
			default => $this->l->t('Jahre'),
		};
		return $m[1] . ' ' . $unit;
	}

	public function category(?string $name): string {
		return $name ?? $this->l->t('Ohne Kategorie');
	}

	/** Decimal comma for German, decimal point otherwise; thousands only for display. */
	public function number(?float $value, bool $thousands = true): string {
		if ($value === null) {
			return '';
		}
		$german = str_starts_with($this->l->getLocaleCode(), 'de');
		return number_format($value, 2, $german ? ',' : '.', $thousands ? ($german ? '.' : ',') : '');
	}

	public function money(?float $value, string $currency): string {
		return $value === null ? '' : $this->number($value) . ' ' . $currency;
	}
}
