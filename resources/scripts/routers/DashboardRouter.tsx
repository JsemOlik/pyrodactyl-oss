import { ChevronLeft, ChevronRight, CircleDollar, CircleQuestion, House, Shield, ShoppingBasket } from '@gravity-ui/icons';
import * as Tooltip from '@radix-ui/react-tooltip';
import { useStoreState } from 'easy-peasy';
import { Fragment, Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { NavLink, Route, Routes, useLocation } from 'react-router-dom';

import routes from '@/routers/routes';

import BillingContainer from '@/components/dashboard/BillingContainer';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import SupportContainer from '@/components/dashboard/SupportContainer';
import TicketDetailContainer from '@/components/dashboard/TicketDetailContainer';
import TicketsContainer from '@/components/dashboard/TicketsContainer';
import MainSidebar from '@/components/elements/MainSidebar';
import MainWrapper from '@/components/elements/MainWrapper';
import { DashboardMobileMenu } from '@/components/elements/MobileFullScreenMenu';
import MobileTopBar from '@/components/elements/MobileTopBar';
import Logo from '@/components/elements/PyroLogo';
import { NotFound } from '@/components/elements/ScreenBlock';
import VpsContainer from '@/components/vps/VpsContainer';

import { GravatarStyle, getGravatarUrl } from '@/lib/gravatar';

import getAccountData, { AccountData } from '@/api/account/getAccountData';
import http from '@/api/http';

const DashboardRouter = () => {
    const location = useLocation();
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const user = useStoreState((state) => state.user.data);

    // Mobile menu state
    const [isMobileMenuVisible, setMobileMenuVisible] = useState(false);
    const [accountData, setAccountData] = useState<AccountData | null>(null);
    const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

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

    const onTriggerReturnToWebsite = () => {
        // Scroll directly to the plans section on the hosting page when possible.
        window.location.href = '/hosting#plans';
    };

    const onSelectAdminPanel = () => {
        window.location.href = `/admin`;
    };

    // Define refs for navigation buttons.
    const NavigationHome = useRef<HTMLAnchorElement | null>(null);
    const NavigationSettingsBilling = useRef<HTMLAnchorElement | null>(null);
    const NavigationSettings = useRef<HTMLAnchorElement | null>(null);
    const NavigationSupport = useRef<HTMLAnchorElement | null>(null);

    const calculateTop = (pathname: string) => {
        // Get currents of navigation refs.
        const ButtonHome = NavigationHome.current;
        const ButtonSettingsBilling = NavigationSettingsBilling.current;
        const ButtonSettings = NavigationSettings.current;
        const ButtonSupport = NavigationSupport.current;

        // Perfectly center the page highlighter with simple math.
        // Height of navigation links (56) minus highlight height (40) equals 16. 16 divided by 2 is 8.
        const HighlightOffset: number = 8;

        if (pathname.endsWith(`/`) && ButtonHome != null) return (ButtonHome as any).offsetTop + HighlightOffset;
        if (pathname.endsWith(`/account`) && ButtonSettings != null)
            return (ButtonSettings as any).offsetTop + HighlightOffset;
        if (pathname.includes('/billing') && ButtonSettingsBilling != null)
            return (ButtonSettingsBilling as any).offsetTop + HighlightOffset;
        if ((pathname.includes('/support') || pathname.includes('/tickets')) && ButtonSupport != null)
            return (ButtonSupport as any).offsetTop + HighlightOffset;

        return '0';
    };

    const top = calculateTop(location.pathname);

    const [height, setHeight] = useState('40px');

    useEffect(() => {
        setHeight('34px');
        const timeoutId = setTimeout(() => setHeight('40px'), 200);
        return () => clearTimeout(timeoutId);
    }, [top]);

    const fetchAccountData = useCallback(() => {
        if (user) {
            getAccountData()
                .then((data) => {
                    setAccountData(data);
                })
                .catch((error) => {
                    console.error('Failed to load account data for sidebar:', error);
                });
        }
    }, [user]);

    useEffect(() => {
        fetchAccountData();
    }, [fetchAccountData]);

    // Listen for gravatar style updates from AccountOverviewContainer
    useEffect(() => {
        const handleGravatarStyleUpdate = (event: Event) => {
            const customEvent = event as CustomEvent<{ gravatar_style: string }>;
            // Update accountData instantly with the new gravatar style
            if (accountData) {
                setAccountData({
                    ...accountData,
                    gravatar_style: customEvent.detail.gravatar_style,
                });
            } else {
                // If accountData isn't loaded yet, refetch it
                fetchAccountData();
            }
        };

        window.addEventListener('gravatar-style-updated', handleGravatarStyleUpdate);
        return () => {
            window.removeEventListener('gravatar-style-updated', handleGravatarStyleUpdate);
        };
    }, [accountData, fetchAccountData]);

    const gravatarStyle = (accountData?.gravatar_style || 'identicon') as GravatarStyle;
    const userAvatarUrl = user?.email ? getGravatarUrl(user.email, 22, gravatarStyle) : null;

    return (
        <Fragment key={'dashboard-router'}>
            {/* Mobile Top Bar */}
            <MobileTopBar
                onMenuToggle={toggleMobileMenu}
                onTriggerLogout={onTriggerLogout}
                onSelectAdminPanel={onSelectAdminPanel}
                rootAdmin={rootAdmin}
            />

            {/* Mobile Full Screen Menu */}
            <DashboardMobileMenu isVisible={isMobileMenuVisible} onClose={closeMobileMenu} />

            <div className='flex flex-row w-full lg:pt-0 pt-16'>
                {/* Desktop Sidebar */}
                <MainSidebar
                    className={`hidden lg:flex lg:relative lg:shrink-0 bg-[#1a1a1a]${isSidebarCollapsed ? ' collapsed' : ''}`}
                >
                    <div
                        className='absolute bg-brand w-[3px] h-10 left-0 rounded-full pointer-events-none '
                        style={{
                            top,
                            height,
                            opacity: top === '0' ? 0 : 1,
                            transition:
                                'linear(0,0.006,0.025 2.8%,0.101 6.1%,0.539 18.9%,0.721 25.3%,0.849 31.5%,0.937 38.1%,0.968 41.8%,0.991 45.7%,1.006 50.1%,1.015 55%,1.017 63.9%,1.001) 390ms',
                        }}
                    />
                    <div
                        className='absolute bg-brand w-12 h-10 blur-2xl left-0 rounded-full pointer-events-none'
                        style={{
                            top,
                            opacity: top === '0' ? 0 : 0.5,
                            transition:
                                'top linear(0,0.006,0.025 2.8%,0.101 6.1%,0.539 18.9%,0.721 25.3%,0.849 31.5%,0.937 38.1%,0.968 41.8%,0.991 45.7%,1.006 50.1%,1.015 55%,1.017 63.9%,1.001) 390ms',
                        }}
                    />
                    <div className='relative flex flex-row items-center justify-between h-8'>
                        <NavLink to={'/'} className='flex shrink-0 h-8 w-fit'>
                            <Logo uniqueId='desktop-sidebar' />
                        </NavLink>
                    </div>
                    <button
                        type='button'
                        aria-label={isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        onClick={() => setIsSidebarCollapsed((prev) => !prev)}
                        className='hidden lg:flex items-center justify-center w-7 h-12 rounded-full bg-[#ffffff11] hover:bg-[#ffffff1f] text-white/70 hover:text-white cursor-pointer transition-colors absolute -right-[14px] top-1/2 -translate-y-1/2 shadow-md border border-white/10'
                    >
                        {isSidebarCollapsed ? (
                            <ChevronRight width={18} height={18} fill='currentColor' />
                        ) : (
                            <ChevronLeft width={18} height={18} fill='currentColor' />
                        )}
                    </button>
                    <div aria-hidden className='mt-8 mb-4 bg-[#ffffff33] min-h-[1px] w-6'></div>
                    <ul data-pyro-subnav-routes-wrapper='' className='pyro-subnav-routes-wrapper'>
                        <NavLink
                            to={'/'}
                            end
                            className={`flex flex-row items-center ${isSidebarCollapsed ? 'justify-center' : ''}`}
                            ref={NavigationHome}
                        >
                            <House width={22} height={22} fill='currentColor' />
                            {!isSidebarCollapsed && <p>Your Servers</p>}
                        </NavLink>
                        {/* NOT FINISHED YET
                        <NavLink to={'/vps-servers'} end className='flex flex-row items-center'>
                            <House width={22} height={22} fill='currentColor' />
                            <p>Your VPS Servers</p>
                        </NavLink> */}
                        {/* Spacer pushes the following links to the bottom */}
                        <div className='pyro-subnav-spacer' />

                        {/* Admin / Website / Settings full-width items above the bottom icon row */}
                        {rootAdmin && (
                            <div
                                onClick={onSelectAdminPanel}
                                className={`flex flex-row items-center cursor-pointer ${
                                    isSidebarCollapsed ? 'justify-center' : ''
                                }`}
                            >
                                <Shield width={22} height={22} />
                                {!isSidebarCollapsed && <p>Admin Panel</p>}
                            </div>
                        )}
                        <div
                            onClick={onTriggerReturnToWebsite}
                            className={`flex flex-row items-center cursor-pointer ${
                                isSidebarCollapsed ? 'justify-center' : ''
                            }`}
                        >
                            <ShoppingBasket width={22} height={22} />
                            {!isSidebarCollapsed && <p>Purchase Server</p>}
                        </div>
                        <NavLink
                            to={'/account'}
                            end
                            className={`flex flex-row items-center ${
                                isSidebarCollapsed ? 'justify-center' : ''
                            }`}
                            ref={NavigationSettings}
                        >
                            {userAvatarUrl ? (
                                <img src={userAvatarUrl} alt='Settings' className='w-[22px] h-[22px] rounded-full' />
                            ) : (
                                <div className='w-[22px] h-[22px] rounded-full bg-zinc-600' />
                            )}
                            {!isSidebarCollapsed && <p>Settings</p>}
                        </NavLink>

                        {/* Separator above bottom icon actions — match top divider, force 1px height and wider width */}
                        <div
                            aria-hidden
                            className='mt-6 mb-3 bg-[#ffffff33] flex-none'
                            style={{ height: '1px', width: '64px' }}
                        />

                        {/* Bottom links as icon-only row; stack vertically when collapsed to avoid clipping */}
                        <Tooltip.Provider delayDuration={150}>
                            <div
                                className={`pt-2 flex ${
                                    isSidebarCollapsed
                                        ? 'flex-col items-center justify-end gap-6 pb-2'
                                        : 'flex-row items-center justify-between gap-4'
                                }`}
                            >
                                <Tooltip.Root>
                                    <Tooltip.Trigger asChild>
                                        <NavLink
                                            to={'/billing'}
                                            end
                                            className='flex flex-row items-center justify-center'
                                            ref={NavigationSettingsBilling}
                                        >
                                            <CircleDollar width={22} height={22} fill='currentColor' />
                                        </NavLink>
                                    </Tooltip.Trigger>
                                    <Tooltip.Portal>
                                        <Tooltip.Content
                                            side='top'
                                            sideOffset={6}
                                            className='px-3 py-2 text-sm rounded-lg border border-white/10 bg-[#050608] text-white shadow-[0_14px_40px_rgba(0,0,0,0.85)] z-50 opacity-0 scale-95 data-[state=delayed-open]:opacity-100 data-[state=delayed-open]:scale-100 transition-all duration-150 ease-out'
                                        >
                                            Billing
                                            <Tooltip.Arrow className='fill-[#050608]' />
                                        </Tooltip.Content>
                                    </Tooltip.Portal>
                                </Tooltip.Root>

                                <Tooltip.Root>
                                    <Tooltip.Trigger asChild>
                                        <NavLink
                                            to={'/support'}
                                            end
                                            className='flex flex-row items-center justify-center'
                                            ref={NavigationSupport}
                                        >
                                            <CircleQuestion width={22} height={22} fill='currentColor' />
                                        </NavLink>
                                    </Tooltip.Trigger>
                                    <Tooltip.Portal>
                                        <Tooltip.Content
                                            side='top'
                                            sideOffset={6}
                                            className='px-3 py-2 text-sm rounded-lg border border-white/10 bg-[#050608] text-white shadow-[0_14px_40px_rgba(0,0,0,0.85)] z-50 opacity-0 scale-95 data-[state=delayed-open]:opacity-100 data-[state=delayed-open]:scale-100 transition-all duration-150 ease-out'
                                        >
                                            Support
                                            <Tooltip.Arrow className='fill-[#050608]' />
                                        </Tooltip.Content>
                                    </Tooltip.Portal>
                                </Tooltip.Root>

                                <Tooltip.Root>
                                    <Tooltip.Trigger asChild>
                                        <button
                                            type='button'
                                            onClick={onTriggerLogout}
                                            className='flex flex-row items-center justify-center text-white/80 hover:text-white cursor-pointer'
                                        >
                                            <span className='text-lg leading-none'>&#x21AA;</span>
                                        </button>
                                    </Tooltip.Trigger>
                                    <Tooltip.Portal>
                                        <Tooltip.Content
                                            side='top'
                                            sideOffset={6}
                                            className='px-3 py-2 text-sm rounded-lg border border-white/10 bg-[#050608] text-white shadow-[0_14px_40px_rgba(0,0,0,0.85)] z-50 opacity-0 scale-95 data-[state=delayed-open]:opacity-100 data-[state=delayed-open]:scale-100 transition-all duration-150 ease-out'
                                        >
                                            Log out
                                            <Tooltip.Arrow className='fill-[#050608]' />
                                        </Tooltip.Content>
                                    </Tooltip.Portal>
                                </Tooltip.Root>
                            </div>
                        </Tooltip.Provider>
                    </ul>
                </MainSidebar>

                <Suspense fallback={null}>
                    <MainWrapper className='w-full'>
                        <main
                            data-pyro-main=''
                            data-pyro-transitionrouter=''
                            className='relative inset-[1px] w-full h-full overflow-y-auto overflow-x-hidden rounded-md bg-[#08080875]'
                        >
                            <Routes>
                                <Route path='' element={<DashboardContainer />} />

                                {routes.account.map(({ route, component: Component }) => (
                                    <Route
                                        key={route}
                                        path={`/account/${route}`.replace('//', '/')}
                                        element={<Component />}
                                    />
                                ))}

                                <Route path='/billing' element={<BillingContainer />} />

                                <Route path='/support' element={<SupportContainer />} />
                                <Route path='/support/tickets' element={<TicketsContainer />} />
                                <Route path='/support/tickets/:id' element={<TicketDetailContainer />} />

                                <Route path='/vps-servers' element={<VpsContainer />} />

                                <Route path='*' element={<NotFound />} />
                            </Routes>
                        </main>
                    </MainWrapper>
                </Suspense>
            </div>
        </Fragment>
    );
};

export default DashboardRouter;
