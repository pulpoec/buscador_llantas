<?php
/**
 * Pruebas aisladas del importador. Ejecutar: php tests\category-sync-test.php
 * No requieren WordPress ni conexiones al servidor real.
 */
define( 'ABSPATH', __DIR__ );

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class Test_Admin_Error extends RuntimeException {}
class Test_Redirect extends RuntimeException {}

function add_action( $hook, $callback, ...$args ) {
	$GLOBALS['hooks'][ $hook ] = $callback;
}
function add_filter( ...$args ) {}
function add_shortcode( ...$args ) {}
function register_deactivation_hook( ...$args ) {}
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function wp_slash( $text ) { return addslashes( $text ); }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function taxonomy_exists( $taxonomy ) { return $GLOBALS['taxonomy_exists']; }
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['capabilities'], true ); }
function check_admin_referer( $action ) {
	$GLOBALS['nonce_checked'] = $action;
	if ( ! $GLOBALS['valid_nonce'] ) {
		throw new Test_Admin_Error( 'invalid_nonce' );
	}
}
function wp_die( $message, $title = '', $args = array() ) {
	throw new Test_Admin_Error( $message, $args['response'] );
}
function admin_url( $path ) { return 'https://wordpress.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_safe_redirect( $url ) { throw new Test_Redirect( $url ); }
function wp_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = array( $url, $args );
	return $GLOBALS['response'];
}
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function get_terms( $args ) {
	if ( $GLOBALS['query_error'] ) {
		return new WP_Error( 'query_failed', 'Query failed' );
	}
	$ids = array();
	foreach ( $GLOBALS['terms'] as $id => $term ) {
		if ( $term['external_id'] === $args['meta_value'] ) {
			$ids[] = $id;
		}
	}
	return array_slice( $ids, 0, $args['number'] );
}
function get_term_by( $field, $value, $taxonomy ) {
	foreach ( $GLOBALS['terms'] as $id => $term ) {
		if ( $term['name'] === $value ) {
			return (object) array( 'term_id' => $id, 'name' => $term['name'] );
		}
	}
	return false;
}
function get_term_meta( $id, $key, $single ) { return $GLOBALS['terms'][ $id ]['external_id']; }
function update_term_meta( $id, $key, $value ) {
	if ( $GLOBALS['meta_error'] ) {
		return $GLOBALS['meta_error'];
	}
	$GLOBALS['terms'][ $id ]['external_id'] = stripslashes( $value );
	return 1;
}
function wp_insert_term( $name, $taxonomy ) {
	if ( $GLOBALS['write_error'] ) {
		return new WP_Error( 'write_failed', 'Write failed' );
	}
	$id = count( $GLOBALS['terms'] ) + 1;
	$GLOBALS['terms'][ $id ] = array( 'name' => stripslashes( $name ), 'external_id' => '', 'slug' => 'original-' . $id );
	return array( 'term_id' => $id );
}
function wp_update_term( $id, $taxonomy, $args ) {
	if ( $GLOBALS['write_error'] ) {
		return new WP_Error( 'write_failed', 'Write failed' );
	}
	$GLOBALS['terms'][ $id ]['name'] = stripslashes( $args['name'] );
	return array( 'term_id' => $id );
}

require dirname( __DIR__ ) . '\buscador_llantas\buscador-llantas.php';
$plugin = new Buscador_Llantas_Pro_Taxonomias();

function reset_fixture( $body = '[]' ) {
	$GLOBALS['terms']           = array();
	$GLOBALS['requests']        = array();
	$GLOBALS['response']        = array( 'status' => 200, 'body' => $body );
	$GLOBALS['taxonomy_exists'] = true;
	$GLOBALS['query_error']     = false;
	$GLOBALS['write_error']     = false;
	$GLOBALS['meta_error']      = false;
	$GLOBALS['capabilities']    = array( 'manage_options', 'manage_product_terms' );
	$GLOBALS['valid_nonce']     = true;
	$GLOBALS['nonce_checked']   = null;
}

function expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function expect_error( $result, $code ) {
	expect( is_wp_error( $result ) && $code === $result->get_error_code(), 'Expected error: ' . $code );
}

