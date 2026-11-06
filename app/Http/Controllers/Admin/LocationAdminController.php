<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Services\AuditService;
use App\Domains\Location\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class LocationAdminController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('location.view')) {
            abort(403, 'Unauthorized access to location administration.');
        }

        $query = Location::query()->with('parent');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%")
                    ->orWhere('name_om', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $level = (string) $request->input('level', 'all');
        if ($level !== 'all' && in_array($level, Location::LEVELS, true)) {
            $query->where('level', $level);
        }

        $status = (string) $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $locations = $query->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->paginate(25)
            ->withQueryString();

        // Load valid candidate parents for location creation
        $candidateParents = Location::query()
            ->where('is_active', true)
            ->whereIn('level', ['country', 'region', 'zone', 'city', 'subcity', 'woreda', 'kebele'])
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'level', 'code']);

        return view('admin.locations.index', [
            'locations' => $locations,
            'candidateParents' => $candidateParents,
            'levels' => Location::LEVELS,
            'validChildLevels' => Location::VALID_CHILD_LEVELS,
            'currentSearch' => $search,
            'currentLevel' => $level,
            'currentStatus' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('location.manage')) {
            abort(403, 'Unauthorized to create locations.');
        }

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:locations,id'],
            'level' => ['required', 'string', 'in:' . implode(',', Location::LEVELS)],
            'code' => ['required', 'string', 'max:24'],
            'name_en' => ['required', 'string', 'max:140'],
            'name_am' => ['nullable', 'string', 'max:140'],
            'name_om' => ['nullable', 'string', 'max:140'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = Str::slug($validated['name_en'] . '-' . $validated['code']);
        if (Location::query()->where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $parent = ! empty($validated['parent_id'])
            ? Location::query()->find($validated['parent_id'])
            : null;

        $ancestry = $parent
            ? ($parent->ancestry_path ?: '/') . "{$parent->id}/"
            : '/';

        $location = new Location([
            'parent_id' => $parent?->id,
            'level' => $validated['level'],
            'code' => strtoupper($validated['code']),
            'slug' => $slug,
            'ancestry_path' => $ancestry,
            'name_en' => $validated['name_en'],
            'name_am' => $validated['name_am'] ?? null,
            'name_om' => $validated['name_om'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => true,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        // validateHierarchy() is called on model saving event
        $location->save();

        $this->auditService->log(
            action: 'location.created',
            auditable: 'Location',
            oldValues: null,
            newValues: ['id' => $location->id, 'code' => $location->code, 'level' => $location->level, 'slug' => $location->slug],
            reason: 'Administrative location creation',
            userId: $user->id
        );

        return redirect()->back()->with('status', "Location '{$location->name_en}' ({$location->code}) created successfully.");
    }

    public function deactivate(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('location.deactivate')) {
            abort(403, 'Unauthorized to deactivate locations.');
        }

        $location = Location::query()->findOrFail($id);

        $referencedCount = $location->referencedPropertiesCount();
        $isPermanentDeleteRequested = $request->boolean('force_delete');

        if ($isPermanentDeleteRequested && $referencedCount > 0) {
            return redirect()->back()->withErrors([
                'error' => "Cannot permanently delete '{$location->name_en}' because it is actively referenced by {$referencedCount} properties. It has been safely deactivated instead.",
            ]);
        }

        $oldStatus = $location->is_active;
        $location->is_active = ! $location->is_active;
        $location->save();

        $actionWord = $location->is_active ? 'reactivated' : 'safely deactivated';

        $this->auditService->log(
            action: "location.{$actionWord}",
            auditable: 'Location',
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $location->is_active, 'referenced_properties' => $referencedCount],
            reason: (string) ($request->input('reason') ?? "Admin {$actionWord} location"),
            userId: $user->id
        );

        return redirect()->back()->with('status', "Location '{$location->name_en}' ({$location->code}) {$actionWord} successfully.");
    }
}
