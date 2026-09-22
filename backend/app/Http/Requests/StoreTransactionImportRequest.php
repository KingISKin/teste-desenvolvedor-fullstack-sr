<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Imports\DTOs\UploadedCsv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StoreTransactionImportRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Content-based detection (finfo), not the client-provided extension.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('imports.max_upload_kb')],
        ];
    }

    public function toUploadedCsv(): UploadedCsv
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return new UploadedCsv((string) $file->getRealPath(), $file->getClientOriginalName());
    }
}
