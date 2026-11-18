import { LoaderIcon } from "lucide-react";
import React, { FormEvent, useState } from "react";
import { Link } from "react-router-dom";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { Head } from "@/components/Head";
import { apiClient } from "@/core/api/client";
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
import { localizeErrors, localizeErrorMessage } from "@/lib/validationErrors";

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status: initialStatus }: ForgotPasswordProps) {
    const { t } = useTranslation();

    const [email, setEmail] = useState("");
    const [status, setStatus] = useState(initialStatus || "");
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        setStatus("");

        try {
            const res = await apiClient.post("/api/tenant/auth/forgot-password", { email });
            setStatus(res.data.message || t("passwordResetLinkSent", "Password reset link sent!"));
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                setErrors(localizeErrors(err.response.data.errors));
            } else if (err.response?.data?.message) {
                setErrors({ email: localizeErrorMessage(err.response.data.message) });
            } else {
                setErrors({ email: t("somethingWentWrong", "An error occurred. Please try again.") });
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <MainLayout>
            <Head title={t("forgotPassword")} />
            <div className="flex items-center justify-center bg-background px-4 py-12">
                <div className="w-full max-w-md">
                    {/* Logo/Brand */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4 shadow-none">
                            <KeyRound className="w-8 h-8 text-primary-foreground" />
                        </div>
                        <h1 className="text-3xl font-bold text-foreground">
                            {t("forgotPassword")}
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-sm mx-auto">
                            {t("forgotPasswordDescription")}
                        </p>
                    </div>

                    <Card>
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
                                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-lg flex items-start gap-3">
                                        <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                                        <p className="text-sm text-emerald-800 dark:text-emerald-200">
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
                                        <p className="text-sm text-destructive text-start">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                {/* Info Box */}
                                <div className="p-4 bg-background dark:bg-card border border-border/90 rounded-lg">
                                    <p className="text-sm text-muted-foreground">
                                        {t("passwordResetLinkInfo")}
                                    </p>
                                </div>
                            </CardContent>

                            <CardFooter className="flex flex-col space-y-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-primary hover:bg-primary/90 text-primary-foreground font-medium py-6 rounded-lg shadow-none transition-colors duration-200"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <LoaderIcon className="h-4 w-4 animate-spin" />
                                            {t("sendingLink")}
                                        </span>
                                    ) : (
                                        t("sendResetLink")
                                    )}
                                </Button>

                                <div className="text-center">
                                    <Link
                                        to="/login"
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors inline-flex items-center gap-2"
                                    >
                                        <ArrowLeft className="w-4 h-4 rtl:rotate-180" />
                                        <span>{t("backToLogin")}</span>
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
