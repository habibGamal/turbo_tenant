import React from 'react';
import { useOrders } from '../api/useOrders';
import { PaginatedOrders, Order } from '../types';

export interface DefaultOrdersListProps {
    initialData?: PaginatedOrders;
    onSelectOrder?: (order: Order) => void;
}

export default function DefaultOrdersList({ initialData, onSelectOrder }: DefaultOrdersListProps) {
    const { data: paginatedOrders, isLoading, error } = useOrders({ page: 1 });
    const ordersData = paginatedOrders || initialData;

    if (isLoading && !ordersData) {
        return (
            <div className="py-8 text-center text-sm text-muted-foreground animate-pulse">
                Loading orders...
            </div>
        );
    }

    if (error && !ordersData) {
        return (
            <div className="py-8 text-center text-sm text-destructive">
                Failed to load orders. Please try again.
            </div>
        );
    }

    const orders = ordersData?.data || [];

    if (orders.length === 0) {
        return (
            <div className="py-12 text-center border rounded-lg bg-card text-card-foreground">
                <p className="text-muted-foreground text-sm">No orders found.</p>
            </div>
        );
    }

    return (
        <div className="w-full space-y-4">
            <h2 className="text-xl font-semibold tracking-tight">Order History</h2>
            <div className="divide-y border rounded-lg bg-card">
                {orders.map((order) => (
                    <div
                        key={order.id}
                        className="p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 hover:bg-muted/50 transition-colors"
                        onClick={() => onSelectOrder?.(order)}
                    >
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-semibold text-foreground">
                                    Order #{order.order_number}
                                </span>
                                <span className="px-2 py-0.5 text-xs rounded-full bg-secondary text-secondary-foreground font-medium capitalize">
                                    {order.status}
                                </span>
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {new Date(order.created_at).toLocaleDateString()}
                            </p>
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="font-medium text-foreground">
                                ${(order.total ?? 0).toFixed(2)}
                            </span>
                            <a
                                href={`/orders/${order.id}`}
                                className="text-xs font-medium text-primary hover:underline"
                            >
                                View Details &rarr;
                            </a>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
