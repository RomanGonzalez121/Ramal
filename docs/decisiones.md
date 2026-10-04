# Decisiones técnicas de Ramal

Cada decisión importante: qué problema había, qué se eligió y qué se descartó.

## M0-1. El mapa: MapLibre GL JS con teselas PMTiles alojadas por nosotros

- **Problema:** el mapa por defecto de cualquier proveedor se ve genérico, y tiene que cambiar de colores con el tema Día/Noche.
- **Se eligió:** MapLibre GL JS con teselas vectoriales de Protomaps en un archivo PMTiles (recorte de Paraná). Es un archivo estático que se sirve con peticiones de rango HTTP, sin claves ni límites de terceros. Licencia ODbL: hay que citar OpenStreetMap en el mapa. Protomaps pide alojar los archivos uno mismo en vez de enlazar sus builds.
- **Se descartó:** Leaflet con teselas de imágenes (no se puede recolorear con la paleta propia) y teselas de un proveedor con plan gratuito (límites y políticas que pueden cambiar).

## M0-2. Hosting: plan gratuito que duerme, con consulta periódica como respaldo

- **Problema:** Reverb necesita un proceso siempre prendido. Verificado en septiembre 2026: ningún plan gratuito lo permite (Render gratis duerme a los 15 minutos y tarda cerca de un minuto en despertar; Fly.io ya no tiene plan gratuito para cuentas nuevas).
- **Se eligió (decisión de Román):** plan gratuito que duerme. La demo publicada usa consulta cada pocos segundos; Reverb se desarrolla y se prueba completo. El cliente conmuta solo entre WebSocket y consulta.
- **Se descartó por ahora:** un VPS de unos 4 USD al mes (Vultr 3,50, DigitalOcean 4), que era la recomendación. Se retoma si el arranque en frío arruina la demo.

## M0-3. Movimiento de los colectivos: interpolación por tiempo sobre el trazado

- **Problema:** el servidor manda posiciones cada pocos segundos; en pantalla el movimiento tiene que ser continuo a 60 fps, con rumbo correcto y sin saltos cuando llega un dato tarde o corregido.
- **Se eligió:** `resources/js/interpolador.js`, un módulo puro sin DOM. Cada dato trae el trazado recorrido desde el último informe (no solo el punto final), así el colectivo dobla en las esquinas en vez de cortar camino. Un dato nuevo arranca desde donde el colectivo se ve ahora, no desde donde el servidor creía que estaba. Una corrección grande dura más (velocidad máxima) para no teletransportar. El rumbo gira con suavizado exponencial por el arco corto. Con `prefers-reduced-motion` salta a la posición nueva. 11 tests con el corredor de pruebas de Node (`npm run test:js`).
- **Se descartó:** animar con transiciones CSS entre dos puntos (corta las esquinas y no se puede interrumpir bien) y animar con una cantidad fija de cuadros (la velocidad cambiaría según el monitor).

## M0-4. Contraste calculado en el servidor

- **Problema:** la paleta promete contrastes WCAG. Mostrarlos escritos a mano invita al error.
- **Se eligió:** `App\Support\Contraste` calcula la relación con la fórmula de WCAG y la tabla de `/identidad` la muestra. Los tests comprueban que los colores de la tabla (`config/identidad.php`) coinciden con los tokens de `app.css` y que las líneas llegan a 4,5:1 en los dos temas.
- **Se descartó:** calcularlo en el navegador (no se puede testear con PHPUnit).

## M0-5. Tipografías alojadas por Vite

- **Se eligió:** el proveedor `google` de `laravel-vite-plugin/fonts` descarga Big Shoulders Display, Familjen Grotesk y Doto y las sirve desde el propio sitio (sin pedidos a Google en producción) con la directiva `@fonts`.

## M0-6. Referencias visuales

Estudio en `docs/referencias/analisis-referencias.md` (Flightradar24, Transit, Citymapper). Resumen de lo que se tomó: mapa apagado con un solo color saturado por objeto móvil, rótulo de llegada como protagonista, titulares con interlineado 1,0, paneles al 90 % de opacidad, decoración de línea fina con vehículos y respuesta de 100 ms en estados.

## M1-1. Datos geográficos: coordenadas en JSON y cálculo en PHP

