import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { PhoneInput } from "@/components/ui/phone-input";
import { useTranslation } from "react-i18next";
import { Governorate } from "@/types";
import { useTenantSafe } from "@/core/context/TenantContext";
import { useFormatMoney } from "@/lib/money";
import { formatAreaOptionLabel } from "@/lib/locationFormatters";

interface GuestData {
    name: string;
    phone: string;
    phone_country_code: string;
    email: string;
    street: string;
    building: string;
    floor: string;
    apartment: string;
    city: string;
    area_id: number | null;
}

interface GuestInformationFormProps {
    guestData: GuestData;
    onChange: (data: Partial<GuestData>) => void;
    governorates: Governorate[];
    orderType: string;
    errors: Record<string, string>;
    simplifiedAddress?: boolean;
}

export default function GuestInformationForm({
    guestData,
    onChange,
    governorates,
    orderType,
    errors,
    simplifiedAddress,
}: GuestInformationFormProps) {
    const { t, i18n } = useTranslation();
    const tenant = useTenantSafe();
    const formatMoney = useFormatMoney();
    const isRTL = i18n.language === "ar";
    const isSimplified =
        simplifiedAddress ?? Boolean(tenant?.settings?.simplified_address_enabled);

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("guestInformation")}</CardTitle>
                <CardDescription>
                    {t("guestInformationDescription")}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                {/* Name */}
                <div className="space-y-2">
                    <Label htmlFor="guest_name">{t("fullName")}</Label>
                    <Input
                        id="guest_name"
                        data-testid="guest-name-input"
                        type="text"
                        value={guestData.name}
                        onChange={(e) => onChange({ name: e.target.value })}
                        placeholder={t("fullNamePlaceholder")}
                    />
                    {errors["guest_data.name"] && (
                        <p className="text-sm text-destructive">
                            {errors["guest_data.name"]}
                        </p>
                    )}
                </div>

                {/* Phone */}
                <div className="space-y-2">
                    <Label htmlFor="guest_phone">{t("phoneNumber")}</Label>
                    <PhoneInput
                        id="guest_phone"
                        data-testid="guest-phone-input"
                        value={guestData.phone}
                        onChange={(value) => onChange({ phone: value || "" })}
                        defaultCountry="EG"
                        countries={['EG', 'SA', 'AE']}
                        placeholder={t("phoneNumberPlaceholder")}
                    />
                    {errors["guest_data.phone"] && (
                        <p className="text-sm text-destructive">
                            {errors["guest_data.phone"]}
                        </p>
                    )}
                </div>

                {/* Email */}
                <div className="space-y-2">
                    <Label htmlFor="guest_email">{t("email")} ({t("optional")})</Label>
                    <Input
                        id="guest_email"
                        data-testid="guest-email-input"
                        type="email"
                        value={guestData.email}
                        onChange={(e) => onChange({ email: e.target.value })}
                        placeholder={t("emailPlaceholder")}
                    />
                    {errors["guest_data.email"] && (
                        <p className="text-sm text-destructive">
                            {errors["guest_data.email"]}
                        </p>
                    )}
                </div>

                {orderType === "web_delivery" && (
                    <>
                        {/* Area Selection */}
                        <div className="space-y-2">
                            <Label htmlFor="guest_area">{t("area")}</Label>
                            <Select
                                value={guestData.area_id?.toString() || ""}
                                onValueChange={(value) =>
                                    onChange({ area_id: parseInt(value) })
                                }
                                dir={isRTL ? "rtl" : "ltr"}
                            >
                                <SelectTrigger id="guest_area" data-testid="guest-area-trigger">
                                    <SelectValue placeholder={t("selectArea")} />
                                </SelectTrigger>
                                <SelectContent>
                                    {governorates.map((governorate) =>
                                        governorate.areas?.map((area) => (
                                            <SelectItem
                                                key={area.id}
                                                value={area.id.toString()}
                                            >
                                                {formatAreaOptionLabel(
                                                    governorate,
                                                    area,
                                                    i18n.language,
                                                    formatMoney,
                                                    t("free")
                                                )}
                                            </SelectItem>
                                        ))
                                    )}
                                </SelectContent>
                            </Select>
                            {errors["guest_data.area_id"] && (
                                <p className="text-sm text-destructive">
                                    {errors["guest_data.area_id"]}
                                </p>
                            )}
                        </div>

                        {/* Street / Detailed Address */}
                        <div className="space-y-2">
                            <Label htmlFor="guest_street">
                                {isSimplified ? t("detailedAddress") : t("street")}
                            </Label>
                            <Input
                                id="guest_street"
                                data-testid="delivery-address-input"
                                type="text"
                                value={guestData.street}
                                onChange={(e) => onChange({ street: e.target.value })}
                                placeholder={
                                    isSimplified
                                        ? t("detailedAddressPlaceholder")
                                        : t("streetPlaceholder")
                                }
                            />
                            {errors["guest_data.street"] && (
                                <p className="text-sm text-destructive">
                                    {errors["guest_data.street"]}
                                </p>
                            )}
                        </div>

                        {!isSimplified && (
                            <>
                                {/* Building */}
                                <div className="space-y-2">
                                    <Label htmlFor="guest_building">{t("building")}</Label>
                                    <Input
                                        id="guest_building"
                                        type="text"
                                        value={guestData.building}
                                        onChange={(e) => onChange({ building: e.target.value })}
                                        placeholder={t("buildingPlaceholder")}
                                    />
                                    {errors["guest_data.building"] && (
                                        <p className="text-sm text-destructive">
                                            {errors["guest_data.building"]}
                                        </p>
                                    )}
                                </div>

                                {/* Floor & Apartment */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="guest_floor">{t("floor")}</Label>
                                        <Input
                                            id="guest_floor"
                                            type="text"
                                            value={guestData.floor}
                                            onChange={(e) => onChange({ floor: e.target.value })}
                                            placeholder={t("floorPlaceholder")}
                                        />
                                        {errors["guest_data.floor"] && (
                                            <p className="text-sm text-destructive">
                                                {errors["guest_data.floor"]}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="guest_apartment">{t("apartment")}</Label>
                                        <Input
                                            id="guest_apartment"
                                            type="text"
                                            value={guestData.apartment}
                                            onChange={(e) => onChange({ apartment: e.target.value })}
                                            placeholder={t("apartmentPlaceholder")}
                                        />
                                        {errors["guest_data.apartment"] && (
                                            <p className="text-sm text-destructive">
                                                {errors["guest_data.apartment"]}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* City */}
                                <div className="space-y-2">
                                    <Label htmlFor="guest_city">{t("city")} ({t("optional")})</Label>
                                    <Input
                                        id="guest_city"
                                        type="text"
                                        value={guestData.city}
                                        onChange={(e) => onChange({ city: e.target.value })}
                                        placeholder={t("cityPlaceholder")}
                                    />
                                    {errors["guest_data.city"] && (
                                        <p className="text-sm text-destructive">
                                            {errors["guest_data.city"]}
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
