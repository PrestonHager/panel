@extends('layouts.admin')

@section('title')
    Server  {{ $server->name }}: {{ $pluginName }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ $pluginName }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ $pluginName }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $pluginName }}</h3>
            </div>
            <div class="box-body">
                <div id="plugin-root-{{ $pluginId }}"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <link rel="stylesheet" href="{{ asset('plugins/plugin-host.css') }}">
    <script>
        window.__PterodactylPluginContext = {
            pluginId: @json($pluginId),
            serverUuid: @json($server->uuid),
            apiBase: @json('/api/plugins/' . $pluginId),
            csrfToken: @json(csrf_token()),
            theme: 'light',
            getPermissions: function () {
                return ['*'];
            },
            hasFullAccess: function () {
                return true;
            },
        };
    </script>
    <script src="/plugins-assets/{{ $pluginId }}/{{ ltrim($bundle, '/') }}"></script>
    <script>
        (function () {
            var key = 'PterodactylPlugin_{{ str_replace('.', '_', $pluginId) }}';
            var mount = window[key];
            if (typeof mount === 'function') {
                mount();
            }
        })();
    </script>
@endsection
