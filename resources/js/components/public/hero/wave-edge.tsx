/** Wavy lower edge of a color block, painted in the page's background. */
export function WaveEdge() {
    return (
        <svg
            aria-hidden
            className="absolute inset-x-0 -bottom-px h-4.5 w-full fill-background"
        >
            <defs>
                <pattern
                    id="salon-wave"
                    width="80"
                    height="18"
                    patternUnits="userSpaceOnUse"
                >
                    <path d="M0 9 Q20 0 40 9 T80 9 V18 H0Z" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#salon-wave)" />
        </svg>
    );
}
