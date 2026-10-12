import { describe, expect, it } from 'vite-plus/test';
import { isIos } from './pwa';

describe('isIos', () => {
    it('knows an iPhone and an iPad that says it is a Mac', () => {
        expect(
            isIos('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'),
        ).toBe(true);
        expect(
            isIos('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 5),
        ).toBe(true);
    });

    it('does not take a Mac or an Android phone for one', () => {
        expect(
            isIos('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 0),
        ).toBe(false);
        expect(isIos('Mozilla/5.0 (Linux; Android 14; Pixel 7)', 5)).toBe(
            false,
        );
    });
});
