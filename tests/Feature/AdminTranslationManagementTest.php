<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminTranslationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
    }

    public function test_admin_settings_page_lists_all_website_words_and_i18n_matrix(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'phone' => '+251911000001',
            'status' => 'active',
        ]);
        $admin->assignRoleBySlug('super_admin');

        $response = $this->actingAs($admin)->get('/admin/settings');
        $response->assertStatus(200);
        $response->assertSee('Multi-Language &amp; i18n Translation Management', false);
        $response->assertSee('auth.send_verification_code');
        $response->assertSee('Send 6-Digit Verification Code');
        $response->assertSee('ባለ 6 አሃዝ የማረጋገጫ ኮድ ይላኩ');
        $response->assertSee('Koodii Mirkaneessaa Dijitii 6 Ergi');

        // Verify key page categories exist in matrix
        $response->assertSee('🔐 Login &amp; Authentication', false);
        $response->assertSee('🧭 Navigation, Header &amp; Footer', false);
        $response->assertSee('🌟 Home Hero &amp; Landing', false);
        $response->assertSee('🔍 Marketplace Search &amp; Map', false);
        $response->assertSee('🏠 Property Details &amp; Legal Vault', false);
        $response->assertSee('📝 Post Property &amp; Listings', false);
    }

    public function test_admin_can_update_translations_dynamically_and_reflects_in_i18n(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'phone' => '+251911000002',
            'status' => 'active',
        ]);
        $admin->assignRoleBySlug('super_admin');

        $response = $this->actingAs($admin)->post('/admin/settings/translations', [
            'translations' => [
                'auth.send_verification_code' => [
                    'en' => 'Send 6-Digit Verification Code',
                    'am' => 'ባለ 6 አሃዝ የማረጋገጫ ኮድ ይላኩ',
                    'om' => 'Koodii Mirkaneessaa Dijitii 6 Ergi',
                ],
            ],
        ]);

        $response->assertRedirect('/admin/settings?tab=translations');

        // Test in Amharic locale
        app()->setLocale('am');
        $this->assertSame('ባለ 6 አሃዝ የማረጋገጫ ኮድ ይላኩ', __('auth.send_verification_code'));

        // Test in Afaan Oromo locale
        app()->setLocale('om');
        $this->assertSame('Koodii Mirkaneessaa Dijitii 6 Ergi', __('auth.send_verification_code'));

        // Test in English locale
        app()->setLocale('en');
        $this->assertSame('Send 6-Digit Verification Code', __('auth.send_verification_code'));
    }

    public function test_login_page_renders_localized_words_based_on_active_locale(): void
    {
        // When visiting /login in English
        $responseEn = $this->get('/login?lang=en');
        $responseEn->assertStatus(200);
        $responseEn->assertSee('Send 6-Digit Verification Code');

        // When visiting /login in Amharic
        $responseAm = $this->get('/login?lang=am');
        $responseAm->assertStatus(200);
        $responseAm->assertSee('ባለ 6 አሃዝ የማረጋገጫ ኮድ ይላኩ');

        // When visiting /login in Afaan Oromo
        $responseOm = $this->get('/login?lang=om');
        $responseOm->assertStatus(200);
        $responseOm->assertSee('Koodii Mirkaneessaa Dijitii 6 Ergi');
    }

    public function test_home_page_and_search_render_localized_words(): void
    {
        // Home page in Amharic
        $homeAm = $this->get('/?lang=am');
        $homeAm->assertStatus(200);
        $homeAm->assertSee('የኢትዮጵያ ንብረት መገበያያ እና የካርታ መፈለጊያ ስርዓት');
        $homeAm->assertSee('በ+251 ኦቲፒ ይግቡ');

        // Home page in Afaan Oromo
        $homeOm = $this->get('/?lang=om');
        $homeOm->assertStatus(200);
        $homeOm->assertSee('Gabaa Qabeenyaa fi Mooraa Kaartaa Itoophiyaa');
        $homeOm->assertSee('OTP +251 Galmaaʼi');

        // Search page in Amharic
        $searchAm = $this->get('/search?lang=am');
        $searchAm->assertStatus(200);
        $searchAm->assertSee('የተረጋገጡ ብቻ');
        $searchAm->assertSee('ንብረቱን እና ህጋዊ ሰነዶችን ይመልከቱ');

        // Search page in Afaan Oromo
        $searchOm = $this->get('/search?lang=om');
        $searchOm->assertStatus(200);
        $searchOm->assertSee('Mirkanaaʼan Qofa');
        $searchOm->assertSee('Qabeenya fi Galmee Seeraa Ilaalaa');
    }

    public function test_admin_can_add_new_phrase_to_marketplace_and_listings_groups(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'phone' => '+251911000003',
            'status' => 'active',
        ]);
        $admin->assignRoleBySlug('super_admin');

        $response = $this->actingAs($admin)->post('/admin/settings/translations/add', [
            'group' => 'marketplace',
            'key' => 'custom_search_tag',
            'en' => 'Special Verified Tag',
            'am' => 'ልዩ የተረጋገጠ ባጅ',
            'om' => 'Mallattoo Addaa Mirkanaaʼe',
        ]);

        $response->assertRedirect('/admin/settings?tab=translations');

        app()->setLocale('am');
        $this->assertSame('ልዩ የተረጋገጠ ባጅ', __('marketplace.custom_search_tag'));
    }
}
