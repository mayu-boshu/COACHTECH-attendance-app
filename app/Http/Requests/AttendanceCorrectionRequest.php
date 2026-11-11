<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['nullable', 'date_format:H:i'],

            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],

            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],

            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            if (
                !$validator->errors()->has('new_clock_in') &&
                !$validator->errors()->has('new_clock_out') &&
                filled($clockIn) &&
                filled($clockOut) &&
                $clockIn > $clockOut
            ) {
                $validator->errors()->add(
                    'new_clock_out',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
            }

            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (
                    filled($breakIn) &&
                    !$validator->errors()->has("new_break_in.$index") &&
                    (
                        ($clockIn && $breakIn < $clockIn) ||
                        ($clockOut && $breakIn > $clockOut)
                    )
                ) {
                    $validator->errors()->add(
                        "new_break_in.$index",
                        '休憩時間が不適切な値です'
                    );
                }

                if (
                    filled($breakOut) &&
                    filled($clockOut) &&
                    !$validator->errors()->has("new_break_out.$index") &&
                    !$validator->errors()->has('new_clock_out') &&
                    $breakOut > $clockOut
                ) {
                    $validator->errors()->add(
                        "new_break_out.$index",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }

        });

    }
    public function messages(): array
    {
        return [
            'comment.required' => '備考を記入してください',
        ];
    }
}

