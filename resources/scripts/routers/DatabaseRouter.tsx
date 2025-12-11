'use client';

import {
    ChartLine,
    Database,
    Ellipsis,
    FileText,
    Gear,
    House,
    LayoutHeaderCellsLarge,
    Terminal,
} from '@gravity-ui/icons';
import { useStoreState } from 'easy-peasy';
import React, { Fragment, Suspense, useEffect, useRef, useState } from 'react';
import { NavLink, Route, Routes, useLocation, useParams } from 'react-router-dom';
import { toast } from 'sonner';

import databaseRoutes from '@/routers/databaseRoutes';

import Can from '@/components/elements/Can';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/elements/DropdownMenu';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import MainSidebar from '@/components/elements/MainSidebar';
import MainWrapper from '@/components/elements/MainWrapper';
import MobileTopBar from '@/components/elements/MobileTopBar';
import PermissionRoute from '@/components/elements/PermissionRoute';
import Logo from '@/components/elements/PyroLogo';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import CommandMenu from '@/components/elements/commandk/CmdK';
import StatBlock from '@/components/server/console/StatBlock';

import { httpErrorToHuman } from '@/api/http';
import http from '@/api/http';
import getBillingPortalUrl from '@/api/server/getBillingPortalUrl';

import { ServerContext } from '@/state/server';

