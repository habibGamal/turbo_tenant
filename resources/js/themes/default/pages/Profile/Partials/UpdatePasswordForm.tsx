import { useRef, useState, FormEventHandler } from "react";
import { Transition } from "@headlessui/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useTranslation } from "react-i18next";
import { apiClient } from "@/core/api/client";

export default function UpdatePasswordForm({
    className = "",
}: {
    className?: string;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { t } = useTranslation();

    const [currentPassword, setCurrentPassword] = useState("");
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const updatePassword: FormEventHandler = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await apiClient.put("/api/tenant/auth/password", {
                current_password: currentPassword,
                password,
                password_confirmation: passwordConfirmation,
            });
            setCurrentPassword("");
            setPassword("");
            setPasswordConfirmation("");
            setRecentlySuccessful(true);
            setTimeout(() => setRecentlySuccessful(false), 2000);
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                const apiErrors: Record<string, string> = {};
                for (const [key, msgs] of Object.entries<any>(err.response.data.errors)) {
                    apiErrors[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
                }
                setErrors(apiErrors);

                if (apiErrors.password) {
                    setPassword("");
                    setPasswordConfirmation("");
                    passwordInput.current?.focus();
                }

                if (apiErrors.current_password) {
                    setCurrentPassword("");
                    currentPasswordInput.current?.focus();
                }
            } else if (err.response?.data?.message) {
                setErrors({ current_password: err.response.data.message });
            } else {
                setErrors({
                    current_password: t("somethingWentWrong", "An error occurred. Please try again."),
                });
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <section className={className}>
            <form onSubmit={updatePassword} className="mt-6 space-y-6">
                <div>
                    <Label htmlFor="current_password">{t("currentPassword")}</Label>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={currentPassword}
                        onChange={(e) => setCurrentPassword(e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                    />
                    {errors.current_password && (
                        <p className="text-sm text-destructive mt-2">
                            {errors.current_password}
                        </p>
                    )}
                </div>

                <div>
                    <Label htmlFor="password">{t("newPassword")}</Label>
                    <Input
                        id="password"
                        ref={passwordInput}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                    />
                    {errors.password && (
                        <p className="text-sm text-destructive mt-2">{errors.password}</p>
                    )}
                </div>

                <div>
                    <Label htmlFor="password_confirmation">
                        {t("confirmPassword")}
                    </Label>
                    <Input
                        id="password_confirmation"
                        value={passwordConfirmation}
                        onChange={(e) => setPasswordConfirmation(e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <p className="text-sm text-destructive mt-2">
                            {errors.password_confirmation}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-4">
                    <Button disabled={processing} className="shadow-none">{t("save")}</Button>

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
