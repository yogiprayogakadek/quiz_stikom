<?php

namespace App\Services;

use App\Models\Font;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class FontLibrary
{
    public function options(?int $organizationId): Collection
    {
        return Font::query()
            ->where(function ($query) use ($organizationId) {
                $query->whereNull('organization_id');

                if ($organizationId !== null) {
                    $query->orWhere('organization_id', $organizationId);
                }
            })
            ->orderByRaw('organization_id IS NULL')
            ->orderBy('nama')
            ->orderBy('berat')
            ->get();
    }

    public function resolve(?int $fontId, ?int $organizationId): ?Font
    {
        if ($fontId === null) {
            return null;
        }

        return $this->options($organizationId)->firstWhere('id', $fontId);
    }

    public function assertUsable(?int $fontId, ?int $organizationId, string $field = 'font_utama_id'): ?Font
    {
        if ($fontId === null) {
            return null;
        }

        $font = $this->resolve($fontId, $organizationId);

        if ($font === null) {
            throw ValidationException::withMessages([
                $field => 'Font yang dipilih tidak tersedia untuk workspace ini.',
            ]);
        }

        return $font;
    }
}
