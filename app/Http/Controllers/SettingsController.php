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
            'profile'          => userById(currentUserId()),
        ]);
    }

    /**
     * Profile: name and the login password. Auth is PIN-only, so the
     * "password" is the user's 6-digit login PIN. Changing it needs the current
     * PIN, and the new one must be unique — the PIN alone identifies the user.
     */
    public function updateProfile(Request $request)
    {
        $user = userById(currentUserId());
        abort_unless($user, 403);

        $data = $request->validateWithBag('profile', [
            'name'             => ['required', 'string', 'max:100'],
            'current_password' => ['nullable', 'required_with:password', 'digits:6'],
            'password'         => ['nullable', 'digits:6', 'confirmed'],
        ], [
            'current_password.required_with' => 'Enter your current password to set a new one.',
            'current_password.digits'        => 'Current password must be your 6-digit PIN.',
            'password.digits'                => 'New password must be exactly 6 digits.',
            'password.confirmed'             => 'New password and confirmation do not match.',
        ]);

        $newPin = $data['password'] ?? null;
        if ($newPin !== null) {
            if (!password_verify($data['current_password'], $user['pin_hash'])) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.'], 'profile')->withInput();
            }
            if (pinInUse($newPin, (int) $user['id'])) {
                return back()->withErrors(['password' => 'That PIN is already in use — choose another.'], 'profile')->withInput();
            }
        }

        $sql    = "UPDATE users SET name = ?, updated_at = NOW()";
        $params = [trim($data['name'])];
        if ($newPin !== null) {
            $sql     .= ", pin_hash = ?";
            $params[] = password_hash($newPin, PASSWORD_BCRYPT);
        }
        $params[] = (int) $user['id'];
        getDB()->prepare($sql . " WHERE id = ?")->execute($params);

        session(['user_name' => trim($data['name'])]);

        return redirect('/settings#profile')->with('flash', $newPin !== null ? 'Profile and password updated.' : 'Profile updated.');
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
