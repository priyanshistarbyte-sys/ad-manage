<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Connection;
use Illuminate\Http\Request;

class AppsController extends Controller
{
    public function index()
    {
        return view('apps.index', [
            'apps'        => App::with('connection')->orderBy('name')->get(),
            'connections' => Connection::where('active', true)->orderBy('name')->get(),
            'editing'     => null,
            'activePage'  => 'apps',
            'pageTitle'   => 'Apps',
        ]);
    }

    public function edit(App $app)
    {
        return view('apps.index', [
            'apps'        => App::with('connection')->orderBy('name')->get(),
            'connections' => Connection::where('active', true)->orderBy('name')->get(),
            'editing'     => $app,
            'activePage'  => 'apps',
            'pageTitle'   => 'Edit App',
        ]);
    }

    public function store(Request $request)
    {
        App::create($this->validated($request) + ['active' => true]);

        return redirect('/apps')->with('flash', 'App added.');
    }

    public function update(Request $request, App $app)
    {
        $app->update($this->validated($request));

        return redirect('/apps')->with('flash', 'App updated.');
    }

    public function destroy(App $app)
    {
        $app->delete();

        return redirect('/apps')->with('flash', 'App removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:150'],
            'package_id'             => ['required', 'string', 'max:191'],
            'connection_id'          => ['nullable', 'exists:connections,id'],
            'google_ads_customer_id' => ['nullable', 'string', 'max:20'],
            'google_ads_campaign_id' => ['nullable', 'string', 'max:30'],
        ]);

        // Store the Google Ads IDs as digits only (strip dashes/spaces).
        $data['google_ads_customer_id'] = preg_replace('/\D/', '', (string) $data['google_ads_customer_id']) ?: null;
        $data['google_ads_campaign_id'] = preg_replace('/\D/', '', (string) $data['google_ads_campaign_id']) ?: null;
        $data['connection_id'] = $data['connection_id'] ?: null;

        return $data;
    }
}
