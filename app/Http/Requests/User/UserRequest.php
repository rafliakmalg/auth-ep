<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'active' => __('index.user.active'),
            'email' => __('index.user.email'),
            'name' => __('index.user.name'),
            'password' => __('index.user.password'),
            'role_ids' => __('index.user.role_ids'),
            'role_ids.*' => __('index.user.role_ids'),
            'username' => __('index.user.username'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'active' => ['boolean'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'name' => ['required', 'string', 'max:255'],
            'password' => [$user ? 'sometimes' : 'required', 'string', 'min:8'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('users', 'username')->ignore($user),
            ],
        ];
    }
}
