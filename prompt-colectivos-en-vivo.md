Creá el archivo de memoria de este proyecto: un único `CLAUDE.md` en la raíz de esta carpeta. No escribas código de la aplicación, no instales nada y no crees ningún otro archivo. Tu trabajo en esta sesión termina cuando el `CLAUDE.md` está escrito y me mostraste un resumen.

Soy Román Gonzalez, desarrollador web junior (Laravel, PHP, MySQL, HTML, CSS, JavaScript). Este es el cuarto proyecto de mi portfolio y tiene que ser mejor que los tres anteriores. Lo voy a defender en entrevistas, así que el archivo de memoria tiene que dejar fijado qué se construye, con qué, en qué orden y cómo se ve, para que cualquier sesión futura trabaje igual.

## El proyecto

Un centro de control de colectivos en vivo. Una ciudad con líneas de colectivo ficticias, dibujadas sobre calles reales. Un simulador mueve los colectivos por sus recorridos y el sistema los sigue en tiempo real.

Quien entra al sitio, sin registrarse, ve en menos de cinco segundos colectivos moviéndose en un mapa. Después puede:

- Elegir una parada y ver cuándo llega el próximo colectivo, con una cuenta regresiva que se actualiza sola.
- Entrar al panel de operador: demoras, colectivos fuera de recorrido, incidentes y gráficos del día.
- Rebobinar: una línea de tiempo para volver a ver cualquier momento del día, como un video.

Regla central: **se simula el mundo, no la tecnología.**

| Simulado (y se dice abiertamente en el sitio) | Real |
|---|---|
| Los colectivos, sus choferes y sus horarios | El mapa, hecho con datos de calles reales |
| Los pasajeros | El tiempo real por WebSockets |
| Los incidentes (demoras, desvíos, fallas) | El cálculo de llegada, con datos geográficos en MySQL |
| | Las colas y tareas programadas que mueven el simulador |
| | La API pública documentada |
| | El historial guardado, que permite rebobinar |

El sitio tiene que explicar en una página qué es simulado y cómo funciona la simulación. Esa honestidad es parte del proyecto.

## Antes de escribir, preguntame

Usá la herramienta de preguntas y esperá mis respuestas. No fijes estos puntos por tu cuenta:

1. Nombre del proyecto. Propuesta: "Ramal" (un ramal es una variante de recorrido de una línea). Ofrecé dos alternativas tuyas.
2. Ciudad del mapa: Gualeguay (donde vivo), Paraná u otra. Las líneas son ficticias en cualquier caso.
3. Escala de la simulación: cuántas líneas y cuántos colectivos. Propuesta: 5 líneas y unos 40 colectivos.
4. Proveedor del mapa. Tiene que dejarme estilizar los colores del mapa con mi propia paleta (un mapa con el aspecto por defecto se ve genérico). Verificá la política de uso y los límites gratuitos de las opciones antes de proponerlas. Candidatas: MapLibre GL JS con teselas vectoriales de Protomaps (archivo PMTiles que puede alojarse sin proveedor), o Leaflet con un estilo propio. Recomendá una y explicá por qué.
5. Hosting para el servidor de WebSockets, que tiene que quedar prendido. Los planes gratuitos suelen dormirse. Investigá opciones y proponé un plan B (consulta cada pocos segundos) si ninguna sirve.
6. Tres referencias visuales que me gusten, para estudiarlas con la skill `taste` (ver más abajo). Proponé cinco candidatas de interfaces de transporte o de mapas en tiempo real bien diseñadas y yo elijo.

Si dudás de algo, preguntá. No inventes.

## Stack (fijo, no lo cambies)

