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
