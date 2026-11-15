import { LoaderIcon } from "lucide-react";
import React, { FormEvent, useEffect, useState } from "react";
import { Link, useNavigate, useSearchParams, useLocation } from "react-router-dom";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { Head } from "@/components/Head";
import { useAuth } from "@/core/context/AuthContext";
import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { LogIn, Mail, Lock, Eye, EyeOff } from "lucide-react";
import { getSafeRedirect } from "@/lib/safeRedirect";

interface LoginProps {
    canResetPassword?: boolean;
    status?: string;
}

export default function Login({ canResetPassword = true, status: initialStatus }: LoginProps) {
    const { t } = useTranslation();
    const [showPassword, setShowPassword] = useState(false);

    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [remember, setRemember] = useState(false);
    const [expoToken, setExpoToken] = useState("");
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const location = useLocation();
    const { login } = useAuth();

    const status = initialStatus || (location.state as any)?.status || searchParams.get("status");

    useEffect(() => {
        const token = window.localStorage.getItem("expoPushToken") || (window as any).pushToken;
        if (token) {
            setExpoToken(token);
        }
    }, []);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await login({
                email,
                password,
                remember,
                ...(expoToken ? { expo_token: expoToken } : {}),
            });
            const redirectUrl = getSafeRedirect(searchParams.get("redirect"));
            navigate(redirectUrl, { replace: true });
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                const apiErrors: Record<string, string> = {};
                for (const [key, msgs] of Object.entries<any>(err.response.data.errors)) {
                    apiErrors[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
                }
                setErrors(apiErrors);
            } else if (err.response?.data?.message) {
                setErrors({ email: err.response.data.message });
            } else {
                setErrors({ email: t("somethingWentWrong", "An error occurred. Please try again.") });
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <MainLayout>
            <Head title={t("login")} />
            <div className="flex items-center justify-center bg-background px-4 py-12">
                <div className="w-full max-w-md">
                    {/* Logo/Brand */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4 shadow-lg">
                            <LogIn className="w-8 h-8 text-primary-foreground" />
                        </div>
                        <h1 className="text-3xl font-bold text-foreground">
                            {t("welcomeBack")}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            {t("loginToYourAccount")}
                        </p>
                    </div>

                    <Card className="shadow-xl border-border bg-card">
                        <CardHeader className="space-y-1">
                            <CardTitle className="text-2xl font-bold text-center">
                                {t("signIn")}
                            </CardTitle>
                            <CardDescription className="text-center">
                                {t("enterCredentials")}
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={submit}>
                            <CardContent className="space-y-4">
                                {status && (
                                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                                        <p className="text-sm text-emerald-800 dark:text-emerald-200 text-center">
                                            {status}
                                        </p>
                                    </div>
                                )}

                                {/* Email Field */}
                                <div className="space-y-2">
                                    <Label htmlFor="email" className="text-sm font-medium">
                                        {t("email")}
                                    </Label>
                                    <div className="relative">
                                        <Mail className="absolute top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground start-3" />
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={email}
                                            autoComplete="username"
                                            autoFocus
                                            onChange={(e) => setEmail(e.target.value)}
                                            className="ps-10"
                                            placeholder={t("emailPlaceholder")}
                                        />
                                    </div>
                                    {errors.email && (
                                        <p className="text-sm text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                {/* Password Field */}
                                <div className="space-y-2">
                                    <Label htmlFor="password" className="text-sm font-medium">
                                        {t("password")}
                                    </Label>
                                    <div className="relative">
                                        <Lock className="absolute top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground start-3" />
                                        <Input
                                            id="password"
                                            type={showPassword ? "text" : "password"}
                                            name="password"
                                            value={password}
                                            autoComplete="current-password"
                                            onChange={(e) => setPassword(e.target.value)}
                                            className="ps-10 pe-10"
                                            placeholder={t("passwordPlaceholder")}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute top-1/2 -translate-y-1/2 end-3 text-muted-foreground hover:text-foreground transition-colors"
                                        >
                                            {showPassword ? (
                                                <EyeOff className="w-5 h-5" />
                                            ) : (
                                                <Eye className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p className="text-sm text-destructive">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                {/* Remember Me & Forgot Password */}
                                <div className="flex items-center justify-between">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="remember"
                                            checked={remember}
                                            onChange={(e) => setRemember(e.target.checked)}
                                            className="w-4 h-4 text-primary bg-background border-input rounded focus:ring-primary"
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            {t("rememberMe")}
                                        </span>
                                    </label>

                                    {canResetPassword && (
                                        <Link
                                            to="/forgot-password"
                                            className="text-sm text-primary hover:text-primary/90 font-medium transition-colors"
                                        >
                                            {t("forgotPassword")}
                                        </Link>
                                    )}
                                </div>
                            </CardContent>

                            <CardFooter className="flex flex-col space-y-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-primary hover:bg-primary/90 text-primary-foreground font-medium py-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <LoaderIcon className="h-4 w-4 animate-spin" />
                                            {t("signingIn")}
                                        </span>
                                    ) : (
                                        t("signIn")
                                    )}
                                </Button>

                                <div className="relative">
                                    <div className="absolute inset-0 flex items-center">
                                        <span className="w-full border-t border-border" />
                                    </div>
                                    <div className="relative flex justify-center text-xs uppercase">
                                        <span className="bg-card px-2 text-muted-foreground">
                                            {t("orContinueWith")}
                                        </span>
                                    </div>
                                </div>

                                <a
                                    href={`/auth/google${expoToken ? `?expo_token=${expoToken}` : ""}`}
                                    className="w-full"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full py-6 border-2 hover:bg-muted transition-all duration-200"
                                    >
                                        <svg
                                            className="w-5 h-5 me-2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                fill="#4285F4"
                                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                            />
                                            <path
                                                fill="#34A853"
                                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                            />
                                            <path
                                                fill="#FBBC05"
                                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                            />
                                            <path
                                                fill="#EA4335"
                                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                            />
                                        </svg>
                                        {t("continueWithGoogle")}
                                    </Button>
                                </a>

                                <div className="text-center text-sm text-muted-foreground">
                                    {t("dontHaveAccount")}{" "}
                                    <Link
                                        to={`/register${
                                            searchParams.get("redirect")
                                                ? `?redirect=${encodeURIComponent(searchParams.get("redirect")!)}`
                                                : ""
                                        }`}
                                        className="text-primary hover:text-primary/90 font-semibold transition-colors"
                                    >
                                        {t("signUp")}
                                    </Link>
                                </div>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
