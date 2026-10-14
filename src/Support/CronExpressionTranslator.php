<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Translates a scheduler cron expression into human-readable text.
 *
 * The scheduler keeps a schedule only as a cron string ("0 9 * * *"), which is
 * hard to read in an admin panel. The class covers the expressions produced by
 * Laravel frequency methods (everyMinute, hourly, dailyAt, …); anything else is
 * returned as is.
 */
final class CronExpressionTranslator
{
    /**
     * @param string $expression Five-field cron expression: minute, hour, day of month, month, day of week
     */
    public function __construct(private readonly string $expression)
    {
    }

    /**
     * Get the human-readable schedule in the current locale.
     */
    public function handle(): string
    {
        $parts = preg_split('/\s+/', trim($this->expression)) ?: [];

        if (count($parts) !== 5) {
            return $this->expression;
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        $time = $this->translateTime($minute, $hour);

        if ($time === null) {
            return $this->expression;
        }

        $period = $this->translatePeriod($dayOfMonth, $month, $dayOfWeek);

        if ($period === null) {
            return $this->expression;
        }

        [$timeText, $isClockTime] = $time;

        if ($period !== '') {
            return $period . ' ' . $timeText;
        }

        // "0 9 * * *" gives a clock time without a period — that is a daily run
        return $isClockTime ? $this->trans('daily') . ' ' . $timeText : $timeText;
    }

    /**
     * Describe the time-of-day part of the expression.
     *
     * @param string $minute Minute field
     * @param string $hour   Hour field
     *
     * @return array{string, bool}|null Text and whether it is a fixed clock time;
     *                                  null when the expression is not supported
     */
    private function translateTime(string $minute, string $hour): ?array
    {
        if ($minute === '*' && $hour === '*') {
            return [$this->trans('every_minute'), false];
        }

        if ($hour === '*') {
            if (preg_match('~^\*/(\d+)$~', $minute, $matches) === 1) {
                return [$this->trans('every_n_minutes', ['n' => $matches[1]]), false];
            }

            if (ctype_digit($minute)) {
                return [$this->trans('hourly_at', ['minute' => $this->pad($minute)]), false];
            }

            if (preg_match('~^\d+(,\d+)+$~', $minute) === 1) {
                return [$this->trans('hourly_at_minutes', ['minutes' => str_replace(',', ', ', $minute)]), false];
            }

            return null;
        }

        if (preg_match('~^\*/(\d+)$~', $hour, $matches) === 1 && ctype_digit($minute)) {
            return [$this->trans('every_n_hours_at', ['n' => $matches[1], 'minute' => $this->pad($minute)]), false];
        }

        if (ctype_digit($hour) && ctype_digit($minute)) {
            return [$this->trans('at', ['time' => $this->pad($hour) . ':' . $this->pad($minute)]), true];
        }

        return null;
    }

    /**
     * Describe the day-based period of the expression.
     *
     * @param string $dayOfMonth Day of month field
     * @param string $month      Month field
     * @param string $dayOfWeek  Day of week field
     *
     * @return string|null Empty string for "every day"; null when the expression is not supported
     */
    private function translatePeriod(string $dayOfMonth, string $month, string $dayOfWeek): ?string
    {
        if ($dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            return '';
        }

        if ($dayOfMonth === '*' && $month === '*' && ctype_digit($dayOfWeek)) {
            /** @var array<int, string> $weekdays */
            $weekdays = Lang::get('jobs-monitor::cron.weekdays');

            return $this->trans('weekly', ['day' => $weekdays[(int) $dayOfWeek] ?? $dayOfWeek]);
        }

        if ($dayOfWeek === '*' && $month === '*' && ctype_digit($dayOfMonth)) {
            return $this->trans('monthly', ['day' => (int) $dayOfMonth]);
        }

        if ($dayOfWeek === '*' && ctype_digit($month) && ctype_digit($dayOfMonth)) {
            return $this->trans('yearly', ['day' => (int) $dayOfMonth, 'month' => $this->pad($month)]);
        }

        if ($dayOfWeek === '*' && $month === '*' && preg_match('~^\*/(\d+)$~', $dayOfMonth, $matches) === 1) {
            return $this->trans('every_n_days', ['n' => $matches[1]]);
        }

        return null;
    }

    /**
     * Translate a cron phrase.
     *
     * @param string                    $key     Key in the cron translation file
     * @param array<string, int|string> $replace Placeholder values
     */
    private function trans(string $key, array $replace = []): string
    {
        return (string) Lang::get('jobs-monitor::cron.' . $key, $replace);
    }

    /**
     * Left-pad a number with zero to two digits.
     *
     * @param string $value Numeric cron field
     */
    private function pad(string $value): string
    {
        return str_pad($value, 2, '0', STR_PAD_LEFT);
    }
}
