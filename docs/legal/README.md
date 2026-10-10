# Legal — borradores (módulo 4.12, paso 9.4)

> **Borrador — no es asesoría legal.** Lo redactó el agente `legal-compliance`. Nada de esta
> carpeta se publica sin la revisión de un abogado colegiado en Perú.
> Versión 0.1 — 2026-10-10.

## Qué hay

| Archivo | Para qué | Estado |
|---|---|---|
| [inventario-datos-y-cookies.md](inventario-datos-y-cookies.md) | Qué datos, cookies y terceros usa hoy el producto (leído del código) | Hecho; rehacer al cerrar cada fase |
| [terminos-salones.md](terminos-salones.md) | Contrato entre la plataforma y el salón (suscripción) + anexo de encargo de datos | Borrador |
| [terminos-clientes.md](terminos-clientes.md) | Condiciones de uso para quien reserva | Borrador |
| [politica-privacidad.md](politica-privacidad.md) | Política de privacidad de la plataforma | Borrador |
| [politica-cookies.md](politica-cookies.md) | Política de cookies y aviso | Borrador |
| [consentimientos.md](consentimientos.md) | Texto exacto de cada casilla y aviso, y qué se guarda como prueba | Borrador |
| [libro-de-reclamaciones.md](libro-de-reclamaciones.md) | Qué construir y qué textos mostrar | Borrador |
| [textos-para-salones.md](textos-para-salones.md) | Textos por defecto que cada salón muestra a sus clientes | Borrador |
| [requisitos-de-producto.md](requisitos-de-producto.md) | Cambios de producto para cumplir, priorizados | Para pasar al plan |

Los huecos que solo puede llenar el dueño van entre corchetes: `[RAZÓN SOCIAL]`, `[RUC]`,
`[DOMICILIO]`, `[CORREO DE CONTACTO]`, `[CORREO DE PRIVACIDAD]`, `[NOMBRE COMERCIAL]`, `[DOMINIO]`,
`[PROVEEDOR DE HOSTING Y PAÍS]`, `[PROVEEDOR DE CORREO Y PAÍS]`, `[PRECIOS DE LOS PLANES]`.
El repositorio es público: esos datos se cargan por configuración, no se escriben aquí.

Las frases marcadas **[PENDIENTE: …]** describen algo que el producto todavía no hace. El texto
está escrito como quedará cuando exista; hasta entonces esa frase no se publica.

## Quién es quién

- **La plataforma** (`[RAZÓN SOCIAL]`): responsable de los datos de las cuentas (dueños, personal,
  clientes con cuenta) y **encargada** de los datos que cada salón guarda de sus clientes.
- **Cada salón**: responsable de los datos de sus clientes y proveedor del servicio de peluquería
  frente a quien reserva. Precios, promociones, señas y cancelaciones son suyos.
- **Quien reserva**: consumidor del salón y usuario de la plataforma.

## Lo urgente (resumen)

1. Hoy no hay páginas legales, ni casilla de aceptación, ni registro de qué se aceptó. Con salones
   y clientes reales eso deja a la plataforma sin contrato y sin prueba de haber informado.
2. No existe Libro de Reclamaciones virtual. La Ley 32495 (11-11-2025) lo exige a toda
   plataforma digital de comercio electrónico, con enlace visible y permanente.
3. La página pública del salón y la tarjeta de mapa de la reserva cargan un iframe de Google Maps
   sin que la persona haga nada: es el único punto donde un tercero puede dejar cookies propias.
4. Borrar la cuenta existe, pero no borra la ficha que el salón tiene de esa persona ni (según el
   código leído) el archivo del avatar. El texto de privacidad lo dice tal cual; hay que decidir
   si se cambia.
5. No hay proveedor de hosting ni de correo elegido: sin eso no se puede declarar a dónde viajan
   los datos (flujo transfronterizo).

## Lo que debe confirmar un abogado (lista corta)

1. Si la plataforma califica como "plataforma digital de comercio electrónico" (Ley 32495) y cómo
   queda el reglamento del Libro de Reclamaciones: el proyecto de modificación se publicó el
   18-07-2026 (R.M. 244-2026-PCM) y **no se pudo confirmar si ya fue aprobado**.
2. Si el salón (microempresa) es "consumidor" frente a la plataforma según el Código de Protección
   y Defensa del Consumidor, y por tanto si las cláusulas de limitación de responsabilidad, cambio
   de precio y suspensión de `terminos-salones.md` pasan el control de cláusulas abusivas (arts. 49
   a 51).
3. Cómo debe ofrecer cada salón su propio Libro de Reclamaciones en su página dentro de peluWeb
   (¿basta un enlace al suyo?, ¿sirve un formulario de la plataforma a nombre del salón?).
4. Edad mínima para tener cuenta y para reservar; tratamiento de datos de menores de 14 años.
5. Si la nota del cliente y la futura ficha con alergias son **datos sensibles** (salud) y qué
   forma de consentimiento necesitan.
6. Inscripción de bancos de datos y comunicación del flujo transfronterizo ante la ANPD bajo el
   reglamento vigente (D.S. 016-2024-JUS): qué trámites tocan a la plataforma y cuáles a cada salón.
