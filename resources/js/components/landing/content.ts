/**
 * All landing page copy lives here so it can be edited in one place.
 * Texts: Spanish neutral (LATAM), tuteo, never assume anyone's gender.
 * Keep it honest: no invented numbers, testimonials or client logos.
 */
import {
    AlarmClock,
    CalendarCheck,
    Crown,
    Flower2,
    HeartHandshake,
    MessageCircle,
    Palette,
    Smartphone,
    Sparkles,
    Star,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { MiniSalon } from '@/components/theme-preview/types';
import { show as samplePhoto } from '@/routes/samples';

export type NavItem = { label: string; href: string };

export type IconItem = {
    icon: LucideIcon;
    title: string;
    description: string;
};

/** A real photo (database/seeders/images/CREDITS.md) and what it shows. */
export type Photo = { src: string; alt: string };

export type PhotoItem = {
    photo: Photo;
    title: string;
    description: string;
};

/** What the landing says about a plan; its name and prices come from the server. */
export type PlanCopy = {
    capacity: string;
    description: string;
    features: string[];
    isFeatured?: boolean;
};

/** A public plan as the server sends it (the plans table). */
export type PublicPlan = {
    code: string;
    name: string;
    priceCents: number;
    founderPriceCents: number | null;
    maxStaff: number;
};

export type PublicPricing = {
    plans: PublicPlan[];
    /** Null once its places or its last day ran out. */
    founder: { slotsLeft: number; endsOn: string | null } | null;
};

export type Faq = { question: string; answer: string };

export const BRAND_NAME = 'peluWeb';

/** Length of the free trial: every text that mentions it reads it from here. */
export const TRIAL_DAYS = 30;

export const PAGE_TITLE = 'Reservas y gestión para tu salón';

export const META_DESCRIPTION = `peluWeb: reservas online 24/7 sin choques, señas por WhatsApp y Yape/Plin, caja y reportes, y una página con la marca de tu salón. Prueba gratis ${TRIAL_DAYS} días.`;

export const NAV_ITEMS: NavItem[] = [
    { label: 'Para quién', href: '#para-quien' },
    { label: 'Tu página', href: '#tu-pagina' },
    { label: 'Cómo funciona', href: '#como-funciona' },
    { label: 'Funciones', href: '#funciones' },
    { label: 'Precios', href: '#precios' },
    { label: 'Preguntas', href: '#preguntas' },
];

function photo(file: string, alt: string): Photo {
    return { src: samplePhoto.url(file), alt };
}

export const AUTH_LABELS = {
    dashboard: 'Ir a mi panel',
    login: 'Iniciar sesión',
    register: 'Prueba gratis',
    openMenu: 'Abrir menú',
    menuTitle: 'Menú',
};

export const HERO = {
    eyebrow: 'Para peluquerías, barberías, uñas y spas del Perú',
    title: 'Tu agenda llena, sin contestar WhatsApp a medianoche',
    subtitle:
        'Tus clientes reservan solos desde tu enlace, a cualquier hora y sin choques de horario. Cobras la seña con Yape o Plin y te olvidas del cuaderno.',
    primaryCta: `Prueba gratis ${TRIAL_DAYS} días`,
    secondaryCta: 'Ver cómo queda tu página',
    secondaryHref: '#tu-pagina',
    trust: ['Sin tarjeta', 'Sin contrato', 'Hecho en Perú'],
};

export const HERO_PHOTOS = {
    main: {
        ...photo(
            'pexels-3993311.jpg',
            'Cliente con color recién aplicado frente a un espejo con luces',
        ),
        label: 'Salón de belleza',
    },
    side: [
        {
            ...photo(
                'pexels-1813272.jpg',
                'Dos barberos atendiendo en una barbería amplia',
            ),
            label: 'Barbería',
        },
        {
            ...photo(
                'pexels-3422099.jpg',
                'Manicurista aplicando esmalte con guantes negros',
            ),
            label: 'Uñas',
        },
    ],
};

export const HERO_CARDS = {
    booking: {
        label: 'Nueva reserva',
        when: 'hace 1 min',
        title: 'Corte y peinado con Lucía',
        detail: 'Hoy, 4:30 p. m.',
    },
    deposit: { label: 'Seña recibida por Yape', amount: 'S/ 20.00' },
};

export const BEFORE_AFTER = {
    eyebrow: 'Lo bueno y lo malo',
    title: 'Así cambia tu día a día',
    description:
        'Lo que hoy te quita tiempo y clientes, y cómo lo resuelve peluWeb.',
    before: {
        title: 'Antes',
        items: [
            'Agenda en un cuaderno o perdida en el chat',
            'Dos reservas cruzadas a la misma hora',
            'Clientes que reservan y no llegan',
            'Cuentas a mano al final del día',
            'Depender solo del boca a boca',
        ],
    },
    after: {
        title: 'Con peluWeb',
        items: [
            'Reservas online, sin choques de horario',
            'Confirmación y seña por WhatsApp con Yape o Plin',
            'Caja y reportes que se arman solos',
            'Tu propia página con tu logo y tu color',
            'Clientes VIP con horarios exclusivos',
        ],
    },
};

export const FEATURES_HEADING = {
    eyebrow: 'Funciones',
    title: 'Todo lo que tu salón necesita, en tu celular',
    description:
        'Pensado para salones con 1 a 15 profesionales que hoy se organizan por WhatsApp.',
};

export const FEATURES: IconItem[] = [
    {
        icon: CalendarCheck,
        title: 'Reservas sin choques',
        description:
            'Cada profesional tiene su propia duración por servicio. El sistema nunca cita a dos clientes a la misma hora con la misma persona.',
    },
    {
        icon: AlarmClock,
        title: 'Horarios tipo alarma',
        description:
            'Cada profesional fija sus horas girando ruedas, como al poner una alarma. Vacaciones y descansos en un toque.',
    },
    {
        icon: Crown,
        title: 'Reservas VIP',
        description:
            'Guarda horarios exclusivos que solo ven tus mejores clientes. Premia la fidelidad sin regalar descuentos.',
    },
    {
        icon: MessageCircle,
        title: 'Señas por WhatsApp y Yape/Plin',
        description:
            'Al reservar se arma el mensaje de WhatsApp con el monto y tu número de Yape o Plin. Menos ausencias, cero comisiones.',
    },
    {
        icon: Wallet,
        title: 'Caja y ganancias',
        description:
            'Ingresos, gastos, comisiones del equipo y cierre de caja. Reportes por día, semana o mes para saber si ganas o pierdes.',
    },
    {
        icon: Palette,
        title: 'Tu marca',
        description:
            'Tu página con tu logo, tu color, fotos de tus trabajos, mapa para llegar y un código QR para imprimir en el local.',
    },
    {
        icon: Users,
        title: 'Tu equipo con permisos',
        description:
            'Invita a tu equipo por enlace. Tú decides quién ve la caja, quién edita servicios y quién solo ve su agenda.',
    },
    {
        icon: Star,
        title: 'Reseñas con interruptor',
        description:
            'Las reseñas atraen clientes, pero una mala también pesa. Por eso eliges: mostrarlas, guardarlas solo para ti u ocultarlas.',
    },
    {
        icon: Smartphone,
        title: 'Instálala en tu celular',
        description:
            'Funciona en Android y iPhone como una app, con ícono en tu pantalla de inicio. Sin pasar por ninguna tienda.',
    },
];

export const HOW_IT_WORKS = {
    eyebrow: 'Cómo funciona',
    title: 'Empieza a recibir reservas hoy mismo',
    steps: [
        {
            title: 'Crea tu salón en un minuto',
            description:
                'Elige si eres barbería, salón, unisex, uñas o spa. Sube tu logo, escoge tu color y el diseño de tu página.',
        },
        {
            title: 'Ajusta servicios, precios y horarios',
            description:
                'Parte de un catálogo con 116 servicios listos: desmarca lo que no ofreces y pon tus precios.',
        },
        {
            title: 'Comparte tu enlace y recibe reservas',
            description:
                'Pon tu enlace en Instagram, WhatsApp o el QR del local. Tus clientes reservan a toda hora, sin escribirte.',
        },
    ],
};

export const AUDIENCES_HEADING = {
    eyebrow: 'Para quién',
    title: 'Hecho para negocios como el tuyo',
    description:
        'Al crear tu salón, peluWeb carga los servicios y ajustes típicos de tu rubro.',
};

export const AUDIENCES: PhotoItem[] = [
    {
        photo: photo(
            'pexels-7518731.jpg',
            'Barbero joven cortando el cabello en una barbería de ladrillo',
        ),
        title: 'Barberías',
        description:
            'Turnos cortos, barbero favorito y una cola para quien llega sin cita.',
    },
    {
        photo: photo(
            'pexels-6503329.jpg',
            'Estilista peinando un cabello rubio largo',
        ),
        title: 'Salones de belleza',
        description:
            'Mientras un tinte reposa, ese tiempo se puede reservar para otro servicio.',
    },
    {
        photo: photo(
            'pexels-16363470.jpg',
            'Manos con manicura brillante sobre un fondo rosa',
        ),
        title: 'Centros de uñas',
        description:
            'Paquetes, fichas de cada cliente y un plan accesible si trabajas por tu cuenta.',
    },
];

export const AUDIENCES_MORE: IconItem[] = [
    {
        icon: UsersRound,
        title: 'Peluquerías unisex',
        description:
            'Servicios para todo público, cada profesional con su agenda y sus comisiones.',
    },
    {
        icon: Flower2,
        title: 'Estética y spa',
        description:
            'Tratamientos largos, señas para asegurar la cita y notas de cada cliente.',
    },
];

export const SHOWCASE = {
    eyebrow: 'Tu página',
    title: 'Así se ve tu página',
    description:
        'Elige uno de los cuatro diseños y ponle tu logo, tu color y tus fotos. Tus clientes la abren desde tu Instagram o tu WhatsApp y reservan ahí mismo.',
    pickerLabel: 'Diseños de página',
    bullets: [
        'Tus fotos, tu logo y tu color',
        'Servicios con precio y duración',
        'Reservan en tres toques, de día o de noche',
        'Seña por Yape o Plin antes de la cita',
    ],
};

/** The example salon inside the phone of "Así se ve tu página". */
export const SHOWCASE_SALON: MiniSalon = {
    name: 'Studio Bella',
    subtitle: 'Peluquería unisex · Miraflores',
    coverUrl: samplePhoto.url('pexels-705255.jpg'),
    logoUrl: null,
    photoUrls: [
        samplePhoto.url('pexels-2799605.jpg'),
        samplePhoto.url('pexels-1319462.jpg'),
        samplePhoto.url('pexels-22668324.jpg'),
    ],
    services: [
        { name: 'Corte y peinado', minutes: 45, price: '45.00' },
        { name: 'Corte clásico', minutes: 30, price: '30.00' },
        { name: 'Manicure en gel', minutes: 60, price: '55.00' },
        { name: 'Perfilado de barba', minutes: 20, price: '20.00' },
        { name: 'Tinte completo', minutes: 90, price: '120.00' },
    ],
};

// Prices and the founder offer come from the server (the plans table and
// config/billing.php); the words were reviewed by legal-compliance on
// 2026-10-15. Never a crossed-out price: the regular price was never charged.
export const PRICING = {
    eyebrow: 'Precios',
    title: 'Un precio fijo, sin comisión por cliente',
    trialNote: `${TRIAL_DAYS} días gratis con todo el plan Equipo, sin datos de pago`,
    cta: 'Empezar prueba gratis',
    perMonth: '/mes',
    featuredLabel: 'Recomendado',
    underCards:
        'Pagas solo por quien atiende con agenda; recepción y caja no se cobran. ¿Más de 6 profesionales? Escríbenos y te cotizamos.',
    footnote:
        'Precios en soles, IGV incluido. Pago mensual por adelantado por Yape o transferencia, sin contrato ni cobro automático.',
    founderLabel: 'Precio fundador',
    plans: {
        solo: {
            capacity: '1 profesional con agenda',
            description: 'Si trabajas por tu cuenta.',
            features: [
                'Reservas en línea ilimitadas',
                'Señas por WhatsApp con Yape o Plin',
                'Caja, gastos y cierre del día',
                'Tu página con tu marca y galería',
                'Promociones y clientes VIP',
            ],
        },
        equipo: {
            capacity: 'Hasta 6 profesionales con agenda',
            description: 'Para el salón con equipo.',
            isFeatured: true,
            features: [
                'Todo lo del plan Solo',
                'Agenda por profesional',
                'Comisiones y reportes',
                'Reseñas verificadas que tú decides mostrar',
                'Recordatorios por correo y avisos en el celular',
            ],
        },
    } satisfies Record<string, PlanCopy>,
};

/** "Precio fundador S/ 29 al mes …": only while places are left and before its last day. */
export function founderLine(
    founder: string,
    regular: string,
    slotsLeft: number,
    endsOn: string | null,
): string {
    const until = endsOn ? ` hasta el ${endsOn}` : '';
    const places =
        slotsLeft === 1
            ? 'el próximo salón'
            : `los próximos ${slotsLeft} salones`;

    return `${PRICING.founderLabel} ${founder} al mes para ${places} que hagan su primer pago${until}. Lo mantienes mientras pagues a tiempo; si tu salón entra en pausa por falta de pago, vuelves al precio regular de ${regular}.`;
}

/** The footnote, with the offer's conditions written out while it lasts. */
export function founderFootnote(
    slotsLeft: number,
    endsOn: string | null,
): string {
    const until = endsOn
        ? `, válido para primeros pagos hasta el ${endsOn} o hasta agotar cupos, lo que ocurra primero`
        : ', hasta agotar cupos';

    return `Precio fundador: quedan ${slotsLeft} ${slotsLeft === 1 ? 'cupo' : 'cupos'} en total entre ambos planes${until}.`;
}

export const FAQ_HEADING = {
    eyebrow: 'Preguntas frecuentes',
    title: 'Lo que suelen preguntarnos',
};

export const FAQS: Faq[] = [
    {
        question: '¿Necesito tarjeta para probar?',
        answer: `No. Tienes ${TRIAL_DAYS} días gratis con todo el plan Equipo y no te pedimos ningún dato de pago. Si te convence, eliges tu plan y pagas por Yape o transferencia.`,
    },
    {
        question: '¿Qué pasa si no pago a tiempo?',
        answer: 'Te avisamos por correo 7, 3 y 1 día antes del vencimiento. Después tienes 7 días de gracia con todo funcionando. Si no pagas en ese plazo, tu salón entra en pausa: ves toda tu información y puedes descargar tus movimientos de caja, pero no entran reservas nuevas. Las citas ya agendadas se respetan. No borramos nada y, al pagar, se reactiva al momento.',
    },
    {
        question: '¿Puedo cancelar cuando quiera?',
        answer: 'Sí. No hay contrato ni cobro automático: si no renuevas, el plan vence al final del periodo pagado.',
    },
    {
        question: '¿Cuántas personas puedo tener?',
        answer: 'Solo cuentan quienes atienden con agenda: 1 en Solo y hasta 6 en Equipo. Recepción y caja entran gratis. Para más de 6, te cotizamos.',
    },
    {
        question: '¿Mis clientes tienen que descargar algo?',
        answer: 'No. Reservan desde tu enlace en el navegador del celular, en menos de un minuto. Si quieren, pueden instalar tu página como una app.',
    },
    {
        question: '¿Funciona en iPhone y Android?',
        answer: 'Sí. peluWeb funciona en cualquier celular, tablet o computadora con internet, y se puede instalar en la pantalla de inicio de Android y iPhone sin pasar por ninguna tienda.',
    },
    {
        question: '¿Cómo cobro las señas?',
        answer: 'Registras tu número de Yape o Plin. Cuando alguien reserva, se prepara un mensaje de WhatsApp con el monto y tus datos. El pago te llega directo a ti; tú confirmas la reserva con un toque. peluWeb no cobra comisión.',
    },
    {
        question: '¿Qué pasa si alguien deja una mala reseña?',
        answer: 'Tú controlas la visibilidad: puedes mostrar las reseñas en tu página, guardarlas en privado solo como opinión para tu equipo o no pedirlas. Si las haces públicas se muestran todas, para que tus clientes confíen en ellas; puedes responder y reportar las ofensivas.',
    },
    {
        question: '¿Puedo tener varios salones?',
        answer: 'Sí. Con una misma cuenta puedes administrar más de un salón y cambiar de uno a otro desde el menú. Cada salón tiene su propio plan.',
    },
    {
        question: '¿Mis datos están seguros?',
        answer: 'Los datos de cada salón están separados: nadie más ve tu agenda, tu caja ni tus clientes. Puedes activar la verificación en dos pasos en tu cuenta y tus datos siguen siendo tuyos aunque dejes de usar peluWeb.',
    },
];

export const ABOUT = {
    eyebrow: 'Nosotros',
    title: 'Quiénes somos',
    paragraphs: [
        'peluWeb es una plataforma peruana creada para que los salones independientes tengan las mismas herramientas que las grandes cadenas, sin pagar precios de cadena.',
        'Sabemos que muchos salones se organizan con un cuaderno y WhatsApp, y que funciona hasta que deja de funcionar. Nuestra misión es que pases menos tiempo ordenando la agenda y más tiempo atendiendo a tus clientes.',
    ],
    values: [
        {
            icon: HeartHandshake,
            title: 'Cercanía',
            description:
                'Te hablamos claro y en tu idioma, y te ayudamos por WhatsApp.',
        },
        {
            icon: Sparkles,
            title: 'Simplicidad',
            description: 'Si algo necesita un manual, lo rediseñamos.',
        },
        {
            icon: Palette,
            title: 'Tu marca primero',
            description:
                'Tus clientes ven tu salón, tu logo y tu color, no el nuestro.',
        },
    ] satisfies IconItem[],
};

export const FINAL_CTA = {
    title: 'Empieza hoy, tu primer mes es gratis',
    description: 'Crea tu salón en un minuto. Sin tarjeta y sin compromiso.',
    cta: 'Crear mi salón gratis',
    photo: photo(
        'pexels-3993133.jpg',
        'Salón en penumbra con espejos iluminados',
    ),
};

export const FOOTER = {
    description:
        'Reservas, equipo, caja y marca para tu salón, en un solo lugar.',
    columns: [
        {
            title: 'Producto',
            links: NAV_ITEMS.filter(
                (item) => !['#nosotros', '#preguntas'].includes(item.href),
            ),
        },
        {
            title: 'Empresa',
            links: [
                { label: 'Nosotros', href: '#nosotros' },
                { label: 'Preguntas', href: '#preguntas' },
            ],
        },
    ],
    legalTitle: 'Legal',
    madeIn: 'Hecho en Perú.',
};
