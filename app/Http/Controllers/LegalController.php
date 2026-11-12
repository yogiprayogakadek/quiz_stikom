<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Support\OrganizationContext;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(?Organization $organization = null): View
    {
        return $this->document('terms', $organization);
    }

    public function privacy(?Organization $organization = null): View
    {
        return $this->document('privacy', $organization);
    }

    private function document(string $type, ?Organization $organization): View
    {
        $customContent = null;

        if ($organization !== null) {
            abort_unless($organization->is_active, 404);
            $context = app(OrganizationContext::class);
            $context->set($organization);
            $context->applyLocale($organization);

            $settings = OrganizationSetting::effective($organization->id);
            $customContent = $type === 'terms'
                ? $settings->terms_content
                : $settings->privacy_content;
        }

        return view('legal.'.$type, [
            'contactEmail' => $this->contactEmail(),
            'organization' => $organization,
            'customContent' => $customContent,
        ]);
    }

    private function contactEmail(): string
    {
        return (string) (AppSetting::current()->mail_from_address ?: config('mail.from.address'));
    }
}
