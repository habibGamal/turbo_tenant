import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { LoaderIcon, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Address, Governorate } from '@/types';
import { AddressPayload } from '@/plugins/locations-delivery/types';
import { useSaveAddress } from '@/plugins/locations-delivery/api/useLocationsDelivery';
import { useFormatMoney } from '@/lib/money';
import { formatPlaceLabel, formatSavedAddressLines } from '@/lib/locationFormatters';
import CheckoutAddressForm, { emptyAddress } from './CheckoutAddressForm';

export interface DeliveryAddressSectionProps {
    addresses: Address[];
    governorates: Governorate[];
    selectedAddressId: number | null;
    onAddressChange: (addressId: number) => void;
    draft: AddressPayload;
    onDraftChange: (draft: AddressPayload) => void;
    draftErrors?: Record<string, string>;
    error?: string;
    simplifiedAddress?: boolean;
}

export default function DeliveryAddressSection({ addresses, governorates, selectedAddressId, onAddressChange, draft, onDraftChange, draftErrors, error, simplifiedAddress }: DeliveryAddressSectionProps) {
    const { t, i18n } = useTranslation();
    const formatMoney = useFormatMoney();
    const [open, setOpen] = useState(false);
    const [newAddress, setNewAddress] = useState<AddressPayload>({ ...emptyAddress, is_default: false });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const saveAddress = useSaveAddress();

    const submitAddress = async (event: React.FormEvent) => {
        event.preventDefault();
        setErrors({});
        try {
            const address = await saveAddress.mutateAsync({ payload: { ...newAddress, area_id: Number(newAddress.area_id) } });
            onAddressChange(address.id);
            setOpen(false);
            setNewAddress({ ...emptyAddress, is_default: false });
        } catch (err: any) {
            const serverErrors = err.response?.data?.errors;
            if (serverErrors) setErrors(Object.fromEntries(Object.entries(serverErrors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)])));
            else setErrors({ general: err.response?.data?.message || t('somethingWentWrong') });
        }
    };

    return <Card variant="recessed" className="overflow-hidden">
        <CardHeader className="p-4 sm:p-6"><div className="flex flex-wrap items-center justify-between gap-4">
            <div className="space-y-1"><CardTitle>{t('deliveryAddress')}</CardTitle><CardDescription>{addresses.length ? t('selectDeliveryAddress') : t('addressDetailsDescription')}</CardDescription></div>
            {addresses.length > 0 && <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild><Button type="button" variant="outline" size="sm" className="border-border/90 bg-background shadow-none hover:bg-accent/60 dark:bg-card"><Plus className="me-2 h-4 w-4" />{t('addNewAddress')}</Button></DialogTrigger>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl" dir={i18n.dir()}>
                    <DialogHeader className="text-start"><DialogTitle>{t('addNewAddress')}</DialogTitle><DialogDescription>{t('addressDetailsDescription')}</DialogDescription></DialogHeader>
                    <form onSubmit={submitAddress} className="space-y-5">
                        <CheckoutAddressForm value={newAddress} onChange={setNewAddress} governorates={governorates} errors={errors} simplifiedAddress={simplifiedAddress} />
                        {errors.general && <p className="text-sm text-destructive" role="alert">{errors.general}</p>}
                        <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setOpen(false)}>{t('cancel')}</Button><Button type="submit" disabled={saveAddress.isPending}>{saveAddress.isPending && <LoaderIcon className="me-2 h-4 w-4 animate-spin" />}{t('save')}</Button></div>
                    </form>
                </DialogContent>
            </Dialog>}
        </div></CardHeader>
        <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
            {addresses.length === 0 ? <CheckoutAddressForm value={draft} onChange={onDraftChange} governorates={governorates} errors={draftErrors} simplifiedAddress={simplifiedAddress} /> :
                <RadioGroup value={selectedAddressId?.toString()} onValueChange={value => onAddressChange(Number(value))} className="grid gap-3" dir={i18n.dir()}>
                    {addresses.map(address => {
                        const areaDisplay = formatPlaceLabel(
                            address.area?.governorate?.name,
                            address.area?.governorate?.name_ar,
                            address.area?.name,
                            address.area?.name_ar,
                            i18n.language,
                            ', '
                        );
                        const addressDetails = formatSavedAddressLines(
                            address,
                            { floor: t('floor'), apt: t('apt') },
                            i18n.language === 'ar'
                        );

                        return (
                            <Label key={address.id} htmlFor={`address-${address.id}`} className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 sm:p-4 transition-colors ${selectedAddressId === address.id ? 'border-primary bg-background dark:bg-card' : 'border-border/90 bg-background/85 hover:border-primary/40 hover:bg-background dark:bg-card/75 dark:hover:bg-card'}`}>
                                <RadioGroupItem value={String(address.id)} id={`address-${address.id}`} className="mt-1" />
                                <span className="min-w-0 flex-1 space-y-1">
                                    <span className="flex flex-wrap items-center gap-2 font-medium">
                                        {areaDisplay}
                                        {address.is_default && <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">{t('default')}</span>}
                                    </span>
                                    {addressDetails && <span className="block text-sm text-muted-foreground">{addressDetails}</span>}
                                    <span className="flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                                        <span dir="ltr">{address.phone_number}</span>
                                        <span>{t('deliveryFee')}: {address.area?.shipping_cost ? formatMoney(address.area.shipping_cost) : t('free')}</span>
                                    </span>
                                </span>
                            </Label>
                        );
                    })}
                </RadioGroup>}
            {error && <p className="mt-3 text-sm text-destructive" data-testid="address-error" role="alert">{error}</p>}
        </CardContent>
    </Card>;
}
