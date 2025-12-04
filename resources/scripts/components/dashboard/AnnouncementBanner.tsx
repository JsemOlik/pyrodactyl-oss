// import React from 'react';
import clsx from 'clsx';

import { Announcement } from '@/api/getAnnouncements';

type Props = { announcements: Announcement[] };

const styleFor = (type: Announcement['type']) =>
    clsx(
        'rounded-md px-4 py-3 text-sm mb-3',
        type === 'success' && 'bg-green-500/15 text-green-200 border border-green-500/30',
        type === 'info' && 'bg-blue-500/15 text-blue-200 border border-blue-500/30',
        type === 'warning' && 'bg-yellow-500/15 text-yellow-200 border border-yellow-500/30',
        type === 'danger' && 'bg-red-500/15 text-red-200 border border-red-500/30',
    );

export default function AnnouncementBanner({ announcements }: Props) {
    if (!announcements?.length) return null;
    return (
        <div className='mb-4'>
            {announcements.map((a) => (
                <>
                    <div className='flex items-center gap-4 flex-wrap min-w-0 flex-1'>
                        <h1 className='text-2xl sm:text-3xl md:text-4xl lg:text-[52px] font-extrabold leading-[98%] tracking-[-0.02em] sm:tracking-[-0.06em] md:tracking-[-0.14rem] break-words mb-6'>
                            Announcement
                        </h1>
                    </div>
                    <div key={a.id} className={styleFor(a.type)}>
                        <div className='font-semibold'>{a.title}</div>
                        <div className='opacity-90'>{a.message}</div>
                    </div>
                </>
            ))}
        </div>
    );
}
