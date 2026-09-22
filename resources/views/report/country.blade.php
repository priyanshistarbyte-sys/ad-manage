@extends('layouts.app')

@section('content')
@php
    $q = fn (array $extra = []) => http_build_query(array_merge(array_filter([
                'app_id' => $filters['app_id'], 'campaign_id' => $filters['campaign_id'],
                'from' => $filters['from'], 'to' => $filters['to'],
            ], fn ($v) => $v !== null && $v !== ''), $extra));
@endphp

<div style="max-width:1400px;margin:0 auto">
    <div class="dash-header" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
            <div class="dash-title" style="color:#fff"><i class="bi bi-globe2" style="color:var(--purple)"></i> Country Report</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/?' . $q()) }}" class="btn-sm-custom" style="text-decoration:none;color:var(--text-muted);align-self:center">
                <i class="bi bi-arrow-left"></i> Back to dashboard
            </a>
        </div>
    </div>

    @include('report._country')
</div>
@endsection
