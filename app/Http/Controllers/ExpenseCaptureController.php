<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCaptureWebhookCredential;
use App\Services\ExpenseCaptureService;
use App\Services\MpesaSmsParser;
use App\Services\ReceiptOcrService;
use App\Services\VoiceExpenseParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpenseCaptureController extends Controller
{
    public function index(Request $request, ReceiptOcrService $ocr): View
    {
        return view('finance.expenses.capture', [
            'drafts' => $request->user()->expenseCaptureDrafts()->where('status', 'pending')->where('expires_at', '>', now())->latest()->get(),
            'ocrAvailable' => $ocr->isConfigured(),
            'webhookConfigured' => $request->user()->expenseCaptureWebhookCredential()->exists(),
        ]);
    }

    public function mpesa(Request $request, MpesaSmsParser $parser, ExpenseCaptureService $capture): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
        $candidate = $parser->parse($data['message']);

        if ($candidate === null) {
            return back()->withErrors(['message' => 'This message is not a supported outgoing M-Pesa confirmation. Check the message or enter the expense manually.']);
        }

        $draft = $capture->createDraft(
            $request->user(),
            'mpesa',
            [...$candidate, 'tag' => 'M-Pesa'.($candidate['merchant'] ? ' · '.$candidate['merchant'] : '')],
            hash('sha256', 'mpesa|'.($candidate['reference'] ?? strtoupper(preg_replace('/\\s+/', ' ', trim($data['message']))))),
        );

        return back()->with('success', 'M-Pesa details extracted. Review the draft before saving it as an expense.')->with('captured_draft_id', $draft->id);
    }

    public function voice(Request $request, VoiceExpenseParser $parser, ExpenseCaptureService $capture): RedirectResponse
    {
        $data = $request->validate(['transcript' => ['required', 'string', 'max:2000']]);
        $candidate = $parser->parse($data['transcript']);

        if ($candidate === null) {
            return back()->withErrors(['transcript' => 'We could not find a positive amount in that transcript. Edit the text or use manual expense entry.']);
        }

        $draft = $capture->createDraft(
            $request->user(),
            'voice',
            $candidate,
            hash('sha256', 'voice|'.mb_strtolower(trim($data['transcript']))),
        );

        return back()->with('success', 'Voice transcript converted to a draft. Confirm its amount, category and date before saving.')->with('captured_draft_id', $draft->id);
    }

    public function barcode(Request $request, ExpenseCaptureService $capture): RedirectResponse
    {
        $data = $request->validate(['barcode' => ['required', 'regex:/^\d{8,14}$/']]);
        $response = Http::acceptJson()
            ->withHeaders(['User-Agent' => 'StudentFinanceManager/1.0 (barcode product lookup)'])
            ->timeout(5)
            ->get('https://world.openfoodfacts.org/api/v2/product/'.$data['barcode'].'.json');

        if (! $response->successful() || data_get($response->json(), 'status') !== 1) {
            return back()->withErrors(['barcode' => 'No product match was found. Enter the expense manually instead.']);
        }

        $product = data_get($response->json(), 'product');
        $name = trim((string) data_get($product, 'product_name', ''));
        if ($name === '') {
            return back()->withErrors(['barcode' => 'The catalog returned no usable product name. Enter the expense manually instead.']);
        }

        $draft = $capture->createDraft(
            $request->user(),
            'barcode',
            ['merchant' => mb_substr($name, 0, 150), 'category' => 'Groceries', 'tag' => 'Barcode lookup'],
            hash('sha256', 'barcode|'.$data['barcode']),
        );

        return back()->with('success', 'Product found. Add the amount and date, then review the draft before saving.')->with('captured_draft_id', $draft->id);
    }

    public function receipt(Request $request, ReceiptOcrService $ocr, ExpenseCaptureService $capture): RedirectResponse
    {
        $data = $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'consent' => ['accepted'],
        ]);

        $file = $request->file('receipt');
        $candidate = $ocr->extract($file);
        if ($candidate === null) {
            return back()->withErrors(['receipt' => 'Receipt OCR is unavailable or could not read this image. Use the manual expense form instead.']);
        }

        $draft = $capture->createDraft(
            $request->user(),
            'receipt',
            $candidate,
            hash('sha256', 'receipt|'.hash_file('sha256', $file->getRealPath())),
        );

        return back()->with('success', 'Receipt details extracted. Verify the amount and date before saving.')->with('captured_draft_id', $draft->id);
    }

    public function confirm(Request $request, int $draft, ExpenseCaptureService $capture): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);
        $capture->confirm($request->user(), $draft, $version, $data);

        return redirect()->route('expenses.index')->with('success', 'Verified expense saved to your existing expense ledger.');
    }

    public function discard(Request $request, int $draft, ExpenseCaptureService $capture): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $capture->discard($request->user(), $draft, (int) $data['lock_version']);

        return back()->with('success', 'Capture draft discarded.');
    }

    public function rotateWebhook(Request $request): RedirectResponse
    {
        $token = Str::random(64);
        $secret = Str::random(64);
        $credential = $request->user()->expenseCaptureWebhookCredential()->first();

        if ($credential === null) {
            $request->user()->expenseCaptureWebhookCredential()->create([
                'token_hash' => hash('sha256', $token),
                'signing_secret' => $secret,
                'rotated_at' => now(),
            ]);
        } else {
            $credential->forceFill([
                'previous_token_hash' => $credential->token_hash,
                'previous_signing_secret' => $credential->signing_secret,
                'previous_expires_at' => now()->addHours(24),
                'token_hash' => hash('sha256', $token),
                'signing_secret' => $secret,
                'rotated_at' => now(),
            ])->save();
        }

        return back()->with('webhook_credentials', ['token' => $token, 'secret' => $secret]);
    }
}
