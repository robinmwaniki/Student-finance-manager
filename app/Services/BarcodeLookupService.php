<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class BarcodeLookupService
{
    /**
     * Return product identity only; catalog pricing is never treated as purchase price.
     *
     * @return array{name: string, brand: string|null}|null
     */
    public function lookup(string $barcode): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => 'StudentFinanceManager/1.0 (barcode product lookup)'])
                ->timeout(5)
                ->connectTimeout(2)
                ->get('https://world.openfoodfacts.org/api/v2/product/'.rawurlencode($barcode).'.json', [
                    'fields' => 'product_name,brands',
                ]);

            if (! $response->successful() || $response->json('status') !== 1) {
                return null;
            }

            $product = $response->json('product', []);
            $name = trim((string) ($product['product_name'] ?? ''));

            if ($name === '') {
                return null;
            }

            return [
                'name' => mb_substr($name, 0, 150),
                'brand' => filled($product['brands'] ?? null) ? mb_substr((string) $product['brands'], 0, 120) : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
