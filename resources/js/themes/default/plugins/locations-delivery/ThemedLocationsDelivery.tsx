import React from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DefaultLocationsDelivery from '@/plugins/locations-delivery/components/DefaultLocationsDelivery';
import { Address } from '@/plugins/locations-delivery/types';

export interface ThemedLocationsDeliveryProps {
  initialAddresses?: Address[];
  onSelectAddress?: (address: Address) => void;
  selectedAddressId?: number | null;
}

export default function ThemedLocationsDelivery({
  initialAddresses,
  onSelectAddress,
  selectedAddressId,
}: ThemedLocationsDeliveryProps) {
  const { t } = useTranslation();

  return (
    <div className="themed-locations-delivery w-full">
      <Card>
        <CardHeader>
          <CardTitle>{t('addresses', 'Addresses')}</CardTitle>
          <CardDescription>
            {t('manageAddressesDescription', 'Manage your saved delivery addresses and defaults.')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <DefaultLocationsDelivery
            initialAddresses={initialAddresses}
            onSelectAddress={onSelectAddress}
            selectedAddressId={selectedAddressId}
          />
        </CardContent>
      </Card>
    </div>
  );
}
