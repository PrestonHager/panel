@extends('layouts.admin')

@section('title')
    Install Plugin
@endsection

@section('content-header')
    <h1>Install Plugin<small>Add a plugin from a GitHub repository.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.plugins') }}">Plugins</a></li>
        <li class="active">Install</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Repository</h3>
                </div>
                <form method="POST" action="{{ route('admin.plugins.install') }}">
                    @csrf
                    <div class="box-body">
                        <div class="alert alert-warning">
                            @lang('admin/plugins.install_warning')
                        </div>
                        <div class="form-group">
                            <label for="github_url" class="control-label">@lang('admin/plugins.github_url')</label>
                            <input type="text" name="github_url" id="github_url" class="form-control" value="{{ old('github_url') }}" placeholder="https://github.com/owner/my-plugin" required />
                            <p class="text-muted small">@lang('admin/plugins.github_url_help')</p>
                        </div>
                        <div class="form-group">
                            <label for="source_ref" class="control-label">@lang('admin/plugins.source_ref')</label>
                            <input type="text" name="source_ref" id="source_ref" class="form-control" value="{{ old('source_ref', 'main') }}" placeholder="main" />
                            <p class="text-muted small">@lang('admin/plugins.source_ref_help')</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <a href="{{ route('admin.plugins') }}" class="btn btn-default">Cancel</a>
                        <button type="submit" class="btn btn-primary pull-right">Install</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
