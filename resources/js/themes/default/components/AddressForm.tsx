import { useForm } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { PhoneInput } from "@/components/ui/phone-input";
import { Loader2 } from "lucide-react";
import { Governorate } from "@/types";

interface AddressFormProps {
    governorates: Governorate[];
    onSuccess?: () => void;
}

export default function AddressForm({
    governorates,
    onSuccess,
}: AddressFormProps) {
    const { t , i18n } = useTranslation();
    const isRTL = i18n.dir() === "rtl";

    const { data, setData, post, processing, errors, reset } = useForm({
        governorate_id: "",
        area_id: "",
        phone_number: "",
        street: "",
        building: "",
        floor: "",
        apartment: "",
        notes: "",
        is_default: false,
    });

    const selectedGovernorate = governorates.find(
        (gov) => gov.id.toString() === data.governorate_id
    );

    const sortedGovernorates = [...governorates].sort((a, b) => {
        const nameA = (i18n.language === 'ar' ? a.name_ar : a.name) || '';
        const nameB = (i18n.language === 'ar' ? b.name_ar : b.name) || '';
        return nameA.localeCompare(nameB);
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("addresses.store"), {
            onSuccess: () => {
                reset();
                if (onSuccess) {
                    onSuccess();
                }
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {/* Governorate */}
            <div className="space-y-2">
                <Label htmlFor="governorate">{t("governorate")}</Label>
                <Select
                    value={data.governorate_id}
                    onValueChange={(value) => {
                        // Auto-select first area when governorate changes
                        const selectedGov = governorates.find(
                            (gov) => gov.id.toString() === value
                        );
                        const firstAreaId = selectedGov?.areas?.[0]?.id.toString() || "";

                        setData((prevData) => ({
                            ...prevData,
                            governorate_id: value,
                            area_id: firstAreaId,
                        }));
                    }}
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    <SelectTrigger id="governorate">
                        <SelectValue placeholder={t("selectGovernorate")} />
                    </SelectTrigger>
                    <SelectContent>
                        {sortedGovernorates.map((governorate) => (
                            <SelectItem
                                key={governorate.id}
                                value={governorate.id.toString()}
                            >
                                {i18n.language === 'ar' ? governorate.name_ar : governorate.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors.governorate_id && (
                    <p className="text-sm text-destructive">{errors.governorate_id}</p>
                )}
            </div>

            {/* Area */}
            <div className="space-y-2">
                <Label htmlFor="area">{t("area")}</Label>
                <Select
                    key={data.governorate_id}
                    value={data.area_id}
                    onValueChange={(value) => setData("area_id", value)}
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    <SelectTrigger id="area">
                        <SelectValue placeholder={t("selectArea")} />
                    </SelectTrigger>
                    <SelectContent>
                        {selectedGovernorate?.areas?.map((area) => (
                            <SelectItem
                                key={area.id}
                                value={area.id.toString()}
                            >
                                {i18n.language === 'ar' ? area.name_ar : area.name} ({t("deliveryFee")}:{" "}
                                {area.shipping_cost.toFixed(2)}{" "}
                                {t("currency")})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors.area_id && (
                    <p className="text-sm text-destructive">{errors.area_id}</p>
                )}
            </div>

            {/* Phone Number */}
            <div className="space-y-2" >
                <Label dir="rtl" htmlFor="phone_number">{t("phoneNumber")}</Label>
                <PhoneInput
                    id="phone_number"
                    value={data.phone_number}
                    onChange={(value) => setData("phone_number", value || "")}
                    defaultCountry="EG"
                    countries={['EG']}
                    placeholder={t("phoneNumberPlaceholder")}
                    dir="ltr"
                />
                {errors.phone_number && (
                    <p dir={i18n.dir()}  className="text-sm text-destructive">
                        {errors.phone_number}
                    </p>
                )}
            </div>

            {/* Street */}
            <div className="space-y-2">
                <Label htmlFor="street">{t("street")}</Label>
                <Input
                    id="street"
                    type="text"
                    value={data.street}
                    onChange={(e) => setData("street", e.target.value)}
                    placeholder={t("streetPlaceholder")}
                />
                {errors.street && (
                    <p className="text-sm text-destructive">{errors.street}</p>
                )}
            </div>

            {/* Building */}
            <div className="space-y-2">
                <Label htmlFor="building">{t("building")}</Label>
                <Input
                    id="building"
                    type="text"
                    value={data.building}
                    onChange={(e) => setData("building", e.target.value)}
                    placeholder={t("buildingPlaceholder")}
                />
                {errors.building && (
                    <p className="text-sm text-destructive">{errors.building}</p>
                )}
            </div>

            {/* Floor & Apartment */}
            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="floor">{t("floor")}</Label>
                    <Input
                        id="floor"
                        type="text"
                        value={data.floor}
                        onChange={(e) => setData("floor", e.target.value)}
                        placeholder={t("floorPlaceholder")}
                    />
                    {errors.floor && (
                        <p className="text-sm text-destructive">
                            {errors.floor}
                        </p>
                    )}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="apartment">{t("apartment")}</Label>
                    <Input
                        id="apartment"
                        type="text"
                        value={data.apartment}
                        onChange={(e) => setData("apartment", e.target.value)}
                        placeholder={t("apartmentPlaceholder")}
                    />
                    {errors.apartment && (
                        <p className="text-sm text-destructive">
                            {errors.apartment}
                        </p>
                    )}
                </div>
            </div>

            {/* Notes */}
            <div className="space-y-2">
                <Label htmlFor="notes">{t("addressNotes")}</Label>
                <Textarea
                    id="notes"
                    value={data.notes}
                    onChange={(e) => setData("notes", e.target.value)}
                    placeholder={t("addressNotesPlaceholder")}
                    rows={2}
                />
                {errors.notes && (
                    <p className="text-sm text-destructive">{errors.notes}</p>
                )}
            </div>

            {/* Set as Default */}
            <div className="flex items-center gap-2">
                <input
                    type="checkbox"
                    id="is_default"
                    checked={data.is_default}
                    onChange={(e) => setData("is_default", e.target.checked)}
                    className="rounded border-gray-300 text-primary focus:ring-primary"
                />
                <Label htmlFor="is_default" className="cursor-pointer">
                    {t("setAsDefaultAddress")}
                </Label>
            </div>

            {/* Submit Button */}
            <Button
                type="submit"
                disabled={processing}
                className="w-full"
                size="lg"
            >
                {processing ? (
                    <>
                        <Loader2 className="ltr:mr-2 rtl:ml-2 h-4 w-4 animate-spin" />
                        {t("saving")}
                    </>
                ) : (
                    t("saveAddress")
                )}
            </Button>
        </form>
    );
}
