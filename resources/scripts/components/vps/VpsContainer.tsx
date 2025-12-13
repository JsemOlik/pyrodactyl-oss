import { House } from '@gravity-ui/icons';
import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import useSWR from 'swr';

import PageContentBlock from '@/components/elements/PageContentBlock';
import Pagination from '@/components/elements/Pagination';
import { PageListContainer } from '@/components/elements/pages/PageList';
import VpsRow from '@/components/vps/VpsRow';

import { PaginatedResult } from '@/api/http';
import getVpsServers from '@/api/vps/getVpsServers';
import { Vps } from '@/api/vps/types';

import useFlash from '@/plugins/useFlash';

import { MainPageHeader } from '../elements/MainPageHeader';

const VpsContainer = () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const { data: vpsServers, error } = useSWR<PaginatedResult<Vps>>(
        ['/api/client/vps-servers', page],
        () => getVpsServers({ page }),
        { revalidateOnFocus: false },
    );

    useEffect(() => {
        if (!vpsServers) return;
        if (vpsServers.pagination.currentPage > 1 && !vpsServers.items.length) {
            setPage(1);
        }
    }, [vpsServers?.pagination.currentPage, vpsServers?.items.length]);

    useEffect(() => {
        if (error) {
            clearAndAddHttpError({ error, key: 'vps' });
        } else {
            clearFlashes('vps');
        }
    }, [error]);

    return (
        <PageContentBlock title={'Your VPS Servers'} showFlashKey={'vps'}>
            <MainPageHeader
                icon={House}
                title={'Your VPS Servers'}
                description={'Manage your virtual private servers'}
            />

            {!vpsServers ? (
                <div className='flex justify-center items-center py-12'>
                    <div className='text-zinc-400'>Loading VPS servers...</div>
                </div>
            ) : vpsServers.items.length === 0 ? (
                <PageListContainer>
                    <div className='text-center py-12'>
                        <p className='text-zinc-400 text-lg mb-4'>You don't have any VPS servers yet.</p>
                        <p className='text-zinc-500 text-sm'>
                            <a href='/' className='text-brand hover:text-brand/80 transition-colors'>
                                Create your first VPS server
                            </a>
                        </p>
                    </div>
                </PageListContainer>
            ) : (
                <>
                    <PageListContainer>
                        {vpsServers.items.map((vps) => (
                            <VpsRow key={vps.uuid} vps={vps} />
                        ))}
                    </PageListContainer>

                    {vpsServers.pagination.totalPages > 1 && (
                        <Pagination pagination={vpsServers.pagination} onPageSelect={(page) => setPage(page)} />
                    )}
                </>
            )}
        </PageContentBlock>
    );
};

export default VpsContainer;
