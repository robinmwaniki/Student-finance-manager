<?php

namespace App\Http\Controllers;

use App\Services\PublicInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicInformationController extends Controller
{
    public function index(PublicInformationService $information): View
    {
        return view('finance.information.index', $information->dashboard());
    }

    public function weather(Request $request, PublicInformationService $information): JsonResponse
    {
        $data = $request->validate([
            'place' => ['nullable', 'string', 'min:2', 'max:120', 'required_without:latitude'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
        $location = filled($data['place'] ?? null)
            ? $information->geocode($data['place'])
            : ['name' => 'Your selected location', 'latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude']];

        if (! $location) {
            return response()->json(['message' => 'We could not find that location. Try a nearby town or landmark.'], 422);
        }

        $weather = $information->weather($location['latitude'], $location['longitude']);

        return response()->json($weather ? [...$weather, 'location' => $location] : ['message' => 'Weather data is temporarily unavailable.'], $weather ? 200 : 503);
    }

    public function nearby(Request $request, PublicInformationService $information): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:bank,atm'],
            'place' => ['nullable', 'string', 'min:2', 'max:120', 'required_without:latitude'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);

        if (filled($data['place'] ?? null)) {
            $location = $information->geocode($data['place']);
            if (! $location) {
                return response()->json(['message' => 'We could not find that location. Try a nearby town or landmark.'], 422);
            }
            $latitude = $location['latitude'];
            $longitude = $location['longitude'];
        } else {
            $latitude = (float) $data['latitude'];
            $longitude = (float) $data['longitude'];
            $location = ['name' => 'Your selected location', 'latitude' => $latitude, 'longitude' => $longitude];
        }

        return response()->json([
            'location' => $location,
            'type' => $data['type'],
            'places' => $information->nearbyPlaces($latitude, $longitude, $data['type']),
            'source' => 'OpenStreetMap contributors · results may be incomplete',
        ]);
    }
}
