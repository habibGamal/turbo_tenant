import React from 'react';
import Navigation from '@/themes/default/components/Navigation';
import Footer from '@/themes/default/components/Footer';
import { PageTransition } from '@/components/PageTransition';
import { cn } from '@/lib/utils';

interface MainLayoutProps {
    children: React.ReactNode;
    categories?: Array<{ id: number; name: string }>;
    cartItemsCount?: number;
    className?: string;
}

export default function MainLayout({ children, categories = [], cartItemsCount = 0, className }: MainLayoutProps) {
    return (
        <div className={cn("min-h-screen bg-background pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-0", className)}>
            {/* Navigation */}
            <Navigation categories={categories} cartItemsCount={cartItemsCount} />

            {/* Main Content with Page Transition */}
            <div>
                <PageTransition>
                    {children}
                </PageTransition>
            </div>

            {/* Footer */}
            <Footer />
        </div>
    );
}
