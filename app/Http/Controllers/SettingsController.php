<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Default number of days used both for the View History page window AND the
     * daily auto-sync trailing window (ending yesterday).
     */
    public const HISTORY_DAYS_DEFAULT = 90;

    public function index()
    {
        return view('settings.index', [
            'activePage'       => 'settings',
            'pageTitle'        => 'Settings',
            'historyDays'      => (int) getSetting('history_visible_days', (string) self::HISTORY_DAYS_DEFAULT),
        ]);
    }

    public function update(Request $request)
    {
        // history_visible_days: 0 = show the full history on the History page (the
        // cron then falls back to the default window); otherwise a day count that
        // bounds both the History view and the daily sync window. Storage is never
        // touched — every snapshot row is always kept.
        $data = $request->validate([
            'history_visible_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        setSetting('history_visible_days', (string) $data['history_visible_days']);

        return redirect('/settings')->with('flash', 'Settings saved.');
    }
}
