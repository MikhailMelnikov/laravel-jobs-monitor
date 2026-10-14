<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Unit;

use MikhailMelnikov\JobsMonitor\Support\CronExpressionTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use MikhailMelnikov\JobsMonitor\Tests\TestCase;

/**
 * Тесты перевода cron-выражений в описание расписания на русском языке.
 */
class CronExpressionTranslatorTest extends TestCase
{
    /**
     * Выражения, которые порождают методы планировщика Laravel, читаются словами.
     *
     * @param string $expression Cron-выражение
     * @param string $expected   Ожидаемое описание
     */
    #[DataProvider('supportedExpressionsProvider')]
    public function testTranslatesExpressionsProducedByScheduler(string $expression, string $expected): void
    {
        self::assertSame($expected, (new CronExpressionTranslator($expression))->handle());
    }

    /**
     * Пары "выражение — описание" для расписаний, которые встречаются в приложении.
     *
     * @return array<string, array{string, string}>
     */
    public static function supportedExpressionsProvider(): array
    {
        return [
            'everyMinute' => ['* * * * *', 'каждую минуту'],
            'everyFiveMinutes' => ['*/5 * * * *', 'каждые 5 мин.'],
            'hourly' => ['0 * * * *', 'каждый час в :00'],
            'hourlyAt' => ['15 * * * *', 'каждый час в :15'],
            'everyThreeHours' => ['0 */3 * * *', 'каждые 3 ч. в :00'],
            'daily' => ['0 0 * * *', 'ежедневно в 00:00'],
            'dailyAt' => ['0 9 * * *', 'ежедневно в 09:00'],
            'dailyAtWithMinutes' => ['30 10 * * *', 'ежедневно в 10:30'],
            'weekly' => ['0 0 * * 1', 'каждую неделю в понедельник в 00:00'],
            'monthly' => ['0 3 1 * *', 'ежемесячно 1 числа в 03:00'],
            'yearly' => ['0 0 1 1 *', 'ежегодно 1.01 в 00:00'],
        ];
    }

    /**
     * Выражение, которое не удалось разобрать, показывается как есть — без выдумок.
     */
    public function testReturnsExpressionAsIsWhenItIsNotSupported(): void
    {
        self::assertSame('0 0 * * 1-5', (new CronExpressionTranslator('0 0 * * 1-5'))->handle());
        self::assertSame('не выражение', (new CronExpressionTranslator('не выражение'))->handle());
    }
}
