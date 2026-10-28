import React, { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { PhoneInput } from "@/components/ui/phone-input";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useTranslation } from "react-i18next";
import { Plus, Pencil, Trash2, MapPin, Loader2 } from "lucide-react";
import {
    useAddresses,
    useGovernorates,
    useSaveAddress,
    useDeleteAddress,
} from "@/plugins/locations-delivery/api/useLocationsDelivery";
import { Address, AddressPayload } from "@/plugins/locations-delivery/types";

export default function AddressManager({ addresses: initialAddresses }: { addresses?: Address[] }) {
    const { t, i18n } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const [editingAddress, setEditingAddress] = useState<Address | null>(null);
    const [addressToDelete, setAddressToDelete] = useState<number | null>(null);
    const [selectedGovernorateId, setSelectedGovernorateId] = useState<string>("");
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const { data: addressesRes, isLoading: isLoadingAddresses } = useAddresses();
    const { data: governoratesRes } = useGovernorates();
    const saveMutation = useSaveAddress();
    const deleteMutation = useDeleteAddress();

    const addresses: Address[] = addressesRes?.data ?? initialAddresses ?? [];
    const governorates = governoratesRes?.data ?? [];

    const [formData, setFormData] = useState<AddressPayload>({
        area_id: "",
        phone_number: "",
        street: "",
        building: "",
        floor: "",
        apartment: "",
        notes: "",
        is_default: false,
    });

    useEffect(() => {
        if (editingAddress) {
            setFormData({
                area_id: editingAddress.area_id?.toString() || "",
                phone_number: editingAddress.phone_number || "",
                street: editingAddress.street || "",
                building: editingAddress.building || "",
                floor: editingAddress.floor || "",
                apartment: editingAddress.apartment || "",
                notes: editingAddress.notes || "",
                is_default: editingAddress.is_default || false,
            });
            setSelectedGovernorateId(editingAddress.area?.governorate?.id?.toString() || "");
        } else {
            setFormData({
                area_id: "",
                phone_number: "",
                street: "",
                building: "",
                floor: "",
                apartment: "",
                notes: "",
                is_default: false,
            });
            setSelectedGovernorateId("");
        }
    }, [editingAddress]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await saveMutation.mutateAsync({
                id: editingAddress?.id,
                payload: {
                    ...formData,
                    area_id: Number(formData.area_id),
                },
            });
            setIsOpen(false);
            setEditingAddress(null);
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                const apiErrors: Record<string, string> = {};
                for (const [key, msgs] of Object.entries<any>(err.response.data.errors)) {
                    apiErrors[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
                }
                setErrors(apiErrors);
            }
        } finally {
            setProcessing(false);
        }
    };

    const handleDelete = (id: number) => {
        setAddressToDelete(id);
    };

    const confirmDelete = async () => {
        if (!addressToDelete) return;
        try {
            await deleteMutation.mutateAsync(addressToDelete);
            setAddressToDelete(null);
        } catch (err) {
            console.error("Failed to delete address:", err);
        }
    };

    const openEditModal = (address: Address) => {
        setEditingAddress(address);
        setIsOpen(true);
    };

    const openCreateModal = () => {
        setEditingAddress(null);
        setIsOpen(true);
    };

    const selectedGovernorate = governorates.find(
        (g) => g.id.toString() === selectedGovernorateId
    );

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-medium">{t("yourAddresses")}</h3>
                <Button onClick={openCreateModal} size="sm">
                    <Plus className="h-4 w-4 ltr:mr-2 rtl:ml-2" />
                    {t("addNewAddress")}
                </Button>
            </div>

            {isLoadingAddresses && addresses.length === 0 ? (
                <div className="flex justify-center p-8">
                    <Loader2 className="h-6 w-6 animate-spin text-primary" />
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {addresses.map((address) => (
                        <div
                            key={address.id}
                            className="border rounded-lg p-4 relative hover:border-primary transition-colors"
                        >
                            {address.is_default && (
                                <span className="absolute top-2 ltr:right-2 rtl:left-2 bg-primary text-primary-foreground text-xs px-2 py-1 rounded-full">
                                    {t("default")}
                                </span>
                            )}
                            <div className="flex items-start gap-3">
                                <MapPin className="h-5 w-5 text-muted-foreground mt-1" />
                                <div className="space-y-1">
                                    <p className="font-medium">
                                        {address.street}, {address.building}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {t("floor")}: {address.floor}, {t("apartment")}:{" "}
                                        {address.apartment}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {address.area?.name}, {address.area?.governorate?.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {address.phone_number}
                                    </p>
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 mt-4">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label={t("editAddress", "تعديل العنوان")}
                                    onClick={() => openEditModal(address)}
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    aria-label={t("confirmDeleteAddressTitle", t("delete", "حذف العنوان"))}
                                    onClick={() => handleDelete(address.id)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>
                            {editingAddress ? t("editAddress") : t("addNewAddress")}
                        </DialogTitle>
                        <DialogDescription>
                            {t("addressDetailsDescription")}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>{t("governorate")}</Label>
                                <Select
                                    value={selectedGovernorateId}
                                    onValueChange={setSelectedGovernorateId}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t("selectGovernorate")} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {governorates.map((gov) => (
                                            <SelectItem
                                                key={gov.id}
                                                value={gov.id.toString()}
                                            >
                                                {gov.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>{t("area")}</Label>
                                <Select
                                    value={formData.area_id ? formData.area_id.toString() : ""}
                                    onValueChange={(val) =>
                                        setFormData((prev) => ({ ...prev, area_id: val }))
                                    }
                                    disabled={!selectedGovernorateId}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t("selectArea")} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectedGovernorate?.areas?.map((area) => (
                                            <SelectItem
                                                key={area.id}
                                                value={area.id.toString()}
                                            >
                                                {area.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.area_id && (
                                    <p className="text-sm text-red-600">{errors.area_id}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>{t("street")}</Label>
                            <Input
                                value={formData.street}
                                onChange={(e) =>
                                    setFormData((prev) => ({ ...prev, street: e.target.value }))
                                }
                                required
                            />
                            {errors.street && (
                                <p className="text-sm text-red-600">{errors.street}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>{t("building")}</Label>
                            <Input
                                value={formData.building}
                                onChange={(e) =>
                                    setFormData((prev) => ({ ...prev, building: e.target.value }))
                                }
                                required
                            />
                            {errors.building && (
                                <p className="text-sm text-red-600">{errors.building}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label dir="rtl">{t("phoneNumber")}</Label>
                            <PhoneInput
                                value={formData.phone_number}
                                onChange={(value) =>
                                    setFormData((prev) => ({ ...prev, phone_number: value || "" }))
                                }
                                defaultCountry="EG"
                                countries={["EG"]}
                                placeholder={t("phoneNumberPlaceholder")}
                                dir="ltr"
                            />
                            {errors.phone_number && (
                                <p dir={i18n.dir()} className="text-sm text-red-600">
                                    {errors.phone_number}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>{t("floor")}</Label>
                                <Input
                                    value={formData.floor}
                                    onChange={(e) =>
                                        setFormData((prev) => ({ ...prev, floor: e.target.value }))
                                    }
                                    required
                                />
                                {errors.floor && (
                                    <p className="text-sm text-red-600">{errors.floor}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>{t("apartment")}</Label>
                                <Input
                                    value={formData.apartment}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            apartment: e.target.value,
                                        }))
                                    }
                                    required
                                />
                                {errors.apartment && (
                                    <p className="text-sm text-red-600">
                                        {errors.apartment}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>{t("notes")}</Label>
                            <Textarea
                                value={formData.notes || ""}
                                onChange={(e) =>
                                    setFormData((prev) => ({ ...prev, notes: e.target.value }))
                                }
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="is_default"
                                checked={formData.is_default}
                                onChange={(e) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        is_default: e.target.checked,
                                    }))
                                }
                                className="rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <Label htmlFor="is_default">{t("setAsDefaultAddress")}</Label>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                            >
                                {t("cancel")}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editingAddress ? t("update") : t("save")}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Address Confirmation Dialog */}
            <AlertDialog
                open={addressToDelete !== null}
                onOpenChange={(open) => !open && setAddressToDelete(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {t("confirmDeleteAddressTitle", "حذف العنوان")}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {t(
                                "confirmDeleteAddress",
                                "هل أنت متأكد أنك تريد حذف هذا العنوان؟"
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setAddressToDelete(null)}>
                            {t("cancel", "إلغاء")}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            data-testid="confirm-delete-address-action"
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={confirmDelete}
                        >
                            {t("delete", "حذف")}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
