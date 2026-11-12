<?php

namespace App\Http\Controllers;

use App\Models\Organization;
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

        $organizations = $q === ''
            ? collect()
            : Organization::query()
                ->where('is_active', true)
                ->where(function ($query) use ($q) {
                    $term = '%'.addcslashes($q, '%_\\').'%';
                    $query->where('name', 'like', $term)->orWhere('slug', 'like', $term);
                })
                ->orderBy('name')
                ->limit(6)
                ->get(['name', 'slug']);

        return view('landing', [
            'q' => $q,
            'organizations' => $organizations,
        ]);
    }
}
