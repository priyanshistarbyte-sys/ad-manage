<?php

namespace App\Services;

/**
 * Classifies a Google Ads conversion-action name into a report revenue bucket
 * (install / trial / trial_convert / repeat_count / ad_rev / convert_rev /
 * renew_rev). Defaults come from config/revenue.php and can be overridden by
 * the `conversion_action_map` setting (JSON: {bucket: [substrings]}).
 */
class ConversionActionMapper
{
    /** @var array<string,array{type:string,match:string[]}> */
    private array $buckets;
    private string $fallback;

    public function __construct()
    {
        $cfg = config('revenue');
        $this->buckets  = $cfg['buckets'];
        $this->fallback = $cfg['fallback_value_bucket'] ?? 'convert_rev';

        // Merge user overrides (substring lists only; type stays from config).
        $override = json_decode((string) getSetting('conversion_action_map'), true);
        if (is_array($override)) {
            foreach ($override as $bucket => $matches) {
                if (isset($this->buckets[$bucket]) && is_array($matches)) {
                    $this->buckets[$bucket]['match'] = array_values(array_filter(array_map('strval', $matches)));
                }
            }
        }
    }

    /** All bucket keys (stable order). */
    public function bucketKeys(): array
    {
        return array_keys($this->buckets);
    }

    /**
     * Bucket a conversion action's metrics into a partial row of bucket => amount.
     * Returns e.g. ['install' => 12] or ['renew_rev' => 45.0].
     *
     * @return array<string,float>
     */
    public function apply(string $actionName, float $count, float $value): array
    {
        $bucket = $this->classify($actionName);

        if ($bucket === null) {
            // Unmatched: value → fallback bucket, count → install (best effort).
            $out = [];
            if ($value != 0.0) $out[$this->fallback] = $value;
            if ($count != 0.0) $out['install'] = ($out['install'] ?? 0) + $count;
            return $out;
        }

        $amount = $this->buckets[$bucket]['type'] === 'value' ? $value : $count;
        return [$bucket => $amount];
    }

    /** First bucket whose any substring appears in the action name, or null. */
    public function classify(string $actionName): ?string
    {
        $name = mb_strtolower(trim($actionName));
        if ($name === '') return null;

        foreach ($this->buckets as $bucket => $def) {
            foreach ($def['match'] as $needle) {
                if ($needle !== '' && str_contains($name, mb_strtolower($needle))) {
                    return $bucket;
                }
            }
        }
        return null;
    }
}
