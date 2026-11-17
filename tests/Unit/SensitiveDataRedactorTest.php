<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveContextProcessor;
use App\Support\Observability\SensitiveDataRedactor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
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
        $record = new LogRecord(
            new DateTimeImmutable,
            'test',
            Level::Info,
            'Failed transaction amount=5000 account=12345678',
            ['password' => 'do-not-log'],
            ['request_id' => 'safe-id'],
        );

        $redacted = $processor($record);

        $this->assertSame('Failed transaction amount=[REDACTED] account=[REDACTED]', $redacted->message);
        $this->assertSame('[REDACTED]', $redacted->context['password']);
        $this->assertSame('safe-id', $redacted->extra['request_id']);
    }
}
