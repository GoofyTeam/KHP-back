<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyBusinessHoursRequest;
use App\Http\Requests\UpdateCompanyContactRequest;
use App\Http\Requests\UpdateCompanyImageRequest;
use App\Http\Requests\UpdateCompanyOptionsRequest;
use App\Models\Company;
use App\Models\CompanyBusinessHour;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * Cas métier : Mise à jour des options de l'entreprise
     *
     * Use cases :
     * - Modifier la langue utilisée pour les données Open Food Facts
     *
     * Cette fonction permet de modifier certaines options de configuration de
     * l'entreprise connectée, comme la langue utilisée pour récupérer des
     * informations externes.
     *
     * @param  UpdateCompanyOptionsRequest  $request  La requête HTTP contenant les options à modifier
     * @return JsonResponse Confirmation avec les options mises à jour
     */
    public function updateOptions(UpdateCompanyOptionsRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validated();

        $attributes = Arr::only($validated, [
            'open_food_facts_language',
            'public_menu_card_url',
            'show_out_of_stock_menus_on_card',
            'show_menu_images',
        ]);

        if ($attributes !== []) {
            $company->fill($attributes);
            $company->save();
        }

        return $this->buildCompanyResponse($company, 'Options mises à jour avec succès');
    }

    public function updateContact(UpdateCompanyContactRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $attributes = Arr::only($request->validated(), [
            'contact_name',
            'contact_email',
            'contact_phone',
            'address_line',
            'postal_code',
            'city',
            'country',
        ]);

        if ($attributes !== []) {
            $company->fill($attributes);
            $company->save();
        }

        return $this->buildCompanyResponse($company, 'Coordonnées mises à jour avec succès');
    }

    public function updateBusinessHours(UpdateCompanyBusinessHoursRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validated();

        DB::transaction(function () use ($company, $validated) {
            $this->syncBusinessHours($company, $validated['business_hours'] ?? []);
        });

        return $this->buildCompanyResponse($company, 'Horaires mis à jour avec succès');
    }

    public function updateLogo(UpdateCompanyImageRequest $request, ImageService $imageService): JsonResponse
    {
        $user = $request->user();
        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validated();

        $newPath = null;

        if ($request->hasFile('image')) {
            $newPath = $imageService->store($request->file('image'), 'companies');
        } elseif (! empty($validated['image_url'])) {
            $newPath = $imageService->storeFromUrl($validated['image_url'], 'companies');
        }

        if (! $newPath) {
            return response()->json([
                'message' => "Aucune image n'a été fournie",
            ], 422);
        }

        $previousPath = $company->logo_path;

        if ($newPath !== $previousPath) {
            $company->logo_path = $newPath;
            $company->save();

            if ($previousPath) {
                $imageService->delete($previousPath);
            }
        }

        return $this->buildCompanyResponse($company, "Image de l'entreprise mise à jour avec succès");
    }

    private function buildCompanyResponse(Company $company, string $message): JsonResponse
    {
        $company->refresh();
        $company->load('businessHours');

        return response()->json([
            'message' => $message,
            'data' => $this->formatCompanyData($company),
        ]);
    }

    private function formatCompanyData(Company $company): array
    {
        return [
            'open_food_facts_language' => $company->open_food_facts_language,
            'public_menu_card_url' => $company->public_menu_card_url,
            'show_out_of_stock_menus_on_card' => $company->show_out_of_stock_menus_on_card,
            'show_menu_images' => $company->show_menu_images,
            'only_sufficient_stock' => ! $company->show_out_of_stock_menus_on_card,
            'with_pictures' => $company->show_menu_images,
            'logo_path' => $company->logo_path,
            'contact_name' => $company->contact_name,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,
            'address_line' => $company->address_line,
            'postal_code' => $company->postal_code,
            'city' => $company->city,
            'country' => $company->country,
            'business_hours' => $company->businessHours
                ->map(fn (CompanyBusinessHour $hour) => [
                    'id' => $hour->id,
                    'day_of_week' => $hour->day_of_week,
                    'opens_at' => $this->formatTimeForResponse($hour->opens_at),
                    'closes_at' => $this->formatTimeForResponse($hour->closes_at),
                    'is_overnight' => $hour->is_overnight,
                    'sequence' => $hour->sequence,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $businessHours
     */
    private function syncBusinessHours(Company $company, array $businessHours): void
    {
        $grouped = [];

        foreach ($businessHours as $entry) {
            $day = (int) $entry['day_of_week'];
            $opensAt = $this->normalizeTimeInput($entry['opens_at']);
            $closesAt = $this->normalizeTimeInput($entry['closes_at']);

            $openMinutes = $this->timeToMinutes($opensAt);
            $closeMinutes = $this->timeToMinutes($closesAt);

            $isOvernight = (bool) ($entry['is_overnight'] ?? false);

            if ($closeMinutes <= $openMinutes) {
                $isOvernight = true;
            }

            $grouped[$day][] = [
                'day_of_week' => $day,
                'opens_at' => $this->toDatabaseTime($opensAt),
                'closes_at' => $this->toDatabaseTime($closesAt),
                'is_overnight' => $isOvernight,
                'open_minutes' => $openMinutes,
            ];
        }

        ksort($grouped);

        $records = [];

        foreach ($grouped as $day => $entries) {
            usort($entries, fn ($a, $b) => $a['open_minutes'] <=> $b['open_minutes']);

            foreach ($entries as $index => $entry) {
                $records[] = [
                    'day_of_week' => $day,
                    'opens_at' => $entry['opens_at'],
                    'closes_at' => $entry['closes_at'],
                    'is_overnight' => $entry['is_overnight'],
                    'sequence' => $index + 1,
                ];
            }
        }

        $company->businessHours()->delete();

        if ($records !== []) {
            $company->businessHours()->createMany($records);
        }
    }

    private function normalizeTimeInput(string $time): string
    {
        $parts = explode(':', $time);

        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function toDatabaseTime(string $time): string
    {
        return $time.':00';
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return ($hour * 60) + $minute;
    }

    private function formatTimeForResponse(?string $time): ?string
    {
        if (! is_string($time) || $time === '') {
            return null;
        }

        return substr($time, 0, 5);
    }
}
