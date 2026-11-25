<?php

namespace Tests\Unit;

use App\Services\LegacyImport\LegacyValueMapper;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LegacyValueMapperTest extends TestCase
{
    private LegacyValueMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new LegacyValueMapper;
    }

    public function test_amounts_are_normalized_without_float_precision_loss_in_output(): void
    {
        $this->assertSame('1200.30', $this->mapper->amount('1200.3'));
        $this->assertSame('0.10', $this->mapper->amount('0.10'));
        $this->assertSame('999999999999.99', $this->mapper->amount('999999999999.99'));
    }

    public function test_invalid_or_negative_amounts_are_rejected(): void
    {
        foreach (['-1.00', '1.999', '1000000000000.00', 'not-money'] as $amount) {
            try {
                $this->mapper->amount($amount);
                $this->fail('Expected invalid amount to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_valid_dates_and_nullable_legacy_zero_dates_are_handled(): void
    {
        $this->assertSame('2026-11-25', $this->mapper->date('2026-11-25'));
        $this->assertNull($this->mapper->date('0000-00-00', nullable: true));
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->date('2026-02-30');
    }

    public function test_legacy_timestamps_are_validated_and_normalized(): void
    {
        $this->assertSame('2026-11-25 10:30:45', $this->mapper->dateTime('2026-11-25 10:30:45'));
        $this->assertNull($this->mapper->dateTime('0000-00-00 00:00:00', nullable: true));
    }

    public function test_invalid_timestamp_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->dateTime('2026-02-30 10:30:45');
    }

    public function test_month_uses_valid_legacy_month_or_derives_it_from_date(): void
    {
        $this->assertSame('2026-11', $this->mapper->month('2026-11'));
        $this->assertSame('2026-11', $this->mapper->month(null, '2026-11-25'));
    }

    public function test_invalid_month_without_fallback_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->month('2026-13');
    }

    #[DataProvider('booleanValues')]
    public function test_legacy_boolean_values_are_explicitly_converted(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->mapper->boolean($value));
    }

    public static function booleanValues(): array
    {
        return [
            [1, true], ['1', true], ['yes', true], ['true', true],
            [0, false], ['0', false], ['no', false], ['false', false],
        ];
    }

    public function test_unknown_boolean_value_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->boolean('maybe');
    }

    public function test_supported_bcrypt_hash_is_preserved_verbatim(): void
    {
        $hash = password_hash('Synthetic-Password-123', PASSWORD_BCRYPT);
        $this->assertSame($hash, $this->mapper->bcryptHash($hash));
    }

    public function test_unsupported_password_hash_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->bcryptHash('plaintext-is-not-a-hash');
    }

    public function test_legacy_debt_types_are_mapped_to_target_domain_values(): void
    {
        $this->assertSame('lent', $this->mapper->debtType('owed'));
        $this->assertSame('borrowed', $this->mapper->debtType('borrowed'));
    }

    public function test_legacy_inactive_recurring_status_is_mapped_to_paused(): void
    {
        $this->assertSame(['status' => 'paused', 'active' => false], $this->mapper->recurringStatus('inactive'));
    }

    public function test_recurring_log_keys_preserve_daily_weekly_and_monthly_periods(): void
    {
        $appliedAt = '2026-11-25 10:30:45';

        $this->assertSame('D:2026-11-25', $this->mapper->recurringPeriodKey('daily', $appliedAt, '2026-11'));
        $this->assertSame('W:2026-W48', $this->mapper->recurringPeriodKey('weekly', $appliedAt, '2026-11'));
        $this->assertSame('M:2026-11', $this->mapper->recurringPeriodKey('monthly', $appliedAt, '2026-11'));
    }

    public function test_unknown_recurring_frequency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->recurringPeriodKey('yearly', '2026-11-25 10:30:45', '2026-11');
    }
}
