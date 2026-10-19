# Buscador de Llantas Pro por Taxonomias

Plugin de WordPress para WooCommerce que permite filtrar productos por medidas de llanta o por vehiculo:

- `ancho_llanta`
- `perfil_llanta`
- `diametro_rin`
- `marca_vehiculo`
- `modelo_vehiculo`

## Requisitos

- WordPress
- WooCommerce activo

## Instalacion

1. Comprimir la carpeta `buscador_llantas` en un archivo ZIP.
2. Ir a `Plugins > Anadir nuevo > Subir plugin`.
3. Subir el ZIP e instalar.
4. Activar el plugin.

## Taxonomias que registra

El plugin registra cinco taxonomias no jerarquicas asociadas al post type `product`:

- Ancho Llanta
- Perfil Llanta
- Diametro Rin
- Marca de Vehiculo
- Modelo de Vehiculo

Estas taxonomias aparecen en la edicion del producto para que el administrador pueda asignar valores.

## Como cargar medidas

1. Ir a `Productos > Ancho Llanta` y crear valores como `185`, `195`, `205`.
2. Ir a `Productos > Perfil Llanta` y crear valores como `55`, `60`, `65`.
3. Ir a `Productos > Diametro Rin` y crear valores como `15`, `16`, `17`.
4. Editar cada producto y asignar un valor en cada taxonomia segun corresponda.

## Como cargar marca y modelo del vehiculo

1. Ir a `Productos > Marca de Vehiculo` y crear las marcas.
2. Ir a `Productos > Modelo de Vehiculo` y crear los modelos.
3. Editar cada producto de llanta y asignarle una marca y un modelo.
4. Al seleccionar una marca en el buscador, el selector de modelo mostrara los modelos asociados a productos de esa marca.

## Shortcode

Usar el shortcode:

```text
[buscador_llantas]
```

Esto renderiza la version normal del formulario, con tres selects y el enlace para limpiar filtros. Para insertar una version compacta en un espacio reducido, usar:

```text
[buscador_llantas tipo="compacto"]
```

La version compacta tiene dos pestañas independientes:

- **Por medida**: ancho, perfil y diametro.
- **Por vehiculo**: marca y modelo. Los modelos disponibles dependen de la marca elegida.

Cada pestaña envía solo sus propios filtros por GET a la pagina de tienda de WooCommerce. La version normal sigue mostrando unicamente la busqueda por medida.


## Como funciona el filtrado

- El plugin detecta los parametros en la URL con `pre_get_posts`.
- Si existen valores enviados, modifica la consulta principal de WooCommerce.
- La relacion entre taxonomias es `AND`.

Ejemplo:

- `ancho_llanta = 185`
- `perfil_llanta = 65`
- `diametro_rin = 15`

El resultado mostrara solo productos que cumplan simultaneamente las tres condiciones.

En la busqueda por vehiculo, el resultado mostrara productos que tengan asignados simultaneamente la marca y el modelo seleccionados.

## Compatibilidad con filtros activos

El plugin se integra con el widget clasico `Active Product Filters` de WooCommerce para mostrar los filtros activos en el sidebar y permitir quitarlos individualmente.

## Diseno visual

El shortcode incluye CSS integrado con estas caracteristicas:

- Barra horizontal limpia
- Bordes suaves
- Sombras sutiles
- Boton destacado
- Responsive en moviles

## Seguridad

- El formulario incluye nonce.
- El nonce se valida antes de aplicar el filtrado.

## Problemas comunes

### No aparecen opciones en los selects

No existen terminos creados en las taxonomias o no estan asociados a productos.

### No aparecen resultados

Los productos no tienen asignados los valores correctos o la combinacion buscada no existe.

### En la lista de productos del admin se ven columnas montadas

Desde la version `1.2.0` las columnas automaticas de estas taxonomias quedan desactivadas para evitar que la tabla del administrador se rompa visualmente.

## Configuracion de estilos

Desde la version `1.5.0` existe una pantalla en **Ajustes → Buscador Llantas** para configurar colores, tipografia, selectores, botones y pestanas (con selector de color). Los valores se guardan en la opcion `buscador_llantas_styles` y se aplican como variables CSS `--bl-*`. El boton **Restaurar valores por defecto** borra la configuracion guardada.

