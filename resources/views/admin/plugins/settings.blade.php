@extends('layouts.admin')

@section('title')
    {{ $plugin->name }} Settings
@endsection

@section('content-header')
    <h1>{{ $plugin->name }}<small>Plugin configuration</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.plugins') }}">Plugins</a></li>
        <li><a href="{{ route('admin.plugins.view', $plugin) }}">{{ $plugin->name }}</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Configuration</h3>
                </div>
                <form method="POST" action="{{ route('admin.plugins.settings.update', $plugin) }}" id="plugin-settings-form-element">
                    @csrf
                    @method('PATCH')
                    <div class="box-body">
                        @if($hasStructuredForm)
                            <p class="text-muted">Configure this plugin using the fields below. Sensitive values are encrypted at rest.</p>
                            <div id="plugin-settings-form" class="ptero-plugin" data-pt-surface="admin"></div>
                            <script type="application/json" id="plugin-settings-schema">@json($schema->toArray('admin'))</script>
                            <script type="application/json" id="plugin-settings-values">@json($values)</script>
                        @else
                            <p class="text-muted">Store plugin configuration as JSON key-value pairs. Sensitive values are encrypted at rest.</p>
                            <div class="form-group">
                                <label for="config_json" class="control-label">Config (JSON)</label>
                                <textarea name="config_json" id="config_json" class="form-control" rows="12">{{ old('config_json', json_encode($plugin->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                            </div>
                        @endif

                        @if($hasStructuredForm)
                            <div class="panel panel-default" style="margin-top: 20px;">
                                <div class="panel-heading">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" href="#advanced-json-collapse">Advanced JSON</a>
                                    </h4>
                                </div>
                                <div id="advanced-json-collapse" class="panel-collapse collapse">
                                    <div class="panel-body">
                                        <p class="text-muted">Override the full configuration object. Structured fields above take precedence when both are submitted.</p>
                                        <textarea name="config_json" id="config_json" class="form-control" rows="8">{{ old('config_json', json_encode($plugin->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="box-footer">
                        <a href="{{ route('admin.plugins.view', $plugin) }}" class="btn btn-default">Back</a>
                        <button type="submit" class="btn btn-primary pull-right">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    @if($hasStructuredForm)
        <link rel="stylesheet" href="{{ route('plugins.panel-tokens') }}">
        <link rel="stylesheet" href="{{ route('plugins.panel-theme') }}">
        <link rel="stylesheet" href="{{ asset('plugins/plugin-host.css') }}">
        <script src="{{ asset('plugins/plugin-settings-form.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var schema = JSON.parse(document.getElementById('plugin-settings-schema').textContent);
                var values = JSON.parse(document.getElementById('plugin-settings-values').textContent);
                var container = document.getElementById('plugin-settings-form');
                var form = document.getElementById('plugin-settings-form-element');

                if (window.PterodactylPluginSettingsForm) {
                    window.PterodactylPluginSettingsForm.render(container, schema.fields, values);
                    form.addEventListener('submit', function () {
                        window.PterodactylPluginSettingsForm.syncHiddenInputs(form, container, schema.fields);
                    });
                }
            });
        </script>
    @endif
@endsection
