<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ParticipantFeedback;
use Illuminate\View\View;

class ParticipantFeedbackController extends Controller
{
    public function index(): View
    {
        $feedbacks = ParticipantFeedback::query()
            ->submitted()
            ->with('organization')
            ->latest('responded_at')
            ->latest('id')
            ->paginate(20);

        return view('master.feedback.index', compact('feedbacks'));
    }
}