- Laravel 13, PHP 8.3, MySQL. Antes de M1, compará los tipos geográficos de MySQL contra guardar GeoJSON y calcular distancias en PHP, elegí uno y anotá el motivo en `docs/decisiones.md`.
- Vistas con Blade. Tailwind CSS 4 para estilos. Alpine.js para la interacción en el navegador.
- Mapa: la opción elegida en la pregunta 4.
- Tiempo real: Laravel Reverb en el servidor y Laravel Echo en el navegador.
- Colas y tareas programadas de Laravel: el simulador, la limpieza del historial viejo.
- API REST con Laravel Sanctum, documentada con OpenAPI.
- Tests con PHPUnit. GitHub Actions corre los tests y Laravel Pint en cada subida.
- Gráficos: SVG propio dibujado a mano. Sin Chart.js ni librerías de gráficos.
- Animaciones con CSS y la Web Animations API. Sin librerías de animación ni de componentes.
- Sin librerías de íconos (ni Heroicons, ni Lucide, ni Font Awesome) y sin emojis en la interfaz.

Los recorridos de las líneas se calculan una vez, por fuera de la aplicación, a partir de las calles reales, y se guardan como datos en la base. La aplicación no depende de ningún servicio de rutas en tiempo de ejecución.

## Dirección visual (ya decidida: escribila tal cual en la memoria)

La identidad sale de la señalética del transporte: el cartel de parada, el rótulo luminoso de destino que va sobre el parabrisas, la pintura de las líneas de la calzada y el plano de recorridos. Es información clara, hecha para leerse rápido y de lejos. No es una app de mapas genérica ni un dashboard.

**Colores.** Dos temas con sentido propio: Día (el plano en papel) y Noche (el mapa oscuro, como el rótulo luminoso).

| Nombre | Día | Noche | Uso |
|---|---|---|---|
| Papel | `#F7F7F4` | | Fondo en Día |
| Asfalto | `#1E2328` | `#1E2328` | Texto en Día; fondo en Noche |
| Calzada | `#2B3138` | `#2B3138` | Superficies elevadas en Noche (paneles, tarjetas) |
| Cordón | `#ECEDE8` | | Superficies y divisiones en Día |
| Señal | `#F5C400` | `#F5C400` | La parada: el amarillo del cartel de parada. Marca lo que está en vivo y la parada elegida |
| Bordó | `#9E2A3C` | `#F0788C` | Línea 1 |
| Ultramar | `#2F4CB3` | `#8BA0FF` | Línea 2 |
| Verde ruta | `#16764B` | `#4CC38A` | Línea 3 |
| Naranja | `#B94A0B` | `#FF9A55` | Línea 4 |
| Violeta | `#7A3FA0` | `#C08BE6` | Línea 5 |
| Gris de apoyo | `#5B6168` | `#9AA1A8` | Texto secundario |

Reglas de color, verificadas con contraste WCAG:

- Cada línea tiene un color y ese color es su identidad en todo el sitio: el recorrido en el mapa, el colectivo, el número de la línea, el gráfico. Los colores de línea no se usan para nada más.
- El estado (en hora, demorado, fuera de recorrido, incidente) nunca se indica solo con color: lleva siempre un ícono propio y una palabra.
- Señal sobre Papel da 1,5:1: nunca se usa como texto ni como figura sobre Papel. En Día aparece con Asfalto encima (9,6:1), como el cartel real. En Noche, Señal sobre Asfalto da 9,6:1 y sirve para texto.
- Los cinco colores de Día sobre Papel dan entre 4,8:1 y 7,0:1. Los de Noche sobre Asfalto dan entre 5,9:1 y 7,5:1 (sobre Calzada, el Bordó de Noche baja a 4,9:1: no usarlo más chico que 14 px).
- Antes de usar una combinación nueva, medí el contraste. Si no llega a 4,5:1 en texto normal, ajustá el tono sin cambiar de color.
- Sin degradados decorativos y sin sombras difusas. Una sola sombra permitida: corta y dura, para el cartel de parada.
- Por defecto el tema sigue al sistema, y hay un interruptor Día/Noche. El mapa cambia de estilo con el tema.

**Tipografía.** Las tres existen en Google Fonts; verificadas.

- Big Shoulders Display (condensada, pesos 700 a 900): los números de línea, los destinos y los titulares grandes. Viene de la rotulación de transporte.
- Familjen Grotesk (pesos 400 a 700): toda la interfaz y el texto corrido.
- Doto (tipografía de puntos, como un panel luminoso): solo para la cuenta regresiva de llegada y el rótulo de destino. Es la voz del panel luminoso y no se usa para nada más.
- Prohibido: Inter, Roboto, Poppins, Montserrat, Space Grotesk, DM Sans y cualquier monoespaciada para etiquetas.
- Sin etiquetas en mayúsculas con espaciado ancho arriba de cada título, y sin textos unidos con puntos medios.

