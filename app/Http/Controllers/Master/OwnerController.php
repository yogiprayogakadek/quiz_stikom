<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $owners = User::query()
            ->where('role', 'admin')
            ->whereHas('ownedOrganizations')
            ->with(['ownedOrganizations' => fn ($query) => $query->orderBy('name')])
            ->withCount('ownedOrganizations')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('master.owners.index', compact('owners', 'search'));
    }
}
