<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveContextProcessor;
use App\Support\Observability\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    public function test_nested_financial_and_authentication_context_is_redacted(): void
    {
        $redacted = SensitiveDataRedactor::redactArray([
            'request_id' => 'safe-id',
            'amount' => 1250.50,
            'nested' => [
                'account_number' => '1234567890',
                'description' => 'Rent',
                'status' => 'posted',
            ],
        ]);

        $this->assertSame('safe-id', $redacted['request_id']);
        $this->assertSame('[REDACTED]', $redacted['amount']);
        $this->assertSame('[REDACTED]', $redacted['nested']['account_number']);
        $this->assertSame('[REDACTED]', $redacted['nested']['description']);
        $this->assertSame('posted', $redacted['nested']['status']);
    }

    public function test_monolog_processor_redacts_context_extra_and_message(): void
    {
        $processor = new RedactSensitiveContextProcessor();
        $record = $processor([
            'message' => 'Failed transaction amount=5000 account=12345678',
            'context' => ['password' => 'do-not-log'],
            'extra' => ['request_id' => 'safe-id'],
        ]);

        $this->assertSame('Failed transaction amount=[REDACTED] account=[REDACTED]', $record['message']);
        $this->assertSame('[REDACTED]', $record['context']['password']);
        $this->assertSame('safe-id', $record['extra']['request_id']);
    }
}
