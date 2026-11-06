@extends('layouts.app', ['title' => 'Development Projects & New Communities — Ethiopia National Property Platform'])

@section('content')
<div class="bg-slate-900 text-slate-100 py-12 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="max-w-3xl">
                <span class="px-2.5 py-1 text-xs font-semibold rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 uppercase tracking-wider">
                    Module 22 · Off-Plan Developments
                </span>
                <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white mt-3 mb-2">
                    Prime Masterplanned Developments
                </h1>
                <p class="text-base text-slate-300">
                    Verified developer projects across Addis Ababa and federal administrative hubs. Featuring independent civil engineer construction milestones, Proclamation No. 1357/2024 compliance, and direct unit inventory stacking matrices.
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                <a href="{{ route('projects.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-sm transition">
                    <span>+ Create New Project</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    @if($projects->isEmpty())
        <div class="text-center py-16 bg-white rounded-2xl border border-dashed border-slate-200">
            <h3 class="text-base font-semibold text-slate-900 mb-1">Featured Development Project</h3>
            <p class="text-xs text-slate-500 mb-4">Explore our flagship Bole luxury residential development.</p>
            <a href="{{ url('/projects/bole-tower-residences') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 text-white font-medium text-xs hover:bg-slate-800 transition">
                <span>View Bole Horizon Residences Matrix</span>
                <span>→</span>
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($projects as $project)
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs hover:shadow-md transition flex flex-col justify-between">
                    <div class="p-6">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded bg-slate-100 text-slate-700 uppercase">
                                {{ ucfirst(str_replace('_', ' ', $project->project_type)) }}
                            </span>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ number_format((float) $project->overall_progress_pct, 1) }}% Built
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 mb-1">
                            <a href="{{ url('/projects/' . $project->slug) }}" class="hover:text-amber-600 transition">
                                {{ $project->title_en }}
                            </a>
                        </h3>
                        @if($project->title_am)
                            <p class="text-xs text-amber-800 font-['Noto_Sans_Ethiopic'] mb-3">
                                {{ $project->title_am }}
                            </p>
                        @endif

                        <p class="text-xs text-slate-500 line-clamp-2 mb-4">
                            {{ $project->description_en }}
                        </p>

                        <div class="text-xs text-slate-600 space-y-1.5 pt-3 border-t border-slate-100">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Location:</span>
                                <span class="font-medium text-slate-800">{{ $project->address_line ?? 'Addis Ababa' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Developer:</span>
                                <span class="font-medium text-slate-800">{{ $project->developer->company->legal_name ?? 'Licensed Developer' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Permit:</span>
                                <span class="font-mono text-slate-700">{{ $project->building_permit_number ?? 'Verified' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-600">
                            Est. Delivery: <strong>{{ \Carbon\Carbon::parse($project->expected_completion_date ?? '2027-12-31')->format('M Y') }}</strong>
                        </span>
                        <a href="{{ url('/projects/' . $project->slug) }}" class="text-xs font-bold text-amber-700 hover:text-amber-900 inline-flex items-center gap-1">
                            Explore Units & Stacking Matrix →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $projects->links() }}
        </div>
    @endif
</div>
@endsection
