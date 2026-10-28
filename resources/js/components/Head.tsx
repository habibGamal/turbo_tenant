import React, { useEffect } from 'react';
import { useTenantSafe } from '@/core/context/TenantContext';

interface HeadProps {
  title?: string;
  description?: string;
}

export function Head({ title, description }: HeadProps) {
  const tenantContext = useTenantSafe();
  const siteName = tenantContext?.settings?.site_name || 'Turbo Tenant';

  useEffect(() => {
    if (title) {
      document.title = `${title} - ${siteName}`;
    } else {
      document.title = siteName;
    }

    if (description) {
      let metaDesc = document.querySelector('meta[name="description"]');
      if (!metaDesc) {
        metaDesc = document.createElement('meta');
        metaDesc.setAttribute('name', 'description');
        document.head.appendChild(metaDesc);
      }
      metaDesc.setAttribute('content', description);
    }
  }, [title, description, siteName]);

  return null;
}

export default Head;
