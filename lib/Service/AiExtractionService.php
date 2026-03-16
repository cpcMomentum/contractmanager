<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class AiExtractionService {

	private const CONTRACT_SCHEMA = [
		'type' => 'object',
		'properties' => [
			'name' => [
				'type' => 'string',
				'description' => 'Contract name or title',
			],
			'vendor' => [
				'type' => 'string',
				'description' => 'Vendor, partner or contracting party name',
			],
			'startDate' => [
				'type' => ['string', 'null'],
				'description' => 'Contract start date in YYYY-MM-DD format',
			],
			'endDate' => [
				'type' => ['string', 'null'],
				'description' => 'Contract end date in YYYY-MM-DD format',
			],
			'contractType' => [
				'type' => 'string',
				'enum' => ['fixed', 'auto_renewal'],
				'description' => 'fixed = limited term, auto_renewal = automatic renewal',
			],
			'cancellationPeriod' => [
				'type' => ['string', 'null'],
				'description' => 'Cancellation period, e.g. "3 months", "6 weeks"',
			],
			'renewalPeriod' => [
				'type' => ['string', 'null'],
				'description' => 'Renewal period for auto_renewal contracts, e.g. "12 months"',
			],
			'cost' => [
				'type' => ['string', 'null'],
				'description' => 'Cost amount as decimal string, e.g. "29.99"',
			],
			'currency' => [
				'type' => 'string',
				'enum' => ['EUR', 'USD', 'GBP', 'CHF'],
				'description' => 'Currency code',
			],
			'costInterval' => [
				'type' => ['string', 'null'],
				'enum' => ['monthly', 'yearly', 'one_time', null],
				'description' => 'Billing interval',
			],
			'confidence' => [
				'type' => 'number',
				'description' => 'Confidence score 0.0 to 1.0 for extraction quality',
			],
			'extractionNotes' => [
				'type' => ['string', 'null'],
				'description' => 'Notes about ambiguous or uncertain extractions',
			],
		],
		'required' => ['name', 'vendor', 'contractType', 'currency', 'confidence'],
		'additionalProperties' => false,
	];

	private const SYSTEM_PROMPT = <<<'PROMPT'
You are a contract data extraction assistant. Extract structured data from the provided contract document.

You MUST respond with a JSON object using EXACTLY these field names:
{
  "name": "Contract title or name (string, required)",
  "vendor": "Vendor, partner, or contracting party name (string, required)",
  "startDate": "YYYY-MM-DD or null",
  "endDate": "YYYY-MM-DD or null",
  "contractType": "fixed" or "auto_renewal",
  "cancellationPeriod": "e.g. 3 months, 6 weeks, or null",
  "renewalPeriod": "e.g. 12 months, or null",
  "cost": "decimal string without currency, e.g. 499.00, or null",
  "currency": "EUR, USD, GBP, or CHF",
  "costInterval": "monthly", "yearly", "one_time", or null,
  "confidence": 0.0 to 1.0,
  "extractionNotes": "notes about ambiguities or null"
}

Rules:
- Use ONLY the field names listed above. Do NOT invent new fields.
- "name" = the contract title or a descriptive name derived from the document
- "vendor" = the other contracting party (provider, supplier, partner)
- If the contract auto-renews, set contractType to "auto_renewal", otherwise "fixed"
- Documents may be in German or other languages - extract data regardless of language
- If a field cannot be determined, set it to null
- Respond with ONLY the JSON object, no markdown formatting
PROMPT;

	public function __construct(
		private SettingsService $settingsService,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Extract contract data from text using AI
	 *
	 * @param string $text The extracted PDF text
	 * @param bool $isScanned Whether the PDF is a scanned document
	 * @param string $rawContent Base64-encoded PDF content (for vision fallback)
	 * @return array Extracted contract fields
	 * @throws \Exception
	 */
	public function extract(string $text, bool $isScanned, string $rawContent = ''): array {
		$provider = $this->settingsService->getAiProvider();

		if ($provider === 'claude') {
			return $this->extractWithClaude($text, $isScanned, $rawContent);
		}

		return $this->extractWithOpenAiCompatible($text, $isScanned, $rawContent);
	}

	private function extractWithClaude(string $text, bool $isScanned, string $rawContent): array {
		$apiKey = $this->settingsService->getAiApiKey();
		$model = $this->settingsService->getAiModel();
		$apiUrl = $this->settingsService->getAiApiUrl();

		if ($isScanned && $rawContent !== '') {
			// Vision mode: send PDF as base64
			$userContent = [
				[
					'type' => 'document',
					'source' => [
						'type' => 'base64',
						'media_type' => 'application/pdf',
						'data' => $rawContent,
					],
				],
				[
					'type' => 'text',
					'text' => 'Extract structured contract data from this document.',
				],
			];
		} else {
			$userContent = "Extract structured contract data from the following document text:\n\n---\n\n" . $text;
		}

		$body = [
			'model' => $model,
			'max_tokens' => 2048,
			'system' => self::SYSTEM_PROMPT,
			'messages' => [
				[
					'role' => 'user',
					'content' => $userContent,
				],
			],
		];

		$client = $this->clientService->newClient();
		$response = $client->post($apiUrl . '/v1/messages', [
			'headers' => [
				'x-api-key' => $apiKey,
				'anthropic-version' => '2023-06-01',
				'Content-Type' => 'application/json',
			],
			'body' => json_encode($body),
			'timeout' => 60,
		]);

		$result = json_decode($response->getBody(), true);

		if (!isset($result['content'][0]['text'])) {
			throw new \Exception('Unexpected Claude API response format');
		}

		$responseText = $result['content'][0]['text'];
		return $this->parseJsonResponse($responseText);
	}

	private function extractWithOpenAiCompatible(string $text, bool $isScanned, string $rawContent): array {
		$apiKey = $this->settingsService->getAiApiKey();
		$model = $this->settingsService->getAiModel();
		$apiUrl = rtrim($this->settingsService->getAiApiUrl(), '/');

		$schemaJson = json_encode(self::CONTRACT_SCHEMA, JSON_PRETTY_PRINT);

		if ($isScanned && $rawContent !== '') {
			$userContent = [
				[
					'type' => 'image_url',
					'image_url' => [
						'url' => 'data:application/pdf;base64,' . $rawContent,
					],
				],
				[
					'type' => 'text',
					'text' => 'Extract structured contract data from this document.',
				],
			];
		} else {
			$userContent = "Extract structured contract data from the following document text:\n\n---\n\n" . $text;
		}

		$body = [
			'model' => $model,
			'max_tokens' => 2048,
			'response_format' => ['type' => 'json_object'],
			'messages' => [
				[
					'role' => 'system',
					'content' => self::SYSTEM_PROMPT . "\n\nRespond with a JSON object matching this schema:\n" . $schemaJson,
				],
				[
					'role' => 'user',
					'content' => $userContent,
				],
			],
		];

		$headers = [
			'Authorization' => 'Bearer ' . $apiKey,
			'Content-Type' => 'application/json',
		];

		$client = $this->clientService->newClient();
		$response = $client->post($apiUrl . '/chat/completions', [
			'headers' => $headers,
			'body' => json_encode($body),
			'timeout' => 60,
		]);

		$result = json_decode($response->getBody(), true);

		if (!isset($result['choices'][0]['message']['content'])) {
			throw new \Exception('Unexpected OpenAI-compatible API response format');
		}

		$responseText = $result['choices'][0]['message']['content'];
		return $this->parseJsonResponse($responseText);
	}

	/**
	 * Parse JSON from LLM response text
	 */
	private function parseJsonResponse(string $responseText): array {
		// Try to extract JSON from the response (may be wrapped in markdown code blocks)
		$json = $responseText;
		if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $responseText, $matches)) {
			$json = $matches[1];
		}

		$data = json_decode($json, true);
		if (!is_array($data)) {
			$this->logger->error('Failed to parse AI response as JSON', [
				'app' => Application::APP_ID,
				'response' => substr($responseText, 0, 500),
			]);
			throw new \Exception('AI response is not valid JSON');
		}

		// Validate required fields
		if (!isset($data['name']) || !isset($data['vendor'])) {
			throw new \Exception('AI response missing required fields (name, vendor)');
		}

		return $data;
	}
}
