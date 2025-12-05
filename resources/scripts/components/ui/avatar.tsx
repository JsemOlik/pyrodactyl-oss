import React, { useState, createContext, useContext } from 'react';

import { cn } from '@/lib/utils';

interface AvatarContextType {
    imageLoaded: boolean;
    imageError: boolean;
    setImageLoaded: (loaded: boolean) => void;
    setImageError: (error: boolean) => void;
}

const AvatarContext = createContext<AvatarContextType | undefined>(undefined);

const useAvatarContext = () => {
    const context = useContext(AvatarContext);
    if (!context) {
        throw new Error('AvatarImage and AvatarFallback must be used within an Avatar component');
    }
    return context;
};

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
    const [imageLoaded, setImageLoaded] = useState(false);
    const [imageError, setImageError] = useState(false);

    return (
        <AvatarContext.Provider value={{ imageLoaded, imageError, setImageLoaded, setImageError }}>
            <div ref={ref} className={cn('relative flex shrink-0 overflow-hidden rounded-full', className)} {...props}>
                {children}
            </div>
        </AvatarContext.Provider>
    );
});
Avatar.displayName = 'Avatar';

const AvatarImage = React.forwardRef<HTMLImageElement, AvatarImageProps>(({ className, src, alt, ...props }, ref) => {
    const { setImageLoaded, setImageError, imageError } = useAvatarContext();

    if (!src || imageError) {
        return null;
    }

    return (
        <img
            ref={ref}
            src={src}
            alt={alt}
            className={cn('aspect-square h-full w-full object-cover absolute inset-0', className)}
            onLoad={() => setImageLoaded(true)}
            onError={() => {
                setImageError(true);
                setImageLoaded(false);
            }}
            {...props}
        />
    );
});
AvatarImage.displayName = 'AvatarImage';

const AvatarFallback = React.forwardRef<HTMLDivElement, AvatarFallbackProps>(
    ({ className, children, ...props }, ref) => {
        const { imageLoaded, imageError } = useAvatarContext();
        const shouldShow = !imageLoaded || imageError;

        if (!shouldShow) {
            return null;
        }

        return (
            <div ref={ref} className={cn('flex h-full w-full items-center justify-center', className)} {...props}>
                {children}
            </div>
        );
    },
);
AvatarFallback.displayName = 'AvatarFallback';

export { Avatar, AvatarImage, AvatarFallback };
