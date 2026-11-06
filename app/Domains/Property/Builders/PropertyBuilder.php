<?php

declare(strict_types=1);

namespace App\Domains\Property\Builders;

use App\Domains\Property\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @extends Builder<Property>
 */
final class PropertyBuilder extends Builder
{
    /**
     * Atomically executes updates. If 'status' is being updated via raw query builder,
     * it executes through each individual model within a transaction to guarantee lifecycle
     * event firing and prevent bypassing auditable PropertyStatusHistory tracking.
     */
    public function update(array $values): int
    {
        $isModelSaving = ($this->model instanceof Property && $this->model->isSavingModel);

        if (array_key_exists('status', $values) && ! $isModelSaving) {
            return (int) DB::transaction(function () use ($values): int {
                $count = 0;
                $this->get()->each(function (Property $property) use ($values, &$count): void {
                    $property->update($values);
                    $count++;
                });

                return $count;
            });
        }

        return parent::update($values);
    }
}
