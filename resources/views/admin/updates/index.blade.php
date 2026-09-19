@extends('layouts.admin')

@section('title')
    Update Center
@endsection

@section('content-header')
    <h1>Update Center <small>Keep Nodexa up to date from GitHub.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Update Center</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-cloud-download"></i> Nodexa Updates</h3></div>
            <div class="box-body">
                <table class="table">
                    <tr><td><strong>Installed version</strong></td><td><code>{{ $installed }}</code></td></tr>
                    <tr><td><strong>GitHub version</strong></td><td>@if($latest)<code>{{ $latest }}</code>@else <span class="text-red">Unavailable</span>@endif</td></tr>
                    <tr><td><strong>Channel</strong></td><td><code>main</code></td></tr>
                    <tr><td><strong>Status</strong></td><td>
                        @if($running)
                            <span class="label label-warning">Updating</span>
                        @elseif($latest && $latest !== $installed)
                            <span class="label label-info">Update available</span>
                        @elseif($latest)
                            <span class="label label-success">Up to date</span>
                        @else
                            <span class="label label-danger">Could not check</span>
                        @endif
                    </td></tr>
                </table>
                @if($error)<div class="alert alert-warning" style="margin-top:15px;">{{ $error }}</div>@endif
            </div>
            <div class="box-footer">
                <form method="POST" action="{{ route('admin.updates.install') }}" onsubmit="return confirm('Install the latest Nodexa update from GitHub?');">
                    {!! csrf_field() !!}
                    <button class="btn btn-primary" {{ $running ? 'disabled' : '' }}>
                        <i class="fa fa-download"></i> {{ $running ? 'Update running…' : 'Install latest update' }}
                    </button>
                    <a class="btn btn-default" href="{{ route('admin.updates') }}"><i class="fa fa-refresh"></i> Check again</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-terminal"></i> Update log</h3></div>
            <div class="box-body">
                <pre style="max-height:420px;overflow:auto;white-space:pre-wrap;">{{ $log ?: 'No update has been run yet.' }}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
