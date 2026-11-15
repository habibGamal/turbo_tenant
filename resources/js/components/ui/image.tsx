import React, { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';
import { useTenantSafe } from '@/core/context/TenantContext';

interface ImageWithFallbackProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src?: string;
    alt: string;
    fallback?: string;
    className?: string;
    forceWidth?: 20 | 480 | 1920;
    mobileBreakpoint?: number;
    w?: number;
    h?: number;
    fit?: 'contain' | 'max' | 'fill' | 'stretch' | 'crop';
    fm?: 'jpg' | 'pjpg' | 'png' | 'gif' | 'webp' | 'avif' | 'tiff';
}

export const ImageWithFallback = React.forwardRef<HTMLImageElement, ImageWithFallbackProps>(
    ({ src, alt, fallback, className, forceWidth, mobileBreakpoint = 480, w, h, fit, fm, sizes, ...props }, ref) => {

        const tenant = useTenantSafe();
        if(tenant?.isDevMode){
            return <div>DEV MODE</div>
        }
        const settings = tenant?.settings;
        const defaultFallback = settings?.image_placeholder || '/images/placeholder-food.svg';
        const finalFallback = fallback || defaultFallback;

        const [imgSrc, setImgSrc] = useState<string>(src || finalFallback);
        const [isLoading, setIsLoading] = useState(true);
        const [hasError, setHasError] = useState(false);

        useEffect(() => {
            setImgSrc(src || finalFallback);
            setIsLoading(true);
            setHasError(false);
        }, [src, finalFallback]);

        const handleError = () => {
            if (imgSrc !== finalFallback) {
                setImgSrc(finalFallback);
                setHasError(true);
            }
            setIsLoading(false);
        };

        const handleLoad = () => {
            setIsLoading(false);
        };

        // Determine variant URLs if using Ahead-of-Time 1920w WebP structure
        const isExternalOrStatic = !imgSrc || imgSrc.startsWith('http') || imgSrc.startsWith('data:') || imgSrc === finalFallback;

        let normalizedSrc = imgSrc;
        if (!isExternalOrStatic) {
            let path = imgSrc.startsWith('/') ? imgSrc.slice(1) : imgSrc;
            // Strip leading "storage/" if present
            if (path.startsWith('storage/')) {
                path = path.slice('storage/'.length);
            }
            // Ensure path starts with images/
            if (!path.startsWith('images/')) {
                path = `images/${path}`;
            }
            normalizedSrc = `/${path}`;
        }

        const isOptimizedVariant = !isExternalOrStatic && /\.1920\.webp$/i.test(normalizedSrc);

        let displaySrc = normalizedSrc;
        let placeholderUrl: string | null = null;
        let url480: string | undefined = undefined;
        let url1920: string | undefined = undefined;

        if (isOptimizedVariant) {
            const basePath = normalizedSrc.replace(/\.1920\.webp$/i, '');
            const url20 = `${basePath}.20.webp`;
            url480 = `${basePath}.480.webp`;
            url1920 = normalizedSrc;

            placeholderUrl = url20;

            if (forceWidth === 20) {
                displaySrc = url20;
            } else if (forceWidth === 480) {
                displaySrc = url480;
            } else if (forceWidth === 1920) {
                displaySrc = url1920;
            } else {
                displaySrc = url1920;
            }
        } else if (!isExternalOrStatic) {
            // Legacy / unmigrated fallback
            displaySrc = normalizedSrc;
        }

        const usePicture = isOptimizedVariant && !forceWidth && !!url480 && !hasError;

        const mainImage = (
            <img
                ref={ref}
                src={displaySrc}
                alt={alt}
                className={cn(
                    'transition-opacity duration-500 relative z-10 w-full h-full object-cover',
                    isLoading && placeholderUrl ? 'opacity-0' : 'opacity-100'
                )}
                onError={handleError}
                onLoad={handleLoad}
                {...props}
            />
        );

        return (
            <div className={cn("relative overflow-hidden", className)}>
                {/* Step 1: 20w Blurred Placeholder (LQIP) */}
                {isLoading && placeholderUrl && !hasError && (
                    <img
                        src={placeholderUrl}
                        alt={alt}
                        className="absolute inset-0 w-full h-full object-cover blur-lg scale-110 transition-opacity duration-500"
                        style={{ filter: 'blur(20px)' }}
                        aria-hidden="true"
                    />
                )}

                {/* Step 2 & 3: Main Responsive / Forced Width Image */}
                {usePicture ? (
                    <picture className="relative z-10 block w-full h-full">
                        <source media={`(max-width: ${mobileBreakpoint}px)`} srcSet={url480} />
                        {mainImage}
                    </picture>
                ) : (
                    mainImage
                )}
            </div>
        );
    }
);

ImageWithFallback.displayName = 'ImageWithFallback';