## Sincronizacion de categorias de WooCommerce

En **Ajustes → Buscador Llantas**, pulsar **Sincronizar categorias** para consultar:

```text
GET https://app.frenoseguro.com:7575/freno/gruposproductos?lote=1
```

El servidor debe devolver un arreglo JSON:

```json
[
  { "_id": "GPR0007", "description": "FRENOS" },
  { "_id": "GPR0011", "description": "LLANTAS" }
]
```

- `description` se guarda como nombre de la categoria de producto (`product_cat`).
- `_id` se guarda en el metadato de termino `_buscador_llantas_grupo_producto_id`, para actualizar la misma categoria aunque cambie su nombre.
- Si ya existe una categoria con el mismo nombre y sin identificador externo, se reutiliza.
- No se eliminan categorias ausentes en la respuesta ni se asignan categorias a productos.
- Solo se consulta `lote=1`; no se recorren otros lotes automaticamente.
- Se requiere WooCommerce activo y permisos de administrador y de gestion de categorias de productos.
- La accion esta protegida por nonce. Se notifican errores de conexion, HTTP, formato y guardado; no se desactiva la validacion TLS.
- Si un error ocurre durante el guardado, los cambios anteriores se conservan y se puede reintentar. Un conflicto de nombres con otro identificador se informa sin fusionar categorias.

La consulta ocurre unicamente al pulsar el boton en WordPress; llamar directamente a la URL del servidor no ejecuta el plugin.

### Pruebas del importador

Desde la raiz del repositorio:

```powershell
php .\tests\category-sync-test.php
```

Las [pruebas aisladas](../tests/category-sync-test.php) simulan las funciones de WordPress y la respuesta HTTP. Verifican importacion, reintentos sin duplicados, renombrado por identificador, conflictos, errores, permisos, nonce y redireccion. No sustituyen una prueba en staging con WooCommerce y el servidor real.

## Sincronizacion de imagenes por SKU

En **Ajustes → Buscador Llantas → Imagenes de productos por SKU**:

1. Configurar la carpeta relativa a `uploads`: **`frenoseguro_img/images-web`**. Corresponde a `/wp-content/uploads/frenoseguro_img/images-web/`; no incluir `21028.jpg` ni otro nombre de archivo.
2. Elegir **Desactivada**, **Cada hora**, **Cada 12 horas** o **Cada dia** y guardar. La sincronizacion automatica esta desactivada inicialmente; al habilitarla, la primera ejecucion se programa para dentro de un minuto.
3. Opcionalmente activar **Al guardar productos** para relacionar las imagenes tambien al crear o actualizar productos con SKU mediante WooCommerce.
4. Pulsar **Ejecutar ahora** para iniciar una sincronizacion manual, incluso con la automatica desactivada. Guardar primero los cambios de carpeta.

### Relacion de archivos y productos

- El nombre coincide exactamente con el SKU: `21028.jpg` se asigna como principal al SKU `21028`. `021030.jpg` corresponde a `021030`, no a `21030`.
- `021030-1.jpg`, `021030-2.png`, etc. se agregan a la galeria en orden numerico. Si el nombre completo corresponde a otro SKU, se reserva para ese otro producto.
- Se admiten JPG/JPEG, PNG, WebP, GIF y AVIF, sujetos al soporte de imagenes de WordPress y del servidor.
- La principal se actualiza solo si existe el archivo correspondiente. La galeria conserva sus imagenes anteriores y agrega las nuevas sin repetir adjuntos.
- Se reutilizan los adjuntos de la biblioteca por ruta. Los archivos se registran desde la carpeta local, sin descargarlos ni copiarlos; no mover ni eliminar los originales.
- Si se reemplaza el contenido de un archivo en la misma ruta, se regeneran sus metadatos al volver a sincronizar.
- No se crean productos ni se modifican precios, inventario o categorias. La sincronizacion del catalogo procesa productos, no variaciones individuales.
- Solo se leen archivos directamente dentro de la carpeta configurada. La carpeta debe existir, ser legible por PHP y estar dentro de `uploads`.

