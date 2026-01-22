import React, { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';

interface ImageWithFallbackProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src?: string;
    alt: string;
    fallback?: string;
    className?: string;
    w?: number;
    h?: number;
    fit?: 'contain' | 'max' | 'fill' | 'stretch' | 'crop';
    fm?: 'jpg' | 'pjpg' | 'png' | 'gif' | 'webp' | 'avif' | 'tiff';
}

const GENERATED_WIDTHS = [320, 480, 640, 768, 960, 1280, 1920, 2560];

export const ImageWithFallback = React.forwardRef<HTMLImageElement, ImageWithFallbackProps>(
    ({ src, alt, fallback, className, w, h, fit, fm, sizes = '100vw', ...props }, ref) => {
        const { settings } = usePage().props;
        const defaultFallback = settings.image_placeholder || '/images/placeholder-food.svg';
        const finalFallback = fallback || defaultFallback;

        const [imgSrc, setImgSrc] = useState(src || finalFallback);
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

        const getOptimizedUrl = (url: string, width?: number, quality?: number) => {
            if (!url || url.startsWith('http') || url.startsWith('data:')) return url;

            const params = new URLSearchParams();
            if (width) {
                params.append('w', width.toString());
            } else if (w) {
                params.append('w', w.toString());
            }

            if (h && !width) params.append('h', h.toString()); // Only use height if not generating srcset width
            if (fit) params.append('fit', fit);
            if (fm) params.append('fm', fm);
            if (quality) params.append('q', quality.toString());

            const queryString = params.toString();
            return `/storage/${url}${queryString ? `?${queryString}` : ''}`;
        };

        const displaySrc = imgSrc === finalFallback ? imgSrc : getOptimizedUrl(imgSrc);

        const srcSet = imgSrc !== finalFallback && !imgSrc.startsWith('http') && !imgSrc.startsWith('data:')
            ? GENERATED_WIDTHS.map(width => {
                const url = getOptimizedUrl(imgSrc, width);
                const finalUrl = url.startsWith('/') ? url : `/storage/${url}`;
                return `${finalUrl} ${width}w`;
            }).join(', ')
            : undefined;

        // Tiny blurred placeholder URL
        const placeholderUrl = imgSrc !== finalFallback && !imgSrc.startsWith('http') && !imgSrc.startsWith('data:')
            ? getOptimizedUrl(imgSrc, 20) // Tiny width for blur
            : null;

        const finalPlaceholderUrl = placeholderUrl && (placeholderUrl.startsWith('/') ? placeholderUrl : `/storage/${placeholderUrl}`);


        return (
            <div className={cn("relative overflow-hidden", className)}>
                {/* Blurred Placeholder */}
                {isLoading && finalPlaceholderUrl && !hasError && (
                    <img
                        src={finalPlaceholderUrl}
                        alt={alt}
                        className={cn(
                            "absolute inset-0 w-full h-full object-cover blur-lg scale-110 transition-opacity duration-500",
                            // If loading is done, we fade this out, but we handle that by unmounting or opacity on the main image
                            // Actually, better to keep it behind and let the main image fade in on top
                        )}
                        style={{ filter: 'blur(20px)' }}
                        aria-hidden="true"
                    />
                )}

                <img
                    ref={ref}
                    src={displaySrc.startsWith('/') ? displaySrc : `/storage/${displaySrc}`}
                    srcSet={srcSet}
                    sizes={sizes}
                    alt={alt}
                    className={cn(
                        'transition-opacity duration-500 relative z-10', // Ensure it's above placeholder
                        isLoading ? 'opacity-0' : 'opacity-100',
                        // We remove className from here because we applied it to the wrapper div. 
                        // Wait, usually className is for the img. 
                        // If we wrap it, we might break layout if the parent expects an img.
                        // But for the placeholder to work effectively as a background, a wrapper is safest.
                        // However, if the user passes classes like 'w-10 h-10 rounded-full', applying them to the wrapper is correct.
                        // But object-cover etc should be on the img.
                        // Let's apply className to BOTH, but be careful.
                        // Actually, the best way for a drop-in replacement is to put the wrapper with the same classes, 
                        // and the img with w-full h-full.
                        "w-full h-full object-cover"
                    )}
                    onError={handleError}
                    onLoad={handleLoad}
                    {...props}
                />
            </div>
        );
    }
);

ImageWithFallback.displayName = 'ImageWithFallback';
