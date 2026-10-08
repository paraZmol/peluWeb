// Maqueta "Barbería nocturna": tema y color de marca por parámetro, e íconos lucide en línea.
//   ?tema=claro          -> modo claro (humo)
//   ?marca=E63946        -> otro color de marca (solo cambia --brand)
(function () {
    var params = new URLSearchParams(location.search);
    var root = document.documentElement;
    if (params.get('tema') === 'claro') {
        root.setAttribute('data-theme', 'light');
    }
    var brand = params.get('marca');
    if (brand && /^[0-9a-fA-F]{6}$/.test(brand)) {
        root.style.setProperty('--brand', '#' + brand);
    }

    // Trazos de lucide-react (stroke 1.75 lo pone .ic en nocturna.css)
    var icons = {
        check: '<path d="M20 6 9 17l-5-5"/>',
        'chevron-right': '<path d="m9 18 6-6-6-6"/>',
        'arrow-left': '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'map-pin':
            '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        scissors:
            '<circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/>',
        sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        sunset: '<path d="M12 10V2"/><path d="m4.93 10.93 1.41 1.41"/><path d="M2 18h2"/><path d="M20 18h2"/><path d="m19.07 10.93-1.41 1.41"/><path d="M22 22H2"/><path d="m16 6-4 4-4-4"/><path d="M16 18a4 4 0 0 0-8 0"/>',
        moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        message: '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98"/><path d="m15.41 6.51-6.82 3.98"/>',
        instagram:
            '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
        link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        store: '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
        bell: '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        'calendar-check':
            '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>',
        smartphone: '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
        wallet: '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    };

    function inject() {
        var symbols = Object.keys(icons)
            .map(function (name) {
                return '<symbol id="i-' + name + '" viewBox="0 0 24 24">' + icons[name] + '</symbol>';
            })
            .join('');
        var holder = document.createElement('div');
        holder.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">' + symbols + '</svg>';
        document.body.prepend(holder.firstChild);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inject);
    } else {
        inject();
    }
})();
