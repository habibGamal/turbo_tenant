import "../css/app.css";
import "./bootstrap";
import "./i18n";

import { createRoot } from "react-dom/client";
import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "@/core/api/query-client";
import { TenantProvider } from "@/core/context/TenantContext";
import { AuthProvider } from "@/core/context/AuthContext";
import { AppRouter } from "@/core/router/AppRouter";
import { initializePlugins } from "@/plugins/registry";
import { Toaster } from "@/components/ui/sonner";

// Initialize decoupled frontend plugins and slot registry
initializePlugins();

// Mount pure React SPA Shell
const appElement = document.getElementById("app");
if (appElement) {
    const root = createRoot(appElement);
    root.render(
        <QueryClientProvider client={queryClient}>
            <TenantProvider>
                <AuthProvider>
                    <AppRouter />
                    <Toaster richColors position="top-center" dir={document.documentElement.dir as any} />
                </AuthProvider>
            </TenantProvider>
        </QueryClientProvider>
    );
}
