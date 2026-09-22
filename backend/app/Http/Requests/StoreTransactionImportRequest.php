<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Imports\DTOs\UploadedCsv;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StoreTransactionImportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value instanceof UploadedFile && $value->getSize() === 0) {
                        $fail('The file is empty.');
                    }
                },
                // The name must end in .csv AND the content must look like text
                // (finfo sniffing); neither check alone is trusted.
                'extensions:csv',
                'mimes:csv,txt',
                'max:'.config('imports.max_upload_kb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'The file must be a .csv file.',
            'file.mimes' => 'The file content must be plain-text CSV.',
        ];
    }

    public function toUploadedCsv(): UploadedCsv
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return new UploadedCsv((string) $file->getRealPath(), $file->getClientOriginalName());
    }
}
