<?php
/**
 * Aggregate-only load qualification result calculations.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if (!defined('ABSPATH') && 'cli' !== PHP_SAPI) {
    exit;
}

final class IDLM_Performance_Qualification_Result {
    /**
     * Summarize request samples without retaining response bodies or identities.
     *
     * @param array<int,array<string,mixed>> $samples Request measurements.
     * @param array<string,mixed>            $thresholds Provisional thresholds.
     * @return array<string,mixed>
     */
    public static function summarize(array $samples, array $thresholds = array()): array {
        $durations = array();
        $bytes = array();
        $outcomes = array(
            'success' => 0,
            'transport_error' => 0,
            'http_error' => 0,
            'application_error' => 0,
            'invalid_response' => 0,
        );

        foreach ($samples as $sample) {
            $durations[] = max(0.0, (float) ($sample['duration_ms'] ?? 0));
            $bytes[] = max(0, (int) ($sample['response_bytes'] ?? 0));
            $outcome = isset($sample['outcome']) ? (string) $sample['outcome'] : 'invalid_response';
            if (!array_key_exists($outcome, $outcomes)) {
                $outcome = 'invalid_response';
            }
            $outcomes[$outcome]++;
        }

        sort($durations, SORT_NUMERIC);
        sort($bytes, SORT_NUMERIC);
        $request_count = count($samples);
        $error_count = $request_count - $outcomes['success'];
        $error_rate = $request_count > 0 ? ($error_count / $request_count) * 100 : 100.0;

        $summary = array(
            'request_count' => $request_count,
            'success_count' => $outcomes['success'],
            'error_count' => $error_count,
            'error_rate_percent' => round($error_rate, 4),
            'outcomes' => $outcomes,
            'latency_ms' => array(
                'min' => self::minimum($durations),
                'mean' => self::mean($durations),
                'p50' => self::percentile($durations, 50),
                'p95' => self::percentile($durations, 95),
                'p99' => self::percentile($durations, 99),
                'max' => self::maximum($durations),
            ),
            'response_bytes' => array(
                'mean' => self::mean($bytes),
                'max' => (int) self::maximum($bytes),
            ),
        );
        $summary['provisional_thresholds'] = self::evaluate_thresholds($summary, $thresholds);

        return $summary;
    }

    /** @param array<int,float|int> $values */
    private static function percentile(array $values, int $percentile): float {
        $count = count($values);
        if (0 === $count) {
            return 0.0;
        }
        $index = max(0, (int) ceil(($percentile / 100) * $count) - 1);
        return round((float) $values[$index], 3);
    }

    /** @param array<int,float|int> $values */
    private static function mean(array $values): float {
        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 3);
    }

    /** @param array<int,float|int> $values */
    private static function minimum(array $values): float {
        return empty($values) ? 0.0 : round((float) min($values), 3);
    }

    /** @param array<int,float|int> $values */
    private static function maximum(array $values): float {
        return empty($values) ? 0.0 : round((float) max($values), 3);
    }

    /**
     * @param array<string,mixed> $summary Aggregate summary.
     * @param array<string,mixed> $thresholds Provisional thresholds.
     * @return array<string,mixed>
     */
    private static function evaluate_thresholds(array $summary, array $thresholds): array {
        $checks = array();
        foreach (array('p95_ms' => 'p95', 'p99_ms' => 'p99') as $threshold_key => $metric_key) {
            if (isset($thresholds[$threshold_key]) && null !== $thresholds[$threshold_key]) {
                $limit = (float) $thresholds[$threshold_key];
                $actual = (float) $summary['latency_ms'][$metric_key];
                $checks[$threshold_key] = array(
                    'limit' => $limit,
                    'actual' => $actual,
                    'within' => $actual < $limit,
                );
            }
        }

        $error_limit = isset($thresholds['error_rate_percent'])
            ? (float) $thresholds['error_rate_percent']
            : 1.0;
        $checks['error_rate_percent'] = array(
            'limit' => $error_limit,
            'actual' => (float) $summary['error_rate_percent'],
            'within' => (float) $summary['error_rate_percent'] < $error_limit,
        );

        $within = !empty($checks);
        foreach ($checks as $check) {
            if (!$check['within']) {
                $within = false;
                break;
            }
        }

        return array(
            'signed' => false,
            'within' => $within,
            'checks' => $checks,
            'qualification_status' => 'evidence_only',
        );
    }
}
