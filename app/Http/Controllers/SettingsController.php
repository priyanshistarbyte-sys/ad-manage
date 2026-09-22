<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /** Default number of capture-days shown on the View History page. */
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
        // 0 = show the full history (no window). Otherwise a positive day count.
        // Storage is never touched by this — every snapshot row is always kept;
        // this only bounds how far back the History page queries/displays.
        $data = $request->validate([
            'history_visible_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        setSetting('history_visible_days', (string) $data['history_visible_days']);

        return redirect('/settings')->with('flash', 'Settings saved.');
    }
}
