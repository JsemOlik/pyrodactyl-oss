import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import { Server } from '@/api/server/getServer';

import ServerRow from './ServerRow';

interface SortableServerRowProps {
    server: Server;
    className?: string;
    index: number;
}

const SortableServerRow = ({ server, className, index }: SortableServerRowProps) => {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: server.uuid });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        animationDelay: `${index * 50 + 50}ms`,
        animationTimingFunction:
            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
            className='transform-gpu skeleton-anim-2 relative cursor-grab active:cursor-grabbing'
        >
            {/* Drag handle - visual indicator */}
            <div
                className='absolute left-0 top-1/2 -translate-y-1/2 z-10 p-2 rounded-md transition-colors'
                style={{ marginLeft: '-2.5rem' }}
            >
                <svg
                    xmlns='http://www.w3.org/2000/svg'
                    width='20'
                    height='20'
                    viewBox='0 0 24 24'
                    fill='none'
                    stroke='currentColor'
                    strokeWidth='2'
                    strokeLinecap='round'
                    strokeLinejoin='round'
                    className='text-[#ffffff66]'
                >
                    <circle cx='9' cy='5' r='1' />
                    <circle cx='9' cy='12' r='1' />
                    <circle cx='9' cy='19' r='1' />
                    <circle cx='15' cy='5' r='1' />
                    <circle cx='15' cy='12' r='1' />
                    <circle cx='15' cy='19' r='1' />
                </svg>
            </div>
            <ServerRow className={className} server={server} isEditMode={true} />
        </div>
    );
};

export default SortableServerRow;
