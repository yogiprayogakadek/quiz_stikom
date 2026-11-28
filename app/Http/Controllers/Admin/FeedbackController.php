<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\ParticipantFeedback;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = app(OrganizationContext::class)->id();
        abort_unless($organizationId !== null, 403);

        $kelasList = Kelas::orderBy('nama')->get();
        $kelasFilter = $request->integer('kelas') ?: null;
        if ($kelasFilter !== null && ! $kelasList->contains('id', $kelasFilter)) {
            $kelasFilter = null;
        }

        $ratingFilter = $request->integer('rating') ?: null;
        if ($ratingFilter !== null && ($ratingFilter < 1 || $ratingFilter > 5)) {
            $ratingFilter = null;
        }

        $baseQuery = ParticipantFeedback::query()
            ->submitted()
            ->where('organization_id', $organizationId);

        $totalSemua = (clone $baseQuery)->count();
        $rataRata = $totalSemua > 0 ? round((float) (clone $baseQuery)->avg('rating'), 1) : null;

        $feedbacks = (clone $baseQuery)
            ->with([
                'user' => fn ($q) => $q->withTrashed()->with([
                    'kelas' => fn ($kq) => $kq->where('kelas.organization_id', $organizationId),
                ]),
                'attempt' => fn ($q) => $q->withoutGlobalScopes()->with([
                    'quizSession' => fn ($sq) => $sq->withoutGlobalScopes()->with([
                        'quiz' => fn ($qz) => $qz->withoutGlobalScopes(),
                        'kelas' => fn ($kl) => $kl->withoutGlobalScopes(),
                    ]),
                ]),
            ])
            ->when($ratingFilter !== null, fn ($q) => $q->where('rating', $ratingFilter))
            ->when($kelasFilter !== null, function ($q) use ($kelasFilter) {
                $q->where(function ($inner) use ($kelasFilter) {
                    $inner->whereHas('attempt.quizSession', fn ($sq) => $sq->withoutGlobalScopes()->where('kelas_id', $kelasFilter))
                        ->orWhereHas('user.kelas', fn ($kq) => $kq->withoutGlobalScopes()->where('kelas.id', $kelasFilter));
                });
            })
            ->latest('responded_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.feedback.index', compact(
            'feedbacks',
            'kelasList',
            'kelasFilter',
            'ratingFilter',
            'totalSemua',
            'rataRata'
        ));
    }
}
