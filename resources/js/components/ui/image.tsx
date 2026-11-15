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
    showPlaceholder?: boolean;
    priority?: boolean;
    w?: number;
    h?: number;
    fit?: 'contain' | 'max' | 'fill' | 'stretch' | 'crop';
    fm?: 'jpg' | 'pjpg' | 'png' | 'gif' | 'webp' | 'avif' | 'tiff';
}

export const ImageWithFallback = React.forwardRef<HTMLImageElement, ImageWithFallbackProps>(
    ({
        src,
        alt,
        fallback,
        className,
        forceWidth,
        mobileBreakpoint = 480,
        showPlaceholder = false,
        priority = false,
        w,
        h,
        fit,
        fm,
        sizes,
        loading: propLoading,
        fetchPriority: propFetchPriority,
        decoding = 'async',
        ...props
    }, ref) => {

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

        const isExternalOrStatic = !imgSrc || imgSrc.startsWith('data:') || imgSrc === finalFallback;
        const isOptimizedVariant = !hasError && !isExternalOrStatic && /\.1920\.webp($|\?)/i.test(imgSrc);

        let displaySrc = imgSrc;
        let placeholderUrl: string | null = null;
        let url480: string | undefined = undefined;
        let url1920: string | undefined = undefined;

        if (isOptimizedVariant) {
            const url20 = imgSrc.replace(/\.1920\.webp(\?.*)?$/i, '.20.webp$1');
            url480 = imgSrc.replace(/\.1920\.webp(\?.*)?$/i, '.480.webp$1');
            url1920 = imgSrc;

            if (showPlaceholder) {
                placeholderUrl = url20;
            }

            if (forceWidth === 20) {
                displaySrc = url20;
            } else if (forceWidth === 480) {
                displaySrc = url480;
            } else if (forceWidth === 1920) {
                displaySrc = url1920;
            } else {
                displaySrc = url1920;
            }
        }

        const usePicture = isOptimizedVariant && !forceWidth && !!url480 && !hasError;
        const effectiveLoading = propLoading || (priority ? 'eager' : 'lazy');
        const effectiveFetchPriority = propFetchPriority || (priority ? 'high' : (effectiveLoading === 'eager' ? 'auto' : 'low'));

        const mainImage = (
            <img
                ref={ref}
                src={displaySrc}
                alt={alt}
                loading={effectiveLoading}
                fetchPriority={effectiveFetchPriority}
                decoding={decoding}
                className={cn(
                    'transition-opacity duration-300 relative z-10 w-full h-full object-cover',
                    isLoading ? 'opacity-0' : 'opacity-100'
                )}
                onError={handleError}
                onLoad={handleLoad}
                {...props}
            />
        );

        return (
            <div className={cn("relative overflow-hidden bg-muted/20", className)}>
                {/* CSS skeleton background while loading to prevent CLS with zero network overhead */}
                {isLoading && (
                    <div className="absolute inset-0 bg-muted/40 animate-pulse pointer-events-none" aria-hidden="true" />
                )}

                {/* Optional 20w Blurred Placeholder (only if explicitly requested via showPlaceholder) */}
                {isLoading && placeholderUrl && !hasError && (
                    <img
                        src={placeholderUrl}
                        alt=""
                        loading={effectiveLoading}
                        className="absolute inset-0 w-full h-full object-cover blur-lg scale-110 transition-opacity duration-500 pointer-events-none"
                        style={{ filter: 'blur(20px)' }}
                        aria-hidden="true"
                    />
                )}

                {/* Main Responsive / Forced Width Image */}
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
