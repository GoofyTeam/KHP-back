<?php

namespace App\Services;

use App\Models\Loss;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LossService
{
    /**
     * Create a loss and update pivot stock atomically.
     *
     * $data keys: company_id, entity_type, entity_id, location_id, quantity, unit, reason, comment
     */
    public function createLoss(array $data): Loss
    {
        // basic validation
        if (empty($data['quantity']) || $data['quantity'] <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be > 0']);
        }

        return DB::transaction(function () use ($data) {
            $loss = Loss::create($data);

            // The LossObserver will handle pivot update after created, but if you prefer
            // immediate and explicit handling you can uncomment and call a local method here.
            // (Observer still runs because it's model-created)

            return $loss;
        });
    }
}
