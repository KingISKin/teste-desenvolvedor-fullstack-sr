<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auth\DTOs\LoginCredentials;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function toCredentials(): LoginCredentials
    {
        return new LoginCredentials(
            $this->string('email')->trim()->lower()->toString(),
            $this->string('password')->toString(),
        );
    }
}
