import React, { useEffect } from 'react';
import ReactPixel from 'react-facebook-pixel';

interface InitPixelProps {
    fbID?: string;
    children: React.ReactNode;
}

const InitPixel: React.FC<InitPixelProps> = ({ fbID, children }) => {
    useEffect(() => {
        if (fbID) {
            ReactPixel.init(fbID, undefined, { autoConfig: true, debug: false });
            ReactPixel.pageView(); // Track initial page view
        }
    }, [fbID]);

    return <>{children}</>;
};

export default InitPixel;