- **Problema:** hay que guardar trazados de recorridos y paradas. MySQL tiene tipos espaciales (POINT, LINESTRING, ST_Distance); la alternativa es guardar coordenadas y calcular en PHP.
- **Se eligió:** el trazado va como lista de puntos `[longitud, latitud]` (orden de GeoJSON) en una columna JSON, con los metros acumulados hasta cada punto ya calculados. Las paradas llevan `latitud` y `longitud` decimales con índice compuesto para filtrar por el rectángulo visible (M3). Las cuentas están en `App\Support\Geo` (haversine y proyección de un punto sobre el trazado).
- **Por qué:** lo central de Ramal es la distancia a lo largo del recorrido (simulador y llegadas), que los tipos espaciales no calculan. Funciona igual en MySQL, MariaDB y SQLite, se prueba con PHPUnit sin base y evita el orden latitud/longitud y los SRID, que cambian entre MySQL 8 y MariaDB 10.4.
- **Se descartó:** tipos espaciales de MySQL (no resuelven el recorrido lineal y complican los tests) y el esquema mixto (dos representaciones que mantener). Se reabre si aparece una consulta tipo "paradas cerca de mí" que justifique un índice espacial.

## M1-2. MySQL 8.4 LTS en desarrollo, CI y producción

- **Problema:** XAMPP trae MariaDB 10.4.32 y el stack fijo dice MySQL.
- **Se eligió:** instalar MySQL 8.4.9 (`winget install Oracle.MySQL`) y correrlo como proceso de usuario, sin servicio de Windows y sin permisos de administrador: datos en `C:\Users\Roman\mysql-ramal\data`, configuración en `my.ini`, escuchando solo en 127.0.0.1. Usuario `root` sin contraseña, solo para desarrollo local. Bases `ramal` y `ramal_test`. La CI usa un servicio `mysql:8.4`. Los tests corren contra MySQL, no SQLite.
- **Se descartó:** la MariaDB de XAMPP (otro motor que el de producción) y SQLite (esconde diferencias).

## M1-3. Recorridos: OSRM público, una sola vez

- **Problema:** los colectivos tienen que seguir calles reales con sus manos, pero la app no puede depender de un servicio de rutas.
- **Se eligió:** `scripts/calcular-recorridos.mjs` (Node, fuera de la app) manda las paradas de cada línea al servidor de demostración de OSRM (1 pedido por segundo, User-Agent propio, atribución a OpenStreetMap y OSRM) y guarda `database/datos/recorridos.json`. El seeder solo lee ese archivo. Cada línea tiene ida y vuelta calculadas por separado, y por eso difieren donde hay calles de mano única.
- **Hallazgo:** los lugares conocidos (parques, hospitales) tienen coordenadas en el centro del edificio, hasta 91 m de la calle. Una parada real va sobre la calle. El script guarda también la ubicación ajustada a la calle que devuelve OSRM y el seeder la usa: las paradas caen a 0 m del trazado. Un test exige menos de 10 m.
- **Límite conocido:** OSRM usa el perfil de auto, no el de colectivo. No conoce carriles exclusivos ni paradas reales. Las paradas son lugares reales de Paraná, pero las líneas son ficticias.
- **Se descartó:** un OSRM propio con el recorte de la ciudad (mucho trabajo para 5 líneas) y dibujar a mano (trazados imprecisos).

## M2-1. Simulador en PHP puro y determinista, con azar sin estado

- **Problema:** el simulador tiene que poder probarse (misma semilla, misma corrida) y correr desde tareas programadas, o sea en procesos distintos que no comparten memoria.
- **Se eligió:** `App\Simulacion\Simulador` es PHP puro, sin Laravel ni base. Recibe los recorridos ya armados (`RutaSimulada`) y devuelve estados nuevos (`EstadoColectivo`, inmutable). Toda la aleatoriedad sale de `Azar::flotante(semilla, colectivo, tick, índice)`, una función de mezcla de bits sin estado: cualquier proceso puede retomar en cualquier tick y obtiene lo mismo. `ServicioSimulacion` es la capa fina que lee y guarda en MySQL.
- **Qué simula, en orden, en cada tick:** incidentes (demora cada ~90 min, desvío cada ~4 h, falla cada ~12 h por colectivo), esperas (8 a 25 s en cada parada, 60 a 150 s en la terminal, donde cambia de sentido) y movimiento (acelera hacia una velocidad de crucero de 16 a 27 km/h que varía, y frena para llegar a cero justo en la parada).
- **Horario de servicio:** fuera de horario los colectivos no están en la calle; al empezar el servicio salen de la terminal de a uno, separados por la frecuencia de su línea. Con `RAMAL_SIEMPRE_EN_SERVICIO=true` (valor por defecto) hay colectivos a cualquier hora, para que la demo siempre muestre algo.
- **Límite conocido:** un desvío por ahora solo baja la velocidad; el cambio real de recorrido llega con M7.
- **Se descartó:** `mt_rand` con semilla (comparte estado global y no sirve entre procesos) y guardar el estado del generador en la base.

## M2-2. Cómo corre: programador, comando y cola

