import "../css/app.css";
import "./bootstrap";
import "./i18n";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { ThemeProvider } from "@/contexts/ThemeContext";
import { getTheme } from "@/lib/theme-registry";
import { getThemeName, resolveThemedPage } from "@/lib/theme-resolver";
import InitPixel from "@/components/InitPixel";
import i18n from "./i18n";
import InitExpo from "./components/InitExpo";

// Set initial document direction to RTL for Arabic
document.documentElement.dir = "rtl";
document.documentElement.lang = "ar";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => {
        // Get site name from settings or fallback to env variable
        const siteName = (window as any).__INERTIA_SITE_NAME__ || appName;
        return title ? `${title} - ${siteName}` : siteName;
    },
    resolve: async (name) => {
        // Try to resolve from theme first, fallback to default Pages
        return resolvePageComponent(
            `./themes/default/pages/${name}.tsx`,
            import.meta.glob("./themes/default/pages/**/*.tsx")
        );
        try {
            // return resolveThemedPage(name);
        } catch (e) {
            console.log(e);
            // If theme resolver fails, use default Laravel resolution
            return resolvePageComponent(
                `./Pages/${name}.tsx`,
                import.meta.glob("./Pages/**/*.tsx")
            );
        }
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Store site name globally for title callback
        (window as any).__INERTIA_SITE_NAME__ =
            (props.initialPage.props.settings as any)?.site_name || appName;

        // Get theme configuration based on tenant or default
        const themeName = getThemeName(props.initialPage.props);
        const themeConfig = getTheme(themeName);
        root.render(
            <ThemeProvider theme={themeConfig}>
                <InitPixel
                    fbID={
                        (props.initialPage.props.settings as any)
                            ?.facebook_app_id
                    }
                >
                    <InitExpo>
                        <App {...props} />
                    </InitExpo>
                </InitPixel>
            </ThemeProvider>
        );
    },
    progress: {
        color: "#4B5563",
    },
});
