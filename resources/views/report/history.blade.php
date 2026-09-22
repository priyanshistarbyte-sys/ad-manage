@extends('layouts.app')

@section('content')
<div style="max-width:1100px;margin:0 auto">
    <div class="dash-header" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
            <div class="dash-title" style="color:#fff"><i class="bi bi-clock-history" style="color:var(--purple)"></i> View History</div>
        </div>
        <a href="{{ url()->previous() }}" class="btn-sm-custom" style="text-decoration:none;color:var(--text-muted);align-self:center">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @include('report._history')
</div>
@endsection
