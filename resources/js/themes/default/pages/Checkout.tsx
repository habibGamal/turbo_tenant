import React, { useState, useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { Head } from "@/components/Head";
import MainLayout from '@/themes/default/layouts/MainLayout';
import { useTranslation } from "react-i18next";
import { Address, Branch, Cart, Governorate, PageProps } from "@/types";
import { PluginSlot } from "@/core/slots/PluginSlot";
import { emitCommerceEvent } from "@/core/analytics";
import { CheckoutSkeleton } from "@/components/ui/page-skeletons";
import { usePlaceOrder } from "@/plugins/order-management";
import { useAuth } from "@/core/context/AuthContext";
import { useTenant } from "@/core/context/TenantContext";
import { apiClient } from "@/core/api/client";
import { useCart, cartItemToCommerceItem } from "@/plugins/cart-engine";
import { useLocationsDelivery } from "@/plugins/locations-delivery";
import { useCurrency } from "@/lib/money";

// Checkout Components (Colocated within respective theme plugin directories)
import EmptyCart from "@/themes/default/plugins/cart-engine/components/EmptyCart";
import OrderTypeSelection from "@/themes/default/plugins/order-management/components/OrderTypeSelection";
import BranchSelection from "@/themes/default/plugins/locations-delivery/components/BranchSelection";
import GuestInformationForm from "@/themes/default/plugins/guest-checkout/components/GuestInformationForm";
import DeliveryAddressSection from "@/themes/default/plugins/locations-delivery/components/DeliveryAddressSection";
import { emptyAddress } from "@/themes/default/plugins/locations-delivery/components/CheckoutAddressForm";
import { useSaveAddress } from "@/plugins/locations-delivery/api/useLocationsDelivery";
import PaymentMethodSelection from "@/themes/default/plugins/order-management/components/PaymentMethodSelection";
import OrderNotesSection from "@/themes/default/plugins/order-management/components/OrderNotesSection";
import CouponCodeSection from "@/themes/default/plugins/order-management/components/CouponCodeSection";
import OrderSummary from "@/themes/default/plugins/order-management/components/OrderSummary";

interface CheckoutPageProps extends Partial<PageProps> {
    cart?: Cart;
    addresses?: Address[];
    branches?: Branch[];
    governorates?: Governorate[];
    is_guest?: boolean;
}

export default function Checkout({
    cart: initialCart,
    addresses: initialAddresses,
    branches: initialBranches,
    governorates: initialGovernorates,
    auth,
    settings,
    is_guest: initialIsGuest,
}: CheckoutPageProps) {
    const { t } = useTranslation();
    const currency = useCurrency();
    const navigate = useNavigate();
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { user, isAuthenticated, isLoading: isAuthLoading, refetchUser } = useAuth();
    const { settings: tenantSettings } = useTenant();
    const checkoutSettings = settings ?? tenantSettings;
    const { data: cartData, isLoading: isCartLoading } = useCart();
    const { data: locationsData, isLoading: isLocationsLoading } = useLocationsDelivery();

    const isLoading = isAuthLoading || isCartLoading || isLocationsLoading;
    const placeOrderMutation = usePlaceOrder();
    const saveAddress = useSaveAddress();
    const [addressDraft, setAddressDraft] = useState({ ...emptyAddress });
    const [addressDraftErrors, setAddressDraftErrors] = useState<Record<string, string>>({});

    const cart = (cartData || initialCart) as Cart;
    const addresses = (locationsData?.addresses || initialAddresses || []) as Address[];
    const branches = (locationsData?.branches || initialBranches || []) as Branch[];
    const governorates = (locationsData?.governorates || initialGovernorates || []) as Governorate[];
    const is_guest = !isAuthenticated;

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

    const lastCartSignatureRef = useRef<string | null>(null);

    useEffect(() => {
        if (!cart?.items || cart.items.length === 0) return;

        const signature = cart.items
            .map((i) => `${i.product_id}:${i.variant?.id ?? ''}:${i.quantity}:${i.subtotal}`)
            .join('|');

        if (lastCartSignatureRef.current === signature) {
            return;
        }

        lastCartSignatureRef.current = signature;

        const items = cart.items.map((i) => cartItemToCommerceItem(i));

        emitCommerceEvent('InitiateCheckout', {
            items,
            value: Number((cart as any).subtotal ?? cart.total) || 0,
            currency,
            content_type: 'product',
        });
    }, [cart?.items, (cart as any)?.subtotal, cart?.total, currency]);

    const [data, setFormData] = useState({
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

    const setData = (field: string, value: any) => {
        setFormData((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const [errors, setErrors] = useState<Record<string, string>>({});

    // Auto-select initial branch and address once bootstrap data loads
    useEffect(() => {
        if (branches.length > 0 && !data.branch_id) {
            setData("branch_id", branches[0].id);
        }
        if (addresses.length > 0 && !data.address_id) {
            const defaultAddr = addresses.find((a) => a.is_default)?.id || addresses[0].id;
            setData("address_id", defaultAddr);
        }
    }, [branches, addresses]);

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
        if (!data.address_id && addresses.length === 0 && addressDraft.area_id) {
            return governorates.flatMap(g => g.areas || []).find(a => a.id === Number(addressDraft.area_id))?.shipping_cost || 0;
        }
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
            service += checkoutSettings.cod_fee || 0;
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

    const scrollToFirstError = (fieldErrors: Record<string, string>) => {
        const fieldPriority = [
            "branch_id",
            "guest_data.name",
            "guest_data.phone",
            "guest_data.email",
            "guest_data.area_id",
            "guest_data.street",
            "guest_data.building",
            "guest_data.floor",
            "guest_data.apartment",
            "address_id",
        ];

        const fieldIdMap: Record<string, string> = {
            branch_id: "checkout-branch-section",
            address_id: "checkout-address-section",
            "guest_data.name": "guest_name",
            "guest_data.phone": "guest_phone",
            "guest_data.email": "guest_email",
            "guest_data.area_id": "guest_area",
            "guest_data.street": "guest_street",
            "guest_data.building": "guest_building",
            "guest_data.floor": "guest_floor",
            "guest_data.apartment": "guest_apartment",
            "guest_data.city": "guest_city",
        };

        const targetField = fieldPriority.find((k) => fieldErrors[k]) || Object.keys(fieldErrors)[0];
        if (!targetField) return;

        const targetId = fieldIdMap[targetField] || targetField.replace(/\./g, "_");

        setTimeout(() => {
            const el =
                document.getElementById(targetId) ||
                document.querySelector(`[data-testid="${targetId}"]`) ||
                document.querySelector(`[name="${targetField}"]`) ||
                document.querySelector(`[data-testid="branch-select-trigger"]`) ||
                document.querySelector(`[data-testid="address-error"]`);
            if (el) {
                el.scrollIntoView({ behavior: "smooth", block: "center" });
                if ("focus" in el && typeof (el as HTMLElement).focus === "function") {
                    (el as HTMLElement).focus();
                }
            }
        }, 50);
    };

    const handleServerErrors = (serverErrors?: Record<string, any>, fallbackMessage?: string) => {
        if (!serverErrors || Object.keys(serverErrors).length === 0) {
            toast.error(fallbackMessage || t("failedToPlaceOrder"));
            return;
        }

        const knownFields = new Set([
            "branch_id",
            "address_id",
            "payment_method",
            "type",
            "note",
            "guest_data.name",
            "guest_data.phone",
            "guest_data.email",
            "guest_data.area_id",
            "guest_data.street",
            "guest_data.building",
            "guest_data.floor",
            "guest_data.apartment",
            "guest_data.city",
        ]);

        const fieldErrors: Record<string, string> = {};
        const unmappedErrors: string[] = [];

        Object.entries(serverErrors).forEach(([key, val]) => {
            const message = Array.isArray(val) ? val.join(", ") : String(val);
            if (knownFields.has(key)) {
                fieldErrors[key] = message;
            } else {
                unmappedErrors.push(message);
            }
        });

        if (Object.keys(fieldErrors).length > 0) {
            setErrors(fieldErrors);
            scrollToFirstError(fieldErrors);
        }

        if (unmappedErrors.length > 0) {
            toast.error(unmappedErrors.join(", "));
        } else if (Object.keys(fieldErrors).length === 0) {
            toast.error(fallbackMessage || t("failedToPlaceOrder"));
        }
    };

    const handlePlaceOrder = async () => {
        const validationErrors: Record<string, string> = {};

        if (!data.branch_id) {
            validationErrors.branch_id = t("pleaseSelectBranch");
        }

        if (data.type === "web_delivery" && !is_guest && !data.address_id) {
            if (addresses.length > 0) {
                validationErrors.address_id = t("pleaseSelectAddress");
            } else {
                const missing: Record<string, string> = {};
                for (const field of ['area_id', 'phone_number', 'street', 'building', 'floor', 'apartment'] as const) {
                    if (!String(addressDraft[field]).trim()) missing[field] = t('required', 'Required');
                }
                setAddressDraftErrors(missing);
                if (Object.keys(missing).length) validationErrors.address_id = t('pleaseProvideAddress', 'Please complete your delivery address.');
            }
        }

        if (data.type === "web_delivery" && is_guest) {
            if (!data.guest_data?.name) {
                validationErrors["guest_data.name"] = t("pleaseProvideGuestInfo");
            }
            if (!data.guest_data?.phone) {
                validationErrors["guest_data.phone"] = t("pleaseProvideGuestInfo");
            }
            if (!data.guest_data?.area_id) {
                validationErrors["guest_data.area_id"] = t("pleaseSelectArea");
            }
        }

        if (Object.keys(validationErrors).length > 0) {
            setErrors(validationErrors);
            scrollToFirstError(validationErrors);
            return;
        }

        if (!cart || cart.items.length === 0) {
            toast.error(t("cartIsEmpty"));
            return;
        }

        setIsSubmitting(true);

        try {
            if (isAuthenticated) {
                const { data: session } = await apiClient.get('/api/tenant/auth/user');
                if (!session.user || session.user.id !== user?.id) {
                    refetchUser();
                    toast.error(t('checkoutSessionExpired'));
                    navigate('/login?redirect=%2Fcheckout', { replace: true });
                    return;
                }
            }

            let addressId = data.address_id;
            if (data.type === 'web_delivery' && isAuthenticated && !addressId && addresses.length === 0) {
                try {
                    const address = await saveAddress.mutateAsync({ payload: { ...addressDraft, area_id: Number(addressDraft.area_id) } });
                    addressId = address.id;
                    setData('address_id', addressId);
                } catch (error: any) {
                    const serverErrors = error.response?.data?.errors;
                    if (serverErrors) {
                        setAddressDraftErrors(Object.fromEntries(Object.entries(serverErrors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)])));
                        scrollToFirstError({ address_id: t('pleaseProvideAddress', 'Please complete your delivery address.') });
                    } else {
                        toast.error(error.response?.data?.message || t('somethingWentWrong'));
                    }
                    return;
                }
            }

            if (data.payment_method !== 'cod') {
                emitCommerceEvent('AddPaymentInfo', {
                    value: totalAmount,
                    currency,
                });
            }

            const payload = {
                ...data,
                address_id: addressId,
                guest_data: is_guest ? data.guest_data : null,
            };

            const orderData = await placeOrderMutation.mutateAsync(payload as any);

            // Save guest data to localStorage for future orders
            saveGuestDataToLocalStorage();

            const { requires_payment, checkout_url, order_id, order_number, tracking_token } = orderData as any;

            if (requires_payment && checkout_url) {
                // Redirect to external payment gateway
                window.location.href = checkout_url;
            } else if (tracking_token) {
                navigate(`/orders/track?order_number=${encodeURIComponent(order_number)}&token=${encodeURIComponent(tracking_token)}`);
            } else {
                navigate(`/orders/${order_id}`);
            }
        } catch (error: any) {
            console.error("Order placement error:", error);

            if (error.response?.data?.errors) {
                handleServerErrors(error.response.data.errors, error.response.data.message);
            } else if (error.response?.data?.message) {
                toast.error(error.response.data.message);
            } else if (!navigator.onLine) {
                toast.error(t('checkoutOffline'));
            } else {
                toast.error(t('checkoutUnconfirmed'));
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    if (isLoading) {
        return (
            <MainLayout className="bg-gradient-to-b from-background via-background/95 to-primary/5">
                <Head title={t("checkout")} />
                <CheckoutSkeleton />
            </MainLayout>
        );
    }

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

            <main className="container mx-auto px-4 py-8 md:py-12 pb-28 lg:pb-12" data-testid="checkout-form">
                <div className="mb-8">
                    <h1 className="text-3xl md:text-4xl font-bold mb-2">
                        {t("checkout")}
                    </h1>
                    <p className="text-muted-foreground">
                        {t("completeYourOrder")}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3 lg:gap-8">
                    {/* Checkout Form */}
                    <div className="lg:col-span-2 space-y-6">
                        <OrderTypeSelection
                            value={data.type}
                            onChange={(value) => setData("type", value)}
                        />

                        <div id="checkout-branch-section">
                            <BranchSelection
                                branches={branches}
                                value={data.branch_id}
                                onChange={(value) => {
                                    setData("branch_id", value);
                                    setErrors((prev) => {
                                        const next = { ...prev };
                                        delete next.branch_id;
                                        return next;
                                    });
                                }}
                                error={errors.branch_id}
                            />
                        </div>

                        {is_guest && (
                            <PluginSlot
                                name="checkout.guest.form"
                                pluginId="guest-checkout"
                                context={{
                                    guestData: data.guest_data!,
                                    onChange: (updates: any) => {
                                        setData("guest_data", {
                                            ...data.guest_data!,
                                            ...updates,
                                        });
                                        setErrors((prev) => {
                                            const next = { ...prev };
                                            Object.keys(updates).forEach((k) => {
                                                delete next[`guest_data.${k}`];
                                            });
                                            return next;
                                        });
                                    },
                                    governorates,
                                    orderType: data.type,
                                    errors,
                                }}
                                fallback={
                                    <GuestInformationForm
                                        guestData={data.guest_data!}
                                        onChange={(updates) => {
                                            setData("guest_data", {
                                                ...data.guest_data!,
                                                ...updates,
                                            });
                                            setErrors((prev) => {
                                                const next = { ...prev };
                                                Object.keys(updates).forEach((k) => {
                                                    delete next[`guest_data.${k}`];
                                                });
                                                return next;
                                            });
                                        }}
                                        governorates={governorates}
                                        orderType={data.type}
                                        errors={errors as Record<string, string>}
                                    />
                                }
                            />
                        )}

                        {data.type === "web_delivery" && !is_guest && (
                            <div id="checkout-address-section">
                                <DeliveryAddressSection
                                    addresses={addresses}
                                    governorates={governorates}
                                    draft={addressDraft}
                                    onDraftChange={(draft) => { setAddressDraft(draft); setAddressDraftErrors({}); }}
                                    draftErrors={addressDraftErrors}
                                    selectedAddressId={data.address_id}
                                    onAddressChange={(addressId) => {
                                        setData("address_id", addressId);
                                        setErrors((prev) => {
                                            const next = { ...prev };
                                            delete next.address_id;
                                            return next;
                                        });
                                    }}
                                    error={errors.address_id}
                                />
                            </div>
                        )}

                        <PaymentMethodSelection
                            value={data.payment_method}
                            onChange={(value) => setData("payment_method", value)}
                            onlinePaymentsEnabled={Boolean(checkoutSettings.online_payments_enabled)}
                            codFee={checkoutSettings.cod_fee}
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
                            orderType={data.type}
                            hasAddressSelected={is_guest ? Boolean(data.guest_data?.area_id) : Boolean(data.address_id || (addresses.length === 0 && addressDraft.area_id))}
                        />
                    </div>
                </div>
            </main>
        </MainLayout>
    );
}
