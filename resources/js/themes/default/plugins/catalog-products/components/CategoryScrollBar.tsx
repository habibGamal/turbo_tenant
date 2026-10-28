import React, { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

export interface CategoryScrollItem {
    id: number;
    name: string;
    name_ar?: string;
    count?: number;
    sort_order?: number;
}

export interface CategoryScrollBarProps {
    categories: CategoryScrollItem[];
    activeCategoryId?: number | string | null;
    onSelectCategory: (categoryId: number, categoryName: string) => void;
    className?: string;
}

export default function CategoryScrollBar({
    categories,
    activeCategoryId,
    onSelectCategory,
    className,
}: CategoryScrollBarProps) {
    const { i18n } = useTranslation();
    const isRTL = i18n.language === 'ar';
    const buttonRefs = useRef<Record<string | number, HTMLButtonElement | null>>({});

    const getText = (text?: string, textAr?: string) => {
        if (isRTL && textAr) return textAr;
        return text || '';
    };

    // Auto-scroll the active tab into the center of the scrollbar
    useEffect(() => {
        if (activeCategoryId !== undefined && activeCategoryId !== null) {
            const activeEl = buttonRefs.current[activeCategoryId];
            if (activeEl) {
                activeEl.scrollIntoView({
                    behavior: 'smooth',
                    inline: 'center',
                    block: 'nearest',
                });
            }
        }
    }, [activeCategoryId]);

    if (!categories || categories.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Categories"
            data-testid="category-scroll-bar"
            className={cn('w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden', className)}
        >
            <div className="flex items-center gap-2 min-w-max py-1 px-1">
                {categories.map((category) => {
                    const isActive = activeCategoryId === category.id;
                    const label = getText(category.name, category.name_ar);

                    return (
                        <button
                            key={category.id}
                            ref={(el) => {
                                buttonRefs.current[category.id] = el;
                            }}
                            type="button"
                            onClick={() => onSelectCategory(category.id, category.name)}
                            data-testid={`category-bar-tab-${category.id}`}
                            data-active={isActive ? 'true' : 'false'}
                            aria-current={isActive ? 'true' : undefined}
                            className={cn(
                                'shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs sm:text-sm transition-all duration-200 select-none cursor-pointer',
                                isActive
                                    ? 'bg-primary text-primary-foreground shadow-sm font-bold scale-[1.02]'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground font-medium'
                            )}
                        >
                            <span>{label}</span>
                            {typeof category.count === 'number' && (
                                <span
                                    className={cn(
                                        'text-[10px] px-1.5 py-0.2 rounded-full font-semibold',
                                        isActive
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-background/80 text-muted-foreground'
                                    )}
                                >
                                    {category.count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </nav>
    );
}
