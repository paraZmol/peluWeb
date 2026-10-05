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
    Hand,
    HeartHandshake,
    MessageCircle,
    Palette,
    Scissors,
    Smartphone,
    Sparkles,
    Star,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type NavItem = { label: string; href: string };

export type IconItem = {
    icon: LucideIcon;
    title: string;
    description: string;
};

export type Appointment = {
    time: string;
    service: string;
    client: string;
    isNow?: boolean;
};

export type Plan = {
    name: string;
    priceSoles: number;
    capacity: string;
    description: string;
    features: string[];
    isFeatured?: boolean;
};

export type Faq = { question: string; answer: string };

export const BRAND_NAME = 'peluWeb';

export const PAGE_TITLE = 'Reservas y gestión para tu salón';

export const META_DESCRIPTION =
    'peluWeb: reservas online 24/7 sin choques, señas por WhatsApp y Yape/Plin, caja y reportes, y una página con la marca de tu salón. Prueba gratis 30 días.';

export const NAV_ITEMS: NavItem[] = [
    { label: 'Funciones', href: '#funciones' },
    { label: 'Cómo funciona', href: '#como-funciona' },
    { label: 'Para quién', href: '#para-quien' },
    { label: 'Precios', href: '#precios' },
    { label: 'Preguntas', href: '#preguntas' },
    { label: 'Nosotros', href: '#nosotros' },
];

export const AUTH_LABELS = {
    dashboard: 'Ir a mi panel',
    login: 'Iniciar sesión',
    register: 'Prueba gratis',
    openMenu: 'Abrir menú',
    menuTitle: 'Menú',
};

export const HERO = {
    eyebrow: 'Para barberías, salones y spas del Perú',
    title: 'Tu salón lleno, tu agenda en orden',
    subtitle:
        'Tus clientes reservan a cualquier hora desde el celular, sin choques de horario. Confirmas por WhatsApp, cobras la seña con Yape o Plin y te olvidas del cuaderno.',
    primaryCta: 'Prueba gratis 30 días',
    secondaryCta: 'Ver cómo funciona',
    secondaryHref: '#como-funciona',
    trust: ['Sin tarjeta', 'Cancela cuando quieras', 'Hecho en Perú'],
};

export const PHONE_MOCKUP = {
    title: 'Hoy',
    date: 'mar 14 oct',
    nowLabel: 'Ahora',
    nextLabel: 'Siguiente en 15 min',
    depositLabel: 'Seña recibida por Yape',
    depositAmount: 'S/ 20.00',
    appointments: [
        {
            time: '10:00',
            service: 'Degradado medio',
            client: 'Carlos',
            isNow: true,
        },
        { time: '10:45', service: 'Perfilado de barba', client: 'Diego' },
        { time: '11:30', service: 'Balayage', client: 'Lucía' },
    ] satisfies Appointment[],
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
                'Elige si eres barbería, salón, unisex, uñas o spa. Sube tu logo y escoge tu color.',
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
    title: 'Hecho a la medida de tu tipo de salón',
    description:
        'Al crear tu salón, peluWeb carga los servicios y ajustes típicos de tu rubro.',
};

export const AUDIENCES: IconItem[] = [
    {
        icon: Scissors,
        title: 'Barberías',
        description:
            'Turnos cortos, barbero favorito y una cola para quien llega sin cita.',
    },
    {
        icon: Sparkles,
        title: 'Salones de belleza',
        description:
            'Mientras un tinte reposa, ese tiempo de espera se puede reservar para otro servicio.',
    },
    {
        icon: UsersRound,
        title: 'Peluquerías unisex',
        description:
            'Servicios para todo público, cada profesional con su agenda y sus comisiones.',
    },
    {
        icon: Hand,
        title: 'Centros de uñas',
        description:
            'Paquetes, fichas de cada cliente y un plan accesible si trabajas por tu cuenta.',
    },
    {
        icon: Flower2,
        title: 'Estética y spa',
        description:
            'Tratamientos largos, señas para asegurar la cita y notas de cada cliente.',
    },
];

// Prices are a proposal pending decision D3 in docs/PLAN.md (see docs/mercado.md).
export const PRICING = {
    eyebrow: 'Precios',
    title: 'Un precio fijo, sin comisión por cliente',
    trialNote: '30 días gratis en cualquier plan, sin tarjeta',
    cta: 'Empezar prueba gratis',
    perMonth: '/mes',
    featuredLabel: 'Más elegido',
    footnote:
        'Precios en soles, IGV incluido. Pago anual con 20 % de descuento.',
    plans: [
        {
            name: 'Solo',
            priceSoles: 39,
            capacity: '1 profesional',
            description: 'Si trabajas por tu cuenta.',
            features: [
                'Reservas ilimitadas',
                'Señas por WhatsApp y Yape/Plin',
                'Caja simple',
                'Tu página con tu marca',
            ],
        },
        {
            name: 'Equipo',
            priceSoles: 89,
            capacity: 'Hasta 6 profesionales',
            description: 'Para el salón con equipo.',
            isFeatured: true,
            features: [
                'Todo lo del plan Solo',
                'Comisiones y reportes',
                'Reservas VIP',
                'Reseñas con interruptor',
                'Galería de trabajos',
            ],
        },
        {
            name: 'Negocio',
            priceSoles: 169,
            capacity: 'Hasta 15 profesionales',
            description: 'Para salones grandes o con varias sedes.',
            features: [
                'Todo lo del plan Equipo',
                'Inventario y paquetes',
                'Permisos por persona',
                'Sede adicional por S/ 79 al mes',
            ],
        },
    ] satisfies Plan[],
};

export const FAQ_HEADING = {
    eyebrow: 'Preguntas frecuentes',
    title: 'Lo que suelen preguntarnos',
};

export const FAQS: Faq[] = [
    {
        question: '¿Necesito tarjeta para probar?',
        answer: 'No. Tienes 30 días gratis con todas las funciones del plan Equipo y no te pedimos tarjeta. Si te convence, eliges un plan y pagas por Yape o transferencia.',
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
        answer: 'Sí. Con una misma cuenta puedes administrar más de un salón, y el plan Negocio permite sumar sedes por S/ 79 al mes cada una.',
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
    legal: {
        title: 'Legal',
        soonLabel: '(pronto)',
        items: [
            'Términos y condiciones',
            'Política de privacidad',
            'Libro de reclamaciones',
        ],
    },
    madeIn: 'Hecho en Perú.',
};