7. Si la plataforma debe designar Oficial de Datos Personales (¿trata "grandes volúmenes"?) y en
   qué fecha según su tamaño.
8. El contrato de encargo del anexo de `terminos-salones.md`: contenido mínimo exigido hoy.
9. Mensajes comerciales con la Ley 32323 (art. 58.1.e del Código): si el salón puede escribir por
   WhatsApp una promoción a un cliente que solo reservó, y cómo debe constar el consentimiento.
10. Plazos de conservación propuestos y los de respuesta a derechos (acceso, rectificación,
    cancelación, oposición, portabilidad).
11. Vigencia y texto actual del art. 14 del Código (promociones): la letra chica automática de
    `docs/promociones.md` dice "Sin límite de cupos" cuando no hay número; confirmar que basta.
12. Persona natural con negocio o persona jurídica: quién firma como `[RAZÓN SOCIAL]`, y si
    hace falta emitir comprobante de pago por la suscripción desde el piloto.

## Sin verificar en fuente oficial

Ver la sección final de `requisitos-de-producto.md` y las marcas "sin verificar" de cada archivo.
En resumen: no se pudo abrir el texto oficial del D.S. 016-2024-JUS (lo confirmado viene de
estudios de abogados y de la nota de El Peruano del 27-01-2026); no se encontró ninguna norma ni
guía peruana específica sobre cookies; el texto del art. 14 del Código se leyó en una copia
alojada por una entidad estatal cuya fecha de actualización no consta; los artículos 150 a 152 y
58 del Código se conocen por la publicación de las leyes que los modifican, no por el texto
consolidado.

## Fuentes consultadas (todas el 2026-10-10)

Datos personales
- El Peruano, "¿Tratas datos sensibles o grandes volúmenes? Evalúa si debes nombrar un ODP" (27-01-2026): https://elperuano.pe/noticia/288028-suplemento-legal-juridica-tratas-datos-sensibles-o-grandes-volumenes-evalua-si-debes-nombrar-un-odp
- Garrigues, nuevo reglamento (D.S. 016-2024-JUS, publicado 30-11-2024, vigente desde 30-03-2025): https://www.garrigues.com/es_ES/noticia/peru-publica-nuevo-reglamento-ley-proteccion-datos-personales
- Cuatrecasas, nuevo reglamento (cronograma del oficial de datos, portabilidad): https://www.cuatrecasas.com/es/latam/propiedad-intelectual/art/nuevo-reglamento-ley-de-proteccion-datos-personales
- CMS, nuevo reglamento: https://cms.law/en/per/publication/nuevo-reglamento-de-proteccion-de-datos-personales
- Ficha de la norma en gob.pe (texto no abierto): https://www.gob.pe/institucion/smv/normas-legales/6426760-016-2024-jus

Consumidor y Libro de Reclamaciones
- Código de Protección y Defensa del Consumidor, copia del texto (arts. 4, 14, 47, 50, 51 leídos): https://www.bn.com.pe/transparenciabn/transparencia-financiera/Ley29571-CodigodeProteccionyDefensadelConsumidor.pdf
- Texto actualizado en El Peruano (no se pudo leer; archivo dañado al descargar): https://diariooficial.elperuano.pe/Normas/obtenerDocumento?idNorma=17
- Ley 31435 (plazo de 15 días hábiles): https://busquedas.elperuano.pe/dispositivo/NL/2050405-1
- Ley 32495 (plataformas digitales), vía resúmenes: https://lpderecho.pe/ley-32495-plataformas-digitales-comercio-electronico-libro-reclamaciones/
- El Peruano, proyecto que modifica el reglamento del Libro (18-07-2026): https://elperuano.pe/noticia/300583-pcm-publica-proyecto-para-modificar-el-reglamento-del-libro-de-reclamaciones
- El Peruano, herramienta gratuita "Tu Libro de Reclamaciones" de Indecopi (02-12-2025): https://elperuano.pe/noticia/284176-mas-orden-mas-clientes-activa-tu-libro-de-reclamaciones-sin-pagar-un-sol
- D.S. 006-2014-PCM (modifica el reglamento del Libro): https://busquedas.elperuano.pe/dispositivo/NL/1041477-1

Comunicaciones comerciales
- Ley 32323 (modifica el art. 58 del Código), El Peruano: https://busquedas.elperuano.pe/api/visor_html/2397811-2
- Ley 28493 (correo comercial no solicitado), copia en Osiptel: https://www.osiptel.gob.pe/media/0hyfzp1a/ley-28493-correo-electronico-comercial-no-solicitado-spam-osiptel.pdf
- CMS, ley contra llamadas y mensajes spam: https://cms.law/en/per/publication/promulgan-ley-contra-llamadas-y-mensajes-spam

Identificación del proveedor
- Garrigues, Ley 32080 elimina la obligación de consignar RUC y razón social en la publicidad (02-07-2024): https://www.garrigues.com/es_ES/noticia/peru-elimina-obligacion-empresas-incluyan-numero-ruc-denominacion-social-documentos
