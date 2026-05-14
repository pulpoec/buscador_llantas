# Buscador de Llantas Pro por Taxonomias

Plugin de WordPress para WooCommerce que permite filtrar productos por tres taxonomias personalizadas:

- `ancho_llanta`
- `perfil_llanta`
- `diametro_rin`

## Requisitos

- WordPress
- WooCommerce activo

## Instalacion

1. Comprimir la carpeta `buscador_llantas` en un archivo ZIP.
2. Ir a `Plugins > Anadir nuevo > Subir plugin`.
3. Subir el ZIP e instalar.
4. Activar el plugin.

## Taxonomias que registra

El plugin registra tres taxonomias no jerarquicas asociadas al post type `product`:

- Ancho Llanta
- Perfil Llanta
- Diametro Rin

Estas taxonomias aparecen en la edicion del producto para que el administrador pueda asignar valores.

## Como cargar medidas

1. Ir a `Productos > Ancho Llanta` y crear valores como `185`, `195`, `205`.
2. Ir a `Productos > Perfil Llanta` y crear valores como `55`, `60`, `65`.
3. Ir a `Productos > Diametro Rin` y crear valores como `15`, `16`, `17`.
4. Editar cada producto y asignar un valor en cada taxonomia segun corresponda.

## Shortcode

Usar el shortcode:

```text
[buscador_llantas]
```

Esto renderiza un formulario con tres selects:

- Ancho
- Perfil
- Diametro

El formulario se envia por GET a la pagina de tienda de WooCommerce.

## Como funciona el filtrado

- El plugin detecta los parametros en la URL con `pre_get_posts`.
- Si existen valores enviados, modifica la consulta principal de WooCommerce.
- La relacion entre taxonomias es `AND`.

Ejemplo:

- `ancho_llanta = 185`
- `perfil_llanta = 65`
- `diametro_rin = 15`

El resultado mostrara solo productos que cumplan simultaneamente las tres condiciones.

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
