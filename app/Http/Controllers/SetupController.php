<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs database migrations.
 *
 * Access rules:
 *  - Fresh install (no users yet / tables missing): open, so the very first
 *    migration can run before anyone can log in. Reset is never allowed here.
 *  - Once a user exists: only a signed-in admin may run it.
 *  - ?reset=1 (migrate:fresh — DROPS ALL TABLES) additionally requires a POST
 *    with confirm=RESET from a signed-in admin.
 */
class SetupController extends Controller
{
    public function run(Request $request)
    {
        $installed = hasUsers();
        $isAdmin   = $installed && isAuthenticated() && isAdmin();

        if ($installed && !$isAdmin) {
            abort(403, 'Setup is restricted to a signed-in admin.');
        }

        $wantsReset = $request->query('reset') === '1';
        $reset      = $wantsReset && $isAdmin && $request->isMethod('post')
                      && $request->input('confirm') === 'RESET';

        // Show a confirmation form instead of resetting on a plain GET.
        if ($wantsReset && !$reset) {
            if (!$isAdmin) {
                abort(403, 'Reset is restricted to a signed-in admin.');
            }
            return response($this->page(
                "<p style='color:#b00'>⚠️ This <strong>DROPS ALL TABLES</strong> and deletes all data.</p>"
                . "<form method='post' action='" . e(url('/setup?reset=1')) . "'>"
                . "<input type='hidden' name='_token' value='" . e(csrf_token()) . "'>"
                . "<p>Type <code>RESET</code> to confirm: <input name='confirm' autocomplete='off'></p>"
                . "<button type='submit'>Reset database</button> "
                . "<a href='" . e(url('/')) . "'>Cancel</a></form>"
            ));
        }

        $output = '';
        try {
            if ($reset) {
                Artisan::call('migrate:fresh', ['--force' => true]);
                $output .= "<h2 style='color:green'>✅ Reset complete — all tables rebuilt.</h2>";
            } else {
                Artisan::call('migrate', ['--force' => true]);
            }
            $output .= '<pre style="font-family:monospace;background:#111;color:#8f8;padding:12px;border-radius:6px;white-space:pre-wrap">'
                     . e(Artisan::output()) . '</pre>';

            // Make sure country names exist before the first sync.
            if (\App\Models\Country::query()->count() === 0) {
                Artisan::call('db:seed', ['--class' => \Database\Seeders\CountrySeeder::class, '--force' => true]);
                $output .= '<p>Seeded country list.</p>';
            }

            $output .= "<p><a href='" . e(url('/')) . "'>Go to Dashboard →</a></p>";
        } catch (\Throwable $e) {
            $output = "<h2 style='color:red'>❌ Error</h2>"
                    . "<p style='color:red'>" . e($e->getMessage()) . "</p>"
                    . "<p>Check DB credentials in <code>.env</code>.</p>";
        }

        return response($this->page($output));
    }

    private function page(string $body): string
    {
        return "<div style='padding:20px;font-family:sans-serif'><h2 style='color:#333'>📊 ad-manage Database Setup</h2>"
            . "<p><strong>Database:</strong> " . e((string) config('database.connections.' . config('database.default') . '.database')) . "</p>"
            . $body . "</div>";
    }
}
