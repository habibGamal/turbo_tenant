import React, { createContext, useContext, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { ThemeConfig, ThemeContextType } from '@/types/theme';
import { router } from '@inertiajs/react';

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

interface ThemeProviderProps {
    theme: ThemeConfig;
    children: React.ReactNode;
    defaultMode?: 'light' | 'dark';
}

export function ThemeProvider({ theme, children, defaultMode = 'light' }: ThemeProviderProps) {
    const [currentMode, setCurrentMode] = useState<'light' | 'dark'>('light');
    const section = useRef<HTMLDivElement>(null)
    const animationInjection = useRef<HTMLDivElement>(null);
    // Move DOM manipulation to useEffect to prevent hydration issues
    useEffect(() => {
        // Only run on client side
        if (typeof window === "undefined") return;

        // const html = document.querySelector("html") as HTMLHtmlElement;
        // html.setAttribute("dir", "rtl");

        const logo = document.querySelector(
            ".loading-container"
        ) as HTMLElement;
        logo?.classList.add("disabled");
    }, []);

    useEffect(() => {
        // Only run on client side
        if (typeof window === "undefined") return;

        const existingAnimation = document.getElementById(
            "section-logo-animation"
        );

        const handleRouterStart = (e: any) => {
            if (
                e.detail.visit.method !== "get" ||
                e.detail.visit.url.pathname === window.location.pathname ||
                e.detail.visit.only.length !== 0
            )
                return;
            section.current?.classList.remove("section-loaded");
            section.current?.classList.add("section-go-away");
            if (animationInjection.current && existingAnimation) {
                animationInjection.current.appendChild(existingAnimation);
                animationInjection.current.classList.remove("hidden");
                animationInjection.current.classList.add("block");
            }
        };

        const handleRouterFinish = (e: any) => {
            if (
                e.detail.visit.method !== "get" ||
                e.detail.visit.only.length !== 0
            )
                return;
            section.current?.classList.remove("section-go-away");
            section.current?.classList.add("section-loaded");
            if (animationInjection.current && existingAnimation) {
                animationInjection.current.classList.remove("block");
                animationInjection.current.classList.add("hidden");
            }
        };

        const handlePopState = () => {
            setTimeout(
                () =>
                    window.scrollTo({
                        top:
                            window.history.state?.documentScrollPosition?.top ||
                            0,
                        behavior: "smooth",
                    }),
                100
            );
        };

        const removeStartListener = router.on("start", handleRouterStart);
        const removeFinishListener = router.on("finish", handleRouterFinish);
        window.addEventListener("popstate", handlePopState);

        // Cleanup event listeners
        return () => {
            removeStartListener();
            removeFinishListener();
            window.removeEventListener("popstate", handlePopState);
        };
    }, []);

    useEffect(() => {
        const root = document.documentElement;
        const colors = theme.colors[currentMode];

        // Apply theme colors as CSS variables
        // Object.entries(colors).forEach(([key, value]) => {
        //     const cssVarName = `--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`;
        //     root.style.setProperty(cssVarName, value);
        // });

        // Apply fonts
        root.style.setProperty('--font-heading', theme.fonts.heading);
        root.style.setProperty('--font-body', theme.fonts.body);
        root.style.setProperty('--font-mono', theme.fonts.mono);

        // Apply radius
        const radiusMap = {
            sm: '0.25rem',
            md: '0.5rem',
            lg: '0.75rem',
            xl: '1rem',
        };
        root.style.setProperty('--radius', radiusMap[theme.radius]);

        // Update dark mode class
        // if (currentMode === 'dark') {
        //     root.classList.add('dark');
        // } else {
        //     root.classList.remove('dark');
        // }
    }, [theme, currentMode]);

    const value: ThemeContextType = {
        theme,
        currentMode,
        setMode: setCurrentMode,
    };

    return <ThemeContext.Provider value={value}>
        <div className="min-h-[calc(100vh-16rem)]">
            <div
                ref={animationInjection}
                className="hidden w-[200px] h-[200px] fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50"
            ></div>
            <div ref={section} className="">
                {children}
            </div>
        </div>
    </ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (context === undefined) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
