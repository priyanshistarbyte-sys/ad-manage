<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Connection;
use App\Models\DailyStat;
use Illuminate\Http\Request;

class AppsController extends Controller
{
    public function index()
    {
        return $this->render(null, 'Apps');
    }

    public function edit(App $app)
    {
        return $this->render($app, 'Edit App');
    }

    private function render(?App $editing, string $pageTitle)
    {
        return view('apps.index', [
            'apps'          => App::orderBy('name')->get(),
            'connections'   => Connection::where('active', true)->orderBy('name')->get(),
            'syncedAppIds'  => DailyStat::whereNotNull('app_id')->distinct()->pluck('app_id')->all(),
            'editing'       => $editing,
            'activePage'    => 'apps',
            'pageTitle'     => $pageTitle,
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
        // An app is just a name + its store App ID (Play package). Sync matches
        // campaigns to the app by this App ID (campaign.app_campaign_setting.app_id),
        // so no ad-account / customer-id / campaign-id is entered here.
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'package_id' => ['required', 'string', 'max:191'],
        ]);

        $data['package_id'] = trim($data['package_id']);

        return $data;
    }
}
