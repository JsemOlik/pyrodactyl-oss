import { useInitials } from '@/hooks/use-initials';
import { Menu, X } from 'lucide-react';
import React from 'react';
import { Link, useNavigate } from 'react-router-dom';

import ActionButton from '@/components/elements/ActionButton';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/elements/DropdownMenu';
import Logo from '@/components/elements/PyroLogo';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { UserMenuContent } from '@/components/user-menu-content';

import { getGravatarUrl } from '@/lib/gravatar';

import { useStoreState } from '@/state/hooks';

type NavItem = {
    label: string;
    href: string;
};

const navItems: NavItem[] = [
    { label: 'Game Hosting', href: '/hosting' },
    { label: 'VPS', href: '/hosting' },
    { label: 'About Us', href: '/' },
];

export default function Navbar() {
    const [open, setOpen] = React.useState(false);
    const [scrolled, setScrolled] = React.useState(false);
    const navigate = useNavigate();
    const user = useStoreState((state) => state.user.data);
    const getInitials = useInitials();

    React.useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 0);
        window.addEventListener('scroll', onScroll);
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const userAvatarUrl = user?.email ? getGravatarUrl(user.email, 80) : null;
    const userInitials = user?.username ? getInitials(user.username) : '?';

    return (
        <>
            <div className='pointer-events-none fixed inset-x-0 top-0 z-[1500] flex w-full max-w-full justify-center overflow-x-hidden px-3 py-2 text-white'>
                <div
                    className={`pointer-events-auto flex h-12 w-full min-w-0 items-center justify-between px-2 transition-all duration-200 ease-in-out ${
                        scrolled
                            ? 'max-w-7xl rounded-2xl border border-white/10 bg-black/60 shadow-lg backdrop-blur-md'
                            : 'max-w-6xl rounded-none border-none bg-transparent shadow-none'
                    }`}
                >
                    {/* Left: Logo */}
                    <Link to='/' className='flex items-center gap-2 rounded-lg px-2 py-1'>
                        <Logo className='h-5 w-5' />
                        <span className='font-yaro tracking-tight'>OSphere</span>
                    </Link>

                    {/* Center: Links */}
                    <nav className='hidden min-w-0 items-center gap-1 md:flex'>
                        {navItems.map((item) => (
                            <NavLink key={item.href} href={item.href}>
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>

                    {/* Right: Auth buttons (desktop) / menu (mobile) */}
                    <div className='flex min-w-0 items-center gap-2'>
                        <div className='hidden min-w-0 items-center gap-2 sm:flex'>
                            {user ? (
                                <>
                                    <ActionButton
                                        variant='secondary'
                                        size='sm'
                                        onClick={() => navigate('/')}
                                        className='h-8 rounded-lg border-white/15 px-3'
                                    >
                                        Dashboard
                                    </ActionButton>

                                    {user.rootAdmin && (
                                        <ActionButton
                                            variant='secondary'
                                            size='sm'
                                            onClick={() => {
                                                window.location.href = '/admin';
                                            }}
                                            className='h-8 rounded-lg border-brand/30 bg-brand/10 px-3 text-brand'
                                        >
                                            Admin
                                        </ActionButton>
                                    )}

                                    {/* Avatar dropdown */}
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                className='size-9 rounded-full p-0 hover:bg-white/10 transition-colors flex items-center justify-center'
                                                aria-label='User menu'
                                            >
                                                <Avatar className='size-8 overflow-hidden rounded-full'>
                                                    <AvatarImage src={userAvatarUrl || undefined} alt={user.username} />
                                                    <AvatarFallback className='rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white'>
                                                        {userInitials}
                                                    </AvatarFallback>
                                                </Avatar>
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align='end'
                                            sideOffset={8}
                                            className='z-[2000] w-56 rounded-xl border border-white/10 bg-neutral-900/95 text-white shadow-xl backdrop-blur-md'
                                        >
                                            <UserMenuContent user={user} />
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </>
                            ) : (
                                <>
                                    <ActionButton
                                        variant='secondary'
                                        size='sm'
                                        onClick={() => navigate('/auth/login')}
                                        className='h-8 rounded-lg px-3'
                                    >
                                        Log in
                                    </ActionButton>

                                    <ActionButton
                                        variant='primary'
                                        size='sm'
                                        onClick={() => navigate('/auth/register')}
                                        className='h-8 rounded-lg px-3'
                                    >
                                        Register
                                    </ActionButton>
                                </>
                            )}
                        </div>

                        {/* Mobile menu toggle */}
                        <button
                            className='rounded-lg p-2 hover:bg-white/5 md:hidden'
                            aria-label='Toggle menu'
                            onClick={() => setOpen((v) => !v)}
                        >
                            {open ? <X className='h-5 w-5' /> : <Menu className='h-5 w-5' />}
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile sheet */}
            <div
                className={`fixed inset-x-0 top-14 z-[1200] mt-2 w-full overflow-x-hidden text-white transition-all md:hidden ${
                    open ? 'pointer-events-auto opacity-100' : 'pointer-events-none -translate-y-2 opacity-0'
                }`}
            >
                <div className='mx-3 rounded-2xl border border-white/10 bg-black/70 p-2 shadow-xl backdrop-blur-md'>
                    <div className='flex flex-col'>
                        {navItems.map((item) => (
                            <Link
                                key={item.href}
                                to={item.href}
                                onClick={() => setOpen(false)}
                                className='rounded-xl px-3 py-2 text-sm hover:bg-white/10'
                            >
                                {item.label}
                            </Link>
                        ))}
                        <div className='mt-2 flex flex-col gap-2 border-t border-white/10 pt-2'>
                            {user ? (
                                <>
                                    <Link
                                        to='/'
                                        onClick={() => setOpen(false)}
                                        className='rounded-xl bg-white/5 px-3 py-2 text-center hover:bg-white/10'
                                    >
                                        Dashboard
                                    </Link>
                                    {user.rootAdmin && (
                                        <button
                                            onClick={() => {
                                                setOpen(false);
                                                window.location.href = '/admin';
                                            }}
                                            className='rounded-xl bg-brand/10 px-3 py-2 text-center hover:bg-brand/20 text-brand border border-brand/30'
                                        >
                                            Admin
                                        </button>
                                    )}
                                </>
                            ) : (
                                <>
                                    <Link
                                        to='/auth/login'
                                        onClick={() => setOpen(false)}
                                        className='rounded-xl px-3 py-2 text-center hover:bg-white/10'
                                    >
                                        Portal (Log in)
                                    </Link>
                                    <Link
                                        to='/auth/register'
                                        onClick={() => setOpen(false)}
                                        className='rounded-xl bg-blue-600 px-3 py-2 text-center text-white hover:bg-blue-700'
                                    >
                                        Register
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Spacer */}
            <div className='h-16' />
        </>
    );
}

function NavLink({ href, children }: { href: string; children: React.ReactNode }) {
    return (
        <Link to={href} className='truncate rounded-xl px-3 py-1.5 text-sm transition-colors hover:bg-white/10'>
            {children}
        </Link>
    );
}
