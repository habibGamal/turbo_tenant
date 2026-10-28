import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import MainLayout from "@/themes/default/layouts/MainLayout";
import { Head } from "@/components/Head";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useTranslation } from "react-i18next";
import { User, Lock, MapPin } from "lucide-react";
import { PluginSlot } from "@/core/slots";
import { useAuth } from "@/core/context/AuthContext";
import AddressManager from "./Profile/Partials/AddressManager";
import UpdateProfileInformationForm from "./Profile/Partials/UpdateProfileInformationForm";
import UpdatePasswordForm from "./Profile/Partials/UpdatePasswordForm";

interface ProfileProps {
    mustVerifyEmail?: boolean;
    status?: string;
}

export default function Profile({ mustVerifyEmail = false, status }: ProfileProps) {
    const { t, i18n } = useTranslation();
    const [activeTab, setActiveTab] = useState("profile");
    const { user, isAuthenticated, isLoading } = useAuth();
    const navigate = useNavigate();

    useEffect(() => {
        if (!isLoading && !isAuthenticated) {
            navigate("/login?redirect=/profile", { replace: true });
        }
    }, [isLoading, isAuthenticated, navigate]);

    if (isLoading || !isAuthenticated || !user) {
        return null;
    }

    return (
        <MainLayout>
            <Head title={t("profile")} />

            <div className="container mx-auto py-10 px-4 md:px-6">
                <h1 className="text-3xl font-bold mb-8">{t("profile")}</h1>

                <Tabs
                    dir={i18n.dir()}
                    defaultValue="profile"
                    className="w-full"
                    onValueChange={setActiveTab}
                >
                    <TabsList className="grid w-full grid-cols-3 mb-8">
                        <TabsTrigger value="profile" className="flex items-center gap-2">
                            <User className="h-4 w-4" />
                            <span className="hidden sm:inline">{t("profileInformation")}</span>
                        </TabsTrigger>
                        <TabsTrigger value="password" className="flex items-center gap-2">
                            <Lock className="h-4 w-4" />
                            <span className="hidden sm:inline">{t("updatePassword")}</span>
                        </TabsTrigger>
                        <TabsTrigger value="addresses" className="flex items-center gap-2">
                            <MapPin className="h-4 w-4" />
                            <span className="hidden sm:inline">{t("addresses")}</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="profile">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("profileInformation")}</CardTitle>
                                <CardDescription>
                                    {t("updateProfileDescription")}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <UpdateProfileInformationForm
                                    mustVerifyEmail={mustVerifyEmail}
                                    status={status}
                                    className="max-w-xl"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="password">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t("updatePassword")}</CardTitle>
                                <CardDescription>
                                    {t("ensureAccountSecure")}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <UpdatePasswordForm className="max-w-xl" />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="addresses">
                        <PluginSlot
                            name="profile.addresses"
                            pluginId="locations-delivery"
                            fallback={
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t("addresses")}</CardTitle>
                                        <CardDescription>
                                            {t("manageAddressesDescription")}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <AddressManager />
                                    </CardContent>
                                </Card>
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}
