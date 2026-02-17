import React, { useState, useEffect } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { useTranslation } from "react-i18next";
import { Address, Branch, Cart, Governorate, PageProps } from "@/types";
import axios from "axios";

// Checkout Components
import EmptyCart from "@/themes/default/components/checkout/EmptyCart";
import OrderTypeSelection from "@/themes/default/components/checkout/OrderTypeSelection";
import BranchSelection from "@/themes/default/components/checkout/BranchSelection";
import GuestInformationForm from "@/themes/default/components/checkout/GuestInformationForm";
import DeliveryAddressSection from "@/themes/default/components/checkout/DeliveryAddressSection";
import PaymentMethodSelection from "@/themes/default/components/checkout/PaymentMethodSelection";
import OrderNotesSection from "@/themes/default/components/checkout/OrderNotesSection";
import CouponCodeSection from "@/themes/default/components/checkout/CouponCodeSection";
import OrderSummary from "@/themes/default/components/checkout/OrderSummary";

interface CheckoutPageProps extends PageProps {
    cart: Cart;
    addresses: Address[];
    branches: Branch[];
    governorates: Governorate[];
    is_guest?: boolean;
}

export default function Checkout({
    cart,
    addresses,
    branches,
    governorates,
    auth,
    settings,
    is_guest = false,
}: CheckoutPageProps) {
    const { t } = useTranslation();
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Coupon State
    type AppliedCoupon = {
        id: number;
        code: string;
        discount: number;
        type: string;
        value: number;
        free_shipping: boolean;
    };
    const [appliedCoupon, setAppliedCoupon] = useState<AppliedCoupon | null>(null);

    // Load saved guest data from localStorage
    const getSavedGuestData = () => {
        if (is_guest) {
            try {
                const saved = localStorage.getItem('turbo_tenant_guest_data');
                if (saved) {
                    return JSON.parse(saved);
                }
            } catch (error) {
                console.error('Error loading guest data from localStorage:', error);
            }
        }
        return null;
    };

    const savedGuestData = getSavedGuestData();

    const { data, setData, post, processing, errors } = useForm({
        branch_id: branches[0]?.id || 0,
        payment_method: "cod",
        address_id:
            addresses.find((a) => a.is_default)?.id || addresses[0]?.id || null,
        note: "",
        type: "web_delivery",
        billing_data: {
            first_name: auth?.user?.name || "",
            email: auth?.user?.email || "",
        },
        coupon_id: null as number | null,
        guest_data: is_guest ? {
            name: savedGuestData?.name || "",
            phone: savedGuestData?.phone || "",
            phone_country_code: savedGuestData?.phone_country_code || "+20",
            email: savedGuestData?.email || "",
            street: savedGuestData?.street || "",
            building: savedGuestData?.building || "",
            floor: savedGuestData?.floor || "",
            apartment: savedGuestData?.apartment || "",
            city: savedGuestData?.city || "",
            area_id: savedGuestData?.area_id || null,
        } : undefined,
    });

    // Save guest data to localStorage
    const saveGuestDataToLocalStorage = () => {
        if (is_guest && data.guest_data) {
            try {
                localStorage.setItem('turbo_tenant_guest_data', JSON.stringify(data.guest_data));
            } catch (error) {
                console.error('Error saving guest data to localStorage:', error);
            }
        }
    };

    const calculateDeliveryFee = () => {
        if (data.type === "web_takeaway") return 0;

        // If free shipping coupon is applied
        if (appliedCoupon?.free_shipping) {
            return 0;
        }

        // For guest users
        if (is_guest && data.guest_data?.area_id) {
            const area = governorates
                .flatMap(g => g.areas || [])
                .find(a => a.id === data.guest_data?.area_id);
            return area?.shipping_cost || 0;
        }

        // For registered users
        if (!data.address_id) return 0;
        const address = addresses.find((a) => a.id === data.address_id);
        return address?.area?.shipping_cost || 0;
    };

    const calculateTax = (amount: number) => {
        return amount * 0.0; // 14% VAT
    };

    const calculateService = (amount: number) => {
        let service = 0;
        if (data.payment_method === 'cod') {
            service += settings.cod_fee || 0;
        }
        return service;
    };

    const deliveryFee = calculateDeliveryFee();
    const tax = calculateTax(cart?.total || 0);
    const service = calculateService(cart?.total || 0);
    const discount = appliedCoupon ? appliedCoupon.discount : 0;
    const totalAmount = (cart?.total || 0) + deliveryFee + tax + service - discount;

    const handleCouponApplied = (coupon: AppliedCoupon) => {
        setAppliedCoupon(coupon);
        setData("coupon_id", coupon.id);
    };

    const handleCouponRemoved = () => {
        setAppliedCoupon(null);
        setData("coupon_id", null);
    };

    const handlePlaceOrder = async () => {
        if (!data.branch_id) {
            alert(t("pleaseSelectBranch"));
            return;
        }

        if (data.type === "web_delivery" && !is_guest && !data.address_id) {
            alert(t("pleaseSelectAddress"));
            return;
        }

        if (data.type === "web_delivery" && is_guest) {
            if (!data.guest_data?.name || !data.guest_data?.phone) {
                alert(t("pleaseProvideGuestInfo"));
                return;
            }
            if (!data.guest_data?.area_id) {
                alert(t("pleaseSelectArea"));
                return;
            }
        }

        if (!cart || cart.items.length === 0) {
            alert(t("cartIsEmpty"));
            return;
        }

        setIsSubmitting(true);

        try {
            const response = await axios.post(route("orders.place"), data);

            if (response.data.success) {
                // Save guest data to localStorage for future orders
                saveGuestDataToLocalStorage();

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

    if (!cart || cart.items.length === 0) {
        return (
            <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5">
                <Head title={t("checkout")} />
                <main className="container mx-auto px-4 py-8">
                    <EmptyCart />
                </main>
            </MainLayout>
        );
    }

    return (
        <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5 dark:from-background dark:via-background dark:to-primary/10">
            <Head title={t("checkout")} />

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
                        <OrderTypeSelection
                            value={data.type}
                            onChange={(value) => setData("type", value)}
                        />

                        <BranchSelection
                            branches={branches}
                            value={data.branch_id}
                            onChange={(value) => setData("branch_id", value)}
                        />

                        {is_guest && (
                            <GuestInformationForm
                                guestData={data.guest_data!}
                                onChange={(updates) =>
                                    setData("guest_data", {
                                        ...data.guest_data!,
                                        ...updates,
                                    })
                                }
                                governorates={governorates}
                                orderType={data.type}
                                errors={errors as Record<string, string>}
                            />
                        )}

                        {data.type === "web_delivery" && !is_guest && (
                            <DeliveryAddressSection
                                addresses={addresses}
                                governorates={governorates}
                                selectedAddressId={data.address_id}
                                onAddressChange={(addressId) =>
                                    setData("address_id", addressId)
                                }
                            />
                        )}

                        <PaymentMethodSelection
                            value={data.payment_method}
                            onChange={(value) => setData("payment_method", value)}
                            onlinePaymentsEnabled={settings.online_payments_enabled}
                        />

                        <OrderNotesSection
                            value={data.note}
                            onChange={(value) => setData("note", value)}
                        />

                        <CouponCodeSection
                            cartItems={cart.items}
                            cartTotal={cart.total}
                            addressId={data.address_id}
                            appliedCoupon={appliedCoupon}
                            onCouponApplied={handleCouponApplied}
                            onCouponRemoved={handleCouponRemoved}
                        />
                    </div>

                    {/* Order Summary */}
                    <div className="lg:col-span-1">
                        <OrderSummary
                            cart={cart}
                            deliveryFee={deliveryFee}
                            tax={tax}
                            service={service}
                            discount={discount}
                            totalAmount={totalAmount}
                            appliedCoupon={appliedCoupon}
                            isSubmitting={isSubmitting}
                            onPlaceOrder={handlePlaceOrder}
                        />
                    </div>
                </div>
            </main>
        </MainLayout>
    );
}
