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
import { Lock, Mail, Eye, EyeOff, ShieldCheck } from "lucide-react";

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const [showPassword, setShowPassword] = React.useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] =
        React.useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: "",
        password_confirmation: "",
    });

    useEffect(() => {
        return () => {
            reset("password", "password_confirmation");
        };
    }, []);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route("password.store"));
    };

    return (
        <MainLayout>
            <Head title={t("resetPassword")} />
            <div
                className="flex items-center justify-center bg-background px-4 py-12"
                dir={isRTL ? "rtl" : "ltr"}
            >
                <div className="w-full max-w-md">
                    {/* Logo/Brand */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4 shadow-lg">
                            <ShieldCheck className="w-8 h-8 text-white" />
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                            {t("resetPassword")}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-2">
                            {t("enterNewPassword")}
                        </p>
                    </div>

                    <Card className="shadow-xl border-0 dark:bg-gray-800">
                        <CardHeader className="space-y-1">
                            <CardTitle className="text-2xl font-bold text-center">
                                {t("createNewPassword")}
                            </CardTitle>
                            <CardDescription className="text-center">
                                {t("passwordRequirements")}
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={submit}>
                            <CardContent className="space-y-4">
                                {/* Email Field (Read-only) */}
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
                                            className={`${isRTL ? "pr-10" : "pl-10"} bg-gray-50 dark:bg-gray-900`}
                                            readOnly
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
                                        {t("newPassword")}
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
                                            autoFocus
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

                                {/* Password Strength Info */}
                                <div className="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                    <p className="text-sm text-blue-800 dark:text-blue-200 font-medium mb-2">
                                        {t("passwordStrengthTitle")}
                                    </p>
                                    <ul className="text-xs text-blue-700 dark:text-blue-300 space-y-1 list-disc ltr:list-inside rtl:pr-4">
                                        <li>{t("passwordStrength1")}</li>
                                        <li>{t("passwordStrength2")}</li>
                                        <li>{t("passwordStrength3")}</li>
                                    </ul>
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
                                            {t("resettingPassword")}
                                        </span>
                                    ) : (
                                        t("resetPassword")
                                    )}
                                </Button>

                                <div className="text-center text-sm text-gray-600 dark:text-gray-400">
                                    {t("rememberPassword")}{" "}
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
