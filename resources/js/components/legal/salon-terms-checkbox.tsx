import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { show } from '@/routes/legal';

const LINK_CLASS = 'font-semibold text-brand-text underline underline-offset-4';

/**
 * Casilla 5, when opening a salon. The data processing agreement is the
 * annex at the end of the salon terms, so both links open that text.
 */
export function SalonTermsCheckbox({ error }: { error?: string }) {
    const termsUrl = show('terminos-salones').url;

    return (
        <div className="grid gap-2">
            <div className="flex items-start gap-3">
                <Checkbox
                    id="salon_terms"
                    name="salon_terms"
                    value="1"
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? 'salon-terms-error' : undefined}
                    className="mt-0.5 size-5"
                    data-test="salon-terms-checkbox"
                />
                <label
                    htmlFor="salon_terms"
                    className="text-sm leading-relaxed text-pretty"
                >
                    Acepto los{' '}
                    <a
                        href={termsUrl}
                        target="_blank"
                        rel="noopener"
                        className={LINK_CLASS}
                    >
                        Términos para salones
                    </a>{' '}
                    y el{' '}
                    <a
                        href={termsUrl}
                        target="_blank"
                        rel="noopener"
                        className={LINK_CLASS}
                    >
                        Encargo de tratamiento de datos
                    </a>
                    , y declaro que puedo actuar en nombre de este negocio.
                </label>
            </div>
            <InputError id="salon-terms-error" message={error} />
        </div>
    );
}
