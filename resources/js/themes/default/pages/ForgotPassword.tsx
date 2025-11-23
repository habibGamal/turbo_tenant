import React, { FormEvent } from "react";
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
import { KeyRound, Mail, ArrowLeft, CheckCircle } from "lucide-react";

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";

    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route("password.email"));
    };

    return (
        <>
            <Head title={t("forgotPassword")} />
            <div
                className="min-h-screen flex items-center justify-center bg-linear-to-br from-orange-50 via-white to-orange-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 px-4 py-12"
                dir={isRTL ? "rtl" : "ltr"}
            >
                <div className="w-full max-w-md">
                    {/* Logo/Brand */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-linear-to-br from-orange-500 to-orange-600 rounded-2xl mb-4 shadow-lg">
                            <KeyRound className="w-8 h-8 text-white" />
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                            {t("forgotPassword")}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-2 max-w-sm mx-auto">
                            {t("forgotPasswordDescription")}
                        </p>
                    </div>

                    <Card className="shadow-xl border-0 dark:bg-gray-800">
                        <CardHeader className="space-y-1">
                            <CardTitle className="text-2xl font-bold text-center">
                                {t("resetPassword")}
                            </CardTitle>
                            <CardDescription className="text-center">
                                {t("enterEmailToResetPassword")}
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={submit}>
                            <CardContent className="space-y-4">
                                {status && (
                                    <div className="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg flex items-start gap-3">
                                        <CheckCircle className="w-5 h-5 text-green-600 dark:text-green-400 shrink-0 mt-0.5" />
                                        <p className="text-sm text-green-800 dark:text-green-200">
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
                                        <Mail
                                            className={`absolute top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 ${
                                                isRTL ? "right-3" : "left-3"
                                            }`}
                                        />
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            autoComplete="username"
                                            autoFocus
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

                                {/* Info Box */}
                                <div className="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                    <p className="text-sm text-blue-800 dark:text-blue-200">
                                        {t("passwordResetLinkInfo")}
                                    </p>
                                </div>
                            </CardContent>

                            <CardFooter className="flex flex-col space-y-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-linear-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium py-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                            {t("sendingLink")}
                                        </span>
                                    ) : (
                                        t("sendResetLink")
                                    )}
                                </Button>

                                <div className="text-center">
                                    <Link
                                        href={route("login")}
                                        className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors inline-flex items-center gap-2"
                                    >
                                        {isRTL ? (
                                            <>
                                                <span>{t("backToLogin")}</span>
                                                <ArrowLeft className="w-4 h-4 rotate-180" />
                                            </>
                                        ) : (
                                            <>
                                                <ArrowLeft className="w-4 h-4" />
                                                <span>{t("backToLogin")}</span>
                                            </>
                                        )}
                                    </Link>
                                </div>
                            </CardFooter>
                        </form>
                    </Card>

                    {/* Back to Home */}
                    <div className="text-center mt-6">
                        <Link
                            href="/"
                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors inline-flex items-center gap-2"
                        >
                            <span>{t("backToHome")}</span>
                            {isRTL ? (
                                <span>←</span>
                            ) : (
                                <span>→</span>
                            )}
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
