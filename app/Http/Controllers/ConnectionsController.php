<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;

/**
 * CRUD for Google Ads Manager (MCC) account connections. Each connection holds
 * its own API credentials; apps reference a connection.
 */
class ConnectionsController extends Controller
{
    public function index()
    {
        return view('connections.index', [
            'connections' => Connection::withCount('apps')->orderBy('name')->get(),
            'editing'     => null,
            'activePage'  => 'connections',
            'pageTitle'   => 'Ad Accounts',
        ]);
    }

    public function edit(Connection $connection)
    {
        return view('connections.index', [
            'connections' => Connection::withCount('apps')->orderBy('name')->get(),
            'editing'     => $connection,
            'activePage'  => 'connections',
            'pageTitle'   => 'Edit Ad Account',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = true;
        Connection::create($data);

        return redirect('/connections')->with('flash', 'Ad account added.');
    }

    public function update(Request $request, Connection $connection)
    {
        $data = $this->validated($request);

        // Keep an existing secret/refresh token if the field was left blank.
        foreach (['client_secret', 'refresh_token'] as $secret) {
            if (($data[$secret] ?? '') === '') {
                unset($data[$secret]);
            }
        }

        $connection->update($data);

        return redirect('/connections')->with('flash', 'Ad account updated.');
    }

    public function destroy(Connection $connection)
    {
        $connection->delete();

        return redirect('/connections')->with('flash', 'Ad account removed.');
    }

    public function test(Connection $connection, \App\Services\GoogleAdsService $ads)
    {
        $r = $ads->testConnection($connection);

        return redirect('/connections')->with(
            $r['ok'] ? 'flash' : 'flash_error',
            "{$connection->name}: " . $r['message']
        );
    }

    /** Sync just this one ad account (its accessible client accounts) for the selected range. */
    public function sync(Request $request, Connection $connection, \App\Services\GoogleAdsService $ads)
    {
        [$start, $end] = $this->syncDateRange($request);

        $r = $ads->syncOne($connection, $start, $end);

        if (!empty($r['campaigns']) || !empty($r['daily'])) {
            return redirect('/connections')->with('flash', sprintf(
                'Data synced successfully for “%s” — %d campaign(s) + %d daily row(s) for %s → %s.',
                $connection->name, $r['campaigns'], $r['daily'], $start, $end
            ));
        }

        return redirect('/connections')->with('flash',
            "Sync finished for “{$connection->name}” — no matching app data found for {$start} → {$end}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:150'],
            'developer_token'    => ['nullable', 'string', 'max:191'],
            'client_id'          => ['nullable', 'string', 'max:191'],
            'client_secret'      => ['nullable', 'string', 'max:191'],
            'refresh_token'      => ['nullable', 'string', 'max:2000'],
            'login_customer_id'  => ['nullable', 'string', 'max:20'],
        ]);

        // Strip stray whitespace/newlines that sneak in when pasting credentials.
        foreach (['developer_token', 'client_id', 'client_secret', 'refresh_token'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = trim((string) $data[$k]);
            }
        }

        $data['login_customer_id'] = preg_replace('/\D/', '', (string) $data['login_customer_id']) ?: null;

        return $data;
    }
}
