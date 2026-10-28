import React, { useEffect, useRef } from 'react';
import { Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { useTenant } from '@/core/context/TenantContext';
import { useAuth } from '@/core/context/AuthContext';

export interface CheckoutGuardProps {
  children: React.ReactNode;
}

export function CheckoutGuard({ children }: CheckoutGuardProps): React.ReactElement | null {
  const { hasPlugin, isLoading: isTenantLoading } = useTenant();
  const { isAuthenticated, isLoading: isAuthLoading } = useAuth();
  const { t } = useTranslation();
  const hasToastedRef = useRef(false);

  const isLoading = isTenantLoading || isAuthLoading;
  const guestCheckoutActive = hasPlugin('guest-checkout');
  const canAccess = isAuthenticated || guestCheckoutActive;

  useEffect(() => {
    if (!isLoading && !canAccess && !hasToastedRef.current) {
      hasToastedRef.current = true;
      toast.error(t('checkoutSignInRequired', 'Please sign in or create an account to proceed'));
    }
  }, [isLoading, canAccess, t]);

  if (isLoading) {
    return null;
  }

  if (!canAccess) {
    return <Navigate to="/login?redirect=/checkout" replace />;
  }

  return <>{children}</>;
}

export default CheckoutGuard;
