# Ramal

## 1. Qué es el proyecto y para qué sirve

Centro de control de colectivos en vivo para una ciudad con líneas ficticias dibujadas sobre calles reales de Paraná. Un simulador mueve los colectivos y el sistema los sigue en tiempo real.
Quien entra, sin registrarse, ve colectivos moviéndose en menos de cinco segundos. Después puede elegir una parada y ver la cuenta regresiva del próximo colectivo, entrar al panel de operador o rebobinar el día con una línea de tiempo.
Es el cuarto proyecto del portfolio de Román Gonzalez (dev web junior: Laravel, PHP, MySQL, HTML, CSS, JS). Tiene que ser mejor que los tres anteriores y defendible en entrevistas.
Prioridad estética alta: la web tiene que verse muy bonita, con mucho movimiento y decoración con sentido (ver sección 5 y pendientes).

## 2. Qué es real y qué es simulado

Regla central: **se simula el mundo, no la tecnología.** El sitio lo dice abiertamente y tiene una página que explica la simulación (M10).

| Simulado | Real |
|---|---|
| Colectivos, choferes y horarios | El mapa, con datos de calles reales (OpenStreetMap) |
| Pasajeros | El tiempo real (Reverb/WebSockets; ver plan B en sección 4) |
| Incidentes (demoras, desvíos, fallas) | El cálculo de llegada, con datos geográficos en MySQL |
| | Colas y tareas programadas que mueven el simulador |
| | La API pública documentada |
| | El historial guardado, que permite rebobinar |

## 3. Stack (fijo, no cambiar)

- Laravel 13, PHP 8.3, MySQL. Antes de M1: comparar tipos geográficos de MySQL contra GeoJSON con distancias en PHP, elegir uno y anotar el motivo en `docs/decisiones.md`.
- Blade, Tailwind CSS 4, Alpine.js.
- Mapa: MapLibre GL JS + teselas vectoriales Protomaps en un archivo PMTiles alojado por nosotros (recorte de Paraná). Estilo propio con la paleta, uno por tema.
- Tiempo real: Laravel Reverb (servidor) y Laravel Echo (navegador).
- Colas y tareas programadas de Laravel: el simulador y la limpieza del historial viejo.
- API REST con Laravel Sanctum, documentada con OpenAPI.
- PHPUnit. GitHub Actions corre tests y Laravel Pint en cada subida.
- Gráficos: SVG propio dibujado a mano. Sin Chart.js ni librerías de gráficos.
- Animaciones con CSS y Web Animations API. Sin librerías de animación ni de componentes.
- Sin librerías de íconos (ni Heroicons, Lucide, Font Awesome) y sin emojis en la interfaz.
- Los recorridos se calculan una vez, fuera de la app, a partir de las calles reales, y se guardan en la base. La app no depende de ningún servicio de rutas en ejecución.

## 4. Decisiones fijadas (con las respuestas de Román)

| Tema | Decisión |
|---|---|
| Nombre | **Ramal** (un ramal es una variante de recorrido de una línea) |
| Ciudad | **Paraná** (cambiado desde Gualeguay a pedido de Román el 3 de octubre de 2026). Las líneas son ficticias |
| Escala | **5 líneas y unos 40 colectivos** |
| Mapa | **MapLibre GL JS + PMTiles propio** (Protomaps). Licencia ODbL: hay que citar OpenStreetMap en el mapa. Protomaps pide alojar los archivos uno mismo, no enlazarlos |
| Referencias visuales (para `taste`) | **Flightradar24, Transit app, Citymapper** |
| Hosting | **Plan gratis que duerme + plan B de consulta periódica.** Ver advertencia abajo |