### Ejecucion y seguimiento

La ejecucion manual procesa los primeros 10 productos inmediatamente y continua por lotes con **WP-Cron**. Recargar los ajustes para consultar estado, productos revisados y productos con relaciones actualizadas. El contador de productos actualizados no incluye archivos cuyo contenido cambio sin cambiar la relacion.

WP-Cron depende de visitas al sitio y no garantiza una hora exacta. Para ejecuciones regulares en sitios sin trafico, configurar un cron del servidor que ejecute los eventos pendientes de WordPress. Desactivar la automatizacion detiene las futuras ejecuciones periodicas; un lote ya iniciado continua hasta terminar. Desactivar el plugin cancela ambos eventos; al reactivarlo se restaura la frecuencia guardada.

Los errores se muestran en ajustes y se registran en **WooCommerce → Estado → Registros**, fuente `buscador-llantas-images`. Los cambios completados antes de un error se conservan; se puede reintentar sin duplicar adjuntos. No se omiten silenciosamente archivos inaccesibles o multiples archivos principales para el mismo SKU.

Las acciones manuales requieren permisos de administrador, editar productos y subir archivos, y estan protegidas por nonce.

### Pruebas aisladas

```powershell
php .\tests\image-sync-test.php
php .\tests\category-sync-test.php
```

Las [pruebas de imagenes](../tests/image-sync-test.php) usan archivos PNG temporales y dobles de WordPress/WooCommerce. No sustituyen la prueba en staging de generacion de miniaturas, guardado real de productos, permisos de archivos y ejecucion de WP-Cron.

## Git y releases

### Inicializacion local

Desde la carpeta del proyecto:

```powershell
git init
git add .
git commit -m "feat: plugin buscador llantas v1.3.0"
```

### Flujo recomendado de versionado

1. Actualizar la version en el archivo principal del plugin.
2. Regenerar `buscador_llantas.zip`.
3. Hacer commit.
4. Crear tag.
5. Publicar release en GitHub adjuntando `buscador_llantas.zip`.

Comandos:

```powershell
git add .
git commit -m "release: v1.3.0"
git tag v1.3.0
git push origin main --tags
```

### GitHub Actions incluido

El repositorio incluye el workflow [.github/workflows/release.yml](../.github/workflows/release.yml).

Funcionamiento:

1. Detecta cualquier push de tags `v*`.
2. Verifica que la version del plugin coincida con el tag.
3. Genera automaticamente `buscador_llantas.zip`.
4. Publica una GitHub Release y adjunta el ZIP.

Flujo recomendado:

```powershell
git add .
git commit -m "release: v1.3.0"
git tag v1.3.0
git push origin main
git push origin v1.3.0
```

## Actualizaciones automaticas desde GitHub

El plugin incluye un verificador de releases de GitHub para repos publicos y privados.

Configuracion minima en `wp-config.php`:

```php
define( 'BUSCADOR_LLANTAS_GITHUB_REPOSITORY', 'git@github.com:pulpoec/buscador_llantas.git' );
```

Para repos privados, agrega ademas un token con permisos de lectura sobre el repositorio:

```php
define( 'BUSCADOR_LLANTAS_GITHUB_TOKEN', 'tu_token_github' );
```

El token recomendado debe tener al menos permiso `repo:read` o acceso equivalente para leer releases y descargar assets.

Condiciones para que funcione correctamente:

1. Debe existir una release publicada.
2. La release debe incluir un asset llamado exactamente `buscador_llantas.zip`.
3. El tag de la release debe coincidir con la version, por ejemplo `v1.3.0`.

Notas:

- En repos publicos, el token no es obligatorio.
- En repos privados, el token si es necesario para consultar la release y descargar el ZIP desde WordPress.
- El updater soporta tanto URLs `https://github.com/...` como `git@github.com:...`.

## Mantenimiento

- Mantener consistencia en los nombres de los terminos.
- Probar cambios en staging antes de produccion.
- Publicar siempre releases versionadas.
