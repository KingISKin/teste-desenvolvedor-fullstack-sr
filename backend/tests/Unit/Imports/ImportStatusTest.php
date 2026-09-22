<?php

declare(strict_types=1);

use App\Domain\Imports\Enums\ImportStatus;

it('flags only completed and failed as terminal states', function (ImportStatus $status, bool $terminal): void {
    expect($status->isTerminal())->toBe($terminal);
})->with([
    [ImportStatus::Pending, false],
    [ImportStatus::Processing, false],
    [ImportStatus::Completed, true],
    [ImportStatus::Failed, true],
]);