const DatabaseRouter = () => {
    const params = useParams<'id'>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    // Mobile menu state
    const [isMobileMenuVisible, setMobileMenuVisible] = useState(false);

    const toggleMobileMenu = () => {
        setMobileMenuVisible(!isMobileMenuVisible);
    };

    const closeMobileMenu = () => {
        setMobileMenuVisible(false);
    };

    const onTriggerLogout = () => {
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    const onSelectManageServer = () => {
        window.open(`/admin/servers/view/${serverId}`);
    };

    const onOpenBilling = async () => {
        if (!uuid) {
            return;
        }

        try {
            const response = await getBillingPortalUrl(uuid);
            window.location.href = response.url;
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to open billing portal.');
        }
    };

    useEffect(
        () => () => {
            clearServerState();
        },
        [],
    );

    useEffect(() => {
        setError('');

        if (params.id === undefined) {
            return;
        }

        getServer(params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearServerState();
        };
    }, [params.id, getServer, clearServerState]);

    // Define refs for navigation buttons
    const NavigationHome = useRef(null);
    const NavigationDatabases = useRef(null);
    const NavigationTables = useRef(null);
    const NavigationQuery = useRef(null);
    const NavigationLogs = useRef(null);
    const NavigationSettings = useRef(null);

    const calculateTop = (pathname: string) => {
        if (!id) {
            return '0';
        }

        const ButtonHome = NavigationHome.current;
        const ButtonDatabases = NavigationDatabases.current;
        const ButtonTables = NavigationTables.current;
        const ButtonQuery = NavigationQuery.current;
        const ButtonLogs = NavigationLogs.current;
        const ButtonSettings = NavigationSettings.current;

        const HighlightOffset: number = 8;

        if (pathname.endsWith(`/server/${id}`) && ButtonHome != null) {
            return (ButtonHome as any).offsetTop + HighlightOffset;
        }
        if (pathname.endsWith(`/server/${id}/databases`) && ButtonDatabases != null) {
            return (ButtonDatabases as any).offsetTop + HighlightOffset;
        }
        if (pathname.endsWith(`/server/${id}/tables`) && ButtonTables != null) {
            return (ButtonTables as any).offsetTop + HighlightOffset;
        }
        if (pathname.endsWith(`/server/${id}/query`) && ButtonQuery != null) {
            return (ButtonQuery as any).offsetTop + HighlightOffset;
        }
        if (pathname.endsWith(`/server/${id}/logs`) && ButtonLogs != null) {
            return (ButtonLogs as any).offsetTop + HighlightOffset;
        }
        if (pathname.endsWith(`/server/${id}/settings`) && ButtonSettings != null) {
            return (ButtonSettings as any).offsetTop + HighlightOffset;
        }

        return '0';
    };

    const top = calculateTop(location.pathname);

    const [height, setHeight] = useState('40px');

    useEffect(() => {
        setHeight('34px');
        const timeoutId = setTimeout(() => setHeight('40px'), 200);
        return () => clearTimeout(timeoutId);
    }, [top]);

    return (
        <Fragment key={'database-router'}>
            {!uuid || !id ? (
                error ? (
                    <ServerError title='Something went wrong' message={error} />
                ) : null
            ) : (
                <>
                    {/* Mobile Top Bar */}
                    <MobileTopBar
                        onMenuToggle={toggleMobileMenu}
                        onTriggerLogout={onTriggerLogout}
                        onSelectAdminPanel={onSelectManageServer}
                        rootAdmin={rootAdmin}
                    />

                    <div className='flex flex-row w-full lg:pt-0 pt-16'>
                        {/* Desktop Sidebar */}
                        <MainSidebar className='hidden lg:flex lg:relative lg:shrink-0 w-[300px] bg-[#1a1a1a] flex flex-col h-screen'>
                            <div
                                className='absolute bg-brand w-[3px] h-10 left-0 rounded-full pointer-events-none'
                                style={{
                                    top,
                                    height,
                                    opacity: top === '0' ? 0 : 1,
                                    transition:
                                        'linear(0,0.006,0.025 2.8%,0.101 6.1%,0.539 18.9%,0.721 25.3%,0.849 31.5%,0.937 38.1%,0.968 41.8%,0.991 45.7%,1.006 50.1%,1.015 55%,1.017 63.9%,1.001) 390ms',
                                }}
                            />
                            <div
                                className='absolute bg-zinc-900 w-12 h-10 blur-2xl left-0 rounded-full pointer-events-none'
                                style={{
                                    top,
                                    opacity: top === '0' ? 0 : 0.5,
                                    transition: 'all 300ms cubic-bezier(0.34, 1.56, 0.64, 1)',
                                }}
                            />
                            <div className='flex flex-row items-center justify-between h-8'>
                                <NavLink to={'/'} className='flex shrink-0 h-8 w-fit'>
                                    <Logo uniqueId='database-desktop-sidebar' />
                                </NavLink>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button className='w-10 h-10 flex items-center justify-center rounded-md text-white hover:bg-[#ffffff11] p-2 select-none cursor-pointer'>
                                            <Ellipsis fill='currentColor' width={26} height={22} />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className='z-99999 select-none relative' sideOffset={8}>
                                        {rootAdmin && (
                                            <DropdownMenuItem onSelect={onSelectManageServer}>
                                                Manage Server
                                                <span className='ml-2 z-10 rounded-full bg-brand px-2 py-1 text-xs select-none'>
                                                    Staff
                                                </span>
                                            </DropdownMenuItem>
                                        )}
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem onSelect={onTriggerLogout}>Log Out</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <div aria-hidden className='mt-8 mb-4 bg-[#ffffff33] min-h-[1px] w-6'></div>
                            <ul
                                data-pyro-subnav-routes-wrapper=''
                                className='pyro-subnav-routes-wrapper flex-grow overflow-y-auto'
                            >
                                <NavLink
                                    className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                    ref={NavigationHome}
                                    to={`/server/${id}`}
                                    end
                                >
                                    <House width={22} height={22} fill='currentColor' />
                                    <p>Overview</p>
                                </NavLink>
                                <Can action={'database.*'} matchAny>
                                    <NavLink
                                        className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                        ref={NavigationDatabases}
                                        to={`/server/${id}/databases`}
                                        end
                                    >
                                        <Database width={22} height={22} fill='currentColor' />
                                        <p>Databases</p>
                                    </NavLink>
                                </Can>
                                <Can action={'database.*'} matchAny>
                                    <NavLink
                                        className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                        ref={NavigationTables}
                                        to={`/server/${id}/tables`}
                                        end
                                    >
                                        <LayoutHeaderCellsLarge width={22} height={22} fill='currentColor' />
                                        <p>Tables</p>
                                    </NavLink>
                                </Can>
                                <Can action={'database.*'} matchAny>
                                    <NavLink
                                        className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                        ref={NavigationQuery}
                                        to={`/server/${id}/query`}
                                        end
                                    >
                                        <Terminal width={22} height={22} fill='currentColor' />
                                        <p>Query</p>
                                    </NavLink>
                                </Can>
                                <Can action={'database.*'} matchAny>
                                    <NavLink
                                        className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                        ref={NavigationLogs}
                                        to={`/server/${id}/logs`}
                                        end
                                    >
                                        <FileText width={22} height={22} fill='currentColor' />
                                        <p>Logs</p>
                                    </NavLink>
                                </Can>
                                <Can action={'database.*'} matchAny>
                                    <NavLink
                                        className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                        ref={NavigationSettings}
                                        to={`/server/${id}/settings`}
                                        end
                                    >
                                        <Gear width={22} height={22} fill='currentColor' />
                                        <p>Settings</p>
                                    </NavLink>
                                </Can>
                            </ul>
                            <div className='shrink-0'>
                                <div aria-hidden className='mt-8 mb-4 bg-[#ffffff33] min-h-[1px] w-full'></div>

                                <StatBlock
                                    title='database'
                                    className='p-4 bg-[#ffffff09] border-[1px] border-[#ffffff11] shadow-xs rounded-xl text-center hover:cursor-default'
                                >
                                    {serverName}
                                </StatBlock>
                                <button
                                    onClick={onOpenBilling}
                                    className='p-4 bg-[#ffffff09] border-[1px] border-[#ffffff11] shadow-xs rounded-xl text-center hover:cursor-pointer hover:bg-[#ffffff12] hover:border-[#ffffff20] transition-colors mt-2 w-full'
                                >
                                    <div className='text-sm font-semibold text-white'>Open in Billing</div>
                                </button>
                            </div>
                        </MainSidebar>

                        <MainWrapper className='w-full'>
                            <CommandMenu />
                            <main
                                data-pyro-main=''
                                data-pyro-transitionrouter=''
                                className='relative inset-[1px] w-full h-full overflow-y-auto overflow-x-hidden rounded-md bg-[#08080875]'
                            >
                                <ErrorBoundary>
                                    <Routes location={location}>
                                        {databaseRoutes.map(({ route, permission, component: Component }) => (
                                            <Route
                                                key={route}
                                                path={route}
                                                element={
                                                    <PermissionRoute permission={permission}>
                                                        <Suspense fallback={null}>
                                                            <Component />
                                                        </Suspense>
                                                    </PermissionRoute>
                                                }
                                            />
                                        ))}

                                        <Route path='*' element={<NotFound />} />
                                    </Routes>
                                </ErrorBoundary>
                            </main>
                        </MainWrapper>
                    </div>
                </>
            )}
        </Fragment>
    );
};

export default DatabaseRouter;
