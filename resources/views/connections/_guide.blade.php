{{-- Step-by-step guide for generating Google Ads API credentials (popup). --}}
@php
    $guideSteps = [
        1 => 'Overview',
        2 => 'Login Customer ID',
        3 => 'Developer Token',
        4 => 'Cloud project',
        5 => 'Consent screen',
        6 => 'Client ID & Secret',
        7 => 'Refresh Token',
        8 => 'Save & Test',
    ];
    $ext = fn ($url, $label) => '<a href="'.e($url).'" target="_blank" rel="noopener">'.e($label).' <i class="bi bi-box-arrow-up-right"></i></a>';
@endphp

<dialog id="credGuide" class="guide-dialog" aria-labelledby="credGuideTitle">
    <div class="guide-head">
        <div>
            <div id="credGuideTitle" class="guide-title"><i class="bi bi-journal-text"></i> Get your Google Ads API credentials</div>
            <div class="guide-sub">Step <span data-guide-current>1</span> of {{ count($guideSteps) }} · <span data-guide-name>Overview</span></div>
        </div>
        <button type="button" class="guide-close" data-guide-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="guide-steps" aria-label="Guide steps">
        @foreach ($guideSteps as $n => $name)
            <button type="button" class="guide-step-pill" data-guide-goto="{{ $n }}" title="{{ $name }}">
                <span class="num">{{ $n }}</span><span class="lbl">{{ $name }}</span>
            </button>
        @endforeach
    </nav>

    <div class="guide-body">

        {{-- 1 --}}
        <section data-guide-panel="1">
            <h6>What you'll need</h6>
            <p>ad-manage reads your campaigns through the official Google Ads API. That needs <strong>five values</strong>, which you create once:</p>
            <table class="guide-table">
                <tr><td>Login Customer ID</td><td>Your Manager (MCC) account number</td><td>Google Ads</td></tr>
                <tr><td>Developer Token</td><td>Lets your MCC use the API</td><td>Google Ads → API Center</td></tr>
                <tr><td>OAuth Client ID</td><td>Identifies "your app" to Google</td><td>Google Cloud Console</td></tr>
                <tr><td>OAuth Client Secret</td><td>Password for that app (<code>GOCSPX-…</code>)</td><td>Google Cloud Console</td></tr>
                <tr><td>Refresh Token</td><td>Lets the app sign in as you (<code>1//…</code>)</td><td>OAuth 2.0 Playground</td></tr>
            </table>
            <h6>Before you start</h6>
            <ul>
                <li>A <strong>Google Ads Manager (MCC) account</strong> that manages the ad accounts running your app campaigns.
                    Don't have one? Create it free at {!! $ext('https://ads.google.com/home/tools/manager-accounts/', 'Manager accounts') !!} and link your ad accounts to it.</li>
                <li>A <strong>Google login</strong> with access to that MCC (you'll use it in step 7).</li>
                <li>About <strong>10 minutes</strong>. Keep this popup open and work through the steps in order.</li>
            </ul>
        </section>

        {{-- 2 --}}
        <section data-guide-panel="2" hidden>
            <h6>Find your Login Customer ID</h6>
            <ol>
                <li>Open {!! $ext('https://ads.google.com', 'ads.google.com') !!} and sign in.</li>
                <li>Use the account switcher (top right) to pick your <strong>Manager account</strong> — not one of the client ad accounts.</li>
                <li>The 10-digit number shown next to the account name (e.g. <code>123-456-7890</code>) is the Login Customer ID.</li>
                <li>Paste it into <strong>Login Customer ID</strong>. Dashes are optional — they're stripped automatically.</li>
            </ol>
            <div class="guide-tip"><i class="bi bi-info-circle"></i> ad-manage will find every client account under this manager automatically — you don't enter client IDs anywhere.</div>
        </section>

        {{-- 3 --}}
        <section data-guide-panel="3" hidden>
            <h6>Get a Developer Token</h6>
            <ol>
                <li>While signed in to the <strong>Manager account</strong>, open <em>Admin → API Center</em>
                    ({!! $ext('https://ads.google.com/aw/apicenter', 'direct link') !!}).
                    <br><small>API Center only appears in Manager accounts.</small></li>
                <li>First time: fill in the API access form (company name, website, contact email), accept the Terms and click <em>Submit</em>.</li>
                <li>Copy the <strong>Developer token</strong> shown on the page and paste it into <strong>Developer Token</strong>.</li>
                <li>Check the <strong>Access level</strong> next to it:
                    <ul>
                        <li><em>Test account</em> — works only on test accounts. Click <strong>Apply for Basic Access</strong> and describe your use
                            (e.g. "internal reporting dashboard for our own app campaigns"). Google usually replies within a few business days.</li>
                        <li><em>Basic</em> or <em>Standard</em> — ready to use with your real accounts.</li>
                    </ul>
                </li>
            </ol>
            <div class="guide-warn"><i class="bi bi-exclamation-triangle"></i> Until Basic Access is approved, sync fails with <code>DEVELOPER_TOKEN_NOT_APPROVED</code>. You can still finish the other steps meanwhile.</div>
        </section>

        {{-- 4 --}}
        <section data-guide-panel="4" hidden>
            <h6>Create a Google Cloud project &amp; enable the API</h6>
            <ol>
                <li>Open {!! $ext('https://console.cloud.google.com', 'Google Cloud Console') !!} (any Google login works; using the same one as Google Ads is simplest).</li>
                <li>Click the project picker at the top → <em>New project</em> → name it e.g. <code>ad-manage</code> → <em>Create</em>. Make sure it's selected.</li>
                <li>Go to <em>APIs &amp; Services → Library</em>
                    ({!! $ext('https://console.cloud.google.com/apis/library/googleads.googleapis.com', 'direct link') !!}).</li>
                <li>Search <strong>Google Ads API</strong>, open it and click <strong>Enable</strong>.</li>
            </ol>
            <div class="guide-tip"><i class="bi bi-info-circle"></i> No billing account is needed — the Google Ads API is free.</div>
        </section>

        {{-- 5 --}}
        <section data-guide-panel="5" hidden>
            <h6>Configure the OAuth consent screen</h6>
            <ol>
                <li>Go to <em>APIs &amp; Services → OAuth consent screen</em> (newer consoles call it <em>Google Auth Platform → Branding</em>)
                    ({!! $ext('https://console.cloud.google.com/apis/credentials/consent', 'direct link') !!}).</li>
                <li>Click <em>Get started</em> / <em>Configure</em>. Enter an <strong>App name</strong> (e.g. <code>ad-manage</code>) and your <strong>support email</strong>.</li>
                <li>Audience / User type: choose <strong>External</strong>. Add your email as the developer contact and finish the wizard.</li>
                <li>Open the <em>Audience</em> (or consent screen summary) page and click <strong>Publish app</strong> so the <em>Publishing status</em> becomes <strong>In production</strong>.</li>
            </ol>
            <div class="guide-warn"><i class="bi bi-exclamation-triangle"></i> Don't skip the publish step. In <em>Testing</em> status Google expires refresh tokens after <strong>7 days</strong>
                and sync starts failing with <code>invalid_grant</code>. You do <em>not</em> need Google's verification review for your own use.</div>
        </section>

        {{-- 6 --}}
        <section data-guide-panel="6" hidden>
            <h6>Create the OAuth Client ID &amp; Secret</h6>
            <ol>
                <li>Go to <em>APIs &amp; Services → Credentials</em>
                    ({!! $ext('https://console.cloud.google.com/apis/credentials', 'direct link') !!}).</li>
                <li>Click <em>+ Create credentials → OAuth client ID</em>.</li>
                <li>Application type: <strong>Web application</strong>. Name: anything, e.g. <code>ad-manage</code>.</li>
                <li>Under <strong>Authorized redirect URIs</strong> click <em>+ Add URI</em> and paste exactly:
                    <div class="guide-copy"><code>https://developers.google.com/oauthplayground</code>
                        <button type="button" class="guide-copy-btn" data-copy="https://developers.google.com/oauthplayground"><i class="bi bi-clipboard"></i> Copy</button></div>
                </li>
                <li>Click <em>Create</em>. A popup shows your credentials:
                    <ul>
                        <li><strong>Client ID</strong> — ends in <code>.apps.googleusercontent.com</code> → paste into <strong>OAuth Client ID</strong>.</li>
                        <li><strong>Client secret</strong> — starts with <code>GOCSPX-</code> → paste into <strong>OAuth Client Secret</strong>.</li>
                    </ul>
                </li>
            </ol>
            <div class="guide-tip"><i class="bi bi-info-circle"></i> Keep this tab open — you need both values again in the next step.
                Newer consoles only show the secret once; if you lose it, add a new secret on the client's page.</div>
        </section>

        {{-- 7 --}}
        <section data-guide-panel="7" hidden>
            <h6>Generate the Refresh Token</h6>
            <ol>
                <li>Open the {!! $ext('https://developers.google.com/oauthplayground', 'OAuth 2.0 Playground') !!}.</li>
                <li>Click the <i class="bi bi-gear"></i> <strong>gear icon</strong> (top right) → tick <strong>Use your own OAuth credentials</strong>
                    → paste your <em>Client ID</em> and <em>Client secret</em> from step 6 → <em>Close</em>.</li>
                <li>In <em>Step 1 – Select &amp; authorize APIs</em>, ignore the list and type this scope into the box at the bottom:
                    <div class="guide-copy"><code>https://www.googleapis.com/auth/adwords</code>
                        <button type="button" class="guide-copy-btn" data-copy="https://www.googleapis.com/auth/adwords"><i class="bi bi-clipboard"></i> Copy</button></div>
                    then click <strong>Authorize APIs</strong>.</li>
                <li>Sign in with the Google login that <strong>has access to your Manager account</strong>.
                    If you see <em>“Google hasn't verified this app”</em>, click <em>Advanced → Go to ad-manage (unsafe)</em> — it's your own app. Click <em>Continue / Allow</em>.</li>
                <li>Back in the Playground, under <em>Step 2</em> click <strong>Exchange authorization code for tokens</strong>.</li>
                <li>Copy the <strong>Refresh token</strong> (starts with <code>1//</code>) and paste it into <strong>Refresh Token</strong>.</li>
            </ol>
            <div class="guide-warn"><i class="bi bi-exclamation-triangle"></i> Getting <code>redirect_uri_mismatch</code>? The redirect URI from step 6 is missing or has a typo (no trailing slash).</div>
        </section>

        {{-- 8 --}}
        <section data-guide-panel="8" hidden>
            <h6>Save &amp; test</h6>
            <ol>
                <li>Give the account a <strong>name</strong>, check all five fields are filled, and click <strong>Add Account</strong>.</li>
                <li>In the list below, click <strong><i class="bi bi-plug"></i> Test</strong>. You should see <em>“Signed in OK · N accessible account(s)”</em>.</li>
                <li>Add your apps on the <strong>Apps</strong> page (name + Play package), then run <strong>Sync All</strong>.</li>
            </ol>
            <h6>If the test fails</h6>
            <table class="guide-table">
                <tr><td><code>invalid_client</code></td><td>Client ID or secret is wrong — re-copy both (step 6).</td></tr>
                <tr><td><code>invalid_grant</code></td><td>Refresh token expired or revoked — publish the consent screen (step 5) and generate a new token (step 7).</td></tr>
                <tr><td><code>DEVELOPER_TOKEN_NOT_APPROVED</code></td><td>Token is still test-only — wait for Basic Access (step 3).</td></tr>
                <tr><td><code>USER_PERMISSION_DENIED</code></td><td>The login used in step 7 can't reach that account, or the Login Customer ID isn't its manager.</td></tr>
                <tr><td><code>SERVICE_DISABLED</code></td><td>Google Ads API isn't enabled in the Cloud project (step 4).</td></tr>
            </table>
            <div class="guide-tip"><i class="bi bi-shield-lock"></i> Keep the Client secret and Refresh token private — anyone with them can read your Google Ads data.
                Revoke a leaked token at {!! $ext('https://myaccount.google.com/permissions', 'myaccount.google.com/permissions') !!}.</div>
        </section>
    </div>

    <div class="guide-foot">
        <button type="button" class="guide-nav" data-guide-prev><i class="bi bi-arrow-left"></i> Back</button>
        <button type="button" class="guide-nav primary" data-guide-next>Next <i class="bi bi-arrow-right"></i></button>
    </div>
</dialog>

<script>
(function () {
    const dlg = document.getElementById('credGuide');
    if (!dlg) return;
    const panels = [...dlg.querySelectorAll('[data-guide-panel]')];
    const pills  = [...dlg.querySelectorAll('[data-guide-goto]')];
    const total  = panels.length;
    const prev   = dlg.querySelector('[data-guide-prev]');
    const next   = dlg.querySelector('[data-guide-next]');
    let step = 1;

    function show(n) {
        step = Math.min(Math.max(1, n), total);
        panels.forEach(p => p.hidden = +p.dataset.guidePanel !== step);
        pills.forEach(b => {
            const k = +b.dataset.guideGoto;
            b.classList.toggle('active', k === step);
            b.classList.toggle('done', k < step);
        });
        dlg.querySelector('[data-guide-current]').textContent = step;
        dlg.querySelector('[data-guide-name]').textContent = pills[step - 1].title;
        prev.disabled = step === 1;
        next.innerHTML = step === total ? 'Done <i class="bi bi-check-lg"></i>' : 'Next <i class="bi bi-arrow-right"></i>';
        dlg.querySelector('.guide-body').scrollTop = 0;
        // Keep the active pill visible in the (horizontally scrolling) step strip.
        const strip = dlg.querySelector('.guide-steps'), pill = pills[step - 1];
        strip.scrollLeft = pill.offsetLeft - (strip.clientWidth - pill.offsetWidth) / 2;
    }

    document.querySelectorAll('[data-guide-step]').forEach(btn =>
        btn.addEventListener('click', () => { show(+btn.dataset.guideStep); dlg.showModal(); }));
    pills.forEach(b => b.addEventListener('click', () => show(+b.dataset.guideGoto)));
    prev.addEventListener('click', () => show(step - 1));
    next.addEventListener('click', () => step === total ? dlg.close() : show(step + 1));
    dlg.querySelector('[data-guide-close]').addEventListener('click', () => dlg.close());
    // Click on the dimmed backdrop closes the popup.
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    dlg.addEventListener('keydown', e => {
        if (e.key === 'ArrowRight' && e.target.tagName !== 'INPUT') show(step + 1);
        if (e.key === 'ArrowLeft'  && e.target.tagName !== 'INPUT') show(step - 1);
    });

    dlg.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', () => {
        navigator.clipboard?.writeText(btn.dataset.copy).then(() => {
            const old = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied';
            setTimeout(() => btn.innerHTML = old, 1500);
        });
    }));

    show(1);
    @if (!$editing && $connections->isEmpty())
    // First visit with no accounts yet: open the guide automatically once.
    if (!localStorage.getItem('credGuideSeen')) {
        localStorage.setItem('credGuideSeen', '1');
        dlg.showModal();
    }
    @endif
})();
</script>