- **Se eligió:** `php artisan ramal:simular` hace un tick cada 2 s. El programador de Laravel lo lanza cada minuto con `--durante=58`, sin solaparse (`withoutOverlapping`) y en segundo plano. En desarrollo se corre sin límite. El tiempo simulado sigue al real: `dt` es lo que pasó de verdad (con tope de 10 s por si la máquina se atrasa).
- **Por qué no una cola para mover los colectivos:** un trabajo por tick agregaría latencia y desorden entre ticks sin ganar nada; la cola sí se usa para lo que puede esperar: emitir los mensajes a Reverb (`ShouldBroadcast`).

## M3-1. Tiempo real por celdas

- **Problema:** "el servidor no envía más de lo necesario".
- **Se eligió:** la ciudad se divide en una grilla de 4 por 4 (`config/ramal.php`). Cada celda tiene su canal `posiciones.{columna}.{fila}`. En cada tick se emite un evento por celda con solo los colectivos que están ahí. El navegador se suscribe solo a las celdas que cruza la vista (más un margen del 12 %) y cambia de suscripciones al mover el mapa. La grilla está implementada dos veces (PHP y JavaScript) y los mismos casos de prueba corren en las dos.
- **Qué viaja:** por cada colectivo, el trazado recorrido desde el último informe (no solo el punto final), para que el navegador doble en las esquinas. Es lo que usa el interpolador (ver M0-3).
- **Se descartó:** un canal único con todo (manda lo que nadie mira) y un canal por colectivo (40 suscripciones por pantalla).

## M3-2. Reconexión y respaldo por consulta

- **Se eligió:** `TiempoReal` (`resources/js/mapa/tiempo-real.js`), con sus dependencias inyectadas para probarlo en Node sin red. Pusher reintenta solo; si en 5 s no hay conexión, el cliente pasa a consultar `/api/posiciones` cada 2 s y vuelve al WebSocket cuando reconecta. Al conectar pide una foto del estado para no esperar el próximo mensaje. La interfaz dice siempre en qué modo está, con ícono y palabra: En vivo, Se actualiza cada 2 segundos, Conectando, Sin datos recientes.
- **Verificado a mano:** se detuvo Reverb con el mapa abierto; pasó a consultar solo, y al volver Reverb regresó a "En vivo" en unos 20 s (Pusher espera antes de reintentar).
- **Hosting gratuito:** con `VITE_TIEMPO_REAL=sondeo` el cliente ni intenta el WebSocket. Es el modo previsto para la demo publicada (ver M0-2).

## M4-1. Mapa: capa de colectivos en HTML y SVG, no en el mapa

- **Se eligió:** MapLibre dibuja solo el mapa base y los recorridos. Los colectivos y las paradas son HTML y SVG encima, posicionados con `map.project()` en cada cuadro. Así cada colectivo gira suave con su rumbo y se mueve con el interpolador, y las paradas son botones reales que se pueden tocar y alcanzar con el teclado.
- **Costo:** 40 colectivos y 11 paradas es poco; con cientos habría que pasar a una capa de MapLibre o a canvas. Medido: 60 cuadros por segundo en el navegador de pruebas con los 40 colectivos.
- **Estilo:** `resources/js/mapa/estilo.js` parte de los estilos de Protomaps y cambia todos los colores por la paleta (Día y Noche). Mapa apagado a propósito: el único color saturado es el de las líneas. Se quitan los comercios y servicios (ensucian y necesitan un sprite aparte). Al cambiar el tema se reemplaza el estilo completo.
- **Tipografías del mapa:** Noto Sans (de `protomaps/basemaps-assets`) alojada en `public/mapa/fuentes`, solo los rangos 0 a 511 (latín).

## M4-2. El archivo PMTiles lo sirve Laravel (en desarrollo)

- **Problema:** PMTiles lee pedacitos con pedidos de rango HTTP y el servidor de desarrollo de PHP los ignora (devuelve el archivo entero).
- **Se eligió:** `TeselasController` sirve `resources/mapa/parana.pmtiles` con `BinaryFileResponse`, que entiende rangos (206), sin sesión ni cookies para que se pueda cachear. En producción conviene que lo sirva directamente nginx o Apache. El archivo pesa 1,8 MB y va en el repositorio; `scripts/descargar-mapa.sh` lo regenera.

## M4-3. Medición

- **Primer colectivo visible:** 3,75 s con el servidor de desarrollo de Vite (sin optimizar, 1.er arranque). Criterio: menos de 5 s. Se repite con la compilación de producción en M11.
- **Cuadros:** 58 a 60 por segundo con 40 colectivos, la línea elegida y el mapa en movimiento.

## Cierre de la etapa 1: auditoría de movimiento (skill improve-animations)

Se revisó todo el movimiento de la etapa 1 contra el catálogo de la skill. Lo que estaba bien: solo `transform` y `opacity` (más el trazo de los recorridos), ningún `ease-in`, ningún `scale(0)`, `prefers-reduced-motion` cubierto. Lo que se corrigió:

1. **Curvas sin token:** la misma curva escrita a mano en 7 lugares. Ahora hay `--ease-out` y `--ease-in-out` en CSS y `resources/js/movimiento.js` en JavaScript (`cubic-bezier(0.23, 1, 0.32, 1)` y `cubic-bezier(0.77, 0, 0.175, 1)`).
2. **Duraciones largas:** pulso de la parada 700 a 450 ms, dibujado del recorrido 700 a 450 ms, cámara hacia una parada 600 a 450 ms, transición de tema 520 a 450 ms.
3. **Cambios que aparecían de golpe:** los paneles de parada y colectivo entran con `opacity` y un desplazamiento de 8 px en 200 ms y salen en 150 ms; la pantalla de carga se desvanece. Con reduced-motion se quita el desplazamiento.
4. **Respuesta al apretar:** todos los botones se achican a 0,97 mientras se aprietan (120 ms). Los colectivos y las paradas del mapa quedan afuera porque su `transform` lo escribe el mapa en cada cuadro.
5. **Reduced-motion en vivo:** si se cambia la preferencia con el mapa abierto, el interpolador pasa a saltar o a deslizarse sin recargar.
6. **Código muerto:** se borró la vista de bienvenida de Laravel, que traía `transition-all` y animaciones de 750 ms.

Además, probando a mano se encontró y corrigió un defecto de interacción: el cartel de una parada tapaba el clic del colectivo detenido en ella. Ahora los colectivos van por encima.

Pendiente de sentir en pantalla (no se puede juzgar solo leyendo el código): la transición circular de tema sobre el lienzo del mapa, y el ritmo de los pulsos cuando varios colectivos llegan juntos.

## M5-1. Estimación de llegada en PHP puro, probada contra el simulador

- **Problema:** decir cuántos segundos faltan para que llegue un colectivo a una parada, de forma creíble y que se pueda probar.
- **Se eligió:** `App\Estimaciones\Estimador`, PHP puro. Suma el camino que le falta (a la **velocidad media de los últimos ~60 s**, no a una fija), la parada promedio (16,5 s) por cada parada del medio, lo que le queda de espera si está detenido, y si la parada quedó atrás o está en la otra mano, la vuelta completa con las esperas en las terminales (105 s promedio). `ServicioSimulacion` guarda esa velocidad media en `posiciones.velocidad_media_ms` (promedio móvil que solo cuenta mientras el colectivo se mueve).
- **Casos borde resueltos de forma explícita y con test:** ya está en la parada (0 s), recién pasó (vuelta completa), viene por el ramal contrario, espera en la terminal, está parado hace rato (piso de 1,5 m/s para no dividir por casi cero), tiene una falla (se suman 5 minutos típicos de arreglo y la cuenta se marca como incierta), y es el último del día (se descartan las llegadas posteriores al fin del servicio de la línea).
- **Se oculta lo que no sirve:** un colectivo que termina su recorrido en esa parada no se anuncia ahí (nadie se sube).
- **Cómo se sabe qué tan bien estima:** la prueba compara la estimación con lo que después hace el simulador de verdad (60 semillas, 6 colectivos cada una). Error mediano medido: **6,4 %**. Con un desvío en el camino, el test exige menos de 25 %.
- **Se descartó:** estimar con una velocidad fija de línea (no refleja demoras) y con el último dato instantáneo (oscila con cada parada).

## M5-2. Rótulo luminoso en el navegador

- **Se eligió:** el panel de la parada pide `/api/paradas/{id}/llegadas` cada 6 s y entre pedidos la cuenta baja sola, segundo a segundo, en el navegador. Los dígitos de Doto parpadean al cambiar el minuto y con menos de 60 s dice "Llegando". El lector de pantalla recibe un anuncio que solo cambia cuando cambia el minuto (no cada segundo).

## M6-1. Panel de operador: acceso, permisos y datos

- **Se eligió:** inicio de sesión de Laravel hecho a mano (no Breeze), con rol `operador` en `users.rol` y un permiso `operar` (`Gate`). Las rutas del panel exigen `auth` y `can:operar`; una cuenta sin rol recibe 403, quien no ingresó va al ingreso (o recibe 401 si pide JSON). Límite de 5 intentos por minuto. Un mismo mensaje para "contraseña mala" y "no es operador", para no revelar qué cuentas existen.
- **Cuenta de demostración:** `operador@ramal.test`, con la contraseña de `OperadorSeeder` (o `RAMAL_OPERADOR_PASSWORD`). Se muestra en la pantalla de ingreso solo si `RAMAL_MOSTRAR_CUENTA_DEMO` está en `true`. **Un test descubrió que, con la cuenta oculta, la contraseña seguía escrita en el código de la página** (dentro de la función "Completar"); ahora esa función no existe cuando la cuenta está oculta.
- **"Del día" es desde la medianoche de Paraná**, no del servidor (UTC): hay un test que cuenta un incidente a las 00:10 de Paraná (03:10 UTC) en el día correcto y uno a las 23:50 en el día anterior.
- **`ramal:rellenar-dia`:** corre el mismo simulador a alta velocidad sobre las horas de hoy anteriores al arranque, para que el panel tenga un día completo. Los incidentes quedan marcados con `origen = relleno` y el comando se puede rehacer con `--rehacer`.

