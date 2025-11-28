export const getPaymentMethodLabel = (method: string): string => {
    switch (method) {
        case 'card':
            return 'creditCard';
        case 'wallet':
            return 'mobileWallet';
        case 'cod':
            return 'cashOnDelivery';
        case 'kiosk':
            return 'kiosk';
        case 'bank_transfer':
            return 'bankTransfer';
        default:
            return method;
    }
};
