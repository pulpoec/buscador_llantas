<?php
/**
 * Pruebas aisladas: php tests\image-sync-test.php
 * Usa archivos PNG temporales y dobles de las APIs de WordPress/WooCommerce.
 */
define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WC_Data_Exception extends Exception {}
class Admin_Denied extends RuntimeException {}
class Admin_Redirect extends RuntimeException {}
class Test_Product {
	private $id;
	private $sku;
	private $image = 0;
	private $gallery = array();
	public $saves = 0;
	public $fail_save = false;
	public function __construct( $id, $sku ) { $this->id = $id; $this->sku = $sku; }
	public function get_id() { return $this->id; }
	public function get_sku() { return $this->sku; }
	public function get_image_id( $context = 'view' ) { return $this->image; }
	public function set_image_id( $id ) { $this->image = $id; }
	public function get_gallery_image_ids( $context = 'view' ) { return $this->gallery; }
	public function set_gallery_image_ids( $ids ) { $this->gallery = $ids; }
	public function is_type( $type ) { return false; }
	public function save() {
		if ( $this->fail_save ) { throw new WC_Data_Exception( 'Product save failed' ); }
		++$this->saves;
		$GLOBALS['sync']->before_product_save( $this );
		return $this->id;
	}
}
function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['deactivation'] = $callback; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function selected( $value, $current ) { if ( $value === $current ) { echo ' selected'; } }
function checked( $value ) { if ( $value ) { echo ' checked'; } }
function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="test" />'; }
function submit_button( $text, $type ) { echo '<button>' . esc_html( $text ) . '</button>'; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['options'] ) ) { return false; }
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['root'], 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function wp_unslash( $text ) { return stripslashes( $text ); }
function wp_slash( $text ) { return addslashes( $text ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_time( $format ) { return '2026-10-19 10:00:00'; }
function wp_next_scheduled( $hook ) { return $GLOBALS['events'][ $hook ]['time'] ?? false; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['events'][ $hook ] ); return 1; }
function wp_schedule_event( $time, $frequency, $hook, $args = array(), $wp_error = false ) {
	if ( $GLOBALS['schedule_error'] ) { return new WP_Error( 'cron_error', 'Cron error' ); }
	$GLOBALS['events'][ $hook ] = array( 'time' => $time, 'frequency' => $frequency );
	return true;
}
function wp_schedule_single_event( $time, $hook, $args = array(), $wp_error = false ) { return wp_schedule_event( $time, '', $hook ); }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['capabilities'], true ); }
function check_admin_referer( $action ) {
	if ( ! $GLOBALS['nonce_valid'] ) { throw new Admin_Denied( 'Invalid nonce', 403 ); }
}
function wp_die( $message, $title = '', $args = array() ) { throw new Admin_Denied( $message, $args['response'] ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . urlencode( $key ) . '=' . urlencode( $value ); }
function wp_safe_redirect( $url ) { throw new Admin_Redirect( $url ); }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
function wc_get_product_id_by_sku( $sku ) {
	foreach ( $GLOBALS['products'] as $id => $product ) {
		if ( $sku === $product->get_sku() ) { return $id; }
	}
	return 0;
}
function wc_get_logger() {
	return new class {
		public function error( $message, $context ) { $GLOBALS['logs'][] = $message; }
	};
}
function get_posts( $args ) {
	if ( 'product' === $args['post_type'] ) {
		return array_slice( array_keys( $GLOBALS['products'] ), $args['offset'], $args['posts_per_page'] );
	}
	foreach ( $GLOBALS['attachments'] as $id => $attachment ) {
		foreach ( $args['meta_query'] as $query ) {
			if ( is_array( $query ) && ( $attachment['meta'][ $query['key'] ] ?? '' ) === $query['value'] ) {
				return array( $id );
			}
		}
	}
	return array();
}
function _wp_relative_upload_path( $path ) { return substr( wp_normalize_path( $path ), strlen( wp_normalize_path( $GLOBALS['root'] ) ) + 1 ); }
function wp_check_filetype_and_ext( $path, $name ) {
	$info = getimagesize( $path );
	return array( 'type' => $info ? $info['mime'] : false );
}
function wp_insert_attachment( $args, $path, $parent, $wp_error ) {
	if ( $GLOBALS['insert_error'] ) { return new WP_Error( 'insert_failed', 'Attachment insert failed' ); }
	$id = 100 + count( $GLOBALS['attachments'] );
	$GLOBALS['attachments'][ $id ] = array( 'meta' => array( '_wp_attached_file' => _wp_relative_upload_path( $path ) ), 'data' => $args );
	return $id;
}
function get_post_meta( $id, $key, $single ) { return $GLOBALS['attachments'][ $id ]['meta'][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) {
	if ( $GLOBALS['meta_error'] ) { return false; }
	$GLOBALS['attachments'][ $id ]['meta'][ $key ] = stripslashes( $value );
	return true;
}
function wp_generate_attachment_metadata( $id, $path ) {
	++$GLOBALS['metadata_calls'];
	if ( $GLOBALS['metadata_error'] ) { return array(); }
	if ( $GLOBALS['simulate_scaled'] ) {
		$GLOBALS['attachments'][ $id ]['meta']['_wp_attached_file'] = 'scaled-image.png';
	}
	return array( 'width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path( $path ) );
}
function wp_update_attachment_metadata( $id, $metadata ) { $GLOBALS['attachments'][ $id ]['metadata'] = $metadata; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['attachments'][ $id ]['metadata'] ?? false; }

require dirname( __DIR__ ) . '\buscador_llantas\class-buscador-llantas-image-sync.php';

$GLOBALS['root'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bl-image-test-' . bin2hex( random_bytes( 8 ) );
mkdir( $GLOBALS['root'] );
mkdir( $GLOBALS['root'] . DIRECTORY_SEPARATOR . 'images' );
$created_files = array();
function image_fixture( $filename ) {
	global $created_files;
	$path = $GLOBALS['root'] . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
	file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=' ) );
	$created_files[] = $path;
	return $path;
}
function reset_images() {
	global $created_files;
	foreach ( $created_files as $path ) { unlink( $path ); }
	$created_files = array();
	$GLOBALS['options'] = array( Buscador_Llantas_Image_Sync::SETTINGS => array( 'folder' => 'images', 'frequency' => '', 'on_save' => false ) );
	$GLOBALS['products'] = array();
	$GLOBALS['attachments'] = array();
	$GLOBALS['events'] = array();
	$GLOBALS['logs'] = array();
	$GLOBALS['metadata_calls'] = 0;
	$GLOBALS['schedule_error'] = false;
	$GLOBALS['insert_error'] = false;
	$GLOBALS['metadata_error'] = false;
	$GLOBALS['meta_error'] = false;
	$GLOBALS['simulate_scaled'] = false;
	$GLOBALS['capabilities'] = array( 'manage_options', 'edit_products', 'upload_files' );
	$GLOBALS['nonce_valid'] = true;
	$_POST = array();
	$GLOBALS['sync'] = new Buscador_Llantas_Image_Sync();
	return $GLOBALS['sync'];
}
function expect_image( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function expect_image_error( $result, $code ) {
	expect_image( is_wp_error( $result ) && $code === $result->get_error_code(), 'Expected ' . $code );
}
function save_image_settings( $sync ) {
	try { $sync->save_settings(); } catch ( Admin_Redirect $redirect ) { return; }
	throw new RuntimeException( 'Settings must redirect' );
}

$tests = array(
	'principal y galeria ordenada por SKU exacto con ceros iniciales' => function () {
		$sync = reset_images();
		image_fixture( '021030.png' );
		image_fixture( '021030-10.png' );
		image_fixture( '021030-2.PNG' );
		image_fixture( '21030.png' );
		$product = new Test_Product( 1, '021030' );
		$product->set_gallery_image_ids( array( 77 ) );
		$GLOBALS['products'][1] = $product;
		expect_image( true === $sync->start(), 'Import must succeed' );
		expect_image( 100 === $product->get_image_id(), 'Wrong primary image' );
		expect_image( array( 77, 101, 102 ) === $product->get_gallery_image_ids(), 'Gallery must preserve manual image' );
		expect_image( 'images/021030-2.PNG' === get_post_meta( 101, '_wp_attached_file', true ), 'Gallery must use natural numeric order' );
		expect_image( 1 === $product->saves, 'Product must persist' );
		expect_image( 'completed' === get_option( $sync::STATUS )['state'], 'Status must complete' );
	},
	'repetir no duplica adjuntos ni guarda productos sin cambios' => function () {
		$sync = reset_images();
		image_fixture( '21028.png' );
		$GLOBALS['products'][1] = new Test_Product( 1, '21028' );
		$sync->start();
		$sync->start();
		expect_image( 1 === count( $GLOBALS['attachments'] ), 'Attachment duplicated' );
		expect_image( 1 === $GLOBALS['products'][1]->saves, 'Unchanged product was saved' );
		expect_image( 1 === $GLOBALS['metadata_calls'], 'Unchanged image regenerated metadata' );
	},
	'reutiliza adjunto existente y origen original de imagen escalada' => function () {
		$sync = reset_images();
		$path = image_fixture( '21028.png' );
		wp_insert_attachment( array(), $path, 0, true );
		$GLOBALS['simulate_scaled'] = true;
		$GLOBALS['products'][1] = new Test_Product( 1, '21028' );
		$sync->start();
		$sync->start();
		expect_image( 1 === count( $GLOBALS['attachments'] ), 'Scaled original must not be imported twice' );
	},
	'actualiza contenido de archivo reemplazado sin cambiar adjunto' => function () {
		$sync = reset_images();
		$path = image_fixture( '21028.png' );
		$GLOBALS['products'][1] = new Test_Product( 1, '21028' );
		$sync->start();
		$old_hash = get_post_meta( 100, $sync::HASH, true );
		file_put_contents( $path, 'changed', FILE_APPEND );
		$sync->start();
		expect_image( 1 === count( $GLOBALS['attachments'] ), 'Replaced file must reuse attachment' );
		expect_image( 2 === $GLOBALS['metadata_calls'], 'Replaced file must regenerate metadata' );
		expect_image( $old_hash !== get_post_meta( 100, $sync::HASH, true ), 'Hash not updated' );
	},
	'ausencia de principal no borra imagen ni galeria' => function () {
		$sync = reset_images();
		image_fixture( 'ABC-1.png' );
		$product = new Test_Product( 1, 'ABC' );
		$product->set_image_id( 55 );
		$product->set_gallery_image_ids( array( 66 ) );
		expect_image( true === $sync->apply_images( $product ), 'Gallery must be added without a primary' );
		expect_image( 55 === $product->get_image_id(), 'Missing file removed existing image' );
		expect_image( array( 66, 100 ) === $product->get_gallery_image_ids(), 'Existing gallery removed' );
		expect_image( false === $sync->apply_images( new Test_Product( 2, '' ) ), 'Missing SKU must be skipped' );
	},
	'un SKU con guion no se asigna a otro producto como galeria' => function () {
		$sync = reset_images();
		image_fixture( 'ABC-1.png' );
		$GLOBALS['products'][1] = new Test_Product( 1, 'ABC' );
		$GLOBALS['products'][2] = new Test_Product( 2, 'ABC-1' );
		$sync->start();
		expect_image( array() === $GLOBALS['products'][1]->get_gallery_image_ids(), 'Another SKU was imported as gallery' );
		expect_image( 100 === $GLOBALS['products'][2]->get_image_id(), 'Exact hyphenated SKU not matched' );
	},
	'carpetas invalidas y traversal fallan sin escribir' => function () {
		foreach ( array( '../outside', '..\\outside', '/etc', 'https://example.test', 'missing', "images\0bad" ) as $folder ) {
			$sync = reset_images();
			$GLOBALS['options'][ $sync::SETTINGS ]['folder'] = $folder;
			expect_image( is_wp_error( $sync->start() ), 'Invalid folder accepted' );
			expect_image( array() === $GLOBALS['attachments'], 'Invalid folder caused writes' );
			expect_image( 'error' === get_option( $sync::STATUS )['state'], 'Folder error hidden' );
		}
	},
	'principal ambigua y errores de adjuntos son visibles y reintentables' => function () {
		$sync = reset_images();
		image_fixture( 'ABC.png' );
		image_fixture( 'ABC.jpg' );
		expect_image_error( $sync->apply_images( new Test_Product( 1, 'ABC' ) ), 'ambiguous_primary_image' );
		foreach ( array( 'insert_error' => 'insert_failed', 'metadata_error' => 'image_metadata_failed', 'meta_error' => 'image_source_save_failed' ) as $flag => $code ) {
			$sync = reset_images();
			image_fixture( 'ABC.png' );
			$GLOBALS[ $flag ] = true;
			$GLOBALS['products'][1] = new Test_Product( 1, 'ABC' );
			expect_image_error( $sync->start(), $code );
			expect_image( ! empty( $GLOBALS['logs'] ), 'Failure must be logged' );
			expect_image( ! get_option( $sync::LOCK ), 'Failure leaked lock' );
			$GLOBALS[ $flag ] = false;
			$sync->start();
			expect_image( 1 === count( $GLOBALS['attachments'] ), 'Retry duplicated attachment' );
		}
	},
	'once productos se procesan por lotes con progreso persistente' => function () {
		$sync = reset_images();
		for ( $id = 1; $id <= 11; ++$id ) { $GLOBALS['products'][ $id ] = new Test_Product( $id, 'SKU' . $id ); }
		$sync->start();
		expect_image( 10 === get_option( $sync::STATUS )['processed'], 'First batch must be bounded' );
		expect_image( wp_next_scheduled( $sync::BATCH ), 'Continuation was not scheduled' );
		expect_image_error( $sync->start(), 'images_running' );
		wp_clear_scheduled_hook( $sync::BATCH );
		$sync->process_batch();
		expect_image( 11 === get_option( $sync::STATUS )['processed'], 'Progress not persisted' );
		expect_image( 'completed' === get_option( $sync::STATUS )['state'], 'Final batch did not finish' );
		expect_image( ! wp_next_scheduled( $sync::BATCH ), 'Finished batch must not remain scheduled' );
	},
	'frecuencias configurables desactivar y restaurar tras reactivacion' => function () {
		foreach ( array( 'hourly', 'twicedaily', 'daily', '' ) as $frequency ) {
			$sync = reset_images();
			$_POST = array( 'image_folder' => '/wp-content/uploads/images', 'image_frequency' => $frequency, 'image_on_save' => '1' );
			save_image_settings( $sync );
			expect_image( 'images' === $sync->get_settings()['folder'], 'Uploads web prefix not normalized' );
			expect_image( $frequency === $sync->get_settings()['frequency'], 'Frequency not saved' );
			expect_image( '' === $frequency ? ! wp_next_scheduled( $sync::CRON ) : wp_next_scheduled( $sync::CRON ), 'Cron differs from selected setting' );
			$sync->deactivate();
			expect_image( ! wp_next_scheduled( $sync::CRON ) && ! wp_next_scheduled( $sync::BATCH ), 'Deactivation left events' );
			$sync->ensure_schedule();
			expect_image( '' === $frequency ? ! wp_next_scheduled( $sync::CRON ) : wp_next_scheduled( $sync::CRON ), 'Schedule not restored after reactivation' );
		}
	},
	'permisos y nonce protegen ejecutar ahora y ajustes' => function () {
		foreach ( array( 'run_now', 'save_settings' ) as $method ) {
			foreach ( array( 'manage_options', 'edit_products', 'upload_files', 'nonce' ) as $missing ) {
				$sync = reset_images();
				if ( 'nonce' === $missing ) { $GLOBALS['nonce_valid'] = false; }
				else { $GLOBALS['capabilities'] = array_values( array_diff( $GLOBALS['capabilities'], array( $missing ) ) ); }
				try { $sync->$method(); throw new RuntimeException( 'Unauthorized request accepted' ); }
				catch ( Admin_Denied $error ) { expect_image( 403 === $error->getCode(), 'Expected 403' ); }
				expect_image( array() === $GLOBALS['attachments'], 'Unauthorized request wrote attachments' );
			}
		}
	},
	'ejecutar ahora funciona aunque automatizacion este desactivada' => function () {
		$sync = reset_images();
		image_fixture( 'ABC.png' );
		$GLOBALS['products'][1] = new Test_Product( 1, 'ABC' );
		try { $sync->run_now(); throw new RuntimeException( 'Expected redirect' ); }
		catch ( Admin_Redirect $redirect ) { expect_image( false !== strpos( $redirect->getMessage(), 'buscador-llantas-estilos' ), 'Wrong redirect' ); }
		expect_image( 100 === $GLOBALS['products'][1]->get_image_id(), 'Manual import did not persist' );
		expect_image( ! wp_next_scheduled( $sync::CRON ), 'Manual run enabled recurring sync' );
	},
	'al guardar configura imagen antes de persistir sin recursion' => function () {
		$sync = reset_images();
		image_fixture( 'ABC.png' );
		$product = new Test_Product( 0, 'ABC' );
		$sync->before_product_save( $product );
		expect_image( 0 === $product->get_image_id(), 'Disabled on-save must not alter product' );
		$GLOBALS['options'][ $sync::SETTINGS ]['on_save'] = true;
		$sync->before_product_save( $product );
		expect_image( 100 === $product->get_image_id(), 'New product must receive image before persistence' );
		expect_image( 0 === $product->saves, 'Hook must not save recursively' );
	},
	'lock errores de guardado y programacion se informan' => function () {
		$sync = reset_images();
		add_option( $sync::LOCK, time() );
		expect_image_error( $sync->start(), 'images_locked' );
		delete_option( $sync::LOCK );
		$GLOBALS['schedule_error'] = true;
		expect_image_error( $sync->start(), 'batch_schedule_failed' );
		$GLOBALS['schedule_error'] = false;
		image_fixture( 'ABC.png' );
		$product = new Test_Product( 1, 'ABC' );
		$product->fail_save = true;
		$GLOBALS['products'][1] = $product;
		expect_image_error( $sync->start(), 'product_save_failed' );
		expect_image( 'error' === get_option( $sync::STATUS )['state'], 'Save failure hidden' );
		expect_image( ! get_option( $sync::LOCK ), 'Exception leaked lock' );
	},
	'la pantalla muestra carpeta frecuencia ejecutar ahora y errores escapados' => function () {
		$sync = reset_images();
		update_option( 'buscador_llantas_image_save_error', '<script>bad</script>' );
		ob_start();
		$sync->render_settings();
		$html = ob_get_clean();
		foreach ( array( 'image_folder', 'image_frequency', 'hourly', 'twicedaily', 'daily', 'Ejecutar ahora', 'image_on_save', 'WP-Cron' ) as $text ) {
			expect_image( false !== strpos( $html, $text ), 'Missing setting: ' . $text );
		}
		expect_image( false === strpos( $html, '<script>' ), 'Admin error must be escaped' );
		expect_image( false !== strpos( $html, '&lt;script&gt;' ), 'Admin error must be visible' );
	},
);

try {
	foreach ( $tests as $name => $test ) {
		$test();
		echo 'PASS: ' . $name . PHP_EOL;
	}
	echo count( $tests ) . ' tests passed.' . PHP_EOL;
} finally {
	foreach ( $created_files as $path ) { unlink( $path ); }
	rmdir( $GLOBALS['root'] . DIRECTORY_SEPARATOR . 'images' );
	rmdir( $GLOBALS['root'] );
}
