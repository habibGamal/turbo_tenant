import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],

    theme: {
    	extend: {
    		fontFamily: {
    			sans: [
    				'Tajawal',
                    ...defaultTheme.fontFamily.sans
                ]
    		}
    	}
    },

    plugins: [forms, require("tailwindcss-animate")],
};
