<?php

namespace App\Http\Controllers;

use App\Models\QuizSession;
use App\Services\QuizEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DisplayController extends Controller
{
    public function show(string $token): View
    {
        $session = QuizSession::where('display_token', $token)->firstOrFail();

        return view('display.show', [
            'session' => $session->load('quiz'),
            'stateUrl' => route('display.state', $token),
            'channel' => 'display.'.$token,
        ]);
    }

    public function state(string $token, QuizEngine $engine): JsonResponse
    {
        $session = QuizSession::where('display_token', $token)->firstOrFail();

        return response()->json($engine->displayState($session));
    }
}
