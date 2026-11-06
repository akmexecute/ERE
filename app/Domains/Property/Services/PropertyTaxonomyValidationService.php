<?php

declare(strict_types=1);

namespace App\Domains\Property\Services;

use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyFeature;
use App\Domains\Property\Models\PropertyType;
use Illuminate\Validation\ValidationException;

final class PropertyTaxonomyValidationService
{
    /**
     * Ensures the property type strictly belongs to the given category and is active.
     *
     * @return array{category: PropertyCategory, propertyType: PropertyType}
     *
     * @throws ValidationException
     */
    public function validateCategoryAndType(int|string $categoryIdentifier, int|string $typeIdentifier): array
    {
        $category = is_numeric($categoryIdentifier)
            ? PropertyCategory::query()->where('id', (int) $categoryIdentifier)->where('is_active', true)->first()
            : PropertyCategory::query()->where('code', (string) $categoryIdentifier)->where('is_active', true)->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category' => ['The selected property category is invalid or inactive.'],
            ]);
        }

        $propertyType = is_numeric($typeIdentifier)
            ? PropertyType::query()->where('id', (int) $typeIdentifier)->where('is_active', true)->first()
            : PropertyType::query()->where('code', (string) $typeIdentifier)->where('is_active', true)->first();

        if (! $propertyType) {
            throw ValidationException::withMessages([
                'property_type' => ['The selected property type is invalid or inactive.'],
            ]);
        }

        if ((int) $propertyType->category_id !== (int) $category->id) {
            throw ValidationException::withMessages([
                'property_type' => [
                    sprintf('The property type "%s" does not belong to the "%s" category.', $propertyType->name_en, $category->name_en),
                ],
                'property_type_id' => [
                    sprintf('The property type "%s" does not belong to the "%s" category.', $propertyType->name_en, $category->name_en),
                ],
            ]);
        }

        return [
            'category' => $category,
            'propertyType' => $propertyType,
        ];
    }

    /**
     * Validates category-specific dynamic feature values against their definitions.
     *
     * @param  array<string, mixed>  $submittedFeatures
     * @return array<string, string> Keyed by feature slug with validated string value
     *
     * @throws ValidationException
     */
    public function validateFeatures(PropertyCategory $category, array $submittedFeatures): array
    {
        $validated = [];

        foreach ($submittedFeatures as $slug => $rawValue) {
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            /** @var PropertyFeature|null $feature */
            $feature = PropertyFeature::query()->where('slug', (string) $slug)->where('is_active', true)->first();
            if (! $feature) {
                throw ValidationException::withMessages([
                    "features.{$slug}" => ["The property feature '{$slug}' is unrecognized or inactive."],
                ]);
            }

            $applicable = $feature->applicable_category_codes ?? [];
            if (! in_array($category->code, $applicable, true)) {
                throw ValidationException::withMessages([
                    "features.{$slug}" => ["The feature '{$feature->name_en}' is not applicable to category '{$category->name_en}'."],
                ]);
            }

            switch ($feature->input_type) {
                case 'number':
                    if (! is_numeric($rawValue) || (float) $rawValue < 0) {
                        throw ValidationException::withMessages([
                            "features.{$slug}" => ["The field '{$feature->name_en}' must be a non-negative number."],
                        ]);
                    }
                    $validated[$feature->slug] = (string) $rawValue;
                    break;

                case 'select':
                    $allowed = $feature->options_json ?? [];
                    if (! in_array((string) $rawValue, $allowed, true)) {
                        throw ValidationException::withMessages([
                            "features.{$slug}" => [
                                sprintf("The selected value for '%s' is invalid. Permitted options: %s.", $feature->name_en, implode(', ', $allowed)),
                            ],
                        ]);
                    }
                    $validated[$feature->slug] = (string) $rawValue;
                    break;

                case 'multi_select':
                case 'multi-select':
                    $allowed = $feature->options_json ?? [];
                    $selectedValues = is_array($rawValue) ? $rawValue : array_filter(array_map('trim', explode(',', (string) $rawValue)));
                    $invalid = array_diff($selectedValues, $allowed);
                    if (! empty($invalid)) {
                        throw ValidationException::withMessages([
                            "features.{$slug}" => [
                                sprintf("The selected values for '%s' contain invalid options: %s. Permitted options: %s.", $feature->name_en, implode(', ', $invalid), implode(', ', $allowed)),
                            ],
                        ]);
                    }
                    $validated[$feature->slug] = json_encode(array_values($selectedValues));
                    break;

                case 'boolean':
                    $boolVal = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($boolVal === null) {
                        throw ValidationException::withMessages([
                            "features.{$slug}" => ["The field '{$feature->name_en}' must be true or false."],
                        ]);
                    }
                    $validated[$feature->slug] = $boolVal ? '1' : '0';
                    break;

                case 'text':
                default:
                    $str = trim((string) $rawValue);
                    if (mb_strlen($str) > 255) {
                        throw ValidationException::withMessages([
                            "features.{$slug}" => ["The field '{$feature->name_en}' must not exceed 255 characters."],
                        ]);
                    }
                    $validated[$feature->slug] = $str;
                    break;
            }
        }

        return $validated;
    }
}
