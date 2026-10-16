import { describe, expect, it } from 'vite-plus/test';
import {
    daysLeftText,
    formatPlanDate,
    standingHeadline,
    subscriptionNotice,
} from './subscription';

const standing = {
    state: 'trial' as const,
    coveredUntil: '2026-11-06',
    daysLeft: 30,
    graceEndsOn: '2026-11-13',
    planName: 'Equipo',
};

describe('formatPlanDate', () => {
    it('leaves the year out only when it is this one', () => {
        expect(formatPlanDate('2026-11-06', 2026)).toBe('6 de noviembre');
        expect(formatPlanDate('2027-10-05', 2026)).toBe('5 de octubre de 2027');
    });
});

describe('daysLeftText', () => {
    it('counts in singular, plural and the last day', () => {
        expect(daysLeftText(30)).toBe('faltan 30 días');
        expect(daysLeftText(1)).toBe('falta 1 día');
        expect(daysLeftText(0)).toBe('es el último día');
    });
});

describe('standingHeadline', () => {
    it('tells when the trial ends and how many days are left', () => {
        expect(standingHeadline(standing)).toMatch(
            /^Tu prueba gratis termina el 6 de noviembre( de 2026)? \(faltan 30 días\)\.$/,
        );
    });

    it('gives the grace deadline after the plan ran out', () => {
        expect(
            standingHeadline({ ...standing, state: 'grace', daysLeft: -2 }),
        ).toMatch(/tienes hasta el 13 de noviembre/);
    });

    it('says a salon with no dates is up to date', () => {
        expect(
            standingHeadline({
                ...standing,
                state: 'active',
                coveredUntil: null,
                daysLeft: null,
            }),
        ).toBe('Tu salón está al día.');
    });
});

describe('subscriptionNotice', () => {
    it('stays quiet while the end is more than a week away', () => {
        expect(
            subscriptionNotice({ state: 'trial', daysLeft: 8, isOwner: true }),
        ).toBeNull();
    });

    it('reminds the owner in the last week', () => {
        expect(
            subscriptionNotice({ state: 'active', daysLeft: 3, isOwner: true })
                ?.message,
        ).toMatch(/^Tu plan vence en 3 días\./);
        expect(
            subscriptionNotice({ state: 'trial', daysLeft: 0, isOwner: true })
                ?.message,
        ).toBe('Tu prueba gratis termina hoy.');
    });

    it('tells the owner in grace until when, and what happens after', () => {
        const message = subscriptionNotice({
            state: 'grace',
            daysLeft: -2,
            graceEndsOn: '2026-10-21',
            isOwner: true,
        })?.message;

        expect(message).toMatch(/^Tu pago está vencido\. Tienes hasta el /);
        expect(message).toContain('las reservas en línea se pausan');
        expect(message).toContain('No se borra nada.');
    });

    it('tells the staff only when the salon is paused, without a plan link', () => {
        expect(
            subscriptionNotice({
                state: 'grace',
                daysLeft: -1,
                isOwner: false,
            }),
        ).toBeNull();
        expect(
            subscriptionNotice({
                state: 'read_only',
                daysLeft: -9,
                isOwner: false,
            }),
        ).toMatchObject({ isBlocking: true, showsPlanLink: false });
    });

    it('interrupts the owner when the salon is paused', () => {
        expect(
            subscriptionNotice({
                state: 'suspended',
                daysLeft: 5,
                isOwner: true,
            }),
        ).toMatchObject({ isBlocking: true, showsPlanLink: true });
    });
});
