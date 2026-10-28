import { useState, useEffect, FormEventHandler } from "react";
import { Transition } from "@headlessui/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useTranslation } from "react-i18next";
import { useAuth } from "@/core/context/AuthContext";
import { apiClient } from "@/core/api/client";

export default function UpdateProfileInformationForm({
    mustVerifyEmail = false,
    status,
    className = "",
}: {
    mustVerifyEmail?: boolean;
    status?: string;
    className?: string;
}) {
    const { user, refetchUser } = useAuth();
    const { t } = useTranslation();

    const [name, setName] = useState(user?.name || "");
    const [email, setEmail] = useState(user?.email || "");
    const [phone, setPhone] = useState(user?.phone || "");
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [verificationStatus, setVerificationStatus] = useState(status || "");

    useEffect(() => {
        if (user) {
            setName(user.name || "");
            setEmail(user.email || "");
            setPhone(user.phone || "");
        }
    }, [user]);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await apiClient.patch("/api/tenant/auth/profile", {
                name,
                email,
                phone: phone || null,
            });
            refetchUser();
            setRecentlySuccessful(true);
            setTimeout(() => setRecentlySuccessful(false), 2000);
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
                setErrors({
                    email: t("somethingWentWrong", "An error occurred. Please try again."),
                });
            }
        } finally {
            setProcessing(false);
        }
    };

    const sendVerification = async () => {
        try {
            await apiClient.post("/email/verification-notification");
            setVerificationStatus("verification-link-sent");
        } catch (err) {
            console.error(err);
        }
    };

    return (
        <section className={className}>
            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <Label htmlFor="name">{t("name")}</Label>
                    <Input
                        id="name"
                        className="mt-1 block w-full"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        required
                        autoComplete="name"
                    />
                    {errors.name && (
                        <p className="text-sm text-destructive mt-2">{errors.name}</p>
                    )}
                </div>

                <div>
                    <Label htmlFor="email">{t("email")}</Label>
                    <Input
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                        autoComplete="username"
                    />
                    {errors.email && (
                        <p className="text-sm text-destructive mt-2">{errors.email}</p>
                    )}
                </div>

                <div>
                    <Label htmlFor="phone">{t("phoneNumber", "Phone Number")}</Label>
                    <Input
                        id="phone"
                        type="tel"
                        className="mt-1 block w-full"
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        autoComplete="tel"
                    />
                    {errors.phone && (
                        <p className="text-sm text-destructive mt-2">{errors.phone}</p>
                    )}
                </div>

                {mustVerifyEmail && (user as any)?.email_verified_at === null && (
                    <div>
                        <p className="text-sm mt-2 text-foreground">
                            {t("emailUnverified")}{" "}
                            <button
                                type="button"
                                onClick={sendVerification}
                                className="underline text-sm text-muted-foreground hover:text-foreground rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                            >
                                {t("clickToResendVerification")}
                            </button>
                        </p>

                        {verificationStatus === "verification-link-sent" && (
                            <div className="mt-2 font-medium text-sm text-emerald-600 dark:text-emerald-400">
                                {t("verificationLinkSent")}
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <Button disabled={processing}>{t("save")}</Button>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-muted-foreground">
                            {t("saved")}
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
