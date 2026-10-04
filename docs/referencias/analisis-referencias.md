# Estudio de referencias para Ramal

Medido el 3 de octubre de 2026 con Playwright a 1440x900, sin sesión. Capturas en esta carpeta. Los valores salen del DOM; cuando la captura contradice al DOM, manda la captura. Solo se midió la portada de cada sitio (página única).

Nota de método: la skill `taste` define cuatro pasos (medir, patrones, principios, observador) con salida en `.md` y `.json` por sitio. Acá se aplicaron con una extracción de DOM más corta que la `extract.js` original y se condensaron los tres sitios en un solo documento, porque lo que se necesita es decidir sobre Ramal. No hay `.json` por sitio.

## 1. Flightradar24 (flightradar24.com)

Captura: `flightradar24.com-viewport.jpeg`. Ya abre sobre el mapa en vivo (centrado en Buenos Aires, zoom 6), sin portada.

**Medidas**
- Paneles flotantes a la izquierda con fondo `rgba(57,57,57,0.9)` y botones `#414141`; barra inferior de acciones (Settings, Weather, Filters, Widgets, Playback) con el mismo gris. Texto `#FFFFFF` y `#EFEFF4`.
- Tipografía: Open Sans (701 elementos) y Roboto (161). Tamaños 14 px (91 usos), 15 px (50) y 11 px (40). Pesos 400, 500 y 600.
- Radios: 4 px (40), 6 px (27), 9999 px (32, pastillas). Sombra única: `1px 3px 6px rgba(0,0,0,.2)`.
- Espaciado: 1, 2, 4, 6 y 8 px dominan. Es una interfaz densa.
- Color vivo: el amarillo `#F8C023` es el avión y los números destacados; naranja `#EA5B0C` en 12 fondos (alertas, calificaciones); verde `#6CCB78` y `#96DC83` solo en llamados a la acción. El mapa de fondo es de azul-gris apagado.
- Transiciones: 0,15 s con `cubic-bezier(0.4,0,0.2,1)`; opacidad 0,2 s.

**Patrones**
1. El mapa está apagado a propósito (agua `#325F79`, tierra gris verdosa) para que el único amarillo saturado, los aviones, se lea al instante.
2. La información vive en paneles que se apoyan sobre el mapa, con 90 % de opacidad, y se pueden colapsar con una flecha.
3. Las acciones frecuentes (rebobinar, filtros) están en una barra inferior con icono y palabra.
4. Los números tienen jerarquía por color, no por tamaño: 14 px en blanco, cifras clave en amarillo.

**Qué se toma para Ramal**
- Mapa en tonos apagados y un solo color saturado por línea para los colectivos. Con cinco líneas el truco es que cada colectivo sea mucho más saturado que cualquier calle.
- Paneles apoyados sobre el mapa en escritorio, con colapso, y una barra inferior con "Rebobinar" como acción visible (lo que ahí se llama Playback).
- Lo que no se toma: la densidad de 11 px (no llega a lo legible en celular), las publicidades y la sombra difusa.

## 2. Transit (transitapp.com)

Captura: `transitapp.com-viewport.jpeg`.

**Medidas**
- Fondo de página `#FDF5F2` (crema cálido) y superficies `#FFEEE6`; texto principal verde oscuro `#12452B` (83 usos), énfasis `#993300`; botones `#27A559`; azul `#024A99` y verde `#1B6539` como colores de línea.
- Tipografía propia, Puffin Transit, en cuatro pesos. Titulares: h1 72 px/72 px, h2 60/60, h3 40/48, h5 24/28, h6 20/24, todos en peso 800. Cuerpo 17 a 18 px, peso 400. Interlineado de titulares igual al tamaño (1,0).
- Radios: 32 px (54 usos), 8 px (36). Sombra suave `0 4px 16px rgba(0,51,105,.08)` y un halo verde `0 0 48px`.
- Espaciado: 8, 10, 16, 32 y 40 px. Secciones holgadas.
- Decoración: collage de edificios, un tren, un colectivo, una parada con cartel azul y tarjetas de tiempo ("3 minutes" sobre naranja). Cielo azul degradado al fondo.

