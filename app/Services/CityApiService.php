<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CityApiService
{
    public function getTalukas(string $state, string $district)
    {
        $response = Http::get('https://india-location-hub.in/api/locations/talukas', [
            'state'    => $state,
            'district' => $district,
        ]);

        if ($response->failed()) {
            Log::error('Failed to fetch talukas');
            return [];
        }

        $data = $response->json();
        return $data['data']['talukas'] ?? [];
    }

    public function fetchVillages(string $state, string $district, string $taluka)
    {
        $response = Http::get('https://india-location-hub.in/api/locations/villages', [
            'state'    => $state,
            'district' => $district,
            'taluka'   => $taluka,
        ]);

        if ($response->failed()) {
            Log::error("Failed to fetch villages for $taluka");
            return [];
        }

        $data = $response->json();
        return $data['data']['villages'] ?? [];
    }
    public function getStates()
    {
        $response = Http::get('https://india-location-hub.in/api/locations/states');

        if ($response->failed()) {
            Log::error('Failed to fetch states');
            return [];
        }

        $data = $response->json();
        return $data['data']['states'] ?? [];
    }

    public function getDistricts(string $state)
    {
        $response = Http::get('https://india-location-hub.in/api/locations/districts', [
            'state' => $state,
        ]);

        if ($response->failed()) {
            Log::error("Failed to fetch districts for $state");
            return [];
        }

        $data = $response->json();
        return $data['data']['districts'] ?? [];
    }
}
