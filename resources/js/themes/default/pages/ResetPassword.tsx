import { LoaderIcon } from "lucide-react";
import React, { FormEvent, useState } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
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
import { Lock, Mail, Eye, EyeOff, ShieldCheck } from "lucide-react";
import { localizeErrors, localizeErrorMessage } from "@/lib/validationErrors";

interface ResetPasswordProps {
    token?: string;
    email?: string;
}

export default function ResetPassword({
    token: propToken,
    email: propEmail,
}: ResetPasswordProps) {
    const { t } = useTranslation();
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);

    const params = useParams<{ token: string }>();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    const token = propToken || params.token || "";
    const isEmailLocked = Boolean(propEmail || searchParams.get("email"));
    const [email, setEmail] = useState(propEmail || searchParams.get("email") || "");
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const res = await apiClient.post("/api/tenant/auth/reset-password", {
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });
            navigate("/login", {
                replace: true,
                state: {
                    status:
                        res.data?.message ||
                        t("passwordResetSuccess", "Your password has been reset."),
                },
            });
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                setErrors(localizeErrors(err.response.data.errors));
            } else if (err.response?.data?.message) {
                setErrors({ email: localizeErrorMessage(err.response.data.message) });
            } else {
                setErrors({
                    email: t("somethingWentWrong", "An error occurred. Please try again."),
                });
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <MainLayout>
            <Head title={t("resetPassword")} />
            <div className="flex items-center justify-center bg-background px-4 py-12">
                <div className="w-full max-w-md">
                    {/* Logo/Brand */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4 shadow-none">
                            <ShieldCheck className="w-8 h-8 text-primary-foreground" />
                        </div>
                        <h1 className="text-3xl font-bold text-foreground">
                            {t("resetPassword")}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            {t("enterNewPassword")}
                        </p>
                    </div>

                    <Card>
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
                                            onChange={(e) => setEmail(e.target.value)}
                                            className={`ps-10 ${isEmailLocked ? "opacity-70 cursor-not-allowed" : ""}`}
                                            placeholder={t("emailPlaceholder")}
                                            readOnly={isEmailLocked}
                                        />
                                    </div>
                                    {errors.email && (
                                        <p className="text-sm text-destructive text-start">
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
                                        <Lock className="absolute top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground start-3" />
                                        <Input
                                            id="password"
                                            type={showPassword ? "text" : "password"}
                                            name="password"
                                            value={password}
                                            autoComplete="new-password"
                                            autoFocus
                                            onChange={(e) => setPassword(e.target.value)}
                                            className="ps-10 pe-10"
                                            placeholder={t("passwordPlaceholder")}
                                        />
                                        <button
                                            type="button"
                                            aria-label={showPassword ? t("hidePassword", "Hide password") : t("showPassword", "Show password")}
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute top-1/2 -translate-y-1/2 end-1 inline-flex h-10 w-10 items-center justify-center rounded-md text-muted-foreground hover:text-foreground transition-colors"
                                        >
                                            {showPassword ? (
                                                <EyeOff className="w-5 h-5" />
                                            ) : (
                                                <Eye className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p className="text-sm text-destructive text-start">
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
                                        <Lock className="absolute top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground start-3" />
                                        <Input
                                            id="password_confirmation"
                                            type={
                                                showPasswordConfirmation
                                                    ? "text"
                                                    : "password"
                                            }
                                            name="password_confirmation"
                                            value={passwordConfirmation}
                                            autoComplete="new-password"
                                            onChange={(e) =>
                                                setPasswordConfirmation(e.target.value)
                                            }
                                            className="ps-10 pe-10"
                                            placeholder={t("confirmPasswordPlaceholder")}
                                        />
                                        <button
                                            type="button"
                                            aria-label={showPasswordConfirmation ? t("hidePassword", "Hide password") : t("showPassword", "Show password")}
                                            onClick={() =>
                                                setShowPasswordConfirmation(
                                                    !showPasswordConfirmation
                                                )
                                            }
                                            className="absolute top-1/2 -translate-y-1/2 end-1 inline-flex h-10 w-10 items-center justify-center rounded-md text-muted-foreground hover:text-foreground transition-colors"
                                        >
                                            {showPasswordConfirmation ? (
                                                <EyeOff className="w-5 h-5" />
                                            ) : (
                                                <Eye className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && (
                                        <p className="text-sm text-destructive">
                                            {errors.password_confirmation}
                                        </p>
                                    )}
                                </div>

                                {/* Password Strength Info */}
                                <div className="p-4 bg-background dark:bg-card border border-border/90 rounded-lg">
                                    <p className="text-sm text-foreground font-medium mb-2">
                                        {t("passwordStrengthTitle")}
                                    </p>
                                    <ul className="text-xs text-muted-foreground space-y-1 list-disc list-inside ps-2">
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
                                    className="w-full bg-primary hover:bg-primary/90 text-primary-foreground font-medium py-6 rounded-lg shadow-none transition-colors duration-200"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <LoaderIcon className="h-4 w-4 animate-spin" />
                                            {t("resettingPassword")}
                                        </span>
                                    ) : (
                                        t("resetPassword")
                                    )}
                                </Button>

                                <div className="text-center text-sm text-muted-foreground">
                                    {t("rememberPassword")}{" "}
                                    <Link
                                        to="/login"
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
