import { describe, expect, it } from 'vite-plus/test';
import { keyToBytes } from './push';

describe('keyToBytes', () => {
    it('reads a base64url key, with or without padding', () => {
        expect(Array.from(keyToBytes('AQID'))).toEqual([1, 2, 3]);
        // "-" and "_" are the URL-safe "+" and "/"; the padding may be missing.
        expect(Array.from(keyToBytes('-_8'))).toEqual([251, 255]);
    });

    it('gives the 65 bytes of an uncompressed P-256 point', () => {
        const key =
            'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';

        expect(keyToBytes(key)).toHaveLength(65);
        expect(keyToBytes(key)[0]).toBe(4);
    });
});
