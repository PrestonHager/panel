import React, { useEffect, useState } from 'react';
import { NavLink, Route, Switch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import SubNavigation from '@/components/elements/SubNavigation';
import { useLocation } from 'react-router';
import Spinner from '@/components/elements/Spinner';
import routes from '@/routers/routes';
import getEnabledPlugins, { EnabledClientPlugin } from '@/api/plugins/getEnabledPlugins';
import PluginClientTabHost from '@/plugins/host/PluginClientTabHost';

export default () => {
    const location = useLocation();
    const [clientPlugins, setClientPlugins] = useState<EnabledClientPlugin[]>([]);

    useEffect(() => {
        getEnabledPlugins()
            .then((response) => setClientPlugins(response.client))
            .catch((err) => console.error('Failed to load client plugins', err));
    }, []);

    const builderPlugin = clientPlugins.find((plugin) => plugin.ui.client.path === '/');

    return (
        <>
            <NavigationBar />
            {location.pathname.startsWith('/account') && (
                <SubNavigation>
                    <div>
                        {routes.account
                            .filter((route) => !!route.name)
                            .map(({ path, name, exact = false }) => (
                                <NavLink key={path} to={`/account/${path}`.replace('//', '/')} exact={exact}>
                                    {name}
                                </NavLink>
                            ))}
                    </div>
                </SubNavigation>
            )}
            <TransitionRouter>
                <React.Suspense fallback={<Spinner centered />}>
                    <Switch location={location}>
                        <Route path={'/'} exact>
                            {builderPlugin ? (
                                <PluginClientTabHost plugin={builderPlugin} />
                            ) : (
                                <DashboardContainer />
                            )}
                        </Route>
                        {clientPlugins
                            .filter((plugin) => plugin.ui.client.path !== '/')
                            .map((plugin) => (
                                <Route
                                    key={plugin.id}
                                    path={plugin.ui.client.path}
                                    exact={plugin.ui.client.exact ?? true}
                                >
                                    <PluginClientTabHost plugin={plugin} />
                                </Route>
                            ))}
                        {routes.account.map(({ path, component: Component }) => (
                            <Route key={path} path={`/account/${path}`.replace('//', '/')} exact>
                                <Component />
                            </Route>
                        ))}
                        <Route path={'*'}>
                            <NotFound />
                        </Route>
                    </Switch>
                </React.Suspense>
            </TransitionRouter>
        </>
    );
};
