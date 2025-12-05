import React from 'react';

import { cn } from '@/lib/utils';

interface AvatarProps {
    className?: string;
    children: React.ReactNode;
}

interface AvatarImageProps {
    src?: string;
    alt?: string;
    className?: string;
}

interface AvatarFallbackProps {
    className?: string;
    children: React.ReactNode;
}

const Avatar = React.forwardRef<HTMLDivElement, AvatarProps>(({ className, children, ...props }, ref) => {
    return (
        <div ref={ref} className={cn('relative flex shrink-0 overflow-hidden rounded-full', className)} {...props}>
            {children}
        </div>
    );
});
Avatar.displayName = 'Avatar';

const AvatarImage = React.forwardRef<HTMLImageElement, AvatarImageProps>(({ className, src, alt, ...props }, ref) => {
    if (!src) {
        return null;
    }
    return (
        <img
            ref={ref}
            src={src}
            alt={alt}
            className={cn('aspect-square h-full w-full object-cover', className)}
            {...props}
        />
    );
});
AvatarImage.displayName = 'AvatarImage';

const AvatarFallback = React.forwardRef<HTMLDivElement, AvatarFallbackProps>(
    ({ className, children, ...props }, ref) => {
        return (
            <div ref={ref} className={cn('flex h-full w-full items-center justify-center', className)} {...props}>
                {children}
            </div>
        );
    },
);
AvatarFallback.displayName = 'AvatarFallback';

export { Avatar, AvatarImage, AvatarFallback };
