import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';

import { ServerContext } from '@/state/server';

const DatabaseLogsContainer = () => {
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    return (
        <ServerContentBlock title='Logs'>
            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0'>
                <MainPageHeader title={serverName || 'Database'} />
                <div className='mt-6'>
                    <p className='text-white/60'>Database logs interface coming soon...</p>
                </div>
            </div>
        </ServerContentBlock>
    );
};

export default DatabaseLogsContainer;
