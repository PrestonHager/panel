@extends('layouts.admin')

@php($pluginRootId = 'plugin-root-' . str_replace('.', '-', $pluginId))

@section('title')
    {{ $plugin->name }} Designer
@endsection

@section('content-header')
    <h1>{{ $plugin->name }}<small>Designer</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.plugins') }}">Plugins</a></li>
        <li><a href="{{ route('admin.plugins.view', $plugin->id) }}">{{ $plugin->name }}</a></li>
        <li class="active">Designer</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $pluginName }}</h3>
            </div>
            <div class="box-body">
                <div id="{{ $pluginRootId }}" data-pt-surface="admin" class="ptero-plugin"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <link rel="stylesheet" href="{{ route('plugins.panel-tokens') }}">
    <link rel="stylesheet" href="{{ route('plugins.panel-theme') }}">
    <link rel="stylesheet" href="{{ asset('plugins/plugin-host.css') }}">
    <script src="{{ asset('plugins/plugin-ui.js') }}"></script>
    <script>
        window.__PterodactylPluginContext = {
            pluginId: @json($pluginId),
            rootId: @json($pluginRootId),
            serverUuid: '',
            apiBase: @json('/api/plugins-admin/' . $pluginId),
            publicApiBase: @json('/api/plugins-public/' . $pluginId),
            csrfToken: @json(csrf_token()),
            surface: 'admin',
            getPermissions: function () {
                return ['*'];
            },
            hasFullAccess: function () {
                return true;
            },
        };
        if (window.PterodactylPluginUi) {
            window.PterodactylPluginUi.enrichContext(
                window.__PterodactylPluginContext,
                'admin',
                document.getElementById(@json($pluginRootId))
            );
        }
    </script>
    <script src="/plugins-assets/{{ $pluginId }}/{{ ltrim($bundle, '/') }}"></script>
    <script>
        (function () {
            var root = document.getElementById(@json($pluginRootId));
            var key = 'PterodactylPlugin_{{ str_replace('.', '_', $pluginId) }}';
            var mount = window[key];
            if (typeof mount === 'function') {
                mount();
                return;
            }

            if (root) {
                root.innerHTML = '<p class="text-danger">Plugin designer failed to load.</p>';
            }
        })();
    </script>
@endsection