**Advertencia sobre el hosting (la recomendación era un VPS de unos 4 USD al mes):** verificado en septiembre 2026, ningún plan gratis mantiene un servicio despierto. Render gratis duerme tras 15 minutos y tarda cerca de un minuto en despertar. Consecuencias que Román aceptó y que hay que respetar:
- Reverb se desarrolla y se prueba completo (M3), pero la demo publicada usa consulta (polling) cada pocos segundos a la API.
- Hay que implementar el cliente con un modo conmutable: WebSocket cuando está disponible, consulta como respaldo.
- El primer acceso a la demo puede tardar por el arranque en frío. Hay que mitigarlo (pantalla de carga con el isotipo, no un spinner genérico) y decirlo en el README.
- Si más adelante se pasa a un VPS, el modo WebSocket queda listo para activarse. Anotarlo en `docs/decisiones.md`.

## 5. Dirección visual (decidida)

La identidad sale de la señalética del transporte: el cartel de parada, el rótulo luminoso de destino, la pintura de la calzada y el plano de recorridos. Información clara, hecha para leerse rápido y de lejos. No es una app de mapas genérica ni un dashboard.

### Colores (dos temas: Día = plano en papel, Noche = rótulo luminoso)

| Nombre | Día | Noche | Uso |
|---|---|---|---|
| Papel | `#F7F7F4` | | Fondo en Día |
| Asfalto | `#1E2328` | `#1E2328` | Texto en Día; fondo en Noche |
| Calzada | `#2B3138` | `#2B3138` | Superficies elevadas en Noche (paneles, tarjetas) |
| Cordón | `#ECEDE8` | | Superficies y divisiones en Día |
| Señal | `#F5C400` | `#F5C400` | La parada. Marca lo que está en vivo y la parada elegida |
| Bordó | `#9E2A3C` | `#F0788C` | Línea 1 |
| Ultramar | `#2F4CB3` | `#8BA0FF` | Línea 2 |
| Verde ruta | `#16764B` | `#4CC38A` | Línea 3 |
| Naranja | `#B94A0B` | `#FF9A55` | Línea 4 |
| Violeta | `#7A3FA0` | `#C08BE6` | Línea 5 |
| Gris de apoyo | `#5B6168` | `#9AA1A8` | Texto secundario |

Reglas de color (verificadas con contraste WCAG):
- Cada línea tiene un color que es su identidad en todo el sitio: recorrido, colectivo, número y gráfico. Los colores de línea no se usan para nada más.
- El estado (en hora, demorado, fuera de recorrido, incidente) nunca se indica solo con color: siempre ícono propio y palabra.
- Señal sobre Papel da 1,5:1: nunca como texto ni figura sobre Papel. En Día va con Asfalto encima (9,6:1), como el cartel real. En Noche, Señal sobre Asfalto da 9,6:1 y sirve para texto.
- Colores de Día sobre Papel: 4,8:1 a 7,0:1. Colores de Noche sobre Asfalto: 5,9:1 a 7,5:1. Sobre Calzada, el Bordó de Noche baja a 4,9:1: no usarlo debajo de 14 px.
- Antes de usar una combinación nueva, medir el contraste. Si no llega a 4,5:1 en texto normal, ajustar el tono sin cambiar de color.
- Sin degradados decorativos y sin sombras difusas. Única sombra permitida: corta y dura, para el cartel de parada.
- El tema sigue al sistema por defecto y hay interruptor Día/Noche. El mapa cambia de estilo con el tema.

### Tipografía (las tres en Google Fonts)

- **Big Shoulders Display** (700 a 900): números de línea, destinos, titulares grandes.
- **Familjen Grotesk** (400 a 700): toda la interfaz y el texto corrido.
- **Doto** (puntos, panel luminoso): solo para la cuenta regresiva y el rótulo de destino. No se usa para nada más.
- Prohibido: Inter, Roboto, Poppins, Montserrat, Space Grotesk, DM Sans y cualquier monoespaciada para etiquetas.
- Sin etiquetas en mayúsculas con espaciado ancho arriba de cada título, y sin textos unidos con puntos medios.

### Piezas propias y decoración con sentido

