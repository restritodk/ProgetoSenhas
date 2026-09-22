<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        $clinic = $this->user()?->clinic;

        return $clinic !== null && $this->user()->can('update', $clinic);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('clinics', 'slug')->ignore($this->user()?->clinic_id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da clínica.',
            'name.max' => 'O nome da clínica deve ter no máximo 255 caracteres.',
            'slug.required' => 'Informe o identificador da clínica.',
            'slug.regex' => 'Use apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Já existe uma clínica com este identificador.',
        ];
    }
}
