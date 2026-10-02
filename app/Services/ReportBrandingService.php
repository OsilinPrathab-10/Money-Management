<?php

namespace App\Services;

use App\Helpers\AppearanceHelper;
use App\Models\Appearance;
use App\Models\CompanyDetail;

class ReportBrandingService
{
    /**
     * @return array{name: string, slogan: ?string, address: string, logo: ?string}
     */
    public function get(): array
    {
        $company = CompanyDetail::query()->first();
        $appearance = Appearance::query()->where('type', 'web')->first();

        return [
            'name' => $company?->company_name
                ?: AppearanceHelper::get('title', config('app.name')),
            'slogan' => $company?->company_slogan
                ?: AppearanceHelper::get('subtitle'),
            'address' => collect([
                $company?->address_line1,
                $company?->address_line2,
                $company?->city,
                $company?->state,
                $company?->pincode,
                $company?->country,
            ])->filter(fn ($value) => filled($value))->implode(', '),
            'logo' => $this->resolveLogoDataUri($appearance?->logo ?: $company?->logo_path),
        ];
    }

    private function resolveLogoDataUri(?string $logoPath): ?string
    {
        if (!$logoPath) {
            return null;
        }

        $normalizedPath = preg_replace('#^(?:public/|storage/)#', '', $logoPath);
        $candidates = [
            storage_path('app/public/' . $normalizedPath),
            public_path('storage/' . $normalizedPath),
            public_path($normalizedPath),
        ];

        foreach ($candidates as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $mime = function_exists('mime_content_type')
                ? mime_content_type($path)
                : null;
            $mime = $mime ?: 'image/' . strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');

            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        }

        return null;
    }
}
