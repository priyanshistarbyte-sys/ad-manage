<?php

/**
 * Maps Google Ads conversion-action NAMES to the report's revenue buckets.
 *
 * Each bucket lists case-insensitive substrings; a conversion action is matched
 * to the FIRST bucket (in the order below) whose any substring appears in its
 * name. Order matters — more specific buckets come first so e.g. a
 * "Trial converted" action lands in trial_convert, not trial, and "Renewal"
 * lands in renew_rev, not convert_rev.
 *
 * `count` buckets take metrics.conversions; `value` buckets take
 * metrics.conversions_value. Overridable at runtime via the
 * `conversion_action_map` setting (JSON of bucket => [substrings]).
 */
return [
    // bucket => ['type' => count|value, 'match' => [substrings...]]
    'buckets' => [
        'trial_convert' => ['type' => 'count', 'match' => ['trial convert', 'trial_convert', 'trial to paid', 'trial converted']],
        'trial'         => ['type' => 'count', 'match' => ['trial', 'free trial', 'start_trial', 'begin_trial']],
        'repeat_count'  => ['type' => 'count', 'match' => ['repeat', 'reorder', 'repeat purchase', 'returning']],
        'install'       => ['type' => 'count', 'match' => ['install', 'first_open', 'app install', 'download']],
        'renew_rev'     => ['type' => 'value', 'match' => ['renew', 'renewal', 'resubscribe']],
        'ad_rev'        => ['type' => 'value', 'match' => ['admob', 'ad revenue', 'ad_revenue', 'adrev', 'ads revenue']],
        'convert_rev'   => ['type' => 'value', 'match' => ['subscribe', 'subscription', 'purchase', 'first purchase', 'convert', 'in_app', 'buy']],
    ],

    // If a conversion action matches nothing above, fold its VALUE into this
    // bucket and its COUNT into install (so no data is silently dropped).
    'fallback_value_bucket' => 'convert_rev',
];
