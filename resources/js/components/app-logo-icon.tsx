import type { SVGAttributes } from 'react';

/**
 * peluWeb mark: open scissors. Uses currentColor so it follows the theme.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <circle cx="6" cy="6" r="3" />
            <circle cx="6" cy="18" r="3" />
            <path d="M20 4 8.12 15.88" />
            <path d="M14.47 14.48 20 20" />
            <path d="M8.12 8.12 12 12" />
        </svg>
    );
}