**Las piezas propias del sitio (la decoración con sentido)**

1. El cartel de parada: el poste con el cartel amarillo es el elemento que marca las paradas en el mapa y el encabezado de la pantalla "¿Cuándo llega?".
2. El rótulo luminoso: la cuenta regresiva en Doto sobre fondo Asfalto, con el número de línea y el destino. Cuando cambia un minuto, los dígitos cambian con un parpadeo corto de panel, no con un fundido.
3. La pintura de la calzada: las divisiones y separadores del sitio son líneas discontinuas como las de la calle, y la línea continua doble sirve para separar secciones grandes.
4. El plano de recorridos: cada línea tiene su tarjeta con el trazado esquemático, estilo plano de subte: línea recta con círculos en cada parada.
5. El colectivo, dibujado propio: un icono de colectivo visto desde arriba, que gira según su rumbo en el mapa, con el color de su línea.

Toda decoración tiene que decir algo del tema o ayudar a leer. Si un adorno no lo hace, se saca. Nada de formas abstractas flotando, ni patrones genéricos, ni ilustraciones de personas.

**Logo e identidad**

- Isotipo: un ramal, es decir, una línea que sale de un círculo (parada) y se bifurca en dos. Funciona en una sola tinta y a 16 px como favicon.
- Logotipo: el nombre en Big Shoulders Display junto al isotipo.
- Íconos propios, dibujados para este proyecto sobre una grilla de 24 px: trazo de 2 px con terminaciones cuadradas, y círculos huecos como paradas. Como mínimo: colectivo, parada, línea, ramal, demora, desvío, incidente, fuera de recorrido, reloj, rebobinar, reproducir, pausar, velocidad, chofer, operador, filtro, alerta, API, mapa y ubicación.
- Una página `/identidad` que muestre logo, colores en los dos temas, tipografías, íconos y piezas en uso. Sirve de guía viva y para mostrar en el portfolio.

**Movimiento: es una prioridad del proyecto**

La animación acá no es adorno: es la información. Un colectivo que se mueve con fluidez dice "esto está vivo". Cada animación tiene que tener una razón.

- **Los colectivos se deslizan.** El servidor manda posiciones cada pocos segundos; el navegador interpola entre una y otra, con el tiempo como referencia (no con una cantidad fija de cuadros), para que el movimiento sea continuo a 60 fps y con rumbo correcto. Si el colectivo se desvía o llega un dato viejo, la corrección es suave, sin saltos. Es la animación más importante del proyecto: se diseña y se prueba primero.
- **Llegada a una parada:** un pulso corto del cartel de parada cuando el colectivo llega.
- **Cuenta regresiva:** los dígitos de Doto cambian con parpadeo de panel; cuando faltan menos de 60 segundos, el rótulo pasa a "Llegando".
- **Elegir una línea:** su recorrido se dibuja de punta a punta (como si se trazara) y las demás bajan de intensidad. Elegir una parada: el mapa se acerca con una transición corta.
- **Rebobinar:** al arrastrar la línea de tiempo, los colectivos se mueven hacia atrás y adelante de forma continua. Es la segunda animación más importante.
- **Panel de operador:** cuando entra un incidente, la fila aparece desde arriba empujando a las demás con suavidad; los gráficos se dibujan al cargar, una sola vez.
- **Cambio de tema:** transición circular desde el interruptor (la API de View Transitions).
- Solo se animan `transform` y `opacity`, salvo el dibujado de trazos (`stroke-dashoffset`). Entradas de 200 a 300 ms que desaceleran al final; salidas más cortas. Toda animación se puede interrumpir.
- Las acciones que se repiten muchas veces por minuto (cada actualización de posición) no llevan decoración extra: lo único que se anima es el desplazamiento.
- Con `prefers-reduced-motion`: los colectivos saltan de posición en vez de deslizarse y se quitan los pulsos y los dibujados; la información sigue completa.
- El rendimiento es parte del movimiento: con 40 colectivos en pantalla se mantiene la fluidez. Medilo.

