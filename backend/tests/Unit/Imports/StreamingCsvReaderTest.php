<?php

declare(strict_types=1);

use App\Infrastructure\Csv\StreamingCsvReader;

/**
 * @return resource
 */
function memoryStream(string $content)
{
    $stream = fopen('php://memory', 'r+b');
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

it('yields records keyed by their physical line number and skips blank lines', function (): void {
    $records = iterator_to_array((new StreamingCsvReader)->records(memoryStream("a,b\n\n1,2\n3,4")));

    expect($records)->toBe([
        1 => ['a', 'b'],
        3 => ['1', '2'],
        4 => ['3', '4'],
    ]);
});

it('handles quoted fields containing commas and doubled quotes (RFC 4180)', function (): void {
    $content = '2026-01-01,"Rent, ""June""",100,Despesa'."\n";

    $records = iterator_to_array((new StreamingCsvReader)->records(memoryStream($content)));

    expect($records[1])->toBe(['2026-01-01', 'Rent, "June"', '100', 'Despesa']);
});

it('handles Windows line endings', function (): void {
    $records = iterator_to_array((new StreamingCsvReader)->records(memoryStream("a,b\r\n1,2\r\n")));

    expect($records)->toBe([1 => ['a', 'b'], 2 => ['1', '2']]);
});

it('keys records by physical line even when a quoted field wrongly spans two lines', function (): void {
    $content = "date,description,amount,type\n2026-01-01,\"Broken\ndescription\",100,Receita\n2026-01-02,Next,200,Despesa\n";

    $records = iterator_to_array((new StreamingCsvReader)->records(memoryStream($content)));

    // Multi-line fields are unsupported: the two halves surface as (invalid)
    // lines 2 and 3, and the following record keeps its real line number 4.
    expect(array_keys($records))->toBe([1, 2, 3, 4])
        ->and($records[4])->toBe(['2026-01-02', 'Next', '200', 'Despesa']);
});
