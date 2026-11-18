<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Service\ReportCsvWriter;
use OCA\ContractManager\Service\ReportFormatter;
use OCA\ContractManager\Service\ReportPdfWriter;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

class ReportWritersTest extends TestCase {

	private function formatter(string $language = 'de'): ReportFormatter {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text, array $params = []): string => vsprintf($text, $params));
		$l->method('l')->willReturnCallback(fn (string $type, DateTime $date): string => $date->format('d.m.Y'));
		$l->method('getLocaleCode')->willReturn($language);
		return new ReportFormatter($l);
	}

	/** @return array<string, mixed> */
	private static function sheet(int $rows): array {
		$list = [];
		for ($i = 1; $i <= $rows; $i++) {
			$list[] = [
				'name' => 'Vertrag Nr. ' . $i . ' für Büro',
				'vendor' => 'Müller & Söhne',
				'contact' => ['Hauptstraße ' . $i . "\n12345 Großstadt", '+49 30 1234567', '=HYPERLINK("x")'],
				'runsUntil' => new DateTime('2027-06-30'),
				'deadline' => null,
				'status' => 'active',
				'costInterval' => 'monthly',
				'cost' => 1234.5,
				'currency' => 'EUR',
				'amountType' => 'netto',
			];
		}
		return ['today' => new DateTime('2026-11-18'), 'groups' => [['category' => 'Software', 'rows' => $list]]];
	}

	public function testCsvOpensInExcel(): void {
		$csv = (new ReportCsvWriter($this->formatter()))->contracts(self::sheet(1));

		$this->assertStringStartsWith("\u{FEFF}Kategorie;Vertrag;Vertragspartner;", $csv);
		$this->assertStringContainsString('Software;"Vertrag Nr. 1 für Büro";"Müller & Söhne";', $csv);
		$this->assertStringContainsString(';1234,50;EUR;Netto', $csv);
		$this->assertStringContainsString("\"Hauptstraße 1\r\n12345 Großstadt\r\n+49 30 1234567\r\n=HYPERLINK(\"\"x\"\")\"", $csv);
		$this->assertStringEndsWith("\r\n", $csv);
	}

	public function testCsvDefusesFormulaCellsButKeepsNumbers(): void {
		$sheet = self::sheet(1);
		$sheet['groups'][0]['rows'][0]['name'] = '=1+1';
		$sheet['groups'][0]['rows'][0]['cost'] = -5.0;

		$csv = (new ReportCsvWriter($this->formatter()))->contracts($sheet);

		$this->assertStringContainsString(";'=1+1;", $csv);
		$this->assertStringContainsString(';-5,00;', $csv);
	}

	public function testEnglishUsesDecimalPoint(): void {
		$this->assertSame('1,234.50', $this->formatter('en')->number(1234.5));
		$this->assertSame('1.234,50', $this->formatter('de')->number(1234.5));
		$this->assertSame('1234,50', $this->formatter('de')->number(1234.5, false));
	}

	public function testPdfRepeatsTheHeaderAndNeverSplitsARow(): void {
		$pdf = (new ReportPdfWriter($this->formatter()))->contracts(self::sheet(60));

		$this->assertStringStartsWith('%PDF', $pdf);
		$pages = (new Parser())->parseContent($pdf)->getPages();
		$this->assertGreaterThan(2, count($pages));
		$seen = [];
		foreach ($pages as $index => $page) {
			$text = $page->getText();
			$this->assertStringContainsString('Vertragspartner', $text, 'header on page ' . ($index + 1));
			$this->assertStringContainsString('Seite ' . ($index + 1) . ' von ' . count($pages), $text);
			preg_match_all('/Hauptstraße (\d+)/u', $text, $street);
			preg_match_all('/Vertrag Nr\. (\d+) für Büro/u', $text, $name);
			$this->assertSame($name[1], $street[1], 'a row is split on page ' . ($index + 1));
			$seen = array_merge($seen, $name[1]);
		}
		$this->assertSame(array_map('strval', range(1, 60)), $seen);
	}

	public function testPdfWritesAllReportsWithoutData(): void {
		$writer = new ReportPdfWriter($this->formatter());
		$today = new DateTime('2026-11-18');

		$costs = $writer->costs(['today' => $today, 'byCategory' => [], 'byVendor' => [], 'totals' => [], 'irregular' => []]);
		$deadlines = $writer->deadlines(['today' => $today, 'until' => new DateTime('2027-02-18'), 'months' => 3, 'rows' => []]);

		$this->assertStringContainsString('Keine Einträge.', (new Parser())->parseContent($costs)->getText());
		$this->assertStringContainsString('18.11.2026 bis 18.02.2027', (new Parser())->parseContent($deadlines)->getText());
		$this->assertStringContainsString('Stand: 18.11.2026', (new Parser())->parseContent($deadlines)->getText());
	}
}
