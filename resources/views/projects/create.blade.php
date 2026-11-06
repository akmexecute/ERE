@extends('layouts.app', ['title' => 'Create New Development Project — Ethiopian Real Estate Marketplace'])

@section('content')
<div class="min-h-screen bg-[#faf9f5] py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Breadcrumb -->
        <div class="flex items-center justify-between">
            <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition">
                <span>&larr;</span> Back to All Projects
            </a>
            <span class="text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full">
                Developer Project Registry · Proclamation No. 1357/2024
            </span>
        </div>

        <!-- Header -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Create New Development Project</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 leading-relaxed">
                Register a multi-unit masterplanned development, residential tower, or commercial project. Once created, you can configure civil construction milestones, payment installments, and individual unit inventory.
            </p>
        </div>

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm space-y-1">
                <div class="font-bold">Please correct the following errors:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('projects.store') }}" class="space-y-6">
            @csrf

            <!-- Section 1: Identification & Developer -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
                    <span>🏢</span> 1. Project Identification &amp; Developer Entity
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Project Title (English) <span class="text-rose-500">*</span></label>
                        <input type="text" name="title_en" value="{{ old('title_en') }}" placeholder="e.g. Bole Horizon Luxury Residences" required class="w-full rounded-xl border-slate-300 text-sm font-medium focus:ring-teal-600 focus:border-teal-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Project Title (Amharic - አማርኛ)</label>
                        <input type="text" name="title_am" value="{{ old('title_am') }}" placeholder="e.g. ቦሌ ሆራይዘን የቅንጦት መኖሪያዎች" class="w-full rounded-xl border-slate-300 text-sm font-['Noto_Sans_Ethiopic'] focus:ring-teal-600 focus:border-teal-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Developer Company <span class="text-rose-500">*</span></label>
                        <select name="company_id" class="w-full rounded-xl border-slate-300 text-sm" required>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->legal_name }} ({{ $company->trade_name ?? 'Developer' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Project Overview &amp; Masterplan Description <span class="text-rose-500">*</span></label>
                        <textarea name="description_en" rows="3" placeholder="Describe the structural architecture, seismic code compliance, amenities, security, and residential community concept..." required class="w-full rounded-xl border-slate-300 text-xs sm:text-sm">{{ old('description_en') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Classification, Permitting & Timeline -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
                    <span>📋</span> 2. Classification, Permitting &amp; Timeline
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Development Type <span class="text-rose-500">*</span></label>
                        <select name="project_type" class="w-full rounded-xl border-slate-300 text-sm" required>
                            @foreach($projectTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('project_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Current Lifecycle Status <span class="text-rose-500">*</span></label>
                        <select name="status" class="w-full rounded-xl border-slate-300 text-sm" required>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ old('status', 'under_construction') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Total Land Parcel Area (m²)</label>
                        <input type="number" step="0.01" name="land_area_sqm" value="{{ old('land_area_sqm', '3500') }}" class="w-full rounded-xl border-slate-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Building Permit Number</label>
                        <input type="text" name="building_permit_number" value="{{ old('building_permit_number') }}" placeholder="e.g. AA/CON/BP-2026-00421" class="w-full rounded-xl border-slate-300 text-sm font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Expected Completion Date</label>
                        <input type="date" name="expected_completion_date" value="{{ old('expected_completion_date', '2028-12-31') }}" class="w-full rounded-xl border-slate-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Estimated Progress (%)</label>
                        <input type="number" step="0.1" min="0" max="100" name="overall_progress_pct" value="{{ old('overall_progress_pct', '15.0') }}" class="w-full rounded-xl border-slate-300 text-sm font-bold text-teal-800">
                    </div>
                </div>
            </div>

            <!-- Section 3: Geographic Location -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
                    <span>📍</span> 3. Geographic Location &amp; Coordinates
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">City</label>
                        <select name="city_id" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Select City...</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" {{ old('city_id') == $city->id || str_contains($city->name_en, 'Addis') ? 'selected' : '' }}>{{ $city->name_en }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Sub-City / District</label>
                        <select name="subcity_id" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Select Sub-City...</option>
                            @foreach($subcities as $sc)
                                <option value="{{ $sc->id }}" {{ old('subcity_id') == $sc->id || str_contains($sc->name_en, 'Bole') ? 'selected' : '' }}>{{ $sc->name_en }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Street Address Line <span class="text-rose-500">*</span></label>
                        <input type="text" name="address_line" value="{{ old('address_line', 'Bole Sub-City, Addis Ababa, Ethiopia') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Latitude (° N)</label>
                        <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', '9.0100000') }}" class="w-full rounded-xl border-slate-300 text-sm font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Longitude (° E)</label>
                        <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', '38.7800000') }}" class="w-full rounded-xl border-slate-300 text-sm font-mono">
                    </div>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="flex items-center justify-end gap-4 pt-4">
                <a href="{{ route('projects.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shadow-sm transition flex items-center gap-2">
                    <span>Create Development Project &rarr;</span>
                </button>
            </div>
        </form>

    </div>
</div>
@endsection

