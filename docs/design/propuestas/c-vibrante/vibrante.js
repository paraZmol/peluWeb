/* Vibrante — marca, tema e íconos de la maqueta.
   ?brand=FF6B1A   cambia el color de marca (lo que en la app pone cada salón)
   ?theme=dark     modo oscuro
   ?embed=1        sin animaciones (para el iframe de "así se ve tu página")
   Replica lo que hará el servidor: elige texto blanco o negro sobre la marca
   (WCAG) y ajusta la marca usada como texto (5:1 claro, 6:1 oscuro contra el fondo). */
(function () {
    const params = new URLSearchParams(location.search);
    const root = document.documentElement;
    if (params.get('theme') === 'dark') root.dataset.theme = 'dark';
    if (params.has('embed')) root.dataset.embed = '1';

    const brand = '#' + (params.get('brand') || '2D5BFF').replace('#', '');

    const toRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
    const toHex = (rgb) => '#' + rgb.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
    const lin = (v) => ((v /= 255) <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
    const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    const contrast = (a, b) => {
        const [x, y] = [lum(a), lum(b)].sort((m, n) => n - m);
        return (x + 0.05) / (y + 0.05);
    };
    const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);

    const rgb = toRgb(brand);
    const white = [255, 255, 255];
    const brandInk = contrast(rgb, white) >= 4.5 ? '#FFFFFF' : '#0E0E11';

    const dark = root.dataset.theme === 'dark';
    const bg = dark ? [14, 14, 17] : [251, 251, 253];
    const toward = dark ? white : [0, 0, 0];
    // Margen sobre 4.5:1 porque este texto también va sobre --brand-soft.
    const target = dark ? 6 : 5;
    let text = rgb;
    for (let t = 0; contrast(text, bg) < target && t < 1; t += 0.04) text = mix(rgb, toward, t);

    root.style.setProperty('--brand', brand);
    root.style.setProperty('--brand-ink', brandInk);
    root.style.setProperty('--brand-text', toHex(text));
    root.style.setProperty('--focus', dark ? '#F4F4F7' : '#0E0E11');

    /* Íconos lucide (trazo 1.75) */
    const icons = {
        check: '<path d="M20 6 9 17l-5-5"/>',
        'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-left': '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        pin: '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        chat: '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98"/><path d="m15.41 6.51-6.82 3.98"/>',
        instagram: '<rect width="20" height="20" x="2" y="2" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
        phone: '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>',
        calendar: '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>',
        link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        sparkles: '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>',
        sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        sunset: '<path d="M12 10V2"/><path d="m4.93 10.93 1.41 1.41"/><path d="M2 18h2"/><path d="M20 18h2"/><path d="m19.07 10.93-1.41 1.41"/><path d="M22 22H2"/><path d="m16 6-4 4-4-4"/><path d="M16 18a4 4 0 0 0-8 0"/>',
        moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        wallet: '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        store: '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
        bell: '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
    };
    const paint = () =>
        document.querySelectorAll('i[data-i]').forEach((el) => {
            const svg = `<svg class="icon ${el.className}" viewBox="0 0 24 24" aria-hidden="true">${icons[el.dataset.i] || ''}</svg>`;
            el.outerHTML = svg;
        });
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', paint) : paint();
})();
