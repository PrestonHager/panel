@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'updates'])

@section('title')
    Panel Updates
@endsection

@section('content-header')
    <h1>Panel Updates<small>Configure upstream sources, backups, and safe upgrades.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')

    @if($outdatedPluginCount > 0)
        <div class="row">
            <div class="col-xs-12">
                <div class="alert alert-warning">
                    <strong>{{ $outdatedPluginCount }}</strong>
                    {{ $outdatedPluginCount === 1 ? 'plugin has' : 'plugins have' }}
                    updates available.
                    <a href="{{ route('admin.plugins') }}" class="btn btn-xs btn-warning" style="margin-left: 8px;">
                        Manage Plugin Updates
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Version Information</h3>
                </div>
                <div class="box-body">
                    <p>
                        Current version: <code>{{ $currentVersion }}</code><br>
                        Configured upstream: <code>{{ $updateCheck['repository'] }}</code>
                        on branch <code>{{ $updateCheck['branch'] }}</code><br>
                        Detected install mode: <code>{{ $detectedMode }}</code><br>
                        @if($latestRelease['version'])
                            Latest upstream release: <code>{{ $latestRelease['version'] }}</code>
                            @if($latestRelease['url'])
                                (<a href="{{ $latestRelease['url'] }}" target="_blank" rel="noopener">view release</a>)
                            @endif
                            <br>
                        @else
                            Latest upstream release could not be determined.<br>
                        @endif
                        @if($updateCheck['installed_commit'])
                            Installed commit: <code>{{ substr($updateCheck['installed_commit'], 0, 7) }}</code>
                            @if($updateCheck['remote_commit'])
                                · upstream commit: <code>{{ substr($updateCheck['remote_commit'], 0, 7) }}</code>
                            @endif
                            <br>
                        @elseif($updateCheck['remote_commit'])
                            Upstream commit on <code>{{ $updateCheck['branch'] }}</code>:
                            <code>{{ substr($updateCheck['remote_commit'], 0, 7) }}</code><br>
                        @endif
                    </p>
                    @if($updateAvailable)
                        <div class="alert alert-warning">
                            @if(($updateCheck['check_method'] ?? null) === 'release' && $latestRelease['version'])
                                A newer release version <code>{{ $latestRelease['version'] }}</code> is available from your configured upstream.
                            @elseif(($updateCheck['check_method'] ?? null) === 'commit' && $updateCheck['remote_commit'])
                                Your configured branch has a newer commit (<code>{{ substr($updateCheck['remote_commit'], 0, 7) }}</code>).
                                The manifest version may be unchanged — run a safe upgrade to pull the latest files.
                            @else
                                An update appears to be available from your configured upstream.
                            @endif
                        </div>
                    @else
                        <div class="alert alert-success">
                            Your panel matches the latest detected upstream release
                            @if($updateCheck['remote_commit'] && ($updateCheck['check_method'] ?? null) === 'commit')
                                and branch commit
                            @endif
                            for <code>{{ $updateCheck['repository'] }}</code>.
                        </div>
                    @endif
                    @if(strtolower($updateCheck['repository']) === 'pterodactyl/panel' && $detectedMode === 'git')
                        <p class="text-muted small">
                            Tracking the official repository only compares release versions by default.
                            If you run a fork or feature branch, set <strong>Repository</strong> to your fork (for example
                            <code>PrestonHager/panel</code>) and <strong>Git Branch</strong> to your branch below so new pushes are detected by commit.
                        </p>
                    @endif
                </div>
            </div>

            <form action="{{ route('admin.settings.updates') }}" method="POST">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Upstream Configuration</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label class="control-label">Repository</label>
                                <input type="text" class="form-control" name="pterodactyl:update:repository" value="{{ old('pterodactyl:update:repository', $repository) }}" required>
                                <p class="text-muted small">GitHub repository in <code>owner/repo</code> format. Use your fork here if needed.</p>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="control-label">Update Mode</label>
                                <select class="form-control" name="pterodactyl:update:mode">
                                    <option value="auto" @if($mode === 'auto') selected @endif>Auto detect (git or release)</option>
                                    <option value="release" @if($mode === 'release') selected @endif>Release tarball</option>
                                    <option value="git" @if($mode === 'git') selected @endif>Git pull</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Git Branch</label>
                                <input type="text" class="form-control" name="pterodactyl:update:branch" value="{{ old('pterodactyl:update:branch', $branch) }}" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Pinned Release (optional)</label>
                                <input type="text" class="form-control" name="pterodactyl:update:release" value="{{ old('pterodactyl:update:release', $release) }}" placeholder="1.12.4">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Git Remote</label>
                                <input type="text" class="form-control" name="pterodactyl:update:git_remote" value="{{ old('pterodactyl:update:git_remote', $gitRemote) }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save Settings</button>
                    </div>
                </div>
            </form>

            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">Run Safe Upgrade</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">
                        We strongly recommend backing up important files and configurations before upgrading.
                        You can download a convenience bundle below, but an automatic backup is always created before the updater runs.
                    </p>

                    <div id="upgrade-status" class="alert alert-info" style="display:none;"></div>
                    <div class="progress" id="upgrade-progress-wrap" style="display:none; margin-bottom: 15px;">
                        <div class="progress-bar progress-bar-striped active" id="upgrade-progress-bar" role="progressbar" style="width: 0%;">0%</div>
                    </div>

                    <button type="button" class="btn @if($updateAvailable) btn-warning @else btn-default @endif" id="open-upgrade-modal">
                        <i class="fa fa-cloud-upload"></i> Start Safe Upgrade
                    </button>
                    <a href="{{ route('admin.settings.updates.download') }}" class="btn btn-default">
                        <i class="fa fa-download"></i> Download Config Bundle
                    </a>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Automatic Backups</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Backup ID</th>
                                <th>Created</th>
                                <th>Version</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $backup)
                                <tr>
                                    <td><code>{{ $backup['id'] }}</code></td>
                                    <td>{{ $backup['created_at'] ?? 'Unknown' }}</td>
                                    <td>{{ $backup['panel_version'] ?? 'Unknown' }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.settings.updates.restore', $backup['id']) }}" style="display:inline" onsubmit="return confirm('Restore this backup? Current panel files and database will be overwritten.');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-warning">Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">No automatic backups have been created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="upgrade-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Safe Panel Upgrade</h4>
                </div>
                <div class="modal-body">
                    <div id="upgrade-step-1">
                        <p>Before upgrading, consider downloading a bundle of your environment file, panel settings, and installed plugin list.</p>
                        <p class="text-muted">You can decline this optional download and continue. An automatic backup is still created before updating.</p>
                    </div>
                    <div id="upgrade-step-2" style="display:none;">
                        <p>An automatic backup will be created, then the panel will update from your configured upstream repository.</p>
                        <p class="text-warning"><strong>If the upgrade fails, the panel will attempt to restore the automatic backup.</strong></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="upgrade-step-1-actions">
                        <a href="{{ route('admin.settings.updates.download') }}" class="btn btn-primary">Download Backup Bundle</a>
                        <button type="button" class="btn btn-default" id="decline-download-continue">Decline &amp; Continue</button>
                        <button type="button" class="btn btn-link" data-dismiss="modal">Cancel</button>
                    </div>
                    <div id="upgrade-step-2-actions" style="display:none;">
                        <form method="POST" action="{{ route('admin.settings.updates.run') }}">
                            @csrf
                            <button type="submit" class="btn btn-warning">Run Update</button>
                            <button type="button" class="btn btn-link" data-dismiss="modal">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var pollTimer = null;

            function showStep(step) {
                document.getElementById('upgrade-step-1').style.display = step === 1 ? 'block' : 'none';
                document.getElementById('upgrade-step-2').style.display = step === 2 ? 'block' : 'none';
                document.getElementById('upgrade-step-1-actions').style.display = step === 1 ? 'block' : 'none';
                document.getElementById('upgrade-step-2-actions').style.display = step === 2 ? 'block' : 'none';
            }

            document.getElementById('open-upgrade-modal').addEventListener('click', function () {
                showStep(1);
                $('#upgrade-modal').modal('show');
            });

            document.getElementById('decline-download-continue').addEventListener('click', function () {
                showStep(2);
            });

            function pollStatus() {
                fetch('{{ route('admin.settings.updates.status') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (response) { return response.json(); }).then(function (data) {
                    var wrap = document.getElementById('upgrade-progress-wrap');
                    var bar = document.getElementById('upgrade-progress-bar');
                    var status = document.getElementById('upgrade-status');

                    if (!data || data.state === 'idle') {
                        return;
                    }

                    wrap.style.display = 'block';
                    status.style.display = 'block';
                    status.className = 'alert alert-info';
                    status.textContent = data.message || 'Upgrade in progress...';
                    bar.style.width = (data.progress || 0) + '%';
                    bar.textContent = (data.progress || 0) + '%';

                    if (data.state === 'completed') {
                        status.className = 'alert alert-success';
                        status.textContent = data.message || 'Upgrade completed.';
                        clearInterval(pollTimer);
                        setTimeout(function () { window.location.reload(); }, 2000);
                    }

                    if (data.state === 'failed') {
                        status.className = 'alert alert-danger';
                        status.textContent = (data.error || data.message || 'Upgrade failed.');
                        clearInterval(pollTimer);
                    }
                }).catch(function () {});
            }

            @if(($status['state'] ?? 'idle') === 'running')
                pollTimer = setInterval(pollStatus, 3000);
                pollStatus();
            @endif
        })();
    </script>
@endsection
