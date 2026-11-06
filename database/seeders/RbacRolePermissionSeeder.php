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
            // Phase 4 Milestone 2: Modules 28, 29, 30
            ['slug' => 'maintenance.create', 'module' => 'maintenance'],
            ['slug' => 'maintenance.view', 'module' => 'maintenance'],
            ['slug' => 'maintenance.assign', 'module' => 'maintenance'],
            ['slug' => 'maintenance.update_status', 'module' => 'maintenance'],
            ['slug' => 'maintenance.manage_costs', 'module' => 'maintenance'],
            ['slug' => 'maintenance.close', 'module' => 'maintenance'],
            ['slug' => 'portfolio.view', 'module' => 'portfolio'],
            ['slug' => 'mandate.create', 'module' => 'mandate'],
            ['slug' => 'mandate.view', 'module' => 'mandate'],
            ['slug' => 'mandate.manage', 'module' => 'mandate'],
            ['slug' => 'tax.manage', 'module' => 'tax'],
            ['slug' => 'invoice.create', 'module' => 'invoice'],
            ['slug' => 'invoice.view', 'module' => 'invoice'],
            ['slug' => 'invoice.void', 'module' => 'invoice'],
            // Module 00 & 01 & 02: Language, Security, Locations
            ['slug' => 'language.view', 'module' => 'localization'],
            ['slug' => 'language.manage', 'module' => 'localization'],
            ['slug' => 'language.export', 'module' => 'localization'],
            ['slug' => 'language.import', 'module' => 'localization'],
            ['slug' => 'security.view', 'module' => 'security'],
            ['slug' => 'security.manage', 'module' => 'security'],
            ['slug' => 'location.view', 'module' => 'location'],
            ['slug' => 'location.manage', 'module' => 'location'],
            ['slug' => 'location.deactivate', 'module' => 'location'],
            ['slug' => 'location.import', 'module' => 'location'],
            // Phase 5 Milestone 2: Modules 33 & 34
            ['slug' => 'subscription.view', 'module' => 'subscription'],
            ['slug' => 'subscription.manage', 'module' => 'subscription'],
            ['slug' => 'promotion.manage', 'module' => 'promotion'],
            ['slug' => 'advertising.manage', 'module' => 'advertising'],
            ['slug' => 'advertising.approve', 'module' => 'advertising'],
            ['slug' => 'virtual_tour.create', 'module' => 'virtual_tour'],
            ['slug' => 'virtual_tour.manage', 'module' => 'virtual_tour'],
            ['slug' => 'gis.view_advanced', 'module' => 'gis'],
            ['slug' => 'listings.moderate.override_subscription_quota', 'module' => 'moderation'],
            ['slug' => 'subscriptions.entitlements.bypass', 'module' => 'subscription'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], array_merge($permission, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Explicit Role-to-Permission Matrix based on SRS specifications
        $rolePermissionsMap = [
            'verification_officer' => [
                'verification.review', 'verification.approve', 'verification.reject', 'property.view',
            ],
            'property_admin' => [
                'property.view', 'property.create', 'property.update', 'property.delete', 'property.approve', 'property.verify',
                'maintenance.create', 'maintenance.view', 'maintenance.assign', 'maintenance.update_status', 'maintenance.manage_costs', 'maintenance.close',
                'portfolio.view', 'mandate.create', 'mandate.view', 'mandate.manage',
                'invoice.create', 'invoice.view',
                'location.view', 'location.manage',
            ],
            'moderator' => [
                'property.view', 'property.verify',
            ],
            'finance_officer' => [
                'payment.view', 'payment.create', 'payment.refund', 'payment.reconcile', 'booking.refund',
                'tax.manage', 'invoice.create', 'invoice.view', 'invoice.void', 'portfolio.view',
            ],
            'platform_admin' => [
                'property.view', 'property.create', 'property.update', 'property.delete', 'property.approve', 'property.verify',
                'verification.review', 'verification.approve', 'verification.reject',
                'booking.view', 'booking.create', 'booking.cancel', 'booking.refund',
                'payment.view', 'payment.create', 'payment.refund', 'payment.reconcile',
                'maintenance.create', 'maintenance.view', 'maintenance.assign', 'maintenance.update_status', 'maintenance.manage_costs', 'maintenance.close',
                'portfolio.view', 'mandate.create', 'mandate.view', 'mandate.manage',
                'tax.manage', 'invoice.create', 'invoice.view', 'invoice.void',
                'language.view', 'language.manage', 'language.export', 'language.import',
                'security.view', 'security.manage',
                'location.view', 'location.manage', 'location.deactivate', 'location.import',
                'subscription.view', 'subscription.manage',
                'promotion.manage', 'advertising.manage', 'advertising.approve',
                'virtual_tour.create', 'virtual_tour.manage', 'gis.view_advanced',
                'listings.moderate.override_subscription_quota',
            ],
            'agent' => [
                'property.view', 'property.create', 'property.update', 'booking.view', 'booking.create',
                'mandate.view', 'maintenance.view', 'virtual_tour.create', 'virtual_tour.manage',
            ],
            'developer' => [
                'property.view', 'property.create', 'property.update', 'booking.view',
                'virtual_tour.create', 'virtual_tour.manage',
            ],
            'host' => [
                'property.view', 'property.create', 'property.update', 'booking.view', 'booking.cancel',
                'maintenance.create', 'maintenance.view', 'virtual_tour.create', 'virtual_tour.manage',
            ],
            'property_owner' => [
                'property.view', 'property.create', 'property.update', 'booking.view',
                'maintenance.create', 'maintenance.view', 'maintenance.update_status', 'mandate.view', 'invoice.view',
                'virtual_tour.create', 'virtual_tour.manage',
            ],
            'buyer' => [
                'property.view', 'booking.view', 'booking.create', 'booking.cancel',
            ],
            'tenant' => [
                'property.view', 'booking.view', 'booking.create', 'booking.cancel',
                'maintenance.create', 'maintenance.view', 'invoice.view',
            ],
            'customer_support' => [
                'property.view', 'booking.view', 'booking.cancel', 'payment.view',
                'maintenance.view', 'maintenance.create',
            ],
        ];

        foreach ($rolePermissionsMap as $roleSlug => $perms) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($perms as $permSlug) {
                $permId = DB::table('permissions')->where('slug', $permSlug)->value('id');
                if ($permId) {
                    DB::table('role_permissions')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }
    }
}
