import AppLogoIcon from '@/components/app-logo-icon';
import { BRAND_NAME } from './content';

export function BrandMark() {
    return (
        <span className="flex items-center gap-2">
            <AppLogoIcon className="size-7 text-primary" />
            <span className="font-display text-xl font-semibold tracking-tight">
                {BRAND_NAME}
            </span>
        </span>
    );
}
