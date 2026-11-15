import React, { useEffect, useRef } from "react";
import { useLocation, useNavigationType } from "react-router-dom";
import { cn } from "@/lib/utils";

if (typeof window !== "undefined" && "history" in window && "scrollRestoration" in window.history) {
    window.history.scrollRestoration = "manual";
}

const scrollPositions = new Map<string, number>();

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
    const navType = useNavigationType();
    const activeKey = transitionKey ?? location.pathname;

    const currentKeyRef = useRef(location.key);
    currentKeyRef.current = location.key;

    const prevKeyRef = useRef(location.key);
    const prevActiveKeyRef = useRef(activeKey);

    // Carry forward position on replace on the same page
    if (prevKeyRef.current !== location.key) {
        if (typeof window !== "undefined") {
            scrollPositions.set(prevKeyRef.current, window.scrollY);
        }
        if (prevActiveKeyRef.current === activeKey) {
            const currentPos =
                scrollPositions.get(prevKeyRef.current) ??
                (typeof window !== "undefined" ? window.scrollY : 0);
            scrollPositions.set(location.key, currentPos);
        }
        prevKeyRef.current = location.key;
        prevActiveKeyRef.current = activeKey;
    }

    // Record window.scrollY for current key on scroll (throttled with requestAnimationFrame)
    useEffect(() => {
        let rafId: number | null = null;
        const handleScroll = () => {
            if (rafId !== null) return;
            rafId = requestAnimationFrame(() => {
                scrollPositions.set(currentKeyRef.current, window.scrollY);
                rafId = null;
            });
        };

        window.addEventListener("scroll", handleScroll, { passive: true });
        return () => {
            if (rafId !== null) {
                cancelAnimationFrame(rafId);
            }
            window.removeEventListener("scroll", handleScroll);
        };
    }, []);

    // Save and restore scroll position on activeKey change
    useEffect(() => {
        if (!scrollToTop) {
            return;
        }

        if (navType === "POP") {
            const savedPosition = scrollPositions.get(location.key);
            if (savedPosition !== undefined) {
                let rafId: number;
                const startTime = performance.now();

                const retryScroll = () => {
                    const maxScroll = Math.max(
                        0,
                        document.documentElement.scrollHeight - window.innerHeight
                    );

                    if (maxScroll >= savedPosition) {
                        window.scrollTo({
                            top: savedPosition,
                            left: 0,
                            behavior: "instant",
                        });
                        return;
                    }

                    const now = performance.now();
                    if (now - startTime >= 1500) {
                        window.scrollTo({
                            top: Math.min(savedPosition, maxScroll),
                            left: 0,
                            behavior: "instant",
                        });
                        return;
                    }

                    rafId = requestAnimationFrame(retryScroll);
                };

                rafId = requestAnimationFrame(retryScroll);

                return () => {
                    cancelAnimationFrame(rafId);
                };
            }
        }

        // Otherwise (PUSH/REPLACE, or nothing saved) keep today's behavior: scroll to top with behavior "instant".
        window.scrollTo({ top: 0, left: 0, behavior: "instant" });
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
