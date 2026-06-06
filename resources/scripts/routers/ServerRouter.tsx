import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { NavLink, Route, Switch, useRouteMatch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import { CSSTransition } from 'react-transition-group';
import Can from '@/components/elements/Can';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import SubNavigation from '@/components/elements/SubNavigation';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';
import getEnabledPlugins, { EnabledPlugin } from '@/api/plugins/getEnabledPlugins';
import PluginServerTabHost from '@/plugins/host/PluginServerTabHost';
import PluginSettingsPanel from '@/components/server/plugins/PluginSettingsPanel';
import PluginCan from '@/components/elements/PluginCan';
import { usePluginPermissions } from '@/plugins/usePluginPermissions';

const PluginNavLink = ({
    plugin,
    to,
}: {
    plugin: EnabledPlugin;
    to: (value: string, url?: boolean) => string;
}) => {
    const allowed = usePluginPermissions(plugin.id, plugin.ui.server.permission);
    if (!allowed) {
        return null;
    }

    return (
        <NavLink to={to(plugin.ui.server.path, true)} exact={plugin.ui.server.exact ?? true}>
            {plugin.ui.server.name}
        </NavLink>
    );
};

const PluginSettingsNavLink = ({
    plugin,
    to,
}: {
    plugin: EnabledPlugin;
    to: (value: string, url?: boolean) => string;
}) => {
    if (!plugin.ui.server.hasClientSettings || !plugin.ui.server.settingsPath) {
        return null;
    }

    const permission = plugin.ui.server.settingsPermission || plugin.ui.server.permission;
    const allowed = usePluginPermissions(plugin.id, permission || undefined);
    if (!allowed) {
        return null;
    }

    return (
        <NavLink to={to(plugin.ui.server.settingsPath, true)} exact>
            {plugin.ui.server.name} Settings
        </NavLink>
    );
};

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');
    const [enabledPlugins, setEnabledPlugins] = useState<EnabledPlugin[]>([]);

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    const to = (value: string, url = false) => {
        if (value === '/') {
            return url ? match.url : match.path;
        }
        return `${(url ? match.url : match.path).replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    useEffect(
        () => () => {
            clearServerState();
        },
        []
    );

    useEffect(() => {
        setError('');

        getServer(match.params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        getEnabledPlugins()
            .then((response) => setEnabledPlugins(response.server))
            .catch((err) => console.error('Failed to load plugins', err));

        return () => {
            clearServerState();
        };
    }, [match.params.id]);

    return (
        <React.Fragment key={'server-router'}>
            <NavigationBar />
            {!uuid || !id ? (
                error ? (
                    <ServerError message={error} />
                ) : (
                    <Spinner size={'large'} centered />
                )
            ) : (
                <>
                    <CSSTransition timeout={150} classNames={'fade'} appear in>
                        <SubNavigation>
                            <div>
                                {routes.server
                                    .filter((route) => !!route.name)
                                    .map((route) =>
                                        route.permission ? (
                                            <Can key={route.path} action={route.permission} matchAny>
                                                <NavLink to={to(route.path, true)} exact={route.exact}>
                                                    {route.name}
                                                </NavLink>
                                            </Can>
                                        ) : (
                                            <NavLink key={route.path} to={to(route.path, true)} exact={route.exact}>
                                                {route.name}
                                            </NavLink>
                                        )
                                    )}
                                {enabledPlugins.map((plugin) => (
                                    <PluginNavLink key={plugin.id} plugin={plugin} to={to} />
                                ))}
                                {enabledPlugins.map((plugin) => (
                                    <PluginSettingsNavLink key={`${plugin.id}-settings`} plugin={plugin} to={to} />
                                ))}
                                {rootAdmin && (
                                    // eslint-disable-next-line react/jsx-no-target-blank
                                    <a href={`/admin/servers/view/${serverId}`} target={'_blank'}>
                                        <FontAwesomeIcon icon={faExternalLinkAlt} />
                                    </a>
                                )}
                            </div>
                        </SubNavigation>
                    </CSSTransition>
                    <InstallListener />
                    <TransferListener />
                    <WebsocketHandler />
                    {inConflictState && (!rootAdmin || (rootAdmin && !location.pathname.endsWith(`/server/${id}`))) ? (
                        <ConflictStateRenderer />
                    ) : (
                        <ErrorBoundary>
                            <TransitionRouter>
                                <Switch location={location}>
                                    {routes.server.map(({ path, permission, component: Component }) => (
                                        <PermissionRoute key={path} permission={permission} path={to(path)} exact>
                                            <Spinner.Suspense>
                                                <Component />
                                            </Spinner.Suspense>
                                        </PermissionRoute>
                                    ))}
                                    {enabledPlugins.map((plugin) => (
                                        <Route
                                            key={plugin.id}
                                            path={to(plugin.ui.server.path)}
                                            exact={plugin.ui.server.exact ?? true}
                                        >
                                            <PluginCan pluginId={plugin.id} permission={plugin.ui.server.permission}>
                                                <PluginServerTabHost plugin={plugin} />
                                            </PluginCan>
                                        </Route>
                                    ))}
                                    {enabledPlugins.map((plugin) =>
                                        plugin.ui.server.hasClientSettings && plugin.ui.server.settingsPath ? (
                                            <Route
                                                key={`${plugin.id}-settings`}
                                                path={to(plugin.ui.server.settingsPath)}
                                                exact
                                            >
                                                <PluginCan
                                                    pluginId={plugin.id}
                                                    permission={
                                                        plugin.ui.server.settingsPermission ||
                                                        plugin.ui.server.permission
                                                    }
                                                >
                                                    <PluginSettingsPanel plugin={plugin} />
                                                </PluginCan>
                                            </Route>
                                        ) : null
                                    )}
                                    <Route path={'*'} component={NotFound} />
                                </Switch>
                            </TransitionRouter>
                        </ErrorBoundary>
                    )}
                </>
            )}
        </React.Fragment>
    );
};
