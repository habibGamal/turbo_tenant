import React from 'react';

interface Props {
    data?: Record<string, unknown>;
}

export const OrderManagementWidget: React.FC<Props> = () => {
    return (
        <div className="plugin-order-management-container p-4 border rounded-lg bg-card">
            <h3 className="font-semibold text-lg">Order Management & Checkout Core</h3>
        </div>
    );
};

export default OrderManagementWidget;
