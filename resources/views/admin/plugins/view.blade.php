@extends('layouts.admin')

@section('title')
    {{ $plugin->name }}
@endsection

@section('content-header')
    <h1>{{ $plugin->name }}<small>{{ $plugin->id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.plugins') }}">Plugins</a></li>
        <li class="active">{{ $plugin->name }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Details</h3>
                </div>
                <div class="box-body">
                    <dl class="dl-horizontal">
                        <dt>@lang('admin/plugins.version')</dt>
                        <dd>{{ $plugin->version }}</dd>
                        <dt>Status</dt>
                        <dd>
                            @if($plugin->enabled)
                                <span class="label label-success">@lang('admin/plugins.enabled')</span>
                            @else
                                <span class="label label-default">@lang('admin/plugins.disabled')</span>
                            @endif
                        </dd>
                        <dt>@lang('admin/plugins.source')</dt>
                        <dd>
                            <a href="{{ $plugin->source_url }}" target="_blank" rel="noopener">{{ $plugin->source_url }}</a>
                            @if($plugin->source_ref)
                                <br><small>Ref: {{ $plugin->source_ref }}</small>
                            @endif
                            @if($plugin->commit_sha)
                                <br><small>Commit: <code>{{ $plugin->commit_sha }}</code></small>
                            @endif
                        </dd>
                    </dl>
                </div>
                <div class="box-footer">
                    <form method="POST" action="{{ route('admin.plugins.update', $plugin) }}" style="display:inline" onsubmit="return confirm('@lang('admin/plugins.confirm_update')')">
                        @csrf
                        <button type="submit" class="btn btn-primary">@lang('admin/plugins.update')</button>
                    </form>
                    @if($plugin->enabled)
                        <form method="POST" action="{{ route('admin.plugins.disable', $plugin) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-warning">@lang('admin/plugins.disable')</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.plugins.enable', $plugin) }}" style="display:inline" onsubmit="return confirm('@lang('admin/plugins.confirm_enable')')">
                            @csrf
                            <button type="submit" class="btn btn-success">@lang('admin/plugins.enable')</button>
                        </form>
                    @endif
                    <a href="{{ route('admin.plugins.settings', $plugin) }}" class="btn btn-default">@lang('admin/plugins.settings')</a>
                    <form method="POST" action="{{ route('admin.plugins.destroy', $plugin) }}" style="display:inline" onsubmit="return confirm('@lang('admin/plugins.confirm_uninstall')')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger pull-right">@lang('admin/plugins.uninstall')</button>
                    </form>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('admin/plugins.permissions')</h3>
                </div>
                <div class="box-body">
                    <ul class="list-unstyled">
                        @forelse($plugin->permissions ?? [] as $permission)
                            <li style="margin-bottom: 10px;">
                                <code>{{ $permission }}</code>
                                <br>
                                <span class="text-muted">{{ $permissionDescriptions[$permission] ?? '' }}</span>
                            </li>
                        @empty
                            <li class="text-muted">No permissions requested.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if($manifest && !empty($manifest->hooks))
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Event Hooks</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table">
                            <tr>
                                <th>Hook</th>
                                <th>Listener</th>
                            </tr>
                            @foreach($manifest->hooks as $hook => $class)
                                <tr>
                                    <td><code>{{ $hook }}</code></td>
                                    <td><code>{{ $class }}</code></td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