**Estética desde el primer día**

El diseño no se deja para el final. El módulo M0 (identidad, componentes visuales y movimiento) se construye y se aprueba antes que cualquier pantalla funcional. Cada módulo nuevo se diseña con las skills (ver más abajo) antes de escribir su HTML, y no se cierra sin revisar su estética y su movimiento con capturas y videos reales. Si una pantalla funciona pero se ve genérica, no está terminada.

**Disposición principal** (primero celular, después se agranda)

```
+-------------------------------------+
| [isotipo] Ramal     Dia/Noche  [  ] |
+-------------------------------------+
|                                     |
|            M A P A                  |
|        colectivos en vivo           |
|   [cartel de parada]   [colectivo]  |
|                                     |
+-------------------------------------+
| Lineas: [1] [2] [3] [4] [5]         |
| Parada elegida: Plaza    LINEA 3    |
|              rotulo:  0 4  min      |
+-------------------------------------+
```

En escritorio, el mapa ocupa la pantalla y los paneles se apoyan sobre él en un costado. Los textos van en español rioplatense, cortos y sin relleno. Cada botón dice exactamente lo que hace.

## Módulos

Escribí cada módulo con: objetivo en una oración, alcance, qué queda afuera, y criterios de aceptación comprobables.

- **M0. Identidad, sistema visual y de movimiento:** nombre, logo, favicon, colores y tipografías como tokens de Tailwind en los dos temas, íconos propios, piezas decorativas, el colectivo dibujado, el interpolador de movimiento probado con datos de mentira, y la página `/identidad`. Se aprueba antes de seguir.
- **M1. Base y datos geográficos:** proyecto Laravel, modelos y migraciones: líneas, ramales, paradas, recorridos (trazado), colectivos, choferes y horarios. Carga de los datos de la ciudad elegida.
- **M2. Simulador:** hace avanzar a cada colectivo por su recorrido con velocidad variable, demora en paradas y horario de servicio; es determinista (con una semilla) para poder testearlo. Corre desde tareas programadas y colas. Genera incidentes (demoras, desvíos, fallas). Tests del avance, de las paradas y de la reproducibilidad.
- **M3. Posiciones y tiempo real:** las posiciones se guardan y se emiten por Reverb a quien esté mirando; reconexión automática; el servidor no envía más de lo necesario (solo lo que se ve en el mapa). Test de lo que se emite.
- **M4. Mapa público:** el mapa estilizado con la paleta propia, los colectivos con movimiento fluido y rumbo, el cartel de parada y la selección de línea. Se entiende en cinco segundos.
- **M5. ¿Cuándo llega?:** cálculo del tiempo estimado de llegada de cada colectivo a cada parada a lo largo del recorrido, considerando velocidad real reciente y demoras; el rótulo luminoso con cuenta regresiva. Tests de la estimación, incluyendo casos borde (el colectivo recién pasó, está en el desvío, es el último del día).
- **M6. Panel de operador:** ingreso con cuenta; vista de demoras, colectivos fuera de recorrido, incidentes y gráficos del día en SVG propio.
- **M7. Incidentes y desvíos:** ciclo de vida del incidente (se genera, el operador lo ve, lo atiende, se resuelve) y su efecto en el recorrido y en las estimaciones.
- **M8. Historial y rebobinado:** las posiciones se guardan de forma compacta y se pueden reproducir con una línea de tiempo (pausar, velocidad, saltar a un momento). Política de retención para que la base no crezca sin límite.
- **M9. API pública:** líneas, paradas, posiciones en vivo y estimaciones, con Sanctum y documentación OpenAPI. Al terminar esa API, sumá un feed en formato GTFS-Realtime, el estándar que usan las apps de transporte reales; si el tiempo no alcanza, queda como pendiente documentado y no bloquea la etapa 3.
- **M10. Cómo funciona:** la página que explica qué es simulado, cómo se calcula la llegada y cómo se resolvió el tiempo real, con el motor mostrado de forma visual.
- **M11. Calidad y publicación:** integración continua, publicación con base de datos persistente, README, accesibilidad (se puede usar el mapa y las paradas con teclado, y un lector de pantalla anuncia las llegadas), prueba de carga con muchos colectivos, y medición de rendimiento.

