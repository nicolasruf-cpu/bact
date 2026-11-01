<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\UserData;
use App\Modules\Identity\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('user') === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'assignments' => ['present', 'array'],
            'assignments.*.site_id' => ['required', 'integer', 'exists:sites,id'],
            'assignments.*.role' => ['required', Rule::enum(Role::class)],
            'lock_version' => [$creating ? 'nullable' : 'required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'email',
            'password' => 'contraseña',
            'assignments' => 'perfiles',
        ];
    }

    public function toData(): UserData
    {
        /** @var list<array{site_id: int|string, role: string}> $assignments */
        $assignments = $this->validated('assignments');

        return new UserData(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            password: $this->validated('password') === null ? null : (string) $this->validated('password'),
            assignments: array_map(fn (array $a) => ['site_id' => (int) $a['site_id'], 'role' => Role::from($a['role'])], $assignments),
            lockVersion: $this->validated('lock_version') === null ? null : (int) $this->validated('lock_version'),
        );
    }
}
