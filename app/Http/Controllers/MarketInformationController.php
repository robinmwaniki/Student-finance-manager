<?php

namespace App\Http\Controllers;

use App\Services\MarketInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MarketInformationController extends Controller
{
    public function index(MarketInformationService $market): View
    {
        return view('finance.market.index', ['dailyQuote' => $market->dailyQuote()]);
    }

    public function data(string $section, MarketInformationService $market): JsonResponse
    {
        $data = match ($section) {
            'inflation' => $market->inflation(),
            'holidays' => $market->holidays(),
            'crypto' => $market->crypto(),
            'stocks' => $market->stocks(),
            'commodities' => $market->commodities(),
            'news' => $market->news(),
            'quote' => $market->dailyQuote(),
            default => abort(404),
        };

        return response()->json($data);
    }

    public function nearby(Request $request, MarketInformationService $market): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required_without:location', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'required_without:location', 'nullable', 'numeric', 'between:-180,180'],
            'location' => ['required_without:latitude', 'nullable', 'string', 'max:120'],
        ]);

        if (filled($data['location'] ?? null)) {
            $place = $market->geocode($data['location']);
            if ($place === null) {
                return response()->json(['message' => 'We could not find that location. Try a town, estate or landmark.'], 422);
            }
            $latitude = $place['latitude'];
            $longitude = $place['longitude'];
            $label = $place['label'];
        } else {
            $latitude = (float) $data['latitude'];
            $longitude = (float) $data['longitude'];
            $label = 'Location selected in your browser';
        }

        try {
            return response()->json([...$market->nearby($latitude, $longitude), 'searched_location' => $label]);
        } catch (Throwable) {
            return response()->json(['message' => 'Nearby bank and ATM data is temporarily unavailable. Try again or search manually later.'], 503);
        }
    }

    public function weather(Request $request, MarketInformationService $market): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required_without:location', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'required_without:location', 'nullable', 'numeric', 'between:-180,180'],
            'location' => ['required_without:latitude', 'nullable', 'string', 'max:120'],
        ]);

        if (filled($data['location'] ?? null)) {
            $place = $market->geocode($data['location']);
            if ($place === null) {
                return response()->json(['message' => 'We could not find that location.'], 422);
            }
            $latitude = $place['latitude'];
            $longitude = $place['longitude'];
            $label = $place['label'];
        } else {
            $latitude = (float) $data['latitude'];
            $longitude = (float) $data['longitude'];
            $label = 'Location selected in your browser';
        }

        try {
            return response()->json([...$market->weather($latitude, $longitude), 'location' => $label]);
        } catch (Throwable) {
            return response()->json(['message' => 'Weather information is temporarily unavailable.'], 503);
        }
    }

    public function purchasingPower(Request $request, MarketInformationService $market): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'years' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        $result = $market->purchasingPower((float) $data['amount'], (int) $data['years']);

        return response()->json($result ?? ['message' => 'Inflation data is temporarily unavailable.'], $result === null ? 503 : 200);
    }
}
