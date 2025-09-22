<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCompanyBusinessHoursRequest extends FormRequest
{
    private array $invalidBusinessHourKeys = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('business_hours') && is_array($this->input('business_hours'))) {
            $this->merge([
                'business_hours' => $this->normalizeBusinessHoursInput($this->input('business_hours')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'business_hours' => 'present|array',
            'business_hours.*' => 'array',
            'business_hours.*.day_of_week' => 'required_with:business_hours|integer|between:1,7',
            'business_hours.*.opens_at' => 'required_with:business_hours|string|date_format:H:i',
            'business_hours.*.closes_at' => 'required_with:business_hours|string|date_format:H:i',
            'business_hours.*.is_overnight' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'business_hours.*.day_of_week.between' => 'Le jour de la semaine doit être compris entre 1 (lundi) et 7 (dimanche).',
            'business_hours.*.opens_at.date_format' => "L'heure d'ouverture doit être au format HH:MM.",
            'business_hours.*.closes_at.date_format' => "L'heure de fermeture doit être au format HH:MM.",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->invalidBusinessHourKeys as $invalidKey) {
                $validator->errors()->add('business_hours', sprintf('Le jour "%s" est invalide pour les horaires.', $invalidKey));
            }

            $entries = $this->input('business_hours');

            if (! is_array($entries) || $entries === []) {
                return;
            }

            $normalized = [];

            foreach ($entries as $index => $entry) {
                if (! is_array($entry)) {
                    $validator->errors()->add("business_hours.{$index}", 'Format invalide pour les horaires.');

                    continue;
                }

                if (! array_key_exists('day_of_week', $entry) || ! array_key_exists('opens_at', $entry) || ! array_key_exists('closes_at', $entry)) {
                    continue;
                }

                $day = (int) $entry['day_of_week'];
                $opensAt = $entry['opens_at'];
                $closesAt = $entry['closes_at'];

                if (! is_string($opensAt) || ! is_string($closesAt)) {
                    continue;
                }

                $openMinutes = $this->timeStringToMinutes($opensAt);
                $closeMinutes = $this->timeStringToMinutes($closesAt);

                if ($openMinutes === $closeMinutes) {
                    $validator->errors()->add("business_hours.{$index}.closes_at", 'Les heures d’ouverture et de fermeture doivent être différentes.');

                    continue;
                }

                $isOvernight = (bool) ($entry['is_overnight'] ?? false);

                if ($closeMinutes < $openMinutes && ! $isOvernight) {
                    $validator->errors()->add("business_hours.{$index}.closes_at", 'Cet horaire se termine avant son ouverture. Activez is_overnight pour un service après minuit.');

                    continue;
                }

                if ($closeMinutes < $openMinutes) {
                    $isOvernight = true;
                }

                $dayIndex = $day - 1;

                if ($dayIndex < 0 || $dayIndex > 6) {
                    continue;
                }

                $start = ($dayIndex * 1440) + $openMinutes;
                $end = ($dayIndex * 1440) + $closeMinutes + ($isOvernight ? 1440 : 0);

                $normalized[] = [
                    'start' => $start,
                    'end' => $end,
                    'index' => $index,
                ];
            }

            usort($normalized, fn ($a, $b) => $a['start'] <=> $b['start']);

            for ($i = 1, $count = count($normalized); $i < $count; $i++) {
                $previous = $normalized[$i - 1];
                $current = $normalized[$i];

                if ($current['start'] < $previous['end']) {
                    $validator->errors()->add("business_hours.{$current['index']}.opens_at", 'Les horaires se chevauchent.');
                }
            }

            if (count($normalized) > 1) {
                $first = $normalized[0];
                $last = $normalized[array_key_last($normalized)];
                $weekMinutes = 7 * 24 * 60;

                if (($first['start'] + $weekMinutes) < $last['end']) {
                    $validator->errors()->add("business_hours.{$first['index']}.opens_at", 'Les horaires se chevauchent entre dimanche et lundi.');
                }
            }
        });
    }

    /**
     * @param  array<mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBusinessHoursInput(array $input): array
    {
        if ($input === []) {
            return [];
        }

        if (array_is_list($input)) {
            return array_values(array_map(function ($entry) {
                if (! is_array($entry)) {
                    return [];
                }

                if (array_key_exists('day_of_week', $entry)) {
                    $entry['day_of_week'] = $this->normalizeDayIdentifier($entry['day_of_week']);
                } elseif (array_key_exists('day', $entry)) {
                    $entry['day_of_week'] = $this->normalizeDayIdentifier($entry['day']);
                    unset($entry['day']);
                }

                if (array_key_exists('opens_at', $entry)) {
                    $entry['opens_at'] = $this->normalizeTimeString($entry['opens_at']);
                }

                if (array_key_exists('closes_at', $entry)) {
                    $entry['closes_at'] = $this->normalizeTimeString($entry['closes_at']);
                }

                return $entry;
            }, $input));
        }

        $normalized = [];

        foreach ($input as $dayKey => $ranges) {
            $day = $this->normalizeDayIdentifier($dayKey);

            if ($day === null) {
                $this->invalidBusinessHourKeys[] = (string) $dayKey;
            }

            if (! is_array($ranges) || $ranges === []) {
                continue;
            }

            foreach ($ranges as $range) {
                if (! is_array($range)) {
                    $range = [];
                }

                if (! array_key_exists('day_of_week', $range)) {
                    $range['day_of_week'] = $day;
                }

                if (array_key_exists('opens_at', $range)) {
                    $range['opens_at'] = $this->normalizeTimeString($range['opens_at']);
                }

                if (array_key_exists('closes_at', $range)) {
                    $range['closes_at'] = $this->normalizeTimeString($range['closes_at']);
                }

                $normalized[] = $range;
            }
        }

        return $normalized;
    }

    private function normalizeDayIdentifier(mixed $value): ?int
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            $intValue = (int) $value;

            return $intValue >= 1 && $intValue <= 7 ? $intValue : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = str_replace(['-', ' '], ['_', '_'], mb_strtolower(trim($value)));

        $map = [
            'monday' => 1,
            'mon' => 1,
            'lundi' => 1,
            'tuesday' => 2,
            'tue' => 2,
            'tues' => 2,
            'mardi' => 2,
            'wednesday' => 3,
            'wed' => 3,
            'mercredi' => 3,
            'thursday' => 4,
            'thu' => 4,
            'thur' => 4,
            'thurs' => 4,
            'jeudi' => 4,
            'friday' => 5,
            'fri' => 5,
            'vendredi' => 5,
            'saturday' => 6,
            'sat' => 6,
            'samedi' => 6,
            'sunday' => 7,
            'sun' => 7,
            'dimanche' => 7,
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $string, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return $string;
    }

    private function timeStringToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return ($hour * 60) + $minute;
    }
}
