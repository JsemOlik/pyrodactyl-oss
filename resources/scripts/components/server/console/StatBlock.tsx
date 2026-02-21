import clsx from 'clsx';
import { useMemo } from 'react';
import type { CSSProperties } from 'react';

import CopyOnClick from '@/components/elements/CopyOnClick';

import styles from './style.module.css';

interface StatBlockProps {
    title?: string;
    copyOnClick?: string;
    children: React.ReactNode;
    className?: string;
    style?: CSSProperties;
    progress?: number | null;
    onClick?: () => void;
}

const StatBlock = ({ title, copyOnClick, className, style, progress, children, onClick }: StatBlockProps) => {
    const progressStyle = useMemo(() => {
        if (progress === undefined || progress === null) return style;

        const fill = progress.toFixed(2);

        return {
            ...style,
            backgroundImage: `linear-gradient(to top, color-mix(in srgb, var(--color-brand) 40%, transparent), color-mix(in srgb, color-mix(in srgb, var(--color-brand), white 20%) 60%, transparent)), linear-gradient(to bottom, #ffffff08, #ffffff05)`,
            backgroundSize: `${fill}% 100%, 100% 100%`,
            backgroundRepeat: 'no-repeat',
        };
    }, [progress, style]);

    return (
        <CopyOnClick text={copyOnClick}>
            <div
                onClick={onClick}
                style={progressStyle}
                className={clsx(
                    'bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-3 sm:p-4 hover:border-[#ffffff20] transition-all duration-150 group shadow-sm',
                    onClick ? 'cursor-pointer' : 'cursor-default',
                    className,
                )}
            >
                <div className={'flex flex-col justify-center overflow-hidden w-full cursor-default'}>
                    {title ? (
                        <p className='leading-tight text-xs text-zinc-400 mb-2 uppercase tracking-wide font-medium'>
                            {title}
                        </p>
                    ) : null}
                    <div
                        className={
                            'text-lg sm:text-xl font-bold leading-tight tracking-tight w-full truncate text-zinc-100 group-hover:text-white transition-colors duration-150'
                        }
                    >
                        {children}
                    </div>
                </div>
            </div>
        </CopyOnClick>
    );
};

export default StatBlock;
