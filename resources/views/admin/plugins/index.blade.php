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
    @if($outdatedCount > 0)
        <div class="row">
            <div class="col-xs-12">
                <div class="alert alert-warning">
                    @lang('admin/plugins.outdated_plugins_alert', ['count' => $outdatedCount])
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Installed Plugins</h3>
                    <div class="box-tools">
                        @if($outdatedCount > 0)
                            <form method="POST" action="{{ route('admin.plugins.upgrade.outdated') }}" style="display:inline" id="upgrade-all-outdated-form">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning">@lang('admin/plugins.upgrade_all_outdated')</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.plugins.install') }}" class="btn btn-sm btn-primary">Install Plugin</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    @if($plugins->isEmpty())
                        <p class="text-muted" style="padding: 15px;">@lang('admin/plugins.no_plugins')</p>
                    @else
                        <form method="POST" action="{{ route('admin.plugins.upgrade') }}" id="upgrade-selected-form">
                            @csrf
                            <table class="table table-hover">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-outdated" title="Select all outdated plugins">
                                    </th>
                                    <th>Name</th>
                                    <th>ID</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                    <th>Permissions</th>
                                    <th></th>
                                </tr>
                                @foreach($plugins as $plugin)
                                    @php($check = $updateChecks[$plugin->id] ?? null)
                                    <tr>
                                        <td>
                                            @if($check && $check['update_available'])
                                                <input type="checkbox" name="plugins[]" value="{{ $plugin->id }}" class="plugin-upgrade-checkbox">
                                            @endif
                                        </td>
                                        <td><a href="{{ route('admin.plugins.view', $plugin) }}">{{ $plugin->name }}</a></td>
                                        <td><code>{{ $plugin->id }}</code></td>
                                        <td>
                                            {{ $plugin->version }}
                                            @if($check && $check['update_available'])
                                                <br>
                                                <span class="label label-warning">@lang('admin/plugins.update_available')</span>
                                                @if($check['latest_version'])
                                                    <small class="text-muted">@lang('admin/plugins.latest_version', ['version' => $check['latest_version']])</small>
                                                @elseif($check['remote_commit'])
                                                    <small class="text-muted">@lang('admin/plugins.new_commit_available')</small>
                                                @elseif(($check['check_method'] ?? null) === 'hash')
                                                    <small class="text-muted">@lang('admin/plugins.new_files_available')</small>
                                                @endif
                                            @endif
                                        </td>
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
                            @if($outdatedCount > 0)
                                <div style="padding: 0 15px 15px;">
                                    <button type="submit" class="btn btn-warning" id="upgrade-selected-btn" disabled>
                                        @lang('admin/plugins.upgrade_selected')
                                    </button>
                                </div>
                            @endif
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var checkboxes = document.querySelectorAll('.plugin-upgrade-checkbox');
            var selectAll = document.getElementById('select-all-outdated');
            var upgradeBtn = document.getElementById('upgrade-selected-btn');
            var upgradeForm = document.getElementById('upgrade-selected-form');
            var upgradeAllForm = document.getElementById('upgrade-all-outdated-form');

            function updateUpgradeButton() {
                if (!upgradeBtn) {
                    return;
                }

                var checked = document.querySelectorAll('.plugin-upgrade-checkbox:checked').length;
                upgradeBtn.disabled = checked === 0;
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                    updateUpgradeButton();
                });
            }

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', updateUpgradeButton);
            });

            if (upgradeForm) {
                upgradeForm.addEventListener('submit', function (event) {
                    if (!confirm(@json(__('admin/plugins.confirm_bulk_upgrade')))) {
                        event.preventDefault();
                    }
                });
            }

            if (upgradeAllForm) {
                upgradeAllForm.addEventListener('submit', function (event) {
                    if (!confirm(@json(__('admin/plugins.confirm_upgrade_all_outdated')))) {
                        event.preventDefault();
                    }
                });
            }
        })();
    </script>
@endsection