**Patrones**
1. Los titulares enormes y pesados (72 px, peso 800, interlineado 1) llevan una palabra en naranja `#FF9A1F` aprox. ("car-free") para marcar la idea.
2. El tiempo de espera es una tarjeta naranja redondeada con el número grande y la unidad pequeña debajo. Esa tarjeta es el protagonista del hero.
3. La decoración es literal: cosas que existen en una calle (parada, tren, bici). Nada abstracto.
4. Todo es redondeado (32 px): tarjetas, botones, el cartel.

**Qué se toma para Ramal**
- La tarjeta de tiempo de llegada como pieza protagonista, grande y con la unidad chica debajo; en Ramal es el rótulo luminoso en Doto.
- Titulares de Big Shoulders Display con interlineado 1,0 y una palabra destacada en el color de la línea.
- Decoración literal del transporte (parada, colectivo, cartelería), que respalda lo pedido por Román: mucha decoración, siempre del mundo del transporte.
- Lo que no se toma: los emojis en la interfaz (Transit los usa en sus avatares) y los degradados de cielo.

## 3. Citymapper (citymapper.com)

Captura: `citymapper.com-viewport.jpeg` (el aviso de cookies tapa el pie).

**Medidas**
- Fondo `#FFFFFF`; superficies `#F6F6F6`; texto `#383838`; verde de marca `#37AB2F`; tarjeta de búsqueda en azul acero `#417193`.
- Tipografía proxima-soft: h1 44 px/48,4 px, h2 y h3 40 px/44 px, todos peso 700; cuerpo 16 px, 400.
- Radios: 20 px (17 usos), 8 px (7), 80 px (pastillas). Sin sombras difusas: un filo de `0 -0,5px 0 0,5px rgba(0,17,34,.06)` (12 usos) y una base de `0 -4px 0 rgba(0,17,34,.133)` en un botón.
- Transiciones muy cortas: 0,1 s para color y fondo; 0,2 s para posición.
- Decoración: horizonte de ciudad en gris claro y una fila de vehículos dibujados con trazo fino, bicis y patinetes; una mascota verde de línea.

**Patrones**
1. Los vehículos se dibujan con una sola línea gris azulada de 1 a 2 px, sin relleno: sirven como textura de fondo sin competir con el texto.
2. El formulario principal es una tarjeta de color pleno de 20 px de radio con campos blancos: un bloque, una tarea ("Desde / Hasta / IR").
3. Los estados se resuelven con filos de un píxel o menos y cambios de color en 100 ms: la interfaz responde rápido y no se mueve.
4. Una palabra del titular va en verde de marca ("Usable").

**Qué se toma para Ramal**
- Dibujos de colectivos en línea fina como decoración de fondo (en vacíos, cargas, la página de "Cómo funciona"), usando el colectivo propio.
- Respuesta de 100 ms en hover y foco; las animaciones largas se reservan para el movimiento con significado.
- Filos de 1 px (la "pintura de la calzada") en lugar de sombras.
- Lo que no se toma: la mascota (Ramal no usa personajes) y el color sólido azul acero fuera de la paleta.

## Decisiones que salen del estudio

1. **Mapa apagado, colectivos saturados.** Las calles y el agua del mapa usan grises y verdes de baja saturación; solo los colectivos y la parada elegida usan color pleno.
2. **Rótulo de llegada como protagonista.** Es la pieza más grande de la pantalla de una parada: número en Doto de 56 a 72 px, unidad en Familjen Grotesk chica debajo.
3. **Titulares con interlineado 1,0** en Big Shoulders Display, 700 a 900, con una palabra en el color de la línea elegida.
4. **Paneles de 90 % de opacidad** sobre el mapa en escritorio, que se colapsan; sin vidrio esmerilado.
5. **Barra de acciones inferior** con ícono propio y palabra: Rebobinar, Líneas, Tema.
6. **Respuesta de 100 ms** en estados de hover y foco, y 200 a 300 ms en entradas de panel.
7. **Decoración de línea fina** con colectivos, paradas y carteles dibujados con el trazo de 2 px del sistema, usada en fondos, vacíos y cargas.
8. **Radios:** 20 px en tarjetas y paneles grandes, 8 px en botones y campos, 9999 px en pastillas de línea. Esto se probará en M0 contra el cartel de parada, que lleva esquinas más rectas.