## M6-2. Gráficos del día en SVG propio

- **Problema:** Alpine no puede repetir elementos (`x-for`) dentro de un `<svg>`.
- **Se eligió:** el SVG se arma como texto en JavaScript y se inserta con `x-html`. Esto además permite que las barras crezcan **una sola vez**: las clases de animación solo se agregan en el primer dibujado, y cuando llegan datos nuevos se reemplaza el SVG sin animación.
- **Reglas de la skill dataviz aplicadas:** marcas finas (14 px), extremo redondeado de 4 px sobre la base, rejilla recesiva, un tooltip por marca, vista de tabla de cada gráfico, y el color de la línea siempre acompañado de su número. Validación de la paleta de líneas: en Día pasa (la separación para daltónicos queda en la franja 6 a 8, permitida porque cada línea lleva su número); en Noche la luminosidad de los cinco colores queda apenas por encima de la franja de la skill, consecuencia de la paleta fijada por Román, que prioriza el contraste con Asfalto (5,9 a 7,5:1). No se tocó la paleta.

## M7-1. Desvíos: caminos alternativos reales

- **Problema:** un desvío tiene que cambiar de verdad por dónde va el colectivo, pero la app no puede consultar rutas mientras funciona.
- **Se eligió:** `scripts/calcular-desvios.mjs` calcula una sola vez, para cada tramo entre paradas consecutivas, un camino alternativo con OSRM. OSRM casi nunca ofrece alternativas en una ciudad de calles en cuadrícula (solo 2 de 42 tramos), así que se fuerza el desvío pasando por un punto lateral a 350 y 600 m del tramo, de un lado y del otro, y se queda con el válido más corto (entre 1,15 y 2,5 veces más largo, y con menos de la mitad de sus puntos a menos de 25 m del tramo normal). Resultado: **40 de 42 tramos** tienen desvío. Se guardan en `database/datos/desvios.json` y `DesviosSeeder` los carga a la tabla `desvios`.
- **Cómo lo recorre el simulador:** el colectivo sigue avanzando por la "distancia virtual" del ramal normal (la que usan las paradas y las estimaciones), pero su posición sale del camino alternativo. Como el alternativo es más largo, cada metro real vale menos metros virtuales (`RutaSimulada::escalaDesvio`): tarda más de una parada a la otra, a 0,85 de su velocidad, y la distancia virtual nunca retrocede. Sale de una parada y vuelve al recorrido en la siguiente; cuando llega, el desvío se cierra solo.
- **Estimaciones:** antes de entrar al camino alternativo se suma el tiempo de más; adentro no hace falta, porque la velocidad media ya se mide en metros del recorrido.
- **Estado nuevo:** `fuera_de_recorrido`, con su ícono propio. En el mapa público los desvíos en curso se dibujan como una tira punteada amarilla con borde de tinta, y el colectivo lleva una insignia.
- **Límite conocido:** OSRM usa el perfil de auto. Los desvíos cruzan calles que un colectivo real quizás no podría usar.

## M7-2. Ciclo de vida del incidente y órdenes del operador

- **Estados:** `activo` (se generó), `atendido` (un operador lo tomó; queda registrado quién y cuándo) y `resuelto` (por el paso del tiempo, por el operador, o porque el colectivo volvió al recorrido). Se guarda `resuelto_por`.
- **Acciones:** *Atender* acorta una falla a 2 minutos como mucho y una demora a 1; *Resolver* la termina ya. Un desvío se puede atender pero no resolver a mano: el colectivo está en la calle y se cierra solo al volver al recorrido.
- **Quién escribe qué:** solo el simulador mueve a los colectivos. El operador no toca las posiciones: anota la orden en el incidente (`accion_pedida`, `atendido`) y el simulador la aplica en su siguiente tick, a los pocos segundos. Evita que dos procesos pisen la misma fila.
- **Se descartó:** que el operador modifique directamente la posición del colectivo (competiría con el simulador) y poder cancelar un desvío en marcha.

## Cierre de la etapa 2

- 161 tests de PHP contra MySQL 8.4 y 28 de JavaScript.
- Pasada de uniformidad estética (pedido de Román): el ingreso de operador, el centro de control, el tablero del mapa público y la página de líneas comparten ahora el mismo lenguaje (tablero luminoso en Doto sobre trama de calles, doble línea amarilla, estados con ícono y forma, entrada escalonada, respuesta al apretar).

