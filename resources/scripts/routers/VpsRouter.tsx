'use client';

import { ChartLine, Ellipsis, Gear, House, PencilToLine } from '@gravity-ui/icons';
import { useStoreState } from 'easy-peasy';
import React, { Fragment, Suspense, useEffect, useRef, useState } from 'react';
import { NavLink, Route, Routes, useLocation, useParams } from 'react-router-dom';

import routes from '@/routers/vpsRoutes';

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
import Logo from '@/components/elements/PyroLogo';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';

import { httpErrorToHuman } from '@/api/http';
import http from '@/api/http';

import { VpsContext } from '@/state/vps';

const VpsRouter = () => {
    const params = useParams<'id'>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const id = VpsContext.useStoreState((state) => state.vps.data?.id);
    const uuid = VpsContext.useStoreState((state) => state.vps.data?.uuid);
    const inConflictState = VpsContext.useStoreState((state) => state.vps.inConflictState);
    const vpsName = VpsContext.useStoreState((state) => state.vps.data?.name);
    const getVps = VpsContext.useStoreActions((actions) => actions.vps.getVps);
    const clearVpsState = VpsContext.useStoreActions((actions) => actions.clearVpsState);

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

    useEffect(
        () => () => {
            clearVpsState();
        },
        [],
    );

    useEffect(() => {
        setError('');

        if (params.id === undefined) {
            return;
        }

        getVps(params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearVpsState();
        };
    }, [params.id]);

    // Define refs for navigation buttons.
    const NavigationHome = useRef(null);
    const NavigationMetrics = useRef(null);
    const NavigationSettings = useRef(null);
    const NavigationActivity = useRef(null);

    const calculateTop = (pathname: string) => {
        if (!id) return '0';

        const ButtonHome = NavigationHome.current;
        const ButtonMetrics = NavigationMetrics.current;
        const ButtonSettings = NavigationSettings.current;
        const ButtonActivity = NavigationActivity.current;

        const HighlightOffset: number = 8;

        if (pathname.endsWith(`/vps-server/${id}`) && ButtonHome != null)
            return (ButtonHome as any).offsetTop + HighlightOffset;
        if (pathname.endsWith(`/vps-server/${id}/metrics`) && ButtonMetrics != null)
            return (ButtonMetrics as any).offsetTop + HighlightOffset;
        if (pathname.endsWith(`/vps-server/${id}/settings`) && ButtonSettings != null)
            return (ButtonSettings as any).offsetTop + HighlightOffset;
        if (pathname.endsWith(`/vps-server/${id}/activity`) && ButtonActivity != null)
            return (ButtonActivity as any).offsetTop + HighlightOffset;

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
        <Fragment key={'vps-router'}>
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
                                    <Logo uniqueId='vps-desktop-sidebar' />
                                </NavLink>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button className='w-10 h-10 flex items-center justify-center rounded-md text-white hover:bg-[#ffffff11] p-2 select-none cursor-pointer'>
                                            <Ellipsis fill='currentColor' width={26} height={22} />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className='z-99999 select-none relative' sideOffset={8}>
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
                                    to={`/vps-server/${id}`}
                                    end
                                >
                                    <House width={22} height={22} fill='currentColor' />
                                    <p>Overview</p>
                                </NavLink>
                                <NavLink
                                    className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                    ref={NavigationMetrics}
                                    to={`/vps-server/${id}/metrics`}
                                    end
                                >
                                    <ChartLine width={22} height={22} fill='currentColor' />
                                    <p>Metrics</p>
                                </NavLink>
                                <NavLink
                                    className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                    ref={NavigationSettings}
                                    to={`/vps-server/${id}/settings`}
                                    end
                                >
                                    <Gear width={22} height={22} fill='currentColor' />
                                    <p>Settings</p>
                                </NavLink>
                                <NavLink
                                    className='flex flex-row items-center transition-colors duration-200 hover:bg-[#ffffff11] rounded-md'
                                    ref={NavigationActivity}
                                    to={`/vps-server/${id}/activity`}
                                    end
                                >
                                    <PencilToLine width={22} height={22} fill='currentColor' />
                                    <p>Activity</p>
                                </NavLink>
                            </ul>
                            <div className='shrink-0'>
                                <div aria-hidden className='mt-8 mb-4 bg-[#ffffff33] min-h-[1px] w-full'></div>
                                <div className='p-4 bg-[#ffffff09] border-[1px] border-[#ffffff11] shadow-xs rounded-xl text-center hover:cursor-default'>
                                    {vpsName}
                                </div>
                            </div>
                        </MainSidebar>

                        <MainWrapper className='w-full'>
                            <main
                                data-pyro-main=''
                                data-pyro-transitionrouter=''
                                className='relative inset-[1px] w-full h-full overflow-y-auto overflow-x-hidden rounded-md bg-[#08080875]'
                            >
                                {inConflictState ? (
                                    <div className='p-8 text-center'>
                                        <h2 className='text-xl font-semibold mb-2'>VPS is being configured</h2>
                                        <p className='text-white/70'>
                                            Your VPS is currently being set up. Please wait...
                                        </p>
                                    </div>
                                ) : (
                                    <ErrorBoundary>
                                        <Routes location={location}>
                                            {routes.vps.map(({ route, component: Component }) => (
                                                <Route
                                                    key={route}
                                                    path={route}
                                                    element={
                                                        <Suspense fallback={null}>
                                                            <Component />
                                                        </Suspense>
                                                    }
                                                />
                                            ))}

                                            <Route path='*' element={<NotFound />} />
                                        </Routes>
                                    </ErrorBoundary>
                                )}
                            </main>
                        </MainWrapper>
                    </div>
                </>
            )}
        </Fragment>
    );
};

export default VpsRouter;
