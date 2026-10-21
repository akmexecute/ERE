<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\GIS\Actions\ReverseGeocodePointAction;
use App\Domains\Location\Models\Location;
use Database\Seeders\EthiopianLocationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LocationAndGisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EthiopianLocationsSeeder::class);
    }

    public function test_seeder_populates_ethiopian_hierarchy(): void
    {
        $country = Location::query()->where('code', 'ETH')->first();
        $this->assertNotNull($country);
        $this->assertSame('country', $country->level);
        $this->assertSame('Ethiopia', $country->name_en);
        $this->assertSame('ኢትዮጵያ', $country->name_am);
        $this->assertSame('Itoophiyaa', $country->name_om);

        // Check Addis Ababa Region and City
        $aaCity = Location::query()->where('code', 'AA')->first();
        $this->assertNotNull($aaCity);

        // Check Sub-cities exist as children
        $subcities = $aaCity->children;
        $this->assertGreaterThanOrEqual(10, $subcities->count());

        $bole = $subcities->firstWhere('code', 'BOLE');
        $this->assertNotNull($bole);
        $this->assertSame('Bole', $bole->name_en);
        $this->assertSame('ቦሌ', $bole->name_am);
        $this->assertSame('Boolee', $bole->name_om);

        // Check Bole neighborhoods
        $gerji = $bole->children->firstWhere('code', 'GERJI');
        $this->assertNotNull($gerji);
        $this->assertSame('Gerji', $gerji->name_en);
        $this->assertSame('ገርጂ', $gerji->name_am);
        $this->assertSame('Garjii', $gerji->name_om);
    }

    public function test_returns_localized_name_according_to_locale(): void
    {
        $bole = Location::query()->where('code', 'BOLE')->firstOrFail();

        $this->assertSame('Bole', $bole->localizedName('en'));
        $this->assertSame('ቦሌ', $bole->localizedName('am'));
        $this->assertSame('Boolee', $bole->localizedName('om'));
    }

    public function test_reverse_geocodes_point_to_administrative_chain(): void
    {
        $action = new ReverseGeocodePointAction();
        // Gerji coordinates in Bole Sub-city
        $chain = $action->execute(8.9965, 38.8095);

        $this->assertNotEmpty($chain);
        // The resolved chain should include either country, region, city, subcity or neighborhood
        $levels = array_keys($chain);
        $this->assertTrue(in_array('city', $levels, true) || in_array('subcity', $levels, true) || in_array('neighborhood', $levels, true));
    }

    public function test_locations_children_api_endpoint(): void
    {
        $eth = Location::query()->where('code', 'ETH')->firstOrFail();

        $res = $this->getJson("/api/v1/locations/children?parent_id={$eth->id}");
        $res->assertOk();
        $res->assertJsonStructure(['data' => [['id', 'name', 'code', 'level']]]);

        $names = collect($res->json('data'))->pluck('code');
        $this->assertTrue($names->contains('AA-REG') || $names->contains('HAR') || $names->contains('SOM'));
    }

    public function test_reverse_geocode_api_endpoint(): void
    {
        $res = $this->getJson('/api/v1/gis/reverse-geocode?lat=9.0192&lng=38.7525');
        $res->assertOk();
        $res->assertJsonStructure(['data']);
    }
}