## M8-1. Historial en formato binario compacto

- **Problema:** rebobinar el día exige guardar dónde estaba cada colectivo, y 40 colectivos cada pocos segundos son miles de filas por hora.
- **Se eligió:** una fila por **foto** (no por colectivo) en `historial_posiciones`, con un campo binario de 15 bytes por colectivo (`Instantanea`): id y ramal en 16 bits, longitud y latitud en enteros de 32 bits por 10⁶ (11 cm de resolución), rumbo en 16 bits y estado en 8. Una foto de 40 colectivos pesa 600 bytes; un día completo (cada 10 s) queda alrededor de 5 MB.
- **Cada 10 segundos por reloj, no por tick:** si se cambiara la velocidad del simulador el historial no se estiraría ni se aplastaría.
- **Retención:** 48 horas (`RAMAL_HISTORIAL_RETENCION`), con limpieza horaria (`ramal:limpiar-historial`) y test de que la base no crece sin límite.
- **Se descartó:** una fila por colectivo y foto (40 veces más filas), guardar JSON (7 veces más pesado) y guardar solo cambios (complica la lectura de cualquier instante).

## M8-2. Cómo se rebobina en el navegador

- **API:** `/api/historial/rango` dice qué hay guardado; `/api/historial?desde=&hasta=` devuelve ventanas de hasta 15 minutos. Lo ya pasado se cachea 5 minutos; lo reciente, 2 segundos.
- **El cliente pide ventanas de 10 minutos** y adelanta la siguiente cuando se acerca al borde o se va rápido. El `Reproductor` interpola entre dos fotos como el modo en vivo, así que arrastrar la línea hacia atrás, hacia adelante o reproducir a 60× mueve a los colectivos de forma continua. Si cambia el ramal o hay un salto de más de 400 m, el colectivo se corta ahí en vez de cruzar el mapa; los huecos de más de 2,5 pasos no se rellenan (se avisa "No se guardó registro de este momento").
- **Mientras se mira el pasado** el tiempo real queda en pausa, los desvíos de ahora se ocultan y las llegadas dicen que son solo en vivo. "Volver al vivo" pide la foto actual y retoma el tiempo real.
- **Se descartó:** mostrar las llegadas del pasado (habría que guardar las estimaciones también) y bajar el día completo de una vez.
- **`ramal:rellenar-dia` ahora también rehace el historial** del día (corre el simulador a alta velocidad desde las 05:30, tarda unos 17 minutos para el día completo con el paso de 10 s). Se niega a correr si el simulador en vivo escribió en los últimos 15 s.

## M9-1. API pública v1 con tokens de Sanctum

- **Problema:** exponer los datos a terceros sin abrir el servidor a cualquiera y sin congelar las rutas internas del mapa.
- **Se eligió:** un prefijo `/api/v1` aparte de las rutas internas (`/api/mapa`, `/api/posiciones`...), que el sitio sigue usando y pueden cambiar. Todas las consultas de datos piden un token de Sanctum con permiso `leer`; solo `/api/v1/estado` (¿anda la simulación?) es abierta. Los operadores crean sus tokens en el panel (`/operador/api`): el valor completo se muestra una sola vez, en la base queda su hash, hay un máximo de 5 por operador y se pueden revocar.
- **Cupo por token, no por IP:** 60 pedidos por minuto (`RAMAL_API_LIMITE`). Detrás de una red compartida (un colegio, una oficina) el límite por IP castigaría a todos por lo que haga uno.
- **Errores en español y con una sola forma** (`{ "mensaje": "..." }`) para 401, 403, 404 y 429. Los 422 de validación mantienen la forma de Laravel (`message` y `errors`), porque las bibliotecas la conocen.
- **Se descartó:** tokens para todo visitante sin ingresar (no hay cómo limitarlos de forma justa), y versionar con cabeceras (en la ruta se ve y se prueba más fácil).
- **Un test comprueba que no se desincronice:** compara las rutas de `/api/v1` registradas en Laravel con las del documento OpenAPI, en las dos direcciones, y qué rutas piden token.
- **Un hallazgo del test:** en Laravel 13 el error de un token sin permiso llega al manejador de errores ya convertido en `AccessDeniedHttpException`, así que el mensaje propio hay que registrarlo para esa clase y no para la de Sanctum.

## M9-2. OpenAPI como fuente de la documentación

