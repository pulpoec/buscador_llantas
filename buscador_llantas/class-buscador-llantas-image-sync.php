<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Buscador_Llantas_Image_Sync {
	const SETTINGS = 'buscador_llantas_image_sync';
	const STATUS   = 'buscador_llantas_image_sync_status';
	const LOCK     = 'buscador_llantas_image_sync_lock';
	const CRON     = 'buscador_llantas_image_sync_start';
	const BATCH    = 'buscador_llantas_image_sync_batch';
	const HASH     = '_buscador_llantas_image_hash';
	const SOURCE   = '_buscador_llantas_image_source';

	private $processing = false;
	private $files = null;

	public function __construct() {
		add_action( 'admin_post_buscador_llantas_image_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_buscador_llantas_image_run', array( $this, 'run_now' ) );
		add_action( self::CRON, array( $this, 'start' ) );
		add_action( self::BATCH, array( $this, 'process_batch' ) );
		add_action( 'init', array( $this, 'ensure_schedule' ) );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'before_product_save' ) );
		register_deactivation_hook( __DIR__ . '/buscador-llantas.php', array( $this, 'deactivate' ) );
	}

	public function get_settings() {
		return wp_parse_args(
			get_option( self::SETTINGS, array() ),
			array( 'folder' => 'frenoseguro_img/images-web', 'frequency' => '', 'on_save' => false )
		);
	}

	private function frequencies() {
		return array(
			''           => __( 'Desactivada', 'buscador-llantas' ),
			'hourly'     => __( 'Cada hora', 'buscador-llantas' ),
			'twicedaily' => __( 'Cada 12 horas', 'buscador-llantas' ),
			'daily'      => __( 'Cada dia', 'buscador-llantas' ),
		);
	}

	public function ensure_schedule() {
		$frequency = $this->get_settings()['frequency'];
		if ( '' !== $frequency && ! wp_next_scheduled( self::CRON ) ) {
			$result = wp_schedule_event( time() + 60, $frequency, self::CRON, array(), true );
			if ( is_wp_error( $result ) || ! $result ) {
				$this->log_save_error( new WP_Error( 'schedule_failed', __( 'No se pudo programar la sincronizacion automatica de imagenes. Revisa WP-Cron y guarda otra vez la configuracion.', 'buscador-llantas' ) ) );
			}
		}
	}

	private function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_products' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'buscador-llantas' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private function redirect() {
		wp_safe_redirect( add_query_arg( 'page', 'buscador-llantas-estilos', admin_url( 'options-general.php' ) ) );
		exit;
	}

	private function fail( $error ) {
		wp_die( esc_html( $error->get_error_message() ), '', array( 'response' => 500, 'back_link' => true ) );
	}

	public function save_settings() {
		$this->authorize( 'buscador_llantas_image_settings' );
		$folder = isset( $_POST['image_folder'] ) && is_string( $_POST['image_folder'] ) ? trim( wp_unslash( $_POST['image_folder'] ) ) : '';
		$frequency = isset( $_POST['image_frequency'] ) && is_string( $_POST['image_frequency'] ) ? wp_unslash( $_POST['image_frequency'] ) : '';
		$uploads = wp_upload_dir();
		// Acepta tambien la ruta web pegada por el administrador, sin el nombre de archivo.
		$prefix = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/';
		if ( 0 === strpos( $folder, $prefix ) ) {
			$folder = substr( $folder, strlen( $prefix ) );
		}
		if ( ! array_key_exists( $frequency, $this->frequencies() ) ) {
			$this->fail( new WP_Error( 'invalid_frequency', __( 'Selecciona una frecuencia valida.', 'buscador-llantas' ) ) );
		}
		$directory = $this->resolve_directory( $folder );
		if ( is_wp_error( $directory ) ) {
			$this->fail( $directory );
		}
		if ( get_option( self::LOCK ) || 'running' === ( get_option( self::STATUS, array() )['state'] ?? '' ) ) {
			$this->fail( new WP_Error( 'images_running', __( 'Espera a que termine la sincronizacion antes de cambiar la configuracion.', 'buscador-llantas' ) ) );
		}
		$settings = array( 'folder' => $folder, 'frequency' => $frequency, 'on_save' => isset( $_POST['image_on_save'] ) );
		if ( false === update_option( self::SETTINGS, $settings, false ) && get_option( self::SETTINGS ) !== $settings ) {
			$this->fail( new WP_Error( 'image_settings_failed', __( 'No se pudo guardar la configuracion de imagenes.', 'buscador-llantas' ) ) );
		}
		$this->files = null;
		wp_clear_scheduled_hook( self::CRON );
		if ( '' !== $frequency ) {
			$scheduled = wp_schedule_event( time() + 60, $frequency, self::CRON, array(), true );
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				$this->record_error( is_wp_error( $scheduled ) ? $scheduled : new WP_Error( 'schedule_failed', __( 'No se pudo programar la sincronizacion.', 'buscador-llantas' ) ) );
				$this->fail( new WP_Error( 'schedule_failed', __( 'La configuracion se guardo, pero no se pudo programar la sincronizacion. Vuelve a guardar los ajustes.', 'buscador-llantas' ) ) );
			}
		}
		$this->redirect();
	}

	public function run_now() {
		$this->authorize( 'buscador_llantas_image_run' );
		$result = $this->start();
		if ( is_wp_error( $result ) ) {
			$this->fail( $result );
		}
		$this->redirect();
	}

	public function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		wp_clear_scheduled_hook( self::BATCH );
		delete_option( self::LOCK );
		$status = get_option( self::STATUS, array() );
		if ( 'running' === ( $status['state'] ?? '' ) ) {
			$status['state'] = 'interrupted';
			$status['message'] = __( 'Sincronizacion interrumpida al desactivar el plugin.', 'buscador-llantas' );
			update_option( self::STATUS, $status, false );
		}
	}

	private function resolve_directory( $folder ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'uploads_error', $uploads['error'] );
		}
		if ( '' === $folder || preg_match( '#[\x00-\x1f]|(^[/\\\\]|:|(^|[/\\\\])\.\.([/\\\\]|$))#', $folder ) ) {
			return new WP_Error( 'invalid_image_folder', __( 'Indica una subcarpeta relativa a uploads, sin .. ni URL externas.', 'buscador-llantas' ) );
		}
		$root = realpath( $uploads['basedir'] );
		$directory = realpath( $uploads['basedir'] . '/' . $folder );
		if ( false === $root || false === $directory || ! is_dir( $directory ) || ! is_readable( $directory ) ||
			0 !== strpos( wp_normalize_path( $directory ) . '/', wp_normalize_path( $root ) . '/' ) ) {
			return new WP_Error( 'image_folder_unavailable', __( 'La carpeta de imagenes no existe, no es legible o esta fuera de uploads.', 'buscador-llantas' ) );
		}
		return $directory;
	}

	private function load_files() {
		if ( null !== $this->files ) {
			return $this->files;
		}
		$directory = $this->resolve_directory( $this->get_settings()['folder'] );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$entries = scandir( $directory );
		if ( false === $entries ) {
			return new WP_Error( 'image_folder_read_failed', __( 'No se pudo leer la carpeta de imagenes.', 'buscador-llantas' ) );
		}
		$files = array();
		foreach ( $entries as $entry ) {
			if ( ! preg_match( '/\.(jpe?g|png|webp|gif|avif)$/i', $entry ) ) {
				continue;
			}
			$path = realpath( $directory . '/' . $entry );
			if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) || dirname( $path ) !== $directory ) {
				return new WP_Error( 'image_file_unavailable', sprintf( __( 'Imagen no accesible o enlace fuera de la carpeta: %s.', 'buscador-llantas' ), $entry ) );
			}
			$files[ $entry ] = $path;
		}
		$this->files = $files;
		return $files;
	}

	private function attachment( $path, $product_id ) {
		$path = wp_normalize_path( $path );
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$type = wp_check_filetype_and_ext( $path, basename( $path ) );
		if ( empty( $type['type'] ) || 0 !== strpos( $type['type'], 'image/' ) ) {
			return new WP_Error( 'invalid_image', sprintf( __( 'Formato de imagen no valido: %s.', 'buscador-llantas' ), basename( $path ) ) );
		}
		$relative = _wp_relative_upload_path( $path );
		$ids = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => 1,
			'meta_query' => array(
				'relation' => 'OR',
				array( 'key' => '_wp_attached_file', 'value' => $relative ),
				array( 'key' => self::SOURCE, 'value' => $relative ),
			),
		) );
		$id = $ids ? (int) $ids[0] : 0;
		if ( ! $id ) {
			$id = wp_insert_attachment( array(
				'post_mime_type' => $type['type'], 'post_title' => pathinfo( $path, PATHINFO_FILENAME ),
				'post_status' => 'inherit', 'post_parent' => $product_id,
			), $path, $product_id, true );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}
		if ( $relative !== get_post_meta( $id, self::SOURCE, true ) &&
			false === update_post_meta( $id, self::SOURCE, wp_slash( $relative ) ) ) {
			return new WP_Error( 'image_source_save_failed', __( 'No se pudo guardar la ruta original de la imagen.', 'buscador-llantas' ) );
		}
		$hash = hash_file( 'sha256', $path );
		if ( false === $hash ) {
			return new WP_Error( 'image_hash_failed', __( 'No se pudo leer el contenido de una imagen.', 'buscador-llantas' ) );
		}
		if ( $hash !== get_post_meta( $id, self::HASH, true ) ) {
			$metadata = wp_generate_attachment_metadata( $id, $path );
			if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
				return new WP_Error( 'image_metadata_failed', sprintf( __( 'No se pudieron generar los metadatos de %s.', 'buscador-llantas' ), basename( $path ) ) );
			}
			wp_update_attachment_metadata( $id, $metadata );
			if ( wp_get_attachment_metadata( $id ) !== $metadata ) {
				return new WP_Error( 'image_metadata_save_failed', __( 'No se pudieron guardar los metadatos de la imagen.', 'buscador-llantas' ) );
			}
			if ( false === update_post_meta( $id, self::HASH, $hash ) && $hash !== get_post_meta( $id, self::HASH, true ) ) {
				return new WP_Error( 'image_hash_save_failed', __( 'No se pudo registrar la version de la imagen.', 'buscador-llantas' ) );
			}
		}
		return (int) $id;
	}

	public function apply_images( $product ) {
		$sku = $product->get_sku();
		if ( '' === $sku ) {
			return false;
		}
		$files = $this->load_files();
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$primary = array();
		$gallery = array();
		foreach ( $files as $name => $path ) {
			$stem = pathinfo( $name, PATHINFO_FILENAME );
			if ( $stem === $sku ) {
				$primary[] = $path;
			} elseif ( preg_match( '/^' . preg_quote( $sku, '/' ) . '-[0-9]+$/D', $stem ) ) {
				// Un SKU con sufijo numerico propio tiene prioridad sobre una galeria.
				if ( ! wc_get_product_id_by_sku( $stem ) ) {
					$gallery[ $name ] = $path;
				}
			}
		}
		if ( count( $primary ) > 1 ) {
			return new WP_Error( 'ambiguous_primary_image', sprintf( __( 'Hay varias imagenes principales para el SKU %s. Conserva solo una extension.', 'buscador-llantas' ), $sku ) );
		}
		uksort( $gallery, 'strnatcasecmp' );
		$primary_id = 0;
		$gallery_ids = array();
		foreach ( array_merge( $primary, array_values( $gallery ) ) as $index => $path ) {
			$id = $this->attachment( $path, $product->get_id() );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			if ( $primary && 0 === $index ) {
				$primary_id = $id;
			} else {
				$gallery_ids[] = $id;
			}
		}
		$changed = false;
		if ( $primary_id && $primary_id !== (int) $product->get_image_id( 'edit' ) ) {
			$product->set_image_id( $primary_id );
			$changed = true;
		}
		$existing = $product->get_gallery_image_ids( 'edit' );
		$merged = array_values( array_unique( array_merge( $existing, $gallery_ids ) ) );
		if ( $merged !== $existing ) {
			$product->set_gallery_image_ids( $merged );
			$changed = true;
		}
		return $changed;
	}

	public function before_product_save( $product ) {
		if ( $this->processing || ! $this->get_settings()['on_save'] || $product->is_type( 'variation' ) ) {
			return;
		}
		if ( ! $this->acquire_lock() ) {
			$this->log_save_error( new WP_Error( 'images_locked', __( 'No se sincronizaron las imagenes al guardar: hay otro proceso activo. Se revisaran en la siguiente sincronizacion.', 'buscador-llantas' ) ) );
			return;
		}
		try {
			$result = $this->apply_images( $product );
			if ( is_wp_error( $result ) ) {
				$this->log_save_error( $result );
			}
		} finally {
			delete_option( self::LOCK );
		}
	}

	private function log_save_error( $error ) {
		update_option( 'buscador_llantas_image_save_error', $error->get_error_message(), false );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $error->get_error_message(), array( 'source' => 'buscador-llantas-images' ) );
		}
	}

	private function record_error( $error ) {
		$status = get_option( self::STATUS, array() );
		$status['state'] = 'error';
		$status['message'] = $error->get_error_message();
		$status['finished'] = current_time( 'mysql' );
		update_option( self::STATUS, $status, false );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $error->get_error_message(), array( 'source' => 'buscador-llantas-images' ) );
		}
		return $error;
	}

	private function acquire_lock() {
		$lock = get_option( self::LOCK );
		if ( $lock && (int) $lock < time() - 600 ) {
			delete_option( self::LOCK );
		}
		return add_option( self::LOCK, time(), '', false );
	}

	public function start() {
		if ( ! $this->acquire_lock() ) {
			return new WP_Error( 'images_locked', __( 'Otro proceso esta sincronizando imagenes.', 'buscador-llantas' ) );
		}
		try {
			$status = get_option( self::STATUS, array() );
			if ( 'running' === ( $status['state'] ?? '' ) && wp_next_scheduled( self::BATCH ) ) {
				return new WP_Error( 'images_running', __( 'Ya hay una sincronizacion en curso.', 'buscador-llantas' ) );
			}
			if ( ! function_exists( 'wc_get_product' ) ) {
				return $this->record_error( new WP_Error( 'woocommerce_missing', __( 'WooCommerce debe estar activo.', 'buscador-llantas' ) ) );
			}
			$this->files = null;
			$files = $this->load_files();
			if ( is_wp_error( $files ) ) {
				return $this->record_error( $files );
			}
			wp_clear_scheduled_hook( self::BATCH );
			delete_option( 'buscador_llantas_image_save_error' );
			update_option( self::STATUS, array(
				'state' => 'running', 'offset' => 0, 'processed' => 0, 'updated' => 0,
				'started' => current_time( 'mysql' ), 'message' => __( 'Sincronizacion en curso.', 'buscador-llantas' ),
			), false );
		} finally {
			delete_option( self::LOCK );
		}
		return $this->process_batch();
	}

	public function process_batch() {
		$status = get_option( self::STATUS, array() );
		if ( 'running' !== ( $status['state'] ?? '' ) ) {
			return true;
		}
		// Programa la continuacion antes del trabajo para poder reintentar tras un timeout.
		if ( ! wp_next_scheduled( self::BATCH ) ) {
			$scheduled = wp_schedule_single_event( time() + 60, self::BATCH, array(), true );
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				return $this->record_error( new WP_Error( 'batch_schedule_failed', __( 'No se pudo programar el siguiente lote. Ejecuta de nuevo la sincronizacion.', 'buscador-llantas' ) ) );
			}
		}
		if ( ! $this->acquire_lock() ) {
			return new WP_Error( 'images_locked', __( 'Otro proceso esta sincronizando imagenes.', 'buscador-llantas' ) );
		}
		$this->processing = true;
		try {
			$status = get_option( self::STATUS, array() );
			if ( 'running' !== ( $status['state'] ?? '' ) ) {
				return true;
			}
			$ids = get_posts( array(
				'post_type' => 'product', 'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'fields' => 'ids', 'posts_per_page' => 10, 'offset' => $status['offset'],
				'orderby' => 'ID', 'order' => 'ASC',
			) );
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					return $this->record_error( new WP_Error( 'product_missing', sprintf( __( 'No se pudo cargar el producto %d.', 'buscador-llantas' ), $id ) ) );
				}
				$changed = $this->apply_images( $product );
				if ( is_wp_error( $changed ) ) {
					return $this->record_error( $changed );
				}
				if ( $changed ) {
					if ( ! $product->save() ) {
						return $this->record_error( new WP_Error( 'product_save_failed', sprintf( __( 'No se pudieron guardar las imagenes del producto %d.', 'buscador-llantas' ), $id ) ) );
					}
					++$status['updated'];
				}
				++$status['processed'];
				++$status['offset'];
				update_option( self::STATUS, $status, false );
			}
			if ( count( $ids ) < 10 ) {
				wp_clear_scheduled_hook( self::BATCH );
				$status['state'] = 'completed';
				$status['finished'] = current_time( 'mysql' );
				$status['message'] = __( 'Sincronizacion completada.', 'buscador-llantas' );
			}
			update_option( self::STATUS, $status, false );
			return true;
		} catch ( WC_Data_Exception $error ) {
			return $this->record_error( new WP_Error( 'product_save_failed', $error->getMessage() ) );
		} finally {
			$this->processing = false;
			delete_option( self::LOCK );
		}
	}

	public function render_settings() {
		if ( ! current_user_can( 'edit_products' ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$settings = $this->get_settings();
		$status = get_option( self::STATUS, array() );
		$save_error = get_option( 'buscador_llantas_image_save_error', '' );
		?>
		<h2><?php esc_html_e( 'Imagenes de productos por SKU', 'buscador-llantas' ); ?></h2>
		<p><?php esc_html_e( 'SKU.jpg es la imagen principal; SKU-1.jpg, SKU-2.png, etc. se agregan a la galeria sin borrar las imagenes existentes. Se conservan los ceros iniciales del SKU.', 'buscador-llantas' ); ?></p>
		<?php if ( $save_error ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $save_error ); ?></p></div>
		<?php endif; ?>
		<?php if ( $status ) : ?>
			<div class="notice <?php echo 'error' === $status['state'] ? 'notice-error' : 'notice-info'; ?> inline"><p>
				<?php echo esc_html( $status['message'] ); ?>
				<?php echo esc_html( sprintf( __( 'Revisados: %1$d. Productos actualizados: %2$d.', 'buscador-llantas' ), $status['processed'] ?? 0, $status['updated'] ?? 0 ) ); ?>
				<?php echo esc_html( ( $status['finished'] ?? $status['started'] ?? '' ) ); ?>
			</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="buscador_llantas_image_settings" />
			<?php wp_nonce_field( 'buscador_llantas_image_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="bl-image-folder"><?php esc_html_e( 'Carpeta dentro de uploads', 'buscador-llantas' ); ?></label></th>
					<td><input class="regular-text" id="bl-image-folder" name="image_folder" value="<?php echo esc_attr( $settings['folder'] ); ?>" required />
						<p class="description"><?php esc_html_e( 'Ejemplo: frenoseguro_img/images-web. No incluyas el nombre de un archivo. Solo se leen archivos de esta carpeta, no subcarpetas.', 'buscador-llantas' ); ?></p></td></tr>
				<tr><th><label for="bl-image-frequency"><?php esc_html_e( 'Sincronizacion automatica', 'buscador-llantas' ); ?></label></th>
					<td><select id="bl-image-frequency" name="image_frequency">
						<?php foreach ( $this->frequencies() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['frequency'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th><?php esc_html_e( 'Al guardar productos', 'buscador-llantas' ); ?></th>
					<td><label><input type="checkbox" name="image_on_save" value="1" <?php checked( $settings['on_save'] ); ?> /> <?php esc_html_e( 'Relacionar imagenes tambien al crear o actualizar un producto con SKU.', 'buscador-llantas' ); ?></label></td></tr>
			</table>
			<?php submit_button( __( 'Guardar configuracion de imagenes', 'buscador-llantas' ), 'secondary' ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="buscador_llantas_image_run" />
			<?php wp_nonce_field( 'buscador_llantas_image_run' ); ?>
			<?php submit_button( __( 'Ejecutar ahora', 'buscador-llantas' ), 'secondary' ); ?>
		</form>
		<p class="description"><?php esc_html_e( 'La ejecucion manual comienza inmediatamente y continua por lotes con WP-Cron. La automatica esta desactivada inicialmente. WP-Cron necesita visitas al sitio o un cron del servidor. Recarga esta pagina para ver el avance. Los errores tambien se registran en WooCommerce > Estado > Registros (buscador-llantas-images).', 'buscador-llantas' ); ?></p>
		<?php
	}
}
