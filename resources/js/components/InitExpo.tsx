import React, { useEffect } from 'react';
import ReactPixel from 'react-facebook-pixel';

declare global {
    interface Window {
        pushToken?: string;
    }
}

interface InitExpoProps {
    children: React.ReactNode;
}

const InitExpo: React.FC<InitExpoProps> = ({  children }) => {
    useEffect(() => {
        if(window.pushToken) {
            alert("Storing Expo Push Token: " + window.pushToken);
            window.localStorage.setItem('expoPushToken', window.pushToken);
        }
    }, []);

    return <>{children}</>;
};

export default InitExpo;
