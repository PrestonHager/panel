@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box
            @if($updateSummary['panel']['update_available'])
                box-danger
            @else
                box-success
            @endif
        ">
            <div class="box-header with-border">
                <h3 class="box-title">Panel Version</h3>
            </div>
            <div class="box-body">
                @if ($updateSummary['panel']['update_available'])
                    <p>
                        Your panel is <strong>not up-to-date.</strong>
                        You are running version <code>{{ $updateSummary['panel']['current_version'] }}</code>
                        @if($updateSummary['panel']['version'])
                            and version <code>{{ $updateSummary['panel']['version'] }}</code> is available from your configured upstream.
                            @if($updateSummary['panel']['url'])
                                (<a href="{{ $updateSummary['panel']['url'] }}" target="_blank" rel="noopener">view release</a>)
                            @endif
                        @endif
                    </p>
                    @if(Auth::user()->root_admin)
                        <a href="{{ route('admin.settings.updates') }}" class="btn btn-warning">
                            <i class="fa fa-cloud-upload"></i> Upgrade Panel
                        </a>
                    @endif
                @else
                    <p>
                        You are running Pterodactyl Panel version <code>{{ $updateSummary['panel']['current_version'] }}</code>.
                        Your panel matches or exceeds the latest detected upstream release.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

@if($updateSummary['plugins']['outdated_count'] > 0)
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-warning">
                <strong>{{ $updateSummary['plugins']['outdated_count'] }}</strong>
                {{ $updateSummary['plugins']['outdated_count'] === 1 ? 'plugin has' : 'plugins have' }}
                updates available.
                <a href="{{ route('admin.plugins') }}" class="btn btn-xs btn-warning" style="margin-left: 8px;">
                    Manage Plugin Updates
                </a>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDiscord() }}"><button class="btn btn-warning" style="width:100%;"><i class="fa fa-fw fa-support"></i> Get Help <small>(via Discord)</small></button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://pterodactyl.io"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-link"></i> Documentation</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://github.com/pterodactyl/panel"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-support"></i> GitHub</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDonations() }}"><button class="btn btn-success" style="width:100%;"><i class="fa fa-fw fa-money"></i> Support the Project</button></a>
    </div>
</div>
@endsection
