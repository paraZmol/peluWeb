/**
 * On a phone the dialog becomes a sheet anchored to the bottom, with its
 * action in the thumb zone; from `sm` up it is the usual centered dialog.
 */
export const SHEET_ON_PHONE =
    'max-h-[92svh] max-sm:top-auto max-sm:bottom-0 max-sm:max-w-full max-sm:translate-y-0 max-sm:rounded-b-none max-sm:border-x-0 max-sm:border-b-0 max-sm:pb-[max(1.5rem,env(safe-area-inset-bottom))] max-sm:data-[state=open]:slide-in-from-bottom-8 max-sm:data-[state=open]:zoom-in-100 max-sm:data-[state=closed]:zoom-out-100 sm:max-h-[90svh]';
