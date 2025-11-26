import React, { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import Navigation from "@/themes/default/components/Navigation";
import Footer from "@/themes/default/components/Footer";
import AddressForm from "@/themes/default/components/AddressForm";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Textarea } from "@/components/ui/textarea";
import { ImageWithFallback } from "@/components/ui/image";
import { Separator } from "@/components/ui/separator";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    CreditCard,
    Wallet,
    Landmark,
    DollarSign,
    MapPin,
    Store,
    ShoppingBag,
    Loader2,
    Plus,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { Address, Branch, Cart, Governorate, PageProps } from "@/types";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import axios from "axios";

interface CheckoutPageProps extends PageProps {
    cart: Cart;
    addresses: Address[];
    branches: Branch[];
    governorates: Governorate[];
}

export default function Checkout({
    cart,
    addresses,
    branches,
    governorates,
    auth,
}: CheckoutPageProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";
    const [selectedAddress, setSelectedAddress] = useState<number | null>(
        addresses.find((a) => a.is_default)?.id || addresses[0]?.id || null
    );
    const [selectedBranch, setSelectedBranch] = useState<number | null>(
        branches[0]?.id || null
    );
    const [isAddressDialogOpen, setIsAddressDialogOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        branch_id: branches[0]?.id || 0,
        payment_method: "cod",
        address_id:
            addresses.find((a) => a.is_default)?.id || addresses[0]?.id || null,
        note: "",
        type: "web_delivery",
        billing_data: {
            first_name: auth.user.name,
            email: auth.user.email,
        },
    });

    const formatCurrency = (amount: number | undefined) => {
        const safeAmount = amount ?? 0;
        return `${safeAmount.toFixed(2)} ${t("currency")}`;
    };

    const calculateDeliveryFee = () => {
        if (data.type === "web_takeaway") return 0;
        if (!data.address_id) return 0;
        const address = addresses.find((a) => a.id === data.address_id);
        return address?.area?.shipping_cost || 0;
    };

    const calculateTax = (amount: number) => {
        return amount * 0.0; // 14% VAT
    };

    const calculateService = (amount: number) => {
        return 0; // No service charge by default
    };

    const deliveryFee = calculateDeliveryFee();
    const tax = calculateTax(cart?.total || 0);
    const service = calculateService(cart?.total || 0);
    const totalAmount = (cart?.total || 0) + deliveryFee + tax + service;

    const handlePlaceOrder = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.branch_id) {
            alert(t("pleaseSelectBranch"));
            return;
        }

        if (data.type === "web_delivery" && !data.address_id) {
            alert(t("pleaseSelectAddress"));
            return;
        }

        if (!cart || cart.items.length === 0) {
            alert(t("cartIsEmpty"));
            return;
        }

        setIsSubmitting(true);

        try {
            const response = await axios.post(route("orders.place"), data);

            if (response.data.success) {
                const { redirect_type, redirect_url, order_id } = response.data;

                if (redirect_type === "external") {
                    // Redirect to external payment gateway
                    window.location.href = redirect_url;
                } else if (redirect_type === "internal") {
                    // Navigate to order show page using Inertia
                    router.visit(redirect_url);
                }
            } else {
                // Handle errors from response
                const errorMessages = response.data.errors
                    ? Object.values(response.data.errors).flat().join(", ")
                    : t("failedToPlaceOrder");
                alert(errorMessages);
            }
        } catch (error: any) {
            console.error("Order placement error:", error);

            if (error.response?.data?.errors) {
                // Laravel validation errors
                const errorMessages = Object.values(error.response.data.errors)
                    .flat()
                    .join(", ");
                alert(errorMessages);
            } else if (error.response?.data?.message) {
                alert(error.response.data.message);
            } else {
                alert(t("failedToPlaceOrder"));
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const paymentMethods = [
        { value: "cod", label: t("cashOnDelivery"), icon: DollarSign },
        { value: "card", label: t("creditCard"), icon: CreditCard },
    ];

    if (!cart || cart.items.length === 0) {
        return (
            <>
                <Head title={t("checkout")} />
                <div className="min-h-screen bg-gradient-to-b from-background via-background/95 to-primary/5">
                    <Navigation />
                    <main className="container mx-auto px-4 py-8">
                        <Card className="py-16">
                            <CardContent className="flex flex-col items-center gap-6">
                                <div className="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center">
                                    <ShoppingBag className="w-12 h-12 text-primary" />
                                </div>
                                <div className="text-center space-y-2">
                                    <h2 className="text-2xl font-semibold">
                                        {t("cartEmpty")}
                                    </h2>
                                    <p className="text-muted-foreground">
                                        {t("cartEmptyDescription")}
                                    </p>
                                </div>
                                <Button
                                    onClick={() => router.visit(route("home"))}
                                    size="lg"
                                >
                                    {t("startShopping")}
                                </Button>
                            </CardContent>
                        </Card>
                    </main>
                    <Footer />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t("checkout")} />
            <div className="min-h-screen bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
                <Navigation />

                <main className="container mx-auto px-4 py-8 md:py-12">
                    <div className="mb-8">
                        <h1 className="text-3xl md:text-4xl font-bold mb-2">
                            {t("checkout")}
                        </h1>
                        <p className="text-muted-foreground">
                            {t("completeYourOrder")}
                        </p>
                    </div>

                    <div className="grid lg:grid-cols-3 gap-8">
                        {/* Checkout Form */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Order Type */}
                            <Card >
                                <CardHeader>
                                    <CardTitle>{t("orderType")}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <RadioGroup
                                        value={data.type}
                                        onValueChange={(value) =>
                                            setData("type", value)
                                        }
                                        className="space-y-3"
                                        dir={isRTL ? "rtl" : "ltr"}
                                    >
                                        <div className="flex items-center space-x-2 ">
                                            <RadioGroupItem
                                                value="web_delivery"
                                                id="delivery"
                                            />
                                            <Label
                                                htmlFor="delivery"
                                                className="flex-1 cursor-pointer"
                                            >
                                                {t("delivery")}
                                            </Label>
                                        </div>
                                        <div className="flex items-center space-x-2 ">
                                            <RadioGroupItem
                                                value="web_takeaway"
                                                id="takeaway"
                                            />
                                            <Label
                                                htmlFor="takeaway"
                                                className="flex-1 cursor-pointer"
                                            >
                                                {t("takeaway")}
                                            </Label>
                                        </div>
                                    </RadioGroup>
                                </CardContent>
                            </Card>

                            {/* Branch Selection */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t("selectBranch")}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Select
                                        value={data.branch_id?.toString()}
                                        onValueChange={(value) =>
                                            setData(
                                                "branch_id",
                                                parseInt(value)
                                            )
                                        }
                                        dir={isRTL ? "rtl" : "ltr"}
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder={t("selectBranch")}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {branches.map((branch) => (
                                                <SelectItem
                                                    key={branch.id}
                                                    value={branch.id.toString()}
                                                >
                                                    {branch.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </CardContent>
                            </Card>

                            {/* Delivery Address */}
                            {data.type === "web_delivery" && (
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle>
                                                    {t("deliveryAddress")}
                                                </CardTitle>
                                                <CardDescription>
                                                    {t("selectDeliveryAddress")}
                                                </CardDescription>
                                            </div>
                                            {addresses.length > 0 && (
                                                <Dialog
                                                    open={isAddressDialogOpen}
                                                    onOpenChange={
                                                        setIsAddressDialogOpen
                                                    }
                                                >
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Plus className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                                                            {t("addNew")}
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                {t(
                                                                    "addNewAddress"
                                                                )}
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                {t(
                                                                    "fillAddressDetails"
                                                                )}
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <AddressForm
                                                            governorates={
                                                                governorates
                                                            }
                                                            onSuccess={() => {
                                                                setIsAddressDialogOpen(
                                                                    false
                                                                );
                                                                router.reload({
                                                                    only: [
                                                                        "addresses",
                                                                    ],
                                                                });
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
                                                    <p className="text-sm">
                                                        {t("noAddressesFound")}
                                                    </p>
                                                </div>
                                                <AddressForm
                                                    governorates={governorates}
                                                    onSuccess={() => {
                                                        router.reload({
                                                            only: ["addresses"],
                                                        });
                                                    }}
                                                />
                                            </div>
                                        ) : (
                                            <RadioGroup
                                                value={data.address_id?.toString()}
                                                onValueChange={(value) =>
                                                    setData(
                                                        "address_id",
                                                        parseInt(value)
                                                    )
                                                }
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
                                                                    {
                                                                        address
                                                                            .area
                                                                            ?.governorate
                                                                            ?.name
                                                                    }
                                                                    ,{" "}
                                                                    {
                                                                        address
                                                                            .area
                                                                            ?.name
                                                                    }
                                                                </span>
                                                                {address.is_default && (
                                                                    <span className="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">
                                                                        {t(
                                                                            "default"
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <p className="text-xs text-muted-foreground">
                                                                {address.street}
                                                                ,{" "}
                                                                {
                                                                    address.building
                                                                }
                                                                , {t("floor")}{" "}
                                                                {address.floor},{" "}
                                                                {t("apt")}{" "}
                                                                {
                                                                    address.apartment
                                                                }
                                                            </p>
                                                            <div className="flex items-center justify-between mt-1">
                                                                <span className="text-xs text-muted-foreground">
                                                                    {
                                                                        address.phone_number
                                                                    }
                                                                </span>
                                                                <span className="text-xs font-medium text-primary">
                                                                    {t(
                                                                        "deliveryFee"
                                                                    )}
                                                                    :{" "}
                                                                    {formatCurrency(
                                                                        address
                                                                            .area
                                                                            ?.shipping_cost ||
                                                                            0
                                                                    )}
                                                                </span>
                                                            </div>
                                                        </Label>
                                                    </div>
                                                ))}
                                            </RadioGroup>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            {/* Payment Method */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t("paymentMethod")}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <RadioGroup
                                        value={data.payment_method}
                                        onValueChange={(value) =>
                                            setData("payment_method", value)
                                        }
                                        className="space-y-3"
                                        dir={isRTL ? "rtl" : "ltr"}
                                    >
                                        {paymentMethods.map((method) => {
                                            const Icon = method.icon;
                                            return (
                                                <div
                                                    key={method.value}
                                                    className="flex items-center space-x-3"
                                                >
                                                    <RadioGroupItem
                                                        value={method.value}
                                                        id={method.value}
                                                    />
                                                    <Label
                                                        htmlFor={method.value}
                                                        className="flex items-center gap-3 flex-1 cursor-pointer"
                                                    >
                                                        <Icon className="w-5 h-5 text-muted-foreground" />
                                                        <span>
                                                            {method.label}
                                                        </span>
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </RadioGroup>
                                </CardContent>
                            </Card>

                            {/* Order Notes */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t("orderNotes")}</CardTitle>
                                    <CardDescription>
                                        {t("addOrderNotesOptional")}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Textarea
                                        placeholder={t("orderNotesPlaceholder")}
                                        value={data.note}
                                        onChange={(e) =>
                                            setData("note", e.target.value)
                                        }
                                        rows={4}
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-1">
                            <Card className="sticky top-4">
                                <CardHeader>
                                    <CardTitle>{t("orderSummary")}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Cart Items List */}
                                    <div className="space-y-2 max-h-96 overflow-y-auto">
                                        {cart.items.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex gap-2 pb-2 border-b border-border/50 last:border-0"
                                            >
                                                {/* Item Image */}
                                                <div className="w-12 h-12 rounded-md bg-gradient-to-br from-primary/5 to-secondary/5 relative overflow-hidden shrink-0">
                                                    <ImageWithFallback
                                                        src={
                                                            item.product
                                                                ?.image
                                                        }
                                                        alt={
                                                            item.product
                                                                ?.name ||
                                                            t("product")
                                                        }
                                                        className="w-full h-full object-cover"
                                                    />
                                                </div>

                                                {/* Item Details */}
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="flex-1 min-w-0">
                                                            <div className="font-medium text-xs leading-tight truncate">
                                                                {item.product
                                                                    ?.name ||
                                                                    t(
                                                                        "unknownProduct"
                                                                    )}
                                                            </div>
                                                            {item.variant && (
                                                                <div className="text-[10px] text-muted-foreground">
                                                                    {
                                                                        item
                                                                            .variant
                                                                            .name
                                                                    }
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="text-xs font-semibold text-primary shrink-0">
                                                            {formatCurrency(
                                                                item.subtotal
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2 mt-0.5">
                                                        {item.product
                                                            ?.sell_by_weight &&
                                                        item.weight_option_value ? (
                                                            <span className="text-[10px] text-muted-foreground">
                                                                {parseFloat(
                                                                    item.quantity
                                                                ).toFixed(
                                                                    2
                                                                )}{" "}
                                                                {
                                                                    item.product
                                                                        .weight_option
                                                                        ?.unit
                                                                }
                                                                {item.weight_multiplier >
                                                                    1 &&
                                                                    ` ${t(
                                                                        "multiply"
                                                                    )} ${
                                                                        item.weight_multiplier
                                                                    }`}
                                                            </span>
                                                        ) : (
                                                            <span className="text-[10px] text-muted-foreground">
                                                                {t("qty")}:{" "}
                                                                {parseFloat(
                                                                    item.quantity
                                                                ).toFixed(0)}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {item.extras.length > 0 && (
                                                        <div className="text-[10px] text-muted-foreground mt-0.5 truncate">
                                                            {item.extras.map(
                                                                (extra) => (
                                                                    <div
                                                                        key={
                                                                            extra.id
                                                                        }
                                                                        className="flex items-center gap-1"
                                                                    >
                                                                        <span>
                                                                            +{" "}
                                                                            {
                                                                                extra.name
                                                                            }
                                                                        </span>
                                                                        {extra.quantity >
                                                                            1 && (
                                                                            <span>
                                                                                {t(
                                                                                    "multiply"
                                                                                )}{" "}
                                                                                {
                                                                                    extra.quantity
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                )
                                                            )}
                                                        </div>
                                                    )}
                                                    <div className="text-sm font-semibold text-primary mt-1">
                                                        {formatCurrency(
                                                            item.subtotal
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    <Separator />

                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t("subtotal")}
                                            </span>
                                            <span className="font-medium">
                                                {formatCurrency(cart?.total)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t("tax")} ({t("vatPercentage")}
                                                )
                                            </span>
                                            <span className="font-medium">
                                                {formatCurrency(tax)}
                                            </span>
                                        </div>
                                        {service > 0 && (
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    {t("service")}
                                                </span>
                                                <span className="font-medium">
                                                    {formatCurrency(service)}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t("deliveryFee")}
                                            </span>
                                            <span className="font-medium">
                                                {deliveryFee > 0
                                                    ? formatCurrency(
                                                          deliveryFee
                                                      )
                                                    : t("free")}
                                            </span>
                                        </div>
                                        <Separator />
                                        <div className="flex items-center justify-between">
                                            <span className="font-semibold text-lg">
                                                {t("total")}
                                            </span>
                                            <span className="font-bold text-2xl text-primary">
                                                {formatCurrency(totalAmount)}
                                            </span>
                                        </div>
                                    </div>

                                    <Button
                                        className="w-full"
                                        size="lg"
                                        onClick={handlePlaceOrder}
                                        disabled={isSubmitting}
                                    >
                                        {isSubmitting ? (
                                            <>
                                                <Loader2 className="ltr:mr-2 rtl:ml-2 h-4 w-4 animate-spin" />
                                                {t("placingOrder")}
                                            </>
                                        ) : (
                                            t("placeOrder")
                                        )}
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
