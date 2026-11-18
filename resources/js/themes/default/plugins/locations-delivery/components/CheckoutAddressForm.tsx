import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneInput } from '@/components/ui/phone-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from 'react-i18next';
import { Governorate } from '@/types';
import { AddressPayload } from '@/plugins/locations-delivery/types';
import { useTenantSafe } from '@/core/context/TenantContext';
import { useFormatMoney } from '@/lib/money';
import { formatAreaOptionLabel } from '@/lib/locationFormatters';

export const emptyAddress: AddressPayload = { area_id: '', phone_number: '', street: '', building: '', floor: '', apartment: '', is_default: true };

export default function CheckoutAddressForm({ value, onChange, governorates, errors = {}, simplifiedAddress }: {
    value: AddressPayload;
    onChange: (value: AddressPayload) => void;
    governorates: Governorate[];
    errors?: Record<string, string>;
    simplifiedAddress?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const tenant = useTenantSafe();
    const formatMoney = useFormatMoney();
    const isSimplified = simplifiedAddress ?? Boolean(tenant?.settings?.simplified_address_enabled);
    const update = (field: keyof AddressPayload, next: string) => onChange({ ...value, [field]: next });
    const fields = ['street', 'building', 'floor', 'apartment'] as const;
    return <div className="space-y-4" dir={i18n.dir()}>
        <div className="space-y-2">
            <Label htmlFor="checkout-address-area">{t('area')}</Label>
            <Select value={String(value.area_id || '')} onValueChange={(v) => update('area_id', v)} dir={i18n.dir()}>
                <SelectTrigger id="checkout-address-area" data-testid="delivery-zone-select"><SelectValue placeholder={t('selectArea')} /></SelectTrigger>
                <SelectContent>{governorates.flatMap(g => (g.areas || []).map(a => <SelectItem key={a.id} value={String(a.id)}>{formatAreaOptionLabel(g, a, i18n.language, formatMoney, t('free'))}</SelectItem>))}</SelectContent>
            </Select>
            {errors.area_id && <p className="text-sm text-destructive">{errors.area_id}</p>}
        </div>
        <div className="space-y-2">
            <Label htmlFor="checkout-address-phone">{t('phoneNumber')}</Label>
            <PhoneInput id="checkout-address-phone" value={value.phone_number} onChange={(v) => update('phone_number', v || '')} defaultCountry="EG" countries={['EG']} />
            {errors.phone_number && <p className="text-sm text-destructive">{errors.phone_number}</p>}
        </div>
        {isSimplified ? (
            <div className="space-y-2">
                <Label htmlFor="checkout-address-street">{t('detailedAddress')}</Label>
                <Input
                    id="checkout-address-street"
                    value={value.street}
                    onChange={e => update('street', e.target.value)}
                    placeholder={t('detailedAddressPlaceholder')}
                />
                {errors.street && <p className="text-sm text-destructive">{errors.street}</p>}
            </div>
        ) : (
            <div className="grid gap-4 sm:grid-cols-2">{fields.map(field => <div className="space-y-2" key={field}>
                <Label htmlFor={`checkout-address-${field}`}>{t(field)}</Label>
                <Input id={`checkout-address-${field}`} value={value[field]} onChange={e => update(field, e.target.value)} />
                {errors[field] && <p className="text-sm text-destructive">{errors[field]}</p>}
            </div>)}</div>
        )}
    </div>;
}
