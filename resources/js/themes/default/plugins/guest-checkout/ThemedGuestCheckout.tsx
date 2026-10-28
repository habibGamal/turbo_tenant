import React from "react";
import GuestInformationForm from "./components/GuestInformationForm";
import { Governorate } from "@/types";

export interface ThemedGuestCheckoutProps {
    className?: string;
    guestData?: any;
    onChange?: any;
    governorates?: Governorate[];
    orderType?: string;
    errors?: Record<string, string>;
}

export default function ThemedGuestCheckout(props: ThemedGuestCheckoutProps) {
    const {
        guestData = {},
        onChange = () => {},
        governorates = [],
        orderType = "web_delivery",
        errors = {},
    } = props;

    const handleNormalizedChange = (dataOrField: any, value?: any) => {
        if (typeof dataOrField === 'string') {
            onChange({ [dataOrField]: value });
        } else {
            onChange(dataOrField);
        }
    };

    return (
        <GuestInformationForm
            guestData={guestData}
            onChange={handleNormalizedChange}
            governorates={governorates}
            orderType={orderType}
            errors={errors}
        />
    );
}