1. **Cartel de parada:** poste con cartel amarillo; marca las paradas en el mapa y encabeza "¿Cuándo llega?".
2. **Rótulo luminoso:** cuenta regresiva en Doto sobre Asfalto, con número de línea y destino. Al cambiar el minuto los dígitos parpadean como un panel, sin fundido.
3. **Pintura de la calzada:** divisiones discontinuas como las de la calle; la línea continua doble separa secciones grandes.
4. **Plano de recorridos:** tarjeta por línea con trazado esquemático tipo subte (línea recta con círculos en cada parada).
5. **Colectivo propio:** ícono visto desde arriba, que gira según el rumbo, con el color de su línea.

Toda decoración tiene que decir algo del tema o ayudar a leer; si no, se saca. Nada de formas abstractas flotando, patrones genéricos ni ilustraciones de personas. Dentro de ese criterio, la página tiene que sentirse rica y cuidada: usar con generosidad las cinco piezas (en el mapa, los paneles, los vacíos, la carga, los separadores, las tarjetas) y proponer más decoración del mismo mundo (señalética, boleterías, paradores, pintura vial, cartelería).

### Logo e identidad

- Isotipo: un ramal, una línea que sale de un círculo (parada) y se bifurca en dos. Una sola tinta, legible a 16 px como favicon.
- Logotipo: "Ramal" en Big Shoulders Display junto al isotipo.
- Íconos propios sobre grilla de 24 px: trazo de 2 px con terminaciones cuadradas y círculos huecos como paradas. Mínimo: colectivo, parada, línea, ramal, demora, desvío, incidente, fuera de recorrido, reloj, rebobinar, reproducir, pausar, velocidad, chofer, operador, filtro, alerta, API, mapa y ubicación.
- Página `/identidad`: logo, colores en los dos temas, tipografías, íconos y piezas en uso. Guía viva y material de portfolio.

### Disposición principal (primero celular, después se agranda)

```
+-------------------------------------+
| [isotipo] Ramal     Dia/Noche  [  ] |
+-------------------------------------+
|            M A P A                  |
|        colectivos en vivo           |
|   [cartel de parada]   [colectivo]  |
+-------------------------------------+
| Lineas: [1] [2] [3] [4] [5]         |
| Parada elegida: Plaza    LINEA 3    |
|              rotulo:  0 4  min      |
+-------------------------------------+
```

En escritorio el mapa ocupa la pantalla y los paneles se apoyan sobre él en un costado. Textos en español rioplatense, cortos y sin relleno. Cada botón dice exactamente lo que hace.

## 6. Movimiento (prioridad del proyecto)

La animación es información: un colectivo que se mueve con fluidez dice "esto está vivo". Cada animación tiene una razón, y el proyecto debe tener mucho movimiento cuidado.

- **Los colectivos se deslizan (la más importante, se diseña y prueba primero).** El servidor manda posiciones cada pocos segundos; el navegador interpola con el tiempo como referencia (no con cantidad fija de cuadros), a 60 fps y con rumbo correcto. Si se desvía o llega un dato viejo, la corrección es suave, sin saltos.
- **Llegada a una parada:** pulso corto del cartel de parada.
- **Cuenta regresiva:** dígitos Doto con parpadeo de panel; con menos de 60 s el rótulo dice "Llegando".
- **Elegir una línea:** su recorrido se dibuja de punta a punta y las demás bajan de intensidad. **Elegir una parada:** el mapa se acerca con transición corta.
- **Rebobinar (segunda más importante):** al arrastrar la línea de tiempo los colectivos se mueven hacia atrás y adelante de forma continua.
- **Panel de operador:** al entrar un incidente, la fila aparece desde arriba empujando a las demás; los gráficos se dibujan al cargar, una sola vez.
- **Cambio de tema:** transición circular desde el interruptor (View Transitions API).
- Solo se animan `transform` y `opacity`, salvo el dibujado de trazos (`stroke-dashoffset`). Entradas de 200 a 300 ms que desaceleran al final; salidas más cortas. Toda animación se puede interrumpir.
- Lo que se repite muchas veces por minuto (cada actualización de posición) no lleva decoración extra: solo se anima el desplazamiento.
- Con `prefers-reduced-motion`: los colectivos saltan de posición, se quitan pulsos y dibujados; la información queda completa.
- Rendimiento: con 40 colectivos en pantalla se mantiene la fluidez. Se mide.

