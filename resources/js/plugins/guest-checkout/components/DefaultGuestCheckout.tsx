import React from 'react';

export interface DefaultGuestCheckoutProps {
  className?: string;
  guestData?: {
    name?: string;
    phone?: string;
    email?: string;
  };
  onChange?: (field: string, value: string) => void;
}

export default function DefaultGuestCheckout({
  className = '',
  guestData = {},
  onChange,
}: DefaultGuestCheckoutProps) {
  return (
    <div className={`default-guest-checkout p-4 border rounded-lg bg-card text-card-foreground ${className}`}>
      <h3 className="font-semibold text-base mb-1">Guest Customer Information</h3>
      <p className="text-xs text-muted-foreground mb-3">
        Place your order without creating an account.
      </p>

      <div className="space-y-3 text-sm">
        <div>
          <label className="block text-xs font-medium mb-1">Full Name</label>
          <input
            type="text"
            value={guestData.name || ''}
            onChange={(e) => onChange?.('name', e.target.value)}
            placeholder="Your name"
            className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
          />
        </div>

        <div>
          <label className="block text-xs font-medium mb-1">Phone Number</label>
          <input
            type="tel"
            value={guestData.phone || ''}
            onChange={(e) => onChange?.('phone', e.target.value)}
            placeholder="e.g. 01012345678"
            className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
          />
        </div>

        <div>
          <label className="block text-xs font-medium mb-1">Email (Optional)</label>
          <input
            type="email"
            value={guestData.email || ''}
            onChange={(e) => onChange?.('email', e.target.value)}
            placeholder="your.email@example.com"
            className="w-full px-3 py-2 border rounded-md text-sm bg-background text-foreground"
          />
        </div>
      </div>
    </div>
  );
}
