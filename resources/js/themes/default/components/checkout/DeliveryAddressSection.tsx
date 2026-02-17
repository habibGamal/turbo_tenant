import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { MapPin, Plus } from "lucide-react";
import { router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import AddressForm from "../AddressForm";
import { Address, Governorate } from "@/types";

interface DeliveryAddressSectionProps {
    addresses: Address[];
    governorates: Governorate[];
    selectedAddressId: number | null;
    onAddressChange: (addressId: number) => void;
}

export default function DeliveryAddressSection({
    addresses,
    governorates,
    selectedAddressId,
    onAddressChange,
}: DeliveryAddressSectionProps) {
    const { t } = useTranslation();
    const [isAddressDialogOpen, setIsAddressDialogOpen] = useState(false);

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle>{t("deliveryAddress")}</CardTitle>
                        <CardDescription>
                            {t("selectDeliveryAddress")}
                        </CardDescription>
                    </div>
                    {addresses.length > 0 && (
                        <Dialog
                            open={isAddressDialogOpen}
                            onOpenChange={setIsAddressDialogOpen}
                        >
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Plus className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                    {t("addNew")}
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                                <DialogHeader>
                                    <DialogTitle>
                                        {t("addNewAddress")}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t("addNewAddressDescription")}
                                    </DialogDescription>
                                </DialogHeader>
                                <AddressForm
                                    governorates={governorates}
                                    onSuccess={() => {
                                        setIsAddressDialogOpen(false);
                                        router.reload({ only: ["addresses"] });
                                    }}
                                />
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {addresses.length === 0 ? (
                    <div className="space-y-4">
                        <div className="text-center py-4 text-muted-foreground">
                            <MapPin className="w-10 h-10 mx-auto mb-3 opacity-50" />
                            <p className="text-sm">{t("noAddressesFound")}</p>
                        </div>
                        <AddressForm
                            governorates={governorates}
                            onSuccess={() => {
                                router.reload({ only: ["addresses"] });
                            }}
                        />
                    </div>
                ) : (
                    <RadioGroup
                        value={selectedAddressId?.toString()}
                        onValueChange={(value) => onAddressChange(parseInt(value))}
                        className="space-y-2"
                    >
                        {addresses.map((address) => (
                            <div
                                key={address.id}
                                className="flex items-start space-x-3 border rounded-lg p-3 hover:bg-accent/50 transition-colors"
                            >
                                <RadioGroupItem
                                    value={address.id.toString()}
                                    id={`address-${address.id}`}
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor={`address-${address.id}`}
                                    className="flex-1 cursor-pointer"
                                >
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="font-medium text-sm">
                                            {address.area?.governorate?.name},{" "}
                                            {address.area?.name}
                                        </span>
                                        {address.is_default && (
                                            <span className="px-2 py-0.5 bg-primary/10 text-primary text-[10px] font-medium rounded-full">
                                                {t("default")}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {address.street}, {address.building},{" "}
                                        {t("floor")} {address.floor}, {t("apt")}{" "}
                                        {address.apartment}
                                    </p>
                                    <div className="flex items-center justify-between mt-1">
                                        <span className="text-xs text-muted-foreground">
                                            {address.phone_number}
                                        </span>
                                        <span className="text-xs font-medium text-primary">
                                            {t("deliveryFee")}:{" "}
                                            {address.area?.shipping_cost
                                                ? `${address.area.shipping_cost.toFixed(2)} ${t("currency")}`
                                                : t("free")}
                                        </span>
                                    </div>
                                </Label>
                            </div>
                        ))}
                    </RadioGroup>
                )}
            </CardContent>
        </Card>
    );
}