Etapas:

1. M0, M1, M2, M3 y M4: se ven colectivos moviéndose con fluidez sobre el mapa de la identidad terminada.
2. M5, M6 y M7: llegadas estimadas, panel de operador e incidentes.
3. M8, M9 y M10: rebobinado, API y la página que lo explica.
4. M11 es transversal: empieza en la etapa 1 (repositorio público e integración continua desde el primer commit).

## Reglas de trabajo (van en la memoria)

- Tengo que poder explicar cada parte en una entrevista. Antes de empezar un módulo, explicame el plan en lenguaje llano y esperá mi visto bueno. Al terminarlo, explicame qué quedó y por qué.
- Commits chicos y frecuentes, en español, con el módulo adelante ("M2: avance de un colectivo por su recorrido y tests"). Repositorio público desde el primer día.
- Cada decisión técnica importante se anota en `docs/decisiones.md`: qué problema había, qué se eligió y qué se descartó.
- Ningún módulo se da por terminado sin tests y sin haberlo visto funcionar en el navegador con capturas reales. Los módulos con movimiento se revisan además con un video o una secuencia de capturas.
- Nombres de modelos, tablas y rutas en español, como en mis otros proyectos.
- No leas ni muestres el archivo `.env`. No subas claves al repositorio.
- Si algo no se puede hacer como está escrito acá, decímelo y proponé la alternativa. No lo cambies en silencio.

## Skills (anotá en la memoria cuándo se usa cada una; son obligatorias, no opcionales)

- **Estudio de referencias, al empezar M0:** `taste` sobre las tres referencias que yo elija. Necesita el servidor Playwright MCP conectado; si no está, avisame antes de seguir. De ahí salen las decisiones de medidas, ritmo y jerarquía, escritas en `docs/decisiones.md`.
- **Pantalla nueva o rediseño:** `frontend-design:frontend-design` antes de escribir el HTML. Primero el plan visual (paleta, tipografía, disposición) y su revisión contra esta memoria; después el código.
- **Cualquier animación o transición:** `animate` para construirla, aplicando el criterio de `emil-design-eng` (sobre todo: ¿debe animarse?, ¿qué propósito tiene?, ¿qué curva y qué duración?, ¿cómo se interrumpe?).
- **Buscar movimiento que falta:** `find-animation-opportunities` al terminar cada pantalla.
- **Auditoría del movimiento de todo el sitio:** `improve-animations` al cerrar cada etapa.
- **Accesibilidad:** `chrome-devtools-mcp:a11y-debugging`, en cada módulo con interfaz.
- **Velocidad de carga y fluidez:** `chrome-devtools-mcp:debug-optimize-lcp` y la medición de rendimiento del mapa con muchos colectivos.
- **Antes de cada commit grande:** `code-review`. **Antes de publicar:** `security-review`.

Si alguna skill no está disponible en la sesión, decilo y aplicá los principios de diseño escritos en esta memoria.

## Formato del `CLAUDE.md`

En español, en Markdown, con estas secciones y en este orden. Máximo 400 líneas: es una referencia que se lee al empezar cada sesión, no un manual.

1. Qué es el proyecto y para qué sirve (5 líneas como mucho)
2. Qué es real y qué es simulado
3. Stack
4. Decisiones fijadas (con mis respuestas)
5. Dirección visual: colores en los dos temas, tipografías, piezas propias y decoración, logo e íconos, disposición
6. Movimiento: las animaciones principales y sus reglas
7. Módulos y etapas
8. Reglas de trabajo
9. Skills y cuándo usarlas
10. Pendientes y decisiones abiertas

Al terminar, mostrame un resumen de diez líneas con lo que quedó fijado y la lista de decisiones abiertas. No empieces el módulo M0 hasta que yo lo pida.
