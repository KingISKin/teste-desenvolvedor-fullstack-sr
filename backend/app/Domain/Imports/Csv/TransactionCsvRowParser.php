<?php

declare(strict_types=1);

namespace App\Domain\Imports\Csv;

use App\Domain\Imports\Exceptions\InvalidCsvHeader;
use App\Domain\Imports\Exceptions\InvalidTransactionRow;
use App\Domain\Transactions\DTOs\TransactionData;
use App\Domain\Transactions\Enums\TransactionType;
use DateTimeImmutable;

/**
 * Business rules for the transactions CSV: header layout and per-row validation.
 */
final class TransactionCsvRowParser
{
    public const HEADER = ['date', 'description', 'amount', 'type'];

    private const MAX_DESCRIPTION_LENGTH = 255;

    // 18 digits always fits a signed 64-bit integer (BIGINT) without overflow.
    private const AMOUNT_PATTERN = '/^\d{1,18}$/';

    private const UTF8_BOM = "\u{FEFF}";

    /**
     * @param  array<int, string|null>  $fields
     *
     * @throws InvalidCsvHeader
     */
    public function assertValidHeader(array $fields): void
    {
        $normalized = array_map(
            static fn (?string $field): string => mb_strtolower(trim((string) $field)),
            $fields,
        );

        if (isset($normalized[0]) && str_starts_with($normalized[0], self::UTF8_BOM)) {
            $normalized[0] = substr($normalized[0], strlen(self::UTF8_BOM));
        }

        if ($normalized !== self::HEADER) {
            throw InvalidCsvHeader::expected(self::HEADER);
        }
    }

    /**
     * @param  array<int, string|null>  $fields
     *
     * @throws InvalidTransactionRow
     */
    public function parse(array $fields): TransactionData
    {
        if (count($fields) !== count(self::HEADER)) {
            throw InvalidTransactionRow::withViolations([
                sprintf('Expected %d columns, got %d.', count(self::HEADER), count($fields)),
            ]);
        }

        [$rawDate, $rawDescription, $rawAmount, $rawType] = array_map(
            static fn (?string $field): string => trim((string) $field),
            array_values($fields),
        );

        $violations = [];

        $date = $this->parseDate($rawDate);
        if ($date === null) {
            $violations[] = 'Date must be a valid calendar date in YYYY-MM-DD format.';
        }

        if ($rawDescription === '') {
            $violations[] = 'Description is required.';
        } elseif (! mb_check_encoding($rawDescription, 'UTF-8')) {
            $violations[] = 'Description must be valid UTF-8 text.';
        } elseif (mb_strlen($rawDescription) > self::MAX_DESCRIPTION_LENGTH) {
            $violations[] = sprintf('Description must not exceed %d characters.', self::MAX_DESCRIPTION_LENGTH);
        }

        $amount = $this->parseAmount($rawAmount);
        if ($amount === null) {
            $violations[] = 'Amount must be a positive integer number of cents.';
        }

        $type = TransactionType::fromCsvLabel($rawType);
        if ($type === null) {
            $violations[] = 'Type must be "Receita" or "Despesa".';
        }

        if ($date === null || $amount === null || $type === null || $violations !== []) {
            /** @var non-empty-list<string> $violations */
            throw InvalidTransactionRow::withViolations($violations);
        }

        return new TransactionData($date, $rawDescription, $amount, $type);
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        // Round-trip check rejects overflowing dates such as 2026-02-30.
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function parseAmount(string $value): ?int
    {
        if (preg_match(self::AMOUNT_PATTERN, $value) !== 1) {
            return null;
        }

        $amount = (int) $value;

        return $amount > 0 ? $amount : null;
    }
}
