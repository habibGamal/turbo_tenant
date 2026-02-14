import { useState } from "react";
import { useForm, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useTranslation } from "react-i18next";
import { Plus, Pencil, Trash2, MapPin } from "lucide-react";
import axios from "axios";
import { useEffect } from "react";

interface Address {
    id: number;
    area_id: number;
    phone_number: string;
    street: string;
    building: string;
    floor: string;
    apartment: string;
    notes: string | null;
    is_default: boolean;
    area: {
        id: number;
        name: string;
        governorate: {
            id: number;
            name: string;
        };
    };
}

interface Governorate {
    id: number;
    name: string;
    areas: {
        id: number;
        name: string;
    }[];
}

export default function AddressManager({ addresses }: { addresses: Address[] }) {
    const { t } = useTranslation();
    const [isOpen, setIsOpen] = useState(false);
    const [editingAddress, setEditingAddress] = useState<Address | null>(null);
    const [governorates, setGovernorates] = useState<Governorate[]>([]);
    const [selectedGovernorateId, setSelectedGovernorateId] = useState<string>("");

    const { data, setData, post, patch, delete: destroy, processing, reset, errors } = useForm({
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
        axios.get(route("governorates-areas.index")).then((response) => {
            if (response.data.success) {
                setGovernorates(response.data.governorates);
            }
        });
    }, []);

    useEffect(() => {
        if (editingAddress) {
            setData({
                area_id: editingAddress.area_id.toString(),
                phone_number: editingAddress.phone_number,
                street: editingAddress.street,
                building: editingAddress.building,
                floor: editingAddress.floor,
                apartment: editingAddress.apartment,
                notes: editingAddress.notes || "",
                is_default: editingAddress.is_default,
            });
            setSelectedGovernorateId(editingAddress.area.governorate.id.toString());
        } else {
            reset();
            setSelectedGovernorateId("");
        }
    }, [editingAddress]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingAddress) {
            patch(route("addresses.update", editingAddress.id), {
                onSuccess: () => {
                    setIsOpen(false);
                    setEditingAddress(null);
                    reset();
                },
            });
        } else {
            post(route("addresses.store"), {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm(t("confirmDeleteAddress"))) {
            destroy(route("addresses.destroy", id));
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
                                    {address.area.name}, {address.area.governorate.name}
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
                                onClick={() => openEditModal(address)}
                            >
                                <Pencil className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="destructive"
                                size="icon"
                                onClick={() => handleDelete(address.id)}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>

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
                                    value={data.area_id}
                                    onValueChange={(val) => setData("area_id", val)}
                                    disabled={!selectedGovernorateId}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t("selectArea")} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectedGovernorate?.areas.map((area) => (
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
                                value={data.street}
                                onChange={(e) => setData("street", e.target.value)}
                                required
                            />
                            {errors.street && (
                                <p className="text-sm text-red-600">{errors.street}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>{t("building")}</Label>
                                <Input
                                    value={data.building}
                                    onChange={(e) => setData("building", e.target.value)}
                                    required
                                />
                                {errors.building && (
                                    <p className="text-sm text-red-600">{errors.building}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>{t("phoneNumber")}</Label>
                                <Input
                                    value={data.phone_number}
                                    onChange={(e) => setData("phone_number", e.target.value)}
                                    required
                                />
                                {errors.phone_number && (
                                    <p className="text-sm text-red-600">
                                        {errors.phone_number}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>{t("floor")}</Label>
                                <Input
                                    value={data.floor}
                                    onChange={(e) => setData("floor", e.target.value)}
                                    required
                                />
                                {errors.floor && (
                                    <p className="text-sm text-red-600">{errors.floor}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>{t("apartment")}</Label>
                                <Input
                                    value={data.apartment}
                                    onChange={(e) => setData("apartment", e.target.value)}
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
                                value={data.notes}
                                onChange={(e) => setData("notes", e.target.value)}
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="is_default"
                                checked={data.is_default}
                                onChange={(e) => setData("is_default", e.target.checked)}
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
        </div>
    );
}
