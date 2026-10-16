import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { show } from '@/routes/legal';

type Props = {
    error?: string;
    tabIndex?: number;
};

const LINK_CLASS = 'font-semibold text-brand-text underline underline-offset-4';

/**
 * Casilla 1: never ticked beforehand; the server checks it too. The texts
 * open in a new tab so the form is not lost.
 */
export function TermsCheckbox({ error, tabIndex }: Props) {
    return (
        <div className="grid gap-2">
            <div className="flex items-start gap-3">
                <Checkbox
                    id="terms"
                    name="terms"
                    value="1"
                    tabIndex={tabIndex}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? 'terms-error' : undefined}
                    className="mt-0.5 size-5"
                    data-test="terms-checkbox"
                />
                <label
                    htmlFor="terms"
                    className="text-sm leading-relaxed text-pretty"
                >
                    Leí y acepto los{' '}
                    <a
                        href={show('terminos-clientes').url}
                        target="_blank"
                        rel="noopener"
                        className={LINK_CLASS}
                    >
                        Términos y condiciones
                    </a>{' '}
                    y la{' '}
                    <a
                        href={show('privacidad').url}
                        target="_blank"
                        rel="noopener"
                        className={LINK_CLASS}
                    >
                        Política de privacidad
                    </a>
                    .
                </label>
            </div>
            <InputError id="terms-error" message={error} />
        </div>
    );
}
