<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve a [start, end] sync window (Y-m-d) from the request's from/to
     * fields, falling back to the current month. Swaps if reversed.
     *
     * @return array{0:string,1:string}
     */
    protected function syncDateRange(Request $request): array
    {
        $parse = function ($v) {
            try {
                return $v ? Carbon::parse($v)->toDateString() : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        $start = $parse($request->input('from'));
        $end   = $parse($request->input('to'));

        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end   = $end   ?: Carbon::now()->toDateString();

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }
}