**Nivel estético uniforme (pedido de Román, 4 de octubre de 2026).** Todas las pantallas llegan al nivel del ingreso de operador y del centro de control: tablero tipo rótulo luminoso en Doto para los números en vivo (`.tablero`, `.tema-noche`), trama de calles, doble línea amarilla de la calzada, un elemento memorable por pantalla, estados con ícono y forma además del color, respuesta al apretar en cada botón y entrada escalonada de los elementos. Antes de dar por cerrado un módulo se revisa esto; si una pantalla se ve "correcta pero plana", se rediseña.

**Estética desde el primer día.** M0 (identidad, componentes visuales y movimiento) se construye y se aprueba antes que cualquier pantalla funcional. Cada módulo se diseña con las skills antes de escribir su HTML y no se cierra sin revisar estética y movimiento con capturas y videos reales. Si una pantalla funciona pero se ve genérica, no está terminada.

## 7. Módulos y etapas

Cada módulo: objetivo, alcance, afuera y criterios de aceptación comprobables.

**M0. Identidad, sistema visual y de movimiento**
- Objetivo: dejar fijada la identidad y probar el movimiento antes de cualquier pantalla funcional.
- Alcance: nombre, logo, favicon, tokens de Tailwind en los dos temas, tipografías, íconos propios, piezas decorativas, colectivo dibujado, interpolador de movimiento probado con datos de mentira, `/identidad`. Estudio de referencias con `taste`.
- Afuera: base de datos, mapa real, datos de Paraná.
- Criterios: `/identidad` muestra todo en ambos temas; contrastes medidos; el interpolador mueve 40 colectivos de mentira a 60 fps; favicon legible a 16 px; Román aprobó.

**M1. Base y datos geográficos**
- Objetivo: tener la ciudad cargada en la base.
- Alcance: proyecto Laravel, modelos y migraciones (líneas, ramales, paradas, recorridos, colectivos, choferes, horarios), carga de datos de Paraná, recorrido calculado por fuera de la app.
- Afuera: simulación, mapa en pantalla.
- Criterios: el seeder deja 5 líneas con recorridos sobre calles reales; tests de modelos y relaciones; decisión geográfica anotada en `docs/decisiones.md`.

**M2. Simulador**
- Objetivo: mover cada colectivo por su recorrido.
- Alcance: velocidad variable, demora en paradas, horario de servicio, incidentes (demoras, desvíos, fallas), determinista con semilla, corre desde tareas programadas y colas.
- Afuera: emisión al navegador.
- Criterios: misma semilla da el mismo resultado; tests de avance, paradas y reproducibilidad.

**M3. Posiciones y tiempo real**
- Objetivo: llevar las posiciones al navegador.
- Alcance: guardado, emisión por Reverb, reconexión automática, enviar solo lo visible en el mapa, modo de consulta periódica de respaldo.
- Afuera: dibujo del mapa.
- Criterios: test de lo que se emite; el cliente cambia solo a consulta si el WebSocket cae; reconecta sin recargar.

**M4. Mapa público**
- Objetivo: que se entienda en cinco segundos.
- Alcance: mapa estilizado con la paleta (dos estilos), colectivos con movimiento fluido y rumbo, cartel de parada, selección de línea, atribución a OpenStreetMap.
- Afuera: cuenta regresiva, panel de operador.
- Criterios: 40 colectivos fluidos; colectivos visibles en menos de 5 s; tema cambia el estilo del mapa; revisado con capturas y video.

**M5. ¿Cuándo llega?**
- Objetivo: decir cuándo llega el próximo colectivo a una parada.
- Alcance: estimación por recorrido con velocidad real reciente y demoras; rótulo luminoso con cuenta regresiva.
- Afuera: historial.
- Criterios: tests con casos borde (recién pasó, está en el desvío, último del día); la cuenta se actualiza sola.

