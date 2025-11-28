import React from 'react';
import Navigation from '@/themes/default/components/Navigation';
import Footer from '@/themes/default/components/Footer';
import { cn } from '@/lib/utils';

interface MainLayoutProps {
    children: React.ReactNode;
    categories?: Array<{ id: number; name: string }>;
    cartItemsCount?: number;
    className?: string;
}

export default function MainLayout({ children, categories = [], cartItemsCount = 0, className }: MainLayoutProps) {
    return (
        <div className={cn("min-h-screen bg-background", className)}>
            {/* Navigation */}
            <Navigation categories={categories} cartItemsCount={cartItemsCount} />

            {/* Main Content */}
            <main className="pb-0 md:pb-0">
                {children}
            </main>

            {/* Footer */}
            <div className="md-hidden h-[70px]"></div>
            <Footer />
        </div>
    );
}