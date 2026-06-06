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
            @if(!empty($updateCheck['update_available']))
                <div class="alert alert-warning">
                    @if(!empty($updateCheck['latest_version']))
                        @lang('admin/plugins.outdated_banner', ['version' => $updateCheck['latest_version']])
                        @if(!empty($updateCheck['release_url']))
                            (<a href="{{ $updateCheck['release_url'] }}" target="_blank" rel="noopener">view release</a>)
                        @endif
                    @elseif(!empty($updateCheck['remote_commit']))
                        @lang('admin/plugins.outdated_commit_banner', ['commit' => substr($updateCheck['remote_commit'], 0, 7)])
                    @else
                        @lang('admin/plugins.update_available')
                    @endif
                </div>
            @endif

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Details</h3>
                </div>
                <div class="box-body">
                    <dl class="dl-horizontal">
                        <dt>@lang('admin/plugins.version')</dt>
                        <dd>
                            {{ $plugin->version }}
                            @if(!empty($updateCheck['update_available']) && !empty($updateCheck['latest_version']))
                                <span class="label label-warning">@lang('admin/plugins.latest_version', ['version' => $updateCheck['latest_version']])</span>
                            @endif
                        </dd>
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
                        @if(!empty($updateCheck['latest_ref']))
                            <input type="hidden" name="ref" value="{{ $updateCheck['latest_ref'] }}">
                        @endif
                        <button type="submit" class="btn btn-primary" @if(empty($updateCheck['update_available'])) disabled title="Plugin is up to date." @endif>
                            @lang('admin/plugins.update')
                        </button>
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
                <form method="POST" action="{{ route('admin.plugins.permissions.update', $plugin) }}">
                    @csrf
                    @method('PATCH')
                    <div class="box-body">
                        @if(!empty($pendingPermissions))
                            <div class="callout callout-warning">
                                <h4>Permission changes pending approval</h4>
                                @if(!empty($pendingPermissions['added']))
                                    <p><strong>Added:</strong> {{ implode(', ', $pendingPermissions['added']) }}</p>
                                @endif
                                @if(!empty($pendingPermissions['removed']))
                                    <p><strong>Removed:</strong> {{ implode(', ', $pendingPermissions['removed']) }}</p>
                                @endif
                                <button type="submit" formaction="{{ route('admin.plugins.permissions.approve', $plugin) }}" class="btn btn-warning btn-sm">Approve new permissions</button>
                            </div>
                        @endif
                        <ul class="list-unstyled">
                            @forelse($plugin->permissions ?? [] as $permission)
                                <li style="margin-bottom: 12px;">
                                    <label style="font-weight: normal;">
                                        <input type="checkbox" name="approved_permissions[]" value="{{ $permission }}"
                                            @if(in_array($permission, $approvedPermissions, true)) checked @endif
                                            @if($plugin->enabled) disabled @endif>
                                        <code>{{ $permission }}</code>
                                        @if(in_array($permission, $highRiskPermissions, true))
                                            <span class="label label-danger">High risk</span>
                                        @endif
                                    </label>
                                    <br>
                                    <span class="text-muted">{{ $permissionDescriptions[$permission] ?? '' }}</span>
                                </li>
                            @empty
                                <li class="text-muted">No permissions requested.</li>
                            @endforelse
                        </ul>

                        @if($manifest && !empty($manifest->httpAllowedHosts))
                            <hr>
                            <h4>Approved HTTP Hosts</h4>
                            <ul class="list-unstyled">
                                @foreach($manifest->httpAllowedHosts as $host)
                                    <li style="margin-bottom: 8px;">
                                        <label style="font-weight: normal;">
                                            <input type="checkbox" name="approved_http_hosts[]" value="{{ $host }}"
                                                @if(in_array($host, $approvedHttpHosts, true)) checked @endif
                                                @if($plugin->enabled) disabled @endif>
                                            <code>{{ $host }}</code>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if($manifest && !is_null($manifest->uiTheme()))
                            @php($theme = $manifest->uiTheme())
                            <hr>
                            <h4>Theme overlay</h4>
                            <p class="text-muted">Approve design token overrides this plugin contributes to the panel theme.</p>
                            <ul class="list-unstyled">
                                <li style="margin-bottom: 8px;">
                                    <label style="font-weight: normal;">
                                        <input type="checkbox" name="approved_theme_enabled" value="1"
                                            @if($approvedTheme['enabled'] ?? false) checked @endif
                                            @if($plugin->enabled) disabled @endif>
                                        Enable theme overlay
                                    </label>
                                </li>
                                @foreach($theme['surfaces'] ?? [] as $surface)
                                    <li style="margin-bottom: 8px;">
                                        <label style="font-weight: normal;">
                                            <input type="checkbox" name="approved_theme_surfaces[]" value="{{ $surface }}"
                                                @if(in_array($surface, $approvedTheme['surfaces'] ?? [], true)) checked @endif
                                                @if($plugin->enabled) disabled @endif>
                                            Surface: <code>{{ $surface }}</code>
                                        </label>
                                    </li>
                                @endforeach
                                @foreach($theme['tokens'] ?? [] as $tokenKey => $tokenValue)
                                    <li style="margin-bottom: 8px;">
                                        <label style="font-weight: normal;">
                                            <input type="checkbox" name="approved_theme_token_keys[]" value="{{ $tokenKey }}"
                                                @if(in_array($tokenKey, $approvedTheme['token_keys'] ?? [], true)) checked @endif
                                                @if($plugin->enabled) disabled @endif>
                                            <code>{{ $tokenKey }}</code> = <code>{{ $tokenValue }}</code>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if($plugin->enabled)
                            <p class="text-muted">Disable the plugin to modify approved capabilities.</p>
                        @endif
                    </div>
                    @if(!$plugin->enabled && !empty($plugin->permissions))
                        <div class="box-footer">
                            <button type="submit" class="btn btn-primary">Save approved permissions</button>
                        </div>
                    @endif
                </form>
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
