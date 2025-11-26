import React, { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface ImageWithFallbackProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src?: string;
    alt: string;
    fallback?: string;
    className?: string;
}

const DEFAULT_FALLBACK = '/images/placeholder-food.svg';

export const ImageWithFallback = React.forwardRef<HTMLImageElement, ImageWithFallbackProps>(
    ({ src, alt, fallback = DEFAULT_FALLBACK, className, ...props }, ref) => {
        const [imgSrc, setImgSrc] = useState(src || fallback);
        const [isLoading, setIsLoading] = useState(true);
        const [hasError, setHasError] = useState(false);

        useEffect(() => {
            setImgSrc(src || fallback);
            setIsLoading(true);
            setHasError(false);
        }, [src, fallback]);

        const handleError = () => {
            if (imgSrc !== fallback) {
                setImgSrc(fallback);
                setHasError(true);
            }
            setIsLoading(false);
        };

        const handleLoad = () => {
            setIsLoading(false);
        };

        return (
            <img
                ref={ref}
                src={imgSrc}
                alt={alt}
                className={cn(
                    'transition-opacity duration-300',
                    isLoading && 'opacity-0',
                    !isLoading && 'opacity-100',
                    className
                )}
                onError={handleError}
                onLoad={handleLoad}
                {...props}
            />
        );
    }
);

ImageWithFallback.displayName = 'ImageWithFallback';
