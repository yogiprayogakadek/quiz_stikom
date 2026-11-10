@php
    $organizationContext = app(\App\Support\OrganizationContext::class);
    $organizations = auth()->user()->organizations
        ->where('pivot.status', 'active')
        ->where('is_active', true)
        ->values();
    $currentOrganization = $organizationContext->current();
@endphp

@if ($currentOrganization)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-outline bg-surface p-3']) }}>
        <p class="text-xs font-semibold text-ink-subtle">Workspace aktif</p>
        @if ($organizations->count() > 1)
            <form method="POST" action="{{ route('organizations.switch') }}" class="mt-1.5 flex gap-2">
                @csrf
                <label class="sr-only" for="organization-switch-{{ $attributes->get('id', 'default') }}">Pilih workspace</label>
                <select id="organization-switch-{{ $attributes->get('id', 'default') }}" name="organization_id" class="input min-w-0 flex-1 py-2 text-sm">
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected($organization->is($currentOrganization))>{{ $organization->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary min-h-11 px-3 text-xs">Pilih</button>
            </form>
        @else
            <p class="mt-1 truncate text-sm font-bold text-ink">{{ $currentOrganization->name }}</p>
        @endif
    </div>
@endif
