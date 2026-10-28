import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, Trash2, MapPin, Loader2 } from 'lucide-react';
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
  const isRTL = i18n.dir() === 'rtl';

  const { data: addressesResponse, isLoading: isLoadingAddresses } = useAddresses();
  const { data: governoratesResponse, isLoading: isLoadingGovs } = useGovernorates();
  const saveMutation = useSaveAddress();
  const deleteMutation = useDeleteAddress();

  const addresses: Address[] = addressesResponse?.data ?? initialAddresses ?? [];
  const governorates = governoratesResponse?.data ?? [];

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
      street: address.street,
      building: address.building,
      floor: address.floor,
      apartment: address.apartment,
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
    <div className="space-y-6 w-full">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium">{t('yourAddresses', 'Your Addresses')}</h3>
        <Button onClick={openCreateModal} size="sm">
          <Plus className="h-4 w-4 ltr:mr-2 rtl:ml-2" />
          {t('addNewAddress', 'Add New Address')}
        </Button>
      </div>

      {isLoadingAddresses ? (
        <div className="flex items-center justify-center py-10">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
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
                className={`border rounded-lg p-4 relative transition-colors ${
                  onSelectAddress ? 'cursor-pointer' : ''
                } ${
                  isSelected ? 'border-primary ring-1 ring-primary bg-primary/5' : 'hover:border-primary/50'
                }`}
              >
                {address.is_default && (
                  <span className="absolute top-2 ltr:right-2 rtl:left-2 bg-primary text-primary-foreground text-xs px-2 py-0.5 rounded-full font-medium">
                    {t('default', 'Default')}
                  </span>
                )}
                <div className="flex items-start gap-3">
                  <MapPin className="h-5 w-5 text-muted-foreground mt-1 shrink-0" />
                  <div className="space-y-1 text-sm">
                    <p className="font-medium text-foreground">
                      {address.street}, {address.building}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {t('floor', 'Floor')}: {address.floor}, {t('apartment', 'Apartment')}: {address.apartment}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {isRTL && address.area?.name_ar ? address.area.name_ar : address.area?.name},{' '}
                      {isRTL && address.area?.governorate?.name_ar
                        ? address.area.governorate.name_ar
                        : address.area?.governorate?.name}
                    </p>
                    <p className="text-xs text-muted-foreground dir-ltr text-start">
                      {address.phone_number}
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
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
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
              <Label>{t('street', 'Street')}</Label>
              <Input
                value={formData.street}
                onChange={(e) => setFormData((prev) => ({ ...prev, street: e.target.value }))}
                required
              />
              {formErrors.street && <p className="text-xs text-destructive">{formErrors.street}</p>}
            </div>

            <div className="space-y-2">
              <Label>{t('building', 'Building')}</Label>
              <Input
                value={formData.building}
                onChange={(e) => setFormData((prev) => ({ ...prev, building: e.target.value }))}
                required
              />
              {formErrors.building && <p className="text-xs text-destructive">{formErrors.building}</p>}
            </div>

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

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                {t('cancel', 'Cancel')}
              </Button>
              <Button type="submit" disabled={saveMutation.isPending}>
                {saveMutation.isPending ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin ltr:mr-2 rtl:ml-2" />
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
