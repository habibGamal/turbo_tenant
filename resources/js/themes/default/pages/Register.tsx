import React, { FormEvent, useEffect } from "react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { Head, Link, useForm } from "@inertiajs/react";
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
import { UserPlus, Mail, Lock, User, Eye, EyeOff } from "lucide-react";

export default function Register() {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const [showPassword, setShowPassword] = React.useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] =
        React.useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        expo_token: "",
    });

    useEffect(() => {
        const token = window.localStorage.getItem('expoPushToken') || window.pushToken;
        if (token) {
            setData("expo_token", token);
        }

        return () => {
            reset("password", "password_confirmation");
        };
    }, []);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route("register"));
    };

    return (
        <MainLayout>
            <Head title={t("register")} />
            <div
                className="flex items-center justify-center bg-background px-4 py-12"
                dir={isRTL ? "rtl" : "ltr"}
            >
                <div className="w-full max-w-md">
                    {/* Logo/Brand */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4 shadow-lg">
                            <UserPlus className="w-8 h-8 text-white" />
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                            {t("createAccount")}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-2">
                            {t("joinUsToday")}
                        </p>
                    </div>

                    <Card className="shadow-xl border-0 dark:bg-gray-800">
                        <CardHeader className="space-y-1">
                            <CardTitle className="text-2xl font-bold text-center">
                                {t("signUp")}
                            </CardTitle>
                            <CardDescription className="text-center">
                                {t("enterDetailsToCreateAccount")}
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={submit}>
                            <CardContent className="space-y-4">
                                {/* Name Field */}
                                <div className="space-y-2">
                                    <Label htmlFor="name" className="text-sm font-medium">
                                        {t("name")}
                                    </Label>
                                    <div className="relative">
                                        <User
                                            className={`absolute top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 ${isRTL ? "right-3" : "left-3"
                                                }`}
                                        />
                                        <Input
                                            id="name"
                                            type="text"
                                            name="name"
                                            value={data.name}
                                            autoComplete="name"
                                            autoFocus
                                            onChange={(e) =>
                                                setData("name", e.target.value)
                                            }
                                            className={`${isRTL ? "pr-10" : "pl-10"}`}
                                            placeholder={t("namePlaceholder")}
                                        />
                                    </div>
                                    {errors.name && (
                                        <p className="text-sm text-red-600 dark:text-red-400">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                {/* Email Field */}
                                <div className="space-y-2">
                                    <Label htmlFor="email" className="text-sm font-medium">
                                        {t("email")}
                                    </Label>
                                    <div className="relative">
                                        <Mail
                                            className={`absolute top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 ${isRTL ? "right-3" : "left-3"
                                                }`}
                                        />
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            autoComplete="username"
                                            onChange={(e) =>
                                                setData("email", e.target.value)
                                            }
                                            className={`${isRTL ? "pr-10" : "pl-10"}`}
                                            placeholder={t("emailPlaceholder")}
                                        />
                                    </div>
                                    {errors.email && (
                                        <p className="text-sm text-red-600 dark:text-red-400">
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
                                        <Lock
                                            className={`absolute top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 ${isRTL ? "right-3" : "left-3"
                                                }`}
                                        />
                                        <Input
                                            id="password"
                                            type={showPassword ? "text" : "password"}
                                            name="password"
                                            value={data.password}
                                            autoComplete="new-password"
                                            onChange={(e) =>
                                                setData("password", e.target.value)
                                            }
                                            className={`${isRTL ? "pr-10 pl-10" : "pl-10 pr-10"}`}
                                            placeholder={t("passwordPlaceholder")}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className={`absolute top-1/2 -translate-y-1/2 ${isRTL ? "left-3" : "right-3"
                                                } text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors`}
                                        >
                                            {showPassword ? (
                                                <EyeOff className="w-5 h-5" />
                                            ) : (
                                                <Eye className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p className="text-sm text-red-600 dark:text-red-400">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                {/* Password Confirmation Field */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="password_confirmation"
                                        className="text-sm font-medium"
                                    >
                                        {t("confirmPassword")}
                                    </Label>
                                    <div className="relative">
                                        <Lock
                                            className={`absolute top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 ${isRTL ? "right-3" : "left-3"
                                                }`}
                                        />
                                        <Input
                                            id="password_confirmation"
                                            type={
                                                showPasswordConfirmation
                                                    ? "text"
                                                    : "password"
                                            }
                                            name="password_confirmation"
                                            value={data.password_confirmation}
                                            autoComplete="new-password"
                                            onChange={(e) =>
                                                setData(
                                                    "password_confirmation",
                                                    e.target.value
                                                )
                                            }
                                            className={`${isRTL ? "pr-10 pl-10" : "pl-10 pr-10"}`}
                                            placeholder={t("confirmPasswordPlaceholder")}
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPasswordConfirmation(
                                                    !showPasswordConfirmation
                                                )
                                            }
                                            className={`absolute top-1/2 -translate-y-1/2 ${isRTL ? "left-3" : "right-3"
                                                } text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors`}
                                        >
                                            {showPasswordConfirmation ? (
                                                <EyeOff className="w-5 h-5" />
                                            ) : (
                                                <Eye className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && (
                                        <p className="text-sm text-red-600 dark:text-red-400">
                                            {errors.password_confirmation}
                                        </p>
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
                                            <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                            {t("creatingAccount")}
                                        </span>
                                    ) : (
                                        t("createAccount")
                                    )}
                                </Button>

                                <div className="relative">
                                    <div className="absolute inset-0 flex items-center">
                                        <span className="w-full border-t border-gray-300 dark:border-gray-600" />
                                    </div>
                                    <div className="relative flex justify-center text-xs uppercase">
                                        <span className="bg-white dark:bg-gray-800 px-2 text-gray-500 dark:text-gray-400">
                                            {t("orContinueWith")}
                                        </span>
                                    </div>
                                </div>

                                <a
                                    href={`${route("auth.google")}${data.expo_token ? `?expo_token=${data.expo_token}` : ''}`}
                                    className="w-full"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full py-6 border-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200"
                                    >
                                        <svg
                                            className="w-5 h-5 ltr:mr-2 rtl:ml-2"
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

                                <div className="text-center text-sm text-gray-600 dark:text-gray-400">
                                    {t("alreadyHaveAccount")}{" "}
                                    <Link
                                        href={route("login")}
                                        className="text-primary hover:text-primary/90 font-semibold transition-colors"
                                    >
                                        {t("signIn")}
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
