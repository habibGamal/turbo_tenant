import React, { useEffect } from "react";
import { useLocation } from "react-router-dom";
import { cn } from "@/lib/utils";

interface PageTransitionProps {
    children: React.ReactNode;
    className?: string;
    /**
     * Optional custom key to trigger the transition.
     * Defaults to location.pathname to animate across page changes
     * without flickering during query param updates.
     */
    transitionKey?: string;
    /**
     * Whether to reset window scroll position to the top on transition.
     * Defaults to true.
     */
    scrollToTop?: boolean;
}

export function PageTransition({
    children,
    className,
    transitionKey,
    scrollToTop = true,
}: PageTransitionProps) {
    const location = useLocation();
    const activeKey = transitionKey ?? location.pathname;

    useEffect(() => {
        if (scrollToTop) {
            window.scrollTo({ top: 0, left: 0, behavior: "instant" });
        }
    }, [activeKey, scrollToTop]);

    return (
        <div
            key={activeKey}
            className={cn("animate-page-enter w-full", className)}
        >
            {children}
        </div>
    );
}

export default PageTransition;
