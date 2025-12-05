import { useNavigate } from 'react-router-dom';

import { DropdownMenuSeparator } from '@/components/elements/DropdownMenu';

import http from '@/api/http';

import { UserData } from '@/state/user';

interface UserMenuContentProps {
    user: UserData;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const navigate = useNavigate();

    const handleLogout = () => {
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    const handleAdminPanel = () => {
        window.location.href = '/admin';
    };

    return (
        <>
            <div className='px-2 py-1.5'>
                <div className='text-sm font-medium text-white'>{user.username}</div>
                <div className='text-xs text-white/70 truncate'>{user.email}</div>
            </div>
            <DropdownMenuSeparator />
            <button
                onClick={() => navigate('/account')}
                className='w-full rounded-lg px-2 py-1.5 text-sm text-left text-white hover:bg-white/10 transition-colors'
            >
                Account Settings
            </button>
            {user.rootAdmin && (
                <button
                    onClick={handleAdminPanel}
                    className='w-full rounded-lg px-2 py-1.5 text-sm text-left text-white hover:bg-white/10 transition-colors flex items-center justify-between'
                >
                    Admin Panel
                    <span className='rounded-full bg-brand px-2 py-0.5 text-xs text-white'>Staff</span>
                </button>
            )}
            <DropdownMenuSeparator />
            <button
                onClick={handleLogout}
                className='w-full rounded-lg px-2 py-1.5 text-sm text-left text-white hover:bg-white/10 transition-colors'
            >
                Log Out
            </button>
        </>
    );
}