$tests = array(
	'importa los siete grupos y repetir no duplica' => function () use ( $plugin ) {
		$groups = array(
			array( '_id' => 'GPR0007', 'description' => 'FRENOS' ),
			array( '_id' => 'GPR0008', 'description' => 'SUSPENSION' ),
			array( '_id' => 'GPR0009', 'description' => 'LUBRICACION' ),
			array( '_id' => 'GPR0011', 'description' => 'LLANTAS' ),
			array( '_id' => 'GPR0012', 'description' => 'BATERIAS' ),
			array( '_id' => 'GPR0015', 'description' => 'MOTOR' ),
			array( '_id' => 'GPR0025', 'description' => 'SUMINISTROS TECNICENTRO' ),
		);
		reset_fixture( json_encode( $groups ) );
		expect( array( 'created' => 7, 'updated' => 0 ) === $plugin->sync_product_categories(), 'Seven categories must be created' );
		foreach ( $groups as $index => $group ) {
			expect( $GLOBALS['terms'][ $index + 1 ]['name'] === $group['description'], 'Persisted name mismatch' );
			expect( $GLOBALS['terms'][ $index + 1 ]['external_id'] === $group['_id'], 'Persisted external ID mismatch' );
		}
		expect( array( 'created' => 0, 'updated' => 7 ) === $plugin->sync_product_categories(), 'Repeat must update existing categories' );
		expect( 7 === count( $GLOBALS['terms'] ), 'Repeat must not duplicate categories' );
		expect( $GLOBALS['requests'][0][0] === Buscador_Llantas_Pro_Taxonomias::PRODUCT_GROUPS_URL, 'Wrong endpoint' );
		expect( ! isset( $GLOBALS['requests'][0][1]['sslverify'] ) || $GLOBALS['requests'][0][1]['sslverify'], 'TLS verification must remain enabled' );
	},
	'reutiliza categoria manual y renombra por identificador sin cambiar slug' => function () use ( $plugin ) {
		reset_fixture( '[{"_id":"GPR0011","description":"LLANTAS"}]' );
		wp_insert_term( 'LLANTAS', 'product_cat' );
		wp_insert_term( 'MANUAL', 'product_cat' );
		expect( array( 'created' => 0, 'updated' => 1 ) === $plugin->sync_product_categories(), 'Existing category must be reused' );
		$GLOBALS['response']['body'] = '[{"_id":"GPR0011","description":"LLANTAS NUEVAS"}]';
		$plugin->sync_product_categories();
		expect( 'LLANTAS NUEVAS' === $GLOBALS['terms'][1]['name'], 'External ID must identify renamed term' );
		expect( 'original-1' === $GLOBALS['terms'][1]['slug'], 'Existing slug must be preserved' );
		expect( 'MANUAL' === $GLOBALS['terms'][2]['name'], 'Unrelated terms must remain untouched' );
		expect( 2 === count( $GLOBALS['terms'] ), 'Rename must not create a new term' );
	},
	'valida toda la respuesta antes de escribir' => function () use ( $plugin ) {
		foreach ( array(
			'not json', '{}', 'null',
			'[{"_id":"GPR0011","description":"LLANTAS"},{}]',
			'[{"_id":1,"description":"LLANTAS"}]',
			'[{"_id":"GPR0011","description":[]}]',
			'[{"_id":"","description":"LLANTAS"}]',
			'[{"_id":"GPR0011","description":"  "}]',
			'[{"_id":"GPR0011","description":"LLANTAS"},{"_id":"GPR0011","description":"OTRA"}]',
		) as $body ) {
			reset_fixture( $body );
			expect( is_wp_error( $plugin->sync_product_categories() ), 'Invalid payload must fail: ' . $body );
			expect( array() === $GLOBALS['terms'], 'Invalid payload must not cause writes' );
		}
		reset_fixture();
		expect( array( 'created' => 0, 'updated' => 0 ) === $plugin->sync_product_categories(), 'Empty array is a valid no-op' );
	},
	'notifica errores de conexion HTTP y WooCommerce inactivo' => function () use ( $plugin ) {
		reset_fixture();
		$GLOBALS['taxonomy_exists'] = false;
		expect_error( $plugin->sync_product_categories(), 'missing_product_categories' );
		expect( array() === $GLOBALS['requests'], 'Missing WooCommerce must not issue requests' );
		reset_fixture();
		$GLOBALS['response'] = new WP_Error( 'timeout', 'Timeout' );
		expect_error( $plugin->sync_product_categories(), 'product_groups_request_failed' );
		reset_fixture();
		$GLOBALS['response']['status'] = 503;
		expect_error( $plugin->sync_product_categories(), 'product_groups_http_error' );
	},
	'impide fusionar identificadores diferentes y detecta metadatos duplicados' => function () use ( $plugin ) {
		reset_fixture( '[{"_id":"GPR0011","description":"LLANTAS"}]' );
		wp_insert_term( 'LLANTAS', 'product_cat' );
		$GLOBALS['terms'][1]['external_id'] = 'DIFFERENT';
		expect_error( $plugin->sync_product_categories(), 'product_group_name_conflict' );
		expect( 'DIFFERENT' === $GLOBALS['terms'][1]['external_id'], 'Conflict must not overwrite the external ID' );
		$GLOBALS['terms'][1]['external_id'] = 'GPR0011';
		wp_insert_term( 'OTRA', 'product_cat' );
		$GLOBALS['terms'][2]['external_id'] = 'GPR0011';
		expect_error( $plugin->sync_product_categories(), 'duplicate_product_group' );
	},
	'notifica errores de consulta escritura y metadatos y permite reintentar' => function () use ( $plugin ) {
		foreach ( array( 'query_error' => 'query_failed', 'write_error' => 'write_failed', 'meta_error' => 'meta_failed' ) as $flag => $code ) {
			reset_fixture( '[{"_id":"GPR0011","description":"LLANTAS"}]' );
			$GLOBALS[ $flag ] = 'meta_error' === $flag ? new WP_Error( $code, 'Meta failed' ) : true;
			expect_error( $plugin->sync_product_categories(), $code );
			$GLOBALS[ $flag ] = false;
			expect( ! is_wp_error( $plugin->sync_product_categories() ), 'Retry must succeed' );
			expect( 1 === count( $GLOBALS['terms'] ), 'Retry must not duplicate a term' );
			expect( 'GPR0011' === $GLOBALS['terms'][1]['external_id'], 'Retry must persist the external ID' );
		}
	},
	'protege el handler con permisos y nonce antes de consultar' => function () use ( $plugin ) {
		foreach ( array( array(), array( 'manage_options' ), array( 'manage_product_terms' ) ) as $capabilities ) {
			reset_fixture();
			$GLOBALS['capabilities'] = $capabilities;
			try {
				$plugin->handle_sync_categories();
				throw new RuntimeException( 'Unauthorized request accepted' );
			} catch ( Test_Admin_Error $error ) {
				expect( 403 === $error->getCode(), 'Expected permission denial' );
			}
			expect( array() === $GLOBALS['requests'], 'Denied request must not call upstream' );
		}
		reset_fixture();
		$GLOBALS['valid_nonce'] = false;
		try {
			$plugin->handle_sync_categories();
			throw new RuntimeException( 'Invalid nonce accepted' );
		} catch ( Test_Admin_Error $error ) {
			expect( 'invalid_nonce' === $error->getMessage(), 'Expected nonce denial' );
		}
		expect( array() === $GLOBALS['requests'], 'Invalid nonce must not call upstream' );
		expect( isset( $GLOBALS['hooks']['admin_post_buscador_llantas_sync_categories'] ), 'Admin action must be registered' );
		expect( ! isset( $GLOBALS['hooks']['admin_post_nopriv_buscador_llantas_sync_categories'] ), 'No anonymous import action' );
	},
	'el handler autorizado importa y redirige con los contadores' => function () use ( $plugin ) {
		reset_fixture( '[{"_id":"GPR0011","description":"LLANTAS"}]' );
		try {
			$plugin->handle_sync_categories();
			throw new RuntimeException( 'Expected success redirect' );
		} catch ( Test_Redirect $redirect ) {
			parse_str( parse_url( $redirect->getMessage(), PHP_URL_QUERY ), $query );
			expect( 'buscador-llantas-estilos' === $query['page'], 'Must return to settings' );
			expect( '1' === $query['categories_synced'], 'Success flag missing' );
			expect( '1' === $query['categories_created'] && '0' === $query['categories_updated'], 'Wrong counters' );
		}
		expect( 'GPR0011' === $GLOBALS['terms'][1]['external_id'], 'Redirect must happen after persistence' );
		expect( 'buscador_llantas_sync_categories' === $GLOBALS['nonce_checked'], 'Nonce must be checked before import' );
	},
	'el handler muestra el error recibido sin ocultarlo' => function () use ( $plugin ) {
		reset_fixture();
		$GLOBALS['response']['status'] = 401;
		try {
			$plugin->handle_sync_categories();
			throw new RuntimeException( 'Upstream error was hidden' );
		} catch ( Test_Admin_Error $error ) {
			expect( 500 === $error->getCode(), 'Import failure must not return success' );
			expect( false !== strpos( $error->getMessage(), 'HTTP 401' ), 'Upstream error must be visible' );
			expect( 'buscador_llantas_sync_categories' === $GLOBALS['nonce_checked'], 'Correct nonce action must be checked' );
		}
	},
);

foreach ( $tests as $name => $test ) {
	$test();
	echo 'PASS: ' . $name . PHP_EOL;
}
echo count( $tests ) . ' tests passed.' . PHP_EOL;
