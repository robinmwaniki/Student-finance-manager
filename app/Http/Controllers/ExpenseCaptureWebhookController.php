<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCaptureWebhookCredential;
use App\Services\ExpenseCaptureService;
use App\Services\MpesaSmsParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class ExpenseCaptureWebhookController extends Controller
{
    public function ingest(Request $request, MpesaSmsParser $parser, ExpenseCaptureService $capture): JsonResponse
    {
        // Reject oversized bodies before parsing JSON or accepting the webhook.
        $rawBody = $request->getContent();
        abort_if(strlen($rawBody) > 10000, 413);

        $bearer = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        abort_if($bearer === '' || mb_strlen($bearer) > 200, 401);

        $tokenHash = hash('sha256', $bearer);
        $credential = ExpenseCaptureWebhookCredential::query()
            ->where('token_hash', $tokenHash)
            ->orWhere('previous_token_hash', $tokenHash)
            ->first();

        abort_if($credential === null, 401);
        $isCurrent = hash_equals($credential->token_hash, $tokenHash);
        if (! $isCurrent) {
            abort_if($credential->previous_expires_at === null || $credential->previous_expires_at->isPast(), 401);
        }

        $timestamp = (string) $request->header('X-Webhook-Timestamp');
        abort_unless(ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300, 401);

        $secret = $isCurrent ? $credential->signing_secret : $credential->previous_signing_secret;
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, (string) $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Webhook-Signature')), 401);

        $rateLimitKey = 'mpesa-webhook:'.$credential->user_id;
        abort_if(RateLimiter::tooManyAttempts($rateLimitKey, 30), 429);
        RateLimiter::hit($rateLimitKey, 60);

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:120'],
            'sms' => ['required', 'string', 'max:3000'],
        ]);
        $candidate = $parser->parse($data['sms']);
        abort_if($candidate === null, 422, 'The webhook message is not a supported outgoing M-PESA confirmation.');

        // Event IDs and transaction fingerprints make provider retries idempotent.
        $accepted = DB::transaction(function () use ($credential, $data, $rawBody, $candidate, $capture): bool {
            $inserted = DB::table('expense_capture_webhook_events')->insertOrIgnore([
                'user_id' => $credential->user_id,
                'event_id' => $data['event_id'],
                'payload_hash' => hash('sha256', $rawBody),
                'received_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            $capture->createDraft(
                $credential->user,
                'mpesa',
                [...$candidate, 'tag' => mb_substr('M-Pesa webhook'.($candidate['merchant'] ? ' · '.$candidate['merchant'] : ''), 0, 50)],
                hash('sha256', 'mpesa|'.($candidate['reference'] ?? strtoupper(trim(preg_replace('/\s+/', ' ', $data['sms']) ?? '')))),
            );

            return true;
        });

        return response()->json(['accepted' => $accepted, 'duplicate' => ! $accepted], $accepted ? 202 : 200);
    }
}
