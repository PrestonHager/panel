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
                <form method="POST" action="{{ route('admin.plugins.settings.update', $plugin) }}">
                    @csrf
                    @method('PATCH')
                    <div class="box-body">
                        <p class="text-muted">Store plugin configuration as JSON key-value pairs. Sensitive values are encrypted at rest.</p>
                        <div class="form-group">
                            <label for="config_json" class="control-label">Config (JSON)</label>
                            <textarea name="config_json" id="config_json" class="form-control" rows="12">{{ old('config_json', json_encode($plugin->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                        </div>
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
