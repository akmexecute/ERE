<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Property\Models\Property;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\User\Models\User;
use Database\Seeders\Phase4RentalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RentalApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_rental_application_generates_unique_code_and_succeeds(): void
    {
        $this->seed(Phase4RentalDemoSeeder::class);

        $user = User::query()->where('phone', '+251911234567')->firstOrFail();
        $property = Property::query()->where('property_code', 'ETH-AA-KZ-RES-00001002')->firstOrFail();

        $payload = [
            'property_id' => $property->id,
            'desired_move_in_date' => now()->addDays(7)->toDateString(),
            'lease_duration_months' => 6,
            'proposed_monthly_rent_etb' => 85000,
            'payment_frequency' => 'quarterly',
            'occupant_count' => 10,
            'applicant_message' => 'hello world',
        ];

        $response = $this->actingAs($user)->post(route('rental-applications.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $application = RentalApplication::query()
            ->where('tenant_id', $user->id)
            ->where('property_id', $property->id)
            ->latest()
            ->firstOrFail();

        $this->assertSame('hello world', $application->applicant_message);
        $this->assertSame(10, $application->occupant_count);
        $this->assertNotEmpty($application->application_code);
        $this->assertStringStartsWith('APP-2026-', $application->application_code);
    }
}
