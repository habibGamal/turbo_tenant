import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, Trash2, MapPin, LoaderIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { PhoneInput } from '@/components/ui/phone-input';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useAddresses, useGovernorates, useSaveAddress, useDeleteAddress } from '../api/useLocationsDelivery';
import { Address, AddressPayload } from '../types';
import { useTenantSafe } from '@/core/context/TenantContext';
import { formatPlaceLabel } from '@/lib/locationFormatters';

interface DefaultLocationsDeliveryProps {
  initialAddresses?: Address[];
  onSelectAddress?: (address: Address) => void;
  selectedAddressId?: number | null;
}

export default function DefaultLocationsDelivery({
  initialAddresses,
  onSelectAddress,
  selectedAddressId,
}: DefaultLocationsDeliveryProps) {
  const { t, i18n } = useTranslation();
  const tenant = useTenantSafe();
  const isSimplified = Boolean(tenant?.settings?.simplified_address_enabled);
  const isRTL = i18n.dir() === 'rtl';

  const { data: addressesData, isLoading: isLoadingAddresses } = useAddresses();
  const { data: governoratesData, isLoading: isLoadingGovs } = useGovernorates();
  const saveMutation = useSaveAddress();
  const deleteMutation = useDeleteAddress();

  const addresses: Address[] = addressesData ?? initialAddresses ?? [];
  const governorates = governoratesData ?? [];

  const [isOpen, setIsOpen] = useState(false);
  const [editingAddress, setEditingAddress] = useState<Address | null>(null);
  const [selectedGovernorateId, setSelectedGovernorateId] = useState<string>('');
  const [formData, setFormData] = useState<AddressPayload>({
    area_id: '',
    phone_number: '',
    street: '',
    building: '',
    floor: '',
    apartment: '',
    notes: '',
    is_default: false,
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  const openCreateModal = () => {
    setEditingAddress(null);
    setSelectedGovernorateId('');
    setFormData({
      area_id: '',
      phone_number: '',
      street: '',
      building: '',
      floor: '',
      apartment: '',
      notes: '',
      is_default: false,
    });
    setFormErrors({});
    setIsOpen(true);
  };

  const openEditModal = (address: Address) => {
    setEditingAddress(address);
    setSelectedGovernorateId(address.area?.governorate?.id?.toString() || '');
    setFormData({
      area_id: address.area_id.toString(),
      phone_number: address.phone_number,
      street: address.street || '',
      building: address.building || '',
      floor: address.floor || '',
      apartment: address.apartment || '',
      notes: address.notes || '',
      is_default: address.is_default,
    });
    setFormErrors({});
    setIsOpen(true);
  };

  const handleDelete = async (id: number) => {
    if (confirm(t('confirmDeleteAddress', 'Are you sure you want to delete this address?'))) {
      try {
        await deleteMutation.mutateAsync(id);
      } catch (err: any) {
        console.error('Failed to delete address:', err);
      }
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormErrors({});

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
      if (err.response?.data?.errors) {
        const errors: Record<string, string> = {};
        for (const [key, messages] of Object.entries(err.response.data.errors)) {
          errors[key] = Array.isArray(messages) ? messages[0] : (messages as string);
        }
        setFormErrors(errors);
      } else {
        setFormErrors({ general: err.response?.data?.message || 'Failed to save address.' });
      }
    }
  };

  const selectedGovernorate = governorates.find(
    (g) => g.id.toString() === selectedGovernorateId
  );

  return (
    <div dir={i18n.dir()} className="space-y-6 w-full text-start">
      <div className="flex flex-wrap gap-3 justify-between items-center">
        <h3 className="text-lg font-medium">{t('yourAddresses', 'Your Addresses')}</h3>
        <Button onClick={openCreateModal} size="sm">
          <Plus className="h-4 w-4 ltr:mr-2 rtl:ml-2" />
          {t('addNewAddress', 'Add New Address')}
        </Button>
      </div>

      {isLoadingAddresses ? (
        <div className="flex items-center justify-center py-10">
          <LoaderIcon className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : addresses.length === 0 ? (
        <div className="text-center py-8 border rounded-lg p-6 bg-muted/20">
          <MapPin className="h-10 w-10 mx-auto text-muted-foreground mb-2 opacity-60" />
          <p className="text-sm text-muted-foreground">{t('noAddressesFound', 'No addresses registered yet.')}</p>
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {addresses.map((address) => {
            const isSelected = selectedAddressId === address.id;
            return (
              <div
                key={address.id}
                onClick={() => onSelectAddress?.(address)}
                className={`border rounded-lg p-4 transition-colors ${
                  onSelectAddress ? 'cursor-pointer' : ''
                } ${
                  isSelected ? 'border-primary ring-1 ring-primary bg-primary/5' : 'hover:border-primary/50'
                }`}
              >
                {address.is_default && (
                  <span className="mb-3 inline-flex w-fit bg-primary text-primary-foreground text-xs px-2 py-0.5 rounded-full font-medium">
                    {t('default', 'Default')}
                  </span>
                )}
                <div className="flex items-start gap-3">
                  <MapPin className="h-5 w-5 text-muted-foreground mt-1 shrink-0" />
                  <div className="space-y-1 text-sm">
                    <p className="font-medium text-foreground">
                      {[address.street, address.building].filter(Boolean).join(', ')}
                    </p>
                    {(address.floor || address.apartment) && (
                      <p className="text-xs text-muted-foreground">
                        {[
                          address.floor ? `${t('floor', 'Floor')}: ${address.floor}` : null,
                          address.apartment ? `${t('apartment', 'Apartment')}: ${address.apartment}` : null,
                        ]
                          .filter(Boolean)
                          .join(', ')}
                      </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                      {formatPlaceLabel(
                        address.area?.governorate?.name,
                        address.area?.governorate?.name_ar,
                        address.area?.name,
                        address.area?.name_ar,
                        i18n.language,
                        ', '
                      )}
                    </p>
                    <p className="text-xs text-muted-foreground text-start">
                      <bdi dir="ltr">{address.phone_number}</bdi>
                    </p>
                  </div>
                </div>

                <div className="flex justify-end gap-2 mt-4 pt-2 border-t">
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    onClick={(e) => {
                      e.stopPropagation();
                      openEditModal(address);
                    }}
                  >
                    <Pencil className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    type="button"
                    variant="destructive"
                    size="icon"
                    className="h-8 w-8"
                    disabled={deleteMutation.isPending}
                    onClick={(e) => {
                      e.stopPropagation();
                      handleDelete(address.id);
                    }}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Address Form Dialog */}
      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent dir={i18n.dir()} className="max-h-[90vh] overflow-y-auto text-start sm:max-w-[500px]">
          <DialogHeader className="text-start sm:text-start rtl:pe-0 rtl:ps-6">
            <DialogTitle>
              {editingAddress ? t('editAddress', 'Edit Address') : t('addNewAddress', 'Add New Address')}
            </DialogTitle>
            <DialogDescription>
              {t('addressDetailsDescription', 'Enter full delivery destination details.')}
            </DialogDescription>
          </DialogHeader>

          {formErrors.general && (
            <div className="p-3 text-sm text-destructive bg-destructive/10 rounded-md">
              {formErrors.general}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t('governorate', 'Governorate')}</Label>
                <Select
                  dir={i18n.dir()}
                  value={selectedGovernorateId}
                  onValueChange={(val) => {
                    setSelectedGovernorateId(val);
                    const selectedGov = governorates.find((g) => g.id.toString() === val);
                    const firstArea = selectedGov?.areas?.[0]?.id.toString() || '';
                    setFormData((prev) => ({ ...prev, area_id: firstArea }));
                  }}
                  disabled={isLoadingGovs}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('selectGovernorate', 'Select Governorate')} />
                  </SelectTrigger>
                  <SelectContent>
                    {governorates.map((gov) => (
                      <SelectItem key={gov.id} value={gov.id.toString()}>
                        {isRTL && gov.name_ar ? gov.name_ar : gov.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label>{t('area', 'Area')}</Label>
                <Select
                  dir={i18n.dir()}
                  value={formData.area_id.toString()}
                  onValueChange={(val) => setFormData((prev) => ({ ...prev, area_id: val }))}
                  disabled={!selectedGovernorateId}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('selectArea', 'Select Area')} />
                  </SelectTrigger>
                  <SelectContent>
                    {selectedGovernorate?.areas?.map((area) => (
                      <SelectItem key={area.id} value={area.id.toString()}>
                        {isRTL && area.name_ar ? area.name_ar : area.name}{' '}
                        {area.shipping_cost > 0 && `(${area.shipping_cost} ${t('currency', 'EGP')})`}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formErrors.area_id && (
                  <p className="text-xs text-destructive">{formErrors.area_id}</p>
                )}
              </div>
            </div>

            <div className="space-y-2">
              <Label>{isSimplified ? t('detailedAddress', 'Detailed Address') : t('street', 'Street')}</Label>
              <Input
                value={formData.street}
                onChange={(e) => setFormData((prev) => ({ ...prev, street: e.target.value }))}
                placeholder={isSimplified ? t('detailedAddressPlaceholder') : undefined}
                required
              />
              {formErrors.street && <p className="text-xs text-destructive">{formErrors.street}</p>}
            </div>

            {!isSimplified && (
              <div className="space-y-2">
                <Label>{t('building', 'Building')}</Label>
                <Input
                  value={formData.building}
                  onChange={(e) => setFormData((prev) => ({ ...prev, building: e.target.value }))}
                  required
                />
                {formErrors.building && <p className="text-xs text-destructive">{formErrors.building}</p>}
              </div>
            )}

            <div className="space-y-2">
              <Label>{t('phoneNumber', 'Phone Number')}</Label>
              <PhoneInput
                value={formData.phone_number}
                onChange={(val) => setFormData((prev) => ({ ...prev, phone_number: val || '' }))}
                defaultCountry="EG"
                countries={['EG']}
                placeholder="+2010xxxxxxxx"
                dir="ltr"
              />
              {formErrors.phone_number && (
                <p className="text-xs text-destructive">{formErrors.phone_number}</p>
              )}
            </div>

            {!isSimplified && (
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>{t('floor', 'Floor')}</Label>
                  <Input
                    value={formData.floor}
                    onChange={(e) => setFormData((prev) => ({ ...prev, floor: e.target.value }))}
                    required
                  />
                  {formErrors.floor && <p className="text-xs text-destructive">{formErrors.floor}</p>}
                </div>

                <div className="space-y-2">
                  <Label>{t('apartment', 'Apartment')}</Label>
                  <Input
                    value={formData.apartment}
                    onChange={(e) => setFormData((prev) => ({ ...prev, apartment: e.target.value }))}
                    required
                  />
                  {formErrors.apartment && (
                    <p className="text-xs text-destructive">{formErrors.apartment}</p>
                  )}
                </div>
              </div>
            )}

            <div className="space-y-2">
              <Label>{t('notes', 'Notes')}</Label>
              <Textarea
                value={formData.notes || ''}
                onChange={(e) => setFormData((prev) => ({ ...prev, notes: e.target.value }))}
                rows={2}
              />
            </div>

            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="is_default_address"
                checked={Boolean(formData.is_default)}
                onChange={(e) => setFormData((prev) => ({ ...prev, is_default: e.target.checked }))}
                className="rounded border-gray-300 text-primary focus:ring-primary"
              />
              <Label htmlFor="is_default_address" className="cursor-pointer">
                {t('setAsDefaultAddress', 'Set as default address')}
              </Label>
            </div>

            <DialogFooter className="gap-2 sm:space-x-0">
              <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                {t('cancel', 'Cancel')}
              </Button>
              <Button type="submit" disabled={saveMutation.isPending}>
                {saveMutation.isPending ? (
                  <>
                    <LoaderIcon className="h-4 w-4 animate-spin ltr:mr-2 rtl:ml-2" />
                    {t('saving', 'Saving...')}
                  </>
                ) : editingAddress ? (
                  t('update', 'Update')
                ) : (
                  t('save', 'Save')
                )}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
