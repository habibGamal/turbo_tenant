import React from 'react';

interface Props {
    data?: Record<string, unknown>;
}

export const PushNotificationsWidget: React.FC<Props> = () => {
    return (
        <div className="plugin-push-notifications-container p-4 border rounded-lg bg-card">
            <h3 className="font-semibold text-lg">Push & Bulk Notifications</h3>
        </div>
    );
};

export default PushNotificationsWidget;
