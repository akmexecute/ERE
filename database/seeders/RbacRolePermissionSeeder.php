<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RbacRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            // Public / Customer Roles (SRS Section 5)
            ['slug' => 'buyer', 'name_en' => 'Buyer', 'name_am' => 'ገዢ', 'name_om' => 'Bitaa', 'is_staff_role' => false],
            ['slug' => 'tenant', 'name_en' => 'Tenant', 'name_am' => 'ተከራይ', 'name_om' => 'Kireeffataa', 'is_staff_role' => false],
            ['slug' => 'property_owner', 'name_en' => 'Property Owner', 'name_am' => 'የንብረት ባለቤት', 'name_om' => 'Abbaa Qabeenyaa', 'is_staff_role' => false],
            ['slug' => 'host', 'name_en' => 'Short-Stay Host', 'name_am' => 'አስተናጋጅ', 'name_om' => 'Keessummeessaa', 'is_staff_role' => false],
            ['slug' => 'agent', 'name_en' => 'Real Estate Agent', 'name_am' => 'ወኪል / ደላላ', 'name_om' => 'Bakka Buʼaa', 'is_staff_role' => false],
            ['slug' => 'developer', 'name_en' => 'Real Estate Developer', 'name_am' => 'አልሚ', 'name_om' => 'Misoomsaa', 'is_staff_role' => false],
            // Admin & Staff Roles (SRS Section 141)
            ['slug' => 'super_admin', 'name_en' => 'Super Admin', 'name_am' => 'ዋና አስተዳዳሪ', 'name_om' => 'Bulchaa Ol-aanaa', 'is_staff_role' => true],
            ['slug' => 'platform_admin', 'name_en' => 'Platform Admin', 'name_am' => 'የፕላትፎርም አስተዳዳሪ', 'name_om' => 'Bulchaa Waltajjii', 'is_staff_role' => true],
            ['slug' => 'property_admin', 'name_en' => 'Property Admin', 'name_am' => 'የንብረት አስተዳዳሪ', 'name_om' => 'Bulchaa Qabeenyaa', 'is_staff_role' => true],
            ['slug' => 'verification_officer', 'name_en' => 'Verification Officer', 'name_am' => 'የማረጋገጫ ባለሙያ', 'name_om' => 'Ogeessa Mirkaneeffannaa', 'is_staff_role' => true],
            ['slug' => 'moderator', 'name_en' => 'Listing Moderator', 'name_am' => 'ተቆጣጣሪ', 'name_om' => 'Toʼataa', 'is_staff_role' => true],
            ['slug' => 'finance_officer', 'name_en' => 'Finance Officer', 'name_am' => 'የፋይናንስ ባለሙያ', 'name_om' => 'Ogeessa Faayinaansii', 'is_staff_role' => true],
            ['slug' => 'customer_support', 'name_en' => 'Customer Support', 'name_am' => 'የደንበኞች ድጋፍ', 'name_om' => 'Deeggarsa Maamilaa', 'is_staff_role' => true],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['slug' => $role['slug']], array_merge($role, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Granular Permissions (SRS Section 140)
        $permissions = [
            ['slug' => 'property.view', 'module' => 'property'],
            ['slug' => 'property.create', 'module' => 'property'],
            ['slug' => 'property.update', 'module' => 'property'],
            ['slug' => 'property.delete', 'module' => 'property'],
            ['slug' => 'property.approve', 'module' => 'property'],
            ['slug' => 'property.verify', 'module' => 'property'],
            ['slug' => 'verification.review', 'module' => 'verification'],
            ['slug' => 'verification.approve', 'module' => 'verification'],
            ['slug' => 'verification.reject', 'module' => 'verification'],
            ['slug' => 'booking.view', 'module' => 'booking'],
            ['slug' => 'booking.create', 'module' => 'booking'],
            ['slug' => 'booking.cancel', 'module' => 'booking'],
            ['slug' => 'booking.refund', 'module' => 'booking'],
            ['slug' => 'payment.view', 'module' => 'payment'],
            ['slug' => 'payment.create', 'module' => 'payment'],
            ['slug' => 'payment.refund', 'module' => 'payment'],
            ['slug' => 'payment.reconcile', 'module' => 'payment'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], array_merge($permission, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