**M6. Panel de operador**
- Objetivo: ver el estado del servicio.
- Alcance: ingreso con cuenta; demoras, fuera de recorrido, incidentes, gráficos del día en SVG propio.
- Afuera: gestión de usuarios avanzada.
- Criterios: acceso protegido (test); gráficos sin librerías; estados con ícono y palabra.

**M7. Incidentes y desvíos**
- Objetivo: ciclo de vida completo del incidente.
- Alcance: se genera, el operador lo ve, lo atiende, se resuelve; efecto en recorrido y estimaciones.
- Afuera: notificaciones externas.
- Criterios: tests de cada transición de estado; la estimación cambia durante un desvío.

**M8. Historial y rebobinado**
- Objetivo: volver a ver cualquier momento del día.
- Alcance: posiciones guardadas de forma compacta, línea de tiempo (pausar, velocidad, saltar), política de retención.
- Afuera: historial de más de lo retenido.
- Criterios: arrastrar la línea mueve los colectivos de forma continua; la base no crece sin límite (test de limpieza).

**M9. API pública**
- Objetivo: exponer los datos.
- Alcance: líneas, paradas, posiciones en vivo y estimaciones, Sanctum, OpenAPI. Al terminar, feed GTFS-Realtime; si el tiempo no alcanza queda como pendiente documentado y no bloquea la etapa 3.
- Afuera: apps de terceros.
- Criterios: documentación OpenAPI navegable; tests de cada endpoint y de autenticación.

**M10. Cómo funciona**
- Objetivo: explicar con honestidad qué es simulado.
- Alcance: página con qué es simulado, cómo se calcula la llegada y cómo se resolvió el tiempo real, con el motor mostrado de forma visual.
- Afuera: documentación técnica de la API (va en OpenAPI).
- Criterios: lectura clara sin saber programación; ilustraciones con las piezas propias.

**M11. Calidad y publicación** (transversal desde la etapa 1)
- Objetivo: dejar el proyecto publicable y medido.
- Alcance: integración continua, publicación con base persistente, README, accesibilidad (teclado en mapa y paradas; lector de pantalla anuncia llegadas), prueba de carga, medición de rendimiento.
- Criterios: CI verde con tests y Pint; auditoría a11y sin errores graves; LCP medido y registrado.

**Etapas**
1. M0, M1, M2, M3, M4: colectivos fluidos sobre el mapa, con la identidad terminada.
2. M5, M6, M7: llegadas, panel de operador, incidentes.
3. M8, M9, M10: rebobinado, API y la página que lo explica.
4. M11: repositorio público e integración continua desde el primer commit.

## 8. Reglas de trabajo

- Román tiene que poder explicar cada parte en una entrevista. Antes de cada módulo: plan en lenguaje llano y esperar su visto bueno. Al terminar: explicar qué quedó y por qué.
- Commits chicos y frecuentes, en español, con el módulo adelante ("M2: avance de un colectivo por su recorrido y tests"). Repositorio público desde el primer día.
- Cada decisión técnica importante va en `docs/decisiones.md`: problema, qué se eligió, qué se descartó.
- Ningún módulo está terminado sin tests y sin verlo en el navegador con capturas reales. Los módulos con movimiento, además con video o secuencia de capturas.
- Nombres de modelos, tablas y rutas en español.
- No leer ni mostrar `.env`. No subir claves.
- Si algo no se puede hacer como está escrito, decirlo y proponer alternativa. No cambiarlo en silencio.
- Estado: **Etapas 1 y 2 completas (M0 a M7).** Etapa 1: identidad, base de datos, simulador determinista, tiempo real por celdas y mapa público. Etapa 2: M5 estimación de llegada (error mediano 6,4 % contra el simulador) con el rótulo luminoso en cada parada; M6 centro de control de operadores (ingreso protegido, plano en vivo, indicadores, gráficos del día en SVG propio); M7 ciclo del incidente (activo, atendido, resuelto) y desvíos reales por otras calles (40 de 42 tramos). 161 tests de PHP contra MySQL 8.4 y 28 de JavaScript. **Etapa 3 en curso:** M8 (historial y rebobinado) hecho; faltan M9 (API pública) y M10 ("Cómo funciona").
- Para ver todo andando: `composer dev` (servidor, Reverb, cola, simulador y Vite) con MySQL ya prendido. Cuenta de operador de demostración: `operador@ramal.test` / `ramal-demo-2026`. Si se cambia código del simulador hay que reiniciar `ramal:simular` y la cola: son procesos largos que no recargan el código.
- Comandos propios: `ramal:simular`, `ramal:rellenar-dia` (incidentes e historial de las horas anteriores; apagar el simulador antes), `ramal:limpiar-historial`. Scripts fuera de la app: `scripts/calcular-recorridos.mjs`, `scripts/calcular-desvios.mjs`, `scripts/descargar-mapa.sh`.
- MySQL 8.4 local: proceso de usuario, no servicio. Para prenderlo: `"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file=C:\Users\Roman\mysql-ramal\my.ini`. Bases `ramal` y `ramal_test`, usuario `root` sin contraseña (solo desarrollo).

