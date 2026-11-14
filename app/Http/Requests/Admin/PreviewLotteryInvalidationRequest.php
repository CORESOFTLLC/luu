<?php

namespace App\Http\Requests\Admin;

use App\Services\LotteryInvalidationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewLotteryInvalidationRequest extends FormRequest
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
        $today = app(LotteryInvalidationService::class)->today();

        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.$today],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_on.required' => 'Эхлэх огноог оруулна уу.',
            'starts_on.date_format' => 'Эхлэх огноо буруу байна.',
            'ends_on.required' => 'Дуусах огноог оруулна уу.',
            'ends_on.date_format' => 'Дуусах огноо буруу байна.',
            'ends_on.after_or_equal' => 'Дуусах огноо эхлэх огнооноос өмнө байж болохгүй.',
            'ends_on.before_or_equal' => 'Ирээдүйн өдрийг хүчингүй болгох боломжгүй.',
        ];
    }
}