- **Se eligió:** escribir `resources/api/openapi.yaml` a mano (OpenAPI 3.1) y dibujar la página `/api` desde ese mismo archivo, con un lector propio (`App\Api\Documentacion`) que resuelve las referencias, arma las tablas de parámetros y respuestas y muestra los ejemplos. Así hay una sola verdad: lo que se lee en la página es lo que descargan las herramientas (`/api/openapi.json`).
- **Probar de verdad:** cada consulta tiene un botón que pide a esta misma API con el token que se pegó arriba (se guarda solo en el navegador) y muestra el estado, el tiempo y los pedidos que quedan. Los ejemplos de respuesta son datos reales recortados.
- **Se descartó:** Swagger UI o Redoc (son librerías de terceros que no respetan la identidad visual del sitio) y generar el documento desde anotaciones en el código (queda más lejos de lo que lee el usuario).
- **Dependencia nueva:** `symfony/yaml`, para leer el YAML.

## M9-3. Feed GTFS Realtime hecho a mano

- **Se eligió:** `/api/v1/gtfs-rt/posiciones` devuelve un `FeedMessage` con una `VehiclePosition` por colectivo, escrito en protobuf con un escritor propio de 40 líneas (`App\Api\GtfsRealtime\Protobuf`: enteros de largo variable, decimales de 32 y 64 bits y bloques con largo). Es lo que leen las aplicaciones de transporte, y evita instalar la biblioteca de Google para cuatro tipos de campo.
- **Tests:** el escritor se prueba con los ejemplos oficiales del formato (300 es `AC 02`, el campo 1 con 150 es `08 96 01`) y el feed completo se vuelve a leer con un lector escrito solo en los tests; el mismo contenido sale en JSON con `?formato=json` y se comprueba que coinciden.
- **Cómo se mapea:** `route_id` es el número de línea; `direction_id` es 0 en la ida y 1 en la vuelta; `vehicle.label` es el interno; si está detenido en una parada informa `STOPPED_AT` con esa parada, y si no `IN_TRANSIT_TO` con la próxima.
- **Límite conocido:** no hay un feed estático de GTFS que lo acompañe (`stops.txt`, `routes.txt`), así que otras aplicaciones no pueden cruzarlo con horarios. Los viajes no tienen identificador. Queda documentado como pendiente: generar el GTFS estático desde la base.

## M10. La página "Cómo funciona"

- **Problema:** el sitio dice que "se simula el mundo, no la tecnología", pero eso tiene que poder entenderse sin leer código.
- **Se eligió:** una página (`/como-funciona`) con cuatro piezas que se pueden tocar en vez de leer: (1) una columna "Inventado" y otra "De verdad" con ícono y palabra; (2) un colectivo de juguete que recorre un ramal, frena en las paradas y espera en las terminales; (3) una **calculadora de llegada** con los mismos pasos del `Estimador` (metros por velocidad media más paradas del camino, desvío y falla), con barras que muestran de dónde sale cada segundo; (4) el viaje de una posición (simulador, base, cola, Reverb, navegador) con un botón para cortar el WebSocket y ver el plan B, más una grilla de celdas para entender por qué el servidor manda solo lo que se ve.
- **Los números no se escriben a mano:** cantidad de líneas, colectivos, paradas, esperas del estimador, peso de una foto del historial y megabytes retenidos salen de las mismas constantes que usa el sistema (`Estimador`, `Instantanea`, `config/ramal.php`); hay tests que lo comprueban, incluido que 40 colectivos × 15 bytes × una foto cada 10 s durante 48 horas son 10,4 MB.
- **La calculadora tiene tests de JavaScript** que repiten el caso del estimador (1800 m a 20 km/h con una parada en el medio dan 5:41) y los bordes: menos de un minuto dice "Llegando", una falla suma 5 minutos, un desvío no suma nada si queda muy cerca.
- **Las barras no dependen del color:** cada parte tiene su textura (liso, rayas, puntos, franjas) además de su nombre y su duración.
- **Se descartó:** un video o una animación fija (no se puede tocar) y un diagrama de arquitectura de ingeniería (explica poco a quien no programa).
- **Dice lo que no funciona:** cierra con "Lo que conviene saber": los desvíos usan perfil de auto, la demo gratuita duerme, falta el GTFS estático y el error del 6,4 % se mide contra el simulador, no contra colectivos reales.

## M11. Calidad, rendimiento y publicación