## 9. Skills y cuándo usarlas

Son obligatorias y se usan todas las disponibles, no solo las de esta lista.

- **Referencias, al empezar M0:** `taste` sobre Flightradar24, Transit app y Citymapper. Necesita el servidor Playwright MCP conectado; si no está, avisar antes de seguir. Las decisiones de medidas, ritmo y jerarquía van a `docs/decisiones.md`.
- **Pantalla nueva o rediseño:** `frontend-design:frontend-design` antes de escribir el HTML. Primero plan visual y revisión contra esta memoria, después código.
- **Cualquier animación o transición:** `animate`, con el criterio de `emil-design-eng` (¿debe animarse?, ¿qué propósito?, ¿qué curva y duración?, ¿cómo se interrumpe?). Para movimiento físico y gestos (rebobinar, arrastre): `apple-design`.
- **Buscar movimiento que falta:** `find-animation-opportunities` al terminar cada pantalla.
- **Auditoría del movimiento de todo el sitio:** `improve-animations` al cerrar cada etapa.
- **Accesibilidad:** `chrome-devtools-mcp:a11y-debugging` en cada módulo con interfaz.
- **Velocidad y fluidez:** `chrome-devtools-mcp:debug-optimize-lcp` y medición del mapa con muchos colectivos.
- **Gráficos del panel:** `dataviz` antes de dibujar cualquier gráfico (respetando SVG propio).
- **Antes de cada commit grande:** `code-review`. **Antes de publicar:** `security-review`.

Si una skill no está disponible, decirlo y aplicar los principios de diseño de esta memoria.

## 10. Pendientes y decisiones abiertas

- **Hosting:** elegido plan gratis que duerme (contra la recomendación de un VPS de ~4 USD). Falta elegir el proveedor concreto del plan gratis y de la base MySQL. Retomar la opción VPS si el arranque en frío arruina la demo.
- **Tensión estética:** el prompt pide decoración solo con sentido; Román pide además mucha decoración y animación. Criterio acordado: mucha, pero siempre del mundo del transporte (sección 5). Confirmar con Román si en M0 quiere ir más allá.
- Tipos geográficos de MySQL contra GeoJSON: decidir antes de M1.
- Fuente y método del recorrido de las 5 líneas sobre calles de Paraná (calculado fuera de la app, una vez).
- Recorte PMTiles de Paraná: definir el área, el zoom máximo y dónde se aloja el archivo (debe soportar peticiones de rango HTTP).
- Nombres de las 5 líneas, de las paradas y los destinos.
- GTFS-Realtime (M9): si el tiempo no alcanza, queda documentado como pendiente.
- Confirmar que `taste` pueda correr (Playwright MCP conectado) antes de M0.
- Medir el contraste de cualquier combinación nueva antes de usarla.
