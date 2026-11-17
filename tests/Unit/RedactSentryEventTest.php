<?php

namespace Tests\Unit;

use App\Support\Observability\RedactSentryEvent;
use Tests\TestCase;
use Sentry\Event;

class RedactSentryEventTest extends TestCase
{
    public function test_event_request_payload_and_sensitive_context_are_removed_or_redacted(): void
    {
        $event = Event::createEvent()
            ->setMessage('Failed transaction amount=9000')
            ->setRequest([
                'method' => 'POST',
                'url' => 'https://example.test/transactions?account=12345678&token=secret',
                'data' => ['amount' => '9000', 'description' => 'Salary'],
                'headers' => ['Authorization' => 'Bearer secret'],
                'cookies' => ['session' => 'secret'],
            ])
            ->setExtra([
                'amount' => 9000,
                'request_id' => 'safe-correlation-id',
            ])
            ->setContext('finance', [
                'account_number' => '12345678',
                'status' => 'posted',
            ]);

        $redacted = RedactSentryEvent::handle($event);

        $this->assertSame([
            'method' => 'POST',
            'url' => 'https://example.test/transactions',
        ], $redacted->getRequest());
        $this->assertSame('[REDACTED]', $redacted->getExtra()['amount']);
        $this->assertSame('safe-correlation-id', $redacted->getExtra()['request_id']);
        $this->assertSame('[REDACTED]', $redacted->getContexts()['finance']['account_number']);
        $this->assertSame('posted', $redacted->getContexts()['finance']['status']);
        $this->assertSame('Failed transaction amount=[REDACTED]', $redacted->getMessage());
    }

    public function test_sentry_configuration_disables_default_personal_data_collection(): void
    {
        $configuration = require base_path('config/sentry.php');

        $this->assertFalse($configuration['send_default_pii']);
        $this->assertSame([RedactSentryEvent::class, 'handle'], $configuration['before_send']);
        $this->assertFalse($configuration['breadcrumbs']['sql_queries']);
        $this->assertFalse($configuration['breadcrumbs']['sql_bindings']);
    }
}
