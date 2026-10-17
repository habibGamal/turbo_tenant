import React from 'react';

interface Props {
    data?: Record<string, unknown>;
}

export const GuestCheckoutWidget: React.FC<Props> = () => {
    return (
        <div className="plugin-guest-checkout-container p-4 border rounded-lg bg-card">
            <h3 className="font-semibold text-lg">Guest Checkout & Order Tracking</h3>
        </div>
    );
};

export default GuestCheckoutWidget;
