<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreLotteryInvalidationRequest extends PreviewLotteryInvalidationRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'note' => ['required', 'string', 'min:5', 'max:1000'],
            'confirm' => ['required', 'string', 'in:батлах'],
            'expected_transactions' => ['required', 'integer', 'min:0'],
            'expected_tickets' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'note.required' => 'Хүчингүй болгох шалтгааныг тайлбарлана уу.',
            'note.min' => 'Тайлбар хэт богино байна.',
            'note.max' => 'Тайлбар 1000 тэмдэгтээс хэтрэхгүй байна.',
            'confirm.required' => 'Баталгаажуулахын тулд "батлах" гэж бичнэ үү.',
            'confirm.in' => 'Баталгаажуулахын тулд "батлах" гэж яг бичнэ үү.',
            'expected_transactions.*' => 'Урьдчилсан тоо олдсонгүй. Огноогоо дахин шалгана уу.',
            'expected_tickets.*' => 'Урьдчилсан тоо олдсонгүй. Огноогоо дахин шалгана уу.',
        ];
    }
}