- **Teclado en el mapa:** las paradas son botones y entran en el orden de Tab con su nombre y líneas. Los 40 colectivos no (40 paradas de Tab no ayudan a nadie): al elegir una línea, solo sus colectivos pasan a poder alcanzarse con Tab. Escape cierra lo último que se abrió (colectivo, después parada, después línea). Un párrafo oculto para lectores de pantalla explica esto. Las llegadas ya se anunciaban con una región `aria-live`.
- **Se midió con el build de producción y apareció un error que el modo de desarrollo escondía:** el mapa no cargaba su trabajador. Detalle y soluciones en `docs/rendimiento.md`, junto con LCP 1,47 s, CLS 0 y 60 cuadros por segundo con los 40 colectivos.
- **Se guardó `/api/mapa` en caché** (5 minutos): bajó el 95 % de las respuestas de 4,9 s a 1,5 s en la prueba de carga.
- **Integración continua:** además de Pint y los tests de PHP y JavaScript, ahora corre `composer audit` y construye la imagen de Docker, para que un despliegue roto se vea antes de publicar.
- **Despliegue:** un contenedor con Apache, el simulador y las tareas programadas (`Dockerfile`), y MySQL externo. Por defecto el navegador usa el plan B de consulta; el WebSocket se activa al compilar con `VITE_TIEMPO_REAL=websocket`. **No se pudo probar el contenedor en esta máquina** (no hay un motor de Docker); queda declarado en `docs/despliegue.md`.
- **Se descartó:** Laravel Octane o FrankenPHP (más rápidos, pero agregan piezas que el plan gratuito no justifica) y probar rendimiento con una herramienta externa (la prueba de carga propia alcanza para comparar antes y después).
- **Pendiente:** elegir el proveedor de hosting y de MySQL; correr el `Dockerfile` de verdad; GTFS estático; auditoría de animaciones con `improve-animations` y revisión de seguridad con `security-review`.

## M11-2. Navegación pensada para quien llega sin saber nada

- **Problema (lo marcó Román):** la barra tenía siete enlaces del mismo peso, mezclaba páginas con atajos internos ("Colores", "Tipografía"), usaba nombres de programador ("API", "Identidad", "Operador") y en el celular escondía dos destinos.
- **Se eligió:**
  - En la barra, solo lo que usa cualquier visitante: Mapa, Líneas y Cómo funciona.
  - "Para desarrolladores" como menú desplegable con "Datos abiertos (API)" e "Identidad visual".
  - La entrada de operadores como botón aparte ("Ingresar", y "Centro de control" cuando ya se ingresó).
  - La página actual se marca con un cartelito amarillo debajo del enlace (no depende del color: es una forma, y además lleva `aria-current`).
  - En el celular, un botón que dice "Menú" (y "Cerrar" al abrirse) con todos los destinos, cada uno con su ícono y una línea que explica adónde lleva.
  - Los atajos de cada página van en una fila propia, "En esta página".
- **Teclado:** el menú desplegable y el del celular se cierran con Escape o al tocar afuera, y los botones avisan su estado con `aria-expanded`.
- **Se descartó:** una barra lateral fija (le saca ancho al mapa) y una hamburguesa sin texto (no dice qué es).

## M11-3. Base que se rehace al despertar (Render gratis)

- **Problema:** el plan gratuito de Render no incluye MySQL (solo PostgreSQL, que expira a los 30 días) y su disco se borra cada vez que el servicio se duerme.
- **Idea de Román:** en vez de buscar una base externa, rehacerlo todo cada vez que arranque.
- **Se eligió:** SQLite adentro del contenedor, creada vacía en cada arranque, con migraciones, líneas, cuenta de operador y un relleno de los últimos 30 minutos (`ramal:rellenar-dia --ultimos=30`) antes de arrancar el simulador. Es coherente con cómo ya funciona el proyecto: la simulación es determinista, así que se puede reconstruir un tramo del día en vez de guardarlo.
- **Costó una sola migración:** una usaba `ALTER TABLE ... MODIFY ... ENUM`, propio de MySQL; ahora en SQLite usa una columna de texto. Con eso los 254 tests pasan con las dos bases, y la integración continua corre ambas.
- **Lo que se acepta perder:** tokens de la API, sesiones e historial viejo en cada despertar. Para una demo de portfolio es razonable; si no lo fuera, se vuelve a MySQL externo cambiando una variable.
- **Se descartó:** MySQL o MariaDB adentro del mismo contenedor (el plan gratuito tiene 512 MB de memoria y un servidor de base consume buena parte) y adaptar la app a PostgreSQL (expira a los 30 días, que era justo el problema).
- **Medido:** migrar, cargar y rehacer 30 minutos de historial tarda 25 segundos en esta máquina, y la web responde igual de rápido sobre SQLite. En Render, con mucha menos CPU, va a tardar más; no se pudo medir.

## M11-4. PHP 8.4 como mínimo

- **Problema:** el primer arranque del contenedor falló con "Composer detected issues in your platform": las dependencias de Symfony 8.1 (que trae Laravel 13) piden PHP 8.4.1 o más, pero `composer.json`, el `Dockerfile` y el CI decían 8.3.
- **Por qué no se vio antes:** la máquina de desarrollo corre PHP 8.5, y el contenedor y el CI nunca se habían construido de verdad.
- **Se eligió:** declarar `"php": "^8.4"` (así la restricción es la real y Composer avisa al instalar, no al ejecutar), y usar `php:8.4-apache` en el contenedor y 8.4 en la integración continua.
