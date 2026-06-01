@extends('layouts.admin')

@section('title')
    Plugins
@endsection

@section('content-header')
    <h1>Plugins<small>Install and manage panel extensions from GitHub.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Plugins</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Installed Plugins</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.plugins.install') }}" class="btn btn-sm btn-primary">Install Plugin</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    @if($plugins->isEmpty())
                        <p class="text-muted" style="padding: 15px;">@lang('admin/plugins.no_plugins')</p>
                    @else
                        <table class="table table-hover">
                            <tr>
                                <th>Name</th>
                                <th>ID</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Permissions</th>
                                <th></th>
                            </tr>
                            @foreach($plugins as $plugin)
                                <tr>
                                    <td><a href="{{ route('admin.plugins.view', $plugin) }}">{{ $plugin->name }}</a></td>
                                    <td><code>{{ $plugin->id }}</code></td>
                                    <td>{{ $plugin->version }}</td>
                                    <td>
                                        @if($plugin->enabled)
                                            <span class="label label-success">@lang('admin/plugins.enabled')</span>
                                        @else
                                            <span class="label label-default">@lang('admin/plugins.disabled')</span>
                                        @endif
                                    </td>
                                    <td>{{ count($plugin->permissions ?? []) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.plugins.view', $plugin) }}" class="btn btn-xs btn-default">Manage</a>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
