<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms', ['contactEmail' => $this->contactEmail()]);
    }

    public function privacy(): View
    {
        return view('legal.privacy', ['contactEmail' => $this->contactEmail()]);
    }

    private function contactEmail(): string
    {
        return (string) (AppSetting::current()->mail_from_address ?: config('mail.from.address'));
    }
}
