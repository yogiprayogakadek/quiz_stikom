<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            return redirect()->route(
                $user->isPlatformAdmin()
                    ? 'master.organizations.index'
                    : ($user->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard')
            );
        }

        $q = trim((string) $request->query('q', ''));
        $q = mb_substr($q, 0, 80);

        return view('landing', [
            'q' => $q,
            'organizations' => $this->cariOrganisasi($q),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);

        return response()->json([
            'q' => $q,
            'organizations' => $this->cariOrganisasi($q)->map(fn (Organization $organization) => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'login_url' => route('organizations.login', $organization),
            ])->values(),
        ]);
    }

    private function cariOrganisasi(string $q): \Illuminate\Support\Collection
    {
        if ($q === '') {
            return collect();
        }

        return Organization::query()
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $term = '%'.addcslashes($q, '%_\\').'%';
                $query->where('name', 'like', $term)->orWhere('slug', 'like', $term);
            })
            ->orderBy('name')
            ->limit(6)
            ->get(['name', 'slug']);
    }
}
