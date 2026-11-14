<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLotteryValidPeriodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-lottery-invalidations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'valid_from' => ['nullable', 'required_with:valid_until', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'required_with:valid_from', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valid_from.required_with' => 'Эхлэх огноог оруулна уу.',
            'valid_from.date_format' => 'Эхлэх огноо буруу байна.',
            'valid_until.required_with' => 'Дуусах огноог оруулна уу.',
            'valid_until.date_format' => 'Дуусах огноо буруу байна.',
            'valid_until.after_or_equal' => 'Дуусах огноо эхлэх огнооноос өмнө байж болохгүй.',
        ];
    }
}
