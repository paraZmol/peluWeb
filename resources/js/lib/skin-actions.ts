/**
 * Large public buttons built on the template skins (ui-system §6): the
 * shadcn `Button` is a 36 px control with its own colors, and a skin must
 * not be mixed with `bg-*`, `border-*` or `shadow-*` on the same element.
 */
const ACTION_BASE =
    'inline-flex min-w-0 items-center justify-center gap-2 rounded-full font-semibold whitespace-nowrap transition-transform duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/60 active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0';

/** The one main action of a view (Reservar cita, Confirmar reserva). */
export const PRIMARY_ACTION = `${ACTION_BASE} skin-primary h-13 px-6 text-base`;

/** A secondary action next to the primary one. */
export const CHIP_ACTION = `${ACTION_BASE} skin-chip h-12 px-5 text-sm`;

/** Round icon button (WhatsApp in the dock, arrows). */
export const CHIP_ICON = `${ACTION_BASE} skin-chip size-13`;

/** Round icon button on top of a photo. */
export const GLASS_ICON = `${ACTION_BASE} skin-glass size-11`;

/** Outlined pill on a block of the brand color (Vibrante cover). */
export const ON_BRAND_ACTION = `${ACTION_BASE} h-11 border-[1.5px] border-primary-foreground/55 px-4 text-sm text-primary-foreground hover:border-primary-foreground`;

/** Outlined round icon button on a block of the brand color. */
export const ON_BRAND_ICON = `${ACTION_BASE} size-11 border-[1.5px] border-primary-foreground/45 text-primary-foreground hover:border-primary-foreground`;

/** Soft pill in the brand tint ("Reservar" of a service, WhatsApp in a card). */
export const SOFT_ACTION = `${ACTION_BASE} h-11 bg-brand-soft px-4 text-sm text-brand-text`;
