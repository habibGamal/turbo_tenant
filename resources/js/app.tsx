import "./bootstrap";
import "./i18n";

import { createRoot } from "react-dom/client";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/core/api/query-client";
import { TenantProvider } from "@/core/context/TenantContext";
import { AuthProvider } from "@/core/context/AuthContext";
import { AppRouter } from "@/core/router/AppRouter";
import { initializePlugins } from "@/plugins/registry";
import { registerTheme, resolveTheme } from "@/themes/registry";
import { defaultTheme } from "@/themes/default";
import { Toaster } from "@/components/ui/sonner";
import { DirectionProvider } from "@radix-ui/react-direction";
import { useTranslation } from "react-i18next";

// Initialize decoupled frontend plugins
initializePlugins();

// Register and activate default theme overrides at startup
registerTheme(defaultTheme);
const activeTheme = resolveTheme();
activeTheme.registerOverrides();

function RootApp() {
    const { i18n } = useTranslation();
    const dir = (i18n.dir?.() || (i18n.language?.startsWith('ar') ? 'rtl' : 'ltr')) as 'rtl' | 'ltr';

    return (
        <DirectionProvider dir={dir}>
            <QueryClientProvider client={queryClient}>
                <TenantProvider>
                    <AuthProvider>
                        <AppRouter />
                        <Toaster position={dir === "rtl" ? "bottom-left" : "bottom-right"} closeButton dir={dir} />
                    </AuthProvider>
                </TenantProvider>
            </QueryClientProvider>
        </DirectionProvider>
    );
}

// Mount pure React SPA Shell
const appElement = document.getElementById("app");
if (appElement) {
    const root = createRoot(appElement);
    root.render(<RootApp />);
}
