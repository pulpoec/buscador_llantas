<?php
/**
 * Plugin Name: Buscador de Llantas Pro por Taxonomias
 * Plugin URI: https://pulpo.ec
 * Description: Buscador avanzado de llantas por taxonomias (ancho, perfil y diametro) para WooCommerce.
 * Version: 1.5.0
 * Author: Equipo Senior PULPO
 * License: GPLv2 or later
 * Text Domain: buscador-llantas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Buscador_Llantas_Pro_Taxonomias' ) ) {
	final class Buscador_Llantas_Pro_Taxonomias {
		const VERSION      = '1.5.0';
		const NONCE_ACTION = 'buscador_llantas_filtrar';
		const NONCE_NAME   = 'buscador_llantas_nonce';
		const VEHICLE_BRAND_TAXONOMY = 'marca_vehiculo';
		const VEHICLE_MODEL_TAXONOMY = 'modelo_vehiculo';
		const VEHICLE_MODELS_NONCE   = 'buscador_llantas_modelos_vehiculo';

		/**
		 * Taxonomias soportadas por el plugin.
		 *
		 * @var array<string,string>
		 */
		private $taxonomies = array(
			'ancho_llanta' => 'Ancho',
			'perfil_llanta' => 'Perfil',
			'diametro_rin' => 'Diametro',
		);

		/**
		 * Taxonomias del buscador por vehiculo.
		 *
		 * @var array<string,string>
		 */
		private $vehicle_taxonomies = array(
			self::VEHICLE_BRAND_TAXONOMY => 'Marca',
			self::VEHICLE_MODEL_TAXONOMY => 'Modelo',
		);

		const STYLES_OPTION = 'buscador_llantas_styles';

		/**
		 * Definicion de los estilos configurables.
		 * Cada item: etiqueta, grupo, tipo, valor por defecto, variable CSS.
		 *
		 * @return array<string,array<string,string>>
		 */
		private function get_style_schema() {
			$font = '"Segoe UI", "Helvetica Neue", sans-serif';
			$s    = array();
			$add  = static function ( $key, $label, $group, $type, $default ) use ( &$s ) {
				$s[ $key ] = array(
					'label'   => $label,
					'group'   => $group,
					'type'    => $type,
					'default' => $default,
				);
			};

			$g = 'Contenedor';
			$add( 'wrap-max-width', 'Ancho maximo', $g, 'length', '1080px' );
			$add( 'wrap-padding', 'Relleno', $g, 'length', '1.1rem' );
			$add( 'wrap-padding-compact', 'Relleno (version corta)', $g, 'length', '0.75rem' );
			$add( 'wrap-radius', 'Radio de borde', $g, 'length', '16px' );
			$add( 'wrap-radius-compact', 'Radio de borde (version corta)', $g, 'length', '12px' );
			$add( 'wrap-bg-start', 'Fondo (inicio degradado)', $g, 'color', '#ffffff' );
			$add( 'wrap-bg-end', 'Fondo (fin degradado)', $g, 'color', '#f8fbff' );
			$add( 'wrap-border', 'Color de borde', $g, 'color', '#e6edf5' );
			$add( 'wrap-shadow', 'Sombra (CSS box-shadow)', $g, 'text', '0 8px 28px rgba(9, 30, 66, 0.08)' );

			$g = 'Tipografia y etiquetas';
			$add( 'font', 'Fuente', $g, 'font', $font );
			$add( 'label-color', 'Color de etiquetas', $g, 'color', '#4d5b6a' );
			$add( 'label-size', 'Tamano de etiquetas', $g, 'length', '0.79rem' );
			$add( 'label-size-compact', 'Tamano de etiquetas (version corta)', $g, 'length', '0.7rem' );
			$add( 'label-transform', 'Transformacion de texto', $g, 'transform', 'uppercase' );

			$g = 'Selectores';
			$add( 'select-height', 'Alto', $g, 'length', '48px' );
			$add( 'select-height-compact', 'Alto (version corta)', $g, 'length', '42px' );
			$add( 'select-radius', 'Radio de borde', $g, 'length', '12px' );
			$add( 'select-radius-compact', 'Radio de borde (version corta)', $g, 'length', '8px' );
			$add( 'select-size', 'Tamano de texto', $g, 'length', '0.96rem' );
			$add( 'select-size-compact', 'Tamano de texto (version corta)', $g, 'length', '0.84rem' );
			$add( 'select-bg', 'Fondo', $g, 'color', '#ffffff' );
			$add( 'select-color', 'Color de texto', $g, 'color', '#1d2a38' );
			$add( 'select-border', 'Color de borde', $g, 'color', '#d8e1eb' );
			$add( 'focus-color', 'Borde al enfocar', $g, 'color', '#1f7bd8' );
			$add( 'focus-ring', 'Halo al enfocar (CSS color)', $g, 'text', 'rgba(31, 123, 216, 0.18)' );

			$g = 'Botones';
			$add( 'button-radius', 'Radio de borde', $g, 'length', '12px' );
			$add( 'button-size', 'Tamano de texto', $g, 'length', '0.95rem' );
			$add( 'submit-bg', 'Buscar: fondo', $g, 'color', '#da171f' );
			$add( 'submit-bg-hover', 'Buscar: fondo (hover)', $g, 'color', '#b9131a' );
			$add( 'submit-color', 'Buscar: color de texto', $g, 'color', '#ffffff' );
			$add( 'submit-shadow', 'Buscar: sombra', $g, 'text', '0 8px 18px rgba(218, 23, 31, 0.25)' );
			$add( 'clear-bg', 'Limpiar: fondo', $g, 'color', '#ffffff' );
			$add( 'clear-bg-hover', 'Limpiar: fondo (hover)', $g, 'color', '#f3f7fb' );
			$add( 'clear-color', 'Limpiar: color de texto', $g, 'color', '#2f4050' );
			$add( 'clear-border', 'Limpiar: color de borde', $g, 'color', '#c7d4e2' );

			$g = 'Pestanas';
			$add( 'tab-size', 'Tamano de texto', $g, 'length', '0.88rem' );
			$add( 'tab-color', 'Color de texto', $g, 'color', '#4d5b6a' );
			$add( 'tab-active-color', 'Color de texto (activa)', $g, 'color', '#1d2a38' );
			$add( 'tab-active-border', 'Linea de pestana activa', $g, 'color', '#da171f' );
			$add( 'tab-border', 'Linea inferior', $g, 'color', '#e6edf5' );

			return $s;
		}

		private function get_allowed_fonts() {
			return array(
				'"Segoe UI", "Helvetica Neue", sans-serif' => 'Segoe UI / Helvetica',
				'Arial, Helvetica, sans-serif'             => 'Arial',
				'Georgia, "Times New Roman", serif'        => 'Georgia',
				'"Trebuchet MS", sans-serif'               => 'Trebuchet MS',
				'Verdana, Geneva, sans-serif'              => 'Verdana',
				'inherit'                                  => 'Heredar del tema',
			);
		}

		/**
		 * Valida un valor segun su tipo; devuelve null si es invalido.
		 */
		private function sanitize_style_value( $type, $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			switch ( $type ) {
				case 'color':
					$hex = sanitize_hex_color( $value );
					return $hex ? $hex : null;
				case 'length':
					return preg_match( '/^(none|\d{1,4}(\.\d{1,2})?(px|rem|em|%))$/', $value ) ? $value : null;
				case 'text':
					return preg_match( '/^[0-9a-zA-Z\s\.,\(\)\-#%]{1,150}$/', $value ) ? $value : null;
				case 'font':
					return array_key_exists( $value, $this->get_allowed_fonts() ) ? $value : null;
				case 'transform':
					return in_array( $value, array( 'uppercase', 'none', 'capitalize' ), true ) ? $value : null;
			}
			return null;
		}

		private function get_styles() {
			$saved  = get_option( self::STYLES_OPTION, array() );
			$saved  = is_array( $saved ) ? $saved : array();
			$styles = array();
			foreach ( $this->get_style_schema() as $key => $def ) {
				$value = isset( $saved[ $key ] ) ? $this->sanitize_style_value( $def['type'], $saved[ $key ] ) : null;
				$styles[ $key ] = null !== $value ? $value : $def['default'];
			}
			return $styles;
		}

		private function get_css_variables_block() {
			$vars = '';
			foreach ( $this->get_styles() as $key => $value ) {
				$vars .= '--bl-' . $key . ':' . $value . ';';
			}
			return '.buscador-llantas-wrap{' . $vars . '}';
		}

		public function register_settings_page() {
			add_options_page(
				'Estilos del Buscador de Llantas',
				'Buscador Llantas',
				'manage_options',
				'buscador-llantas-estilos',
				array( $this, 'render_settings_page' )
			);
		}

		public function register_settings() {
			register_setting(
				'buscador_llantas_styles_group',
				self::STYLES_OPTION,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize_styles_option' ),
					'default'           => array(),
				)
			);
		}

		public function sanitize_styles_option( $input ) {
			$input = is_array( $input ) ? wp_unslash( $input ) : array();
			$clean = array();
			foreach ( $this->get_style_schema() as $key => $def ) {
				$value = isset( $input[ $key ] ) ? $this->sanitize_style_value( $def['type'], $input[ $key ] ) : null;
				$clean[ $key ] = null !== $value ? $value : $def['default'];
			}
			return $clean;
		}

		public function enqueue_admin_assets( $hook ) {
			if ( 'settings_page_buscador-llantas-estilos' !== $hook ) {
				return;
			}
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".bl-color").wpColorPicker();});' );
		}

		public function handle_reset_styles() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'No autorizado.', 403 );
			}
			check_admin_referer( 'buscador_llantas_reset_styles' );
			delete_option( self::STYLES_OPTION );
			wp_safe_redirect( add_query_arg( array( 'page' => 'buscador-llantas-estilos', 'reset' => '1' ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$styles = $this->get_styles();
			$groups = array();
			foreach ( $this->get_style_schema() as $key => $def ) {
				$groups[ $def['group'] ][ $key ] = $def;
			}
			?>
			<div class="wrap">
				<h1>Estilos del Buscador de Llantas</h1>
				<?php if ( isset( $_GET['reset'] ) ) : ?>
					<div class="notice notice-success is-dismissible"><p>Estilos restaurados a los valores por defecto.</p></div>
				<?php endif; ?>
				<form method="post" action="options.php">
					<?php settings_fields( 'buscador_llantas_styles_group' ); ?>
					<?php foreach ( $groups as $group => $fields ) : ?>
						<h2><?php echo esc_html( $group ); ?></h2>
						<table class="form-table" role="presentation">
							<?php foreach ( $fields as $key => $def ) :
								$name = self::STYLES_OPTION . '[' . $key . ']';
								$id   = 'bl-' . $key;
								?>
								<tr>
									<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $def['label'] ); ?></label></th>
									<td>
										<?php if ( 'font' === $def['type'] ) : ?>
											<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
												<?php foreach ( $this->get_allowed_fonts() as $val => $label ) : ?>
													<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $styles[ $key ], $val ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php elseif ( 'transform' === $def['type'] ) : ?>
											<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
												<?php foreach ( array( 'uppercase' => 'MAYUSCULAS', 'capitalize' => 'Capitalizado', 'none' => 'Normal' ) as $val => $label ) : ?>
													<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $styles[ $key ], $val ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php else : ?>
											<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $styles[ $key ] ); ?>" class="<?php echo 'color' === $def['type'] ? 'bl-color' : 'regular-text'; ?>" data-default-color="<?php echo esc_attr( $def['default'] ); ?>" />
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php endforeach; ?>
					<?php submit_button( 'Guardar estilos' ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('¿Restaurar todos los estilos por defecto?');">
					<input type="hidden" name="action" value="buscador_llantas_reset_styles" />
					<?php wp_nonce_field( 'buscador_llantas_reset_styles' ); ?>
					<?php submit_button( 'Restaurar valores por defecto', 'secondary' ); ?>
				</form>
			</div>
			<?php
		}

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_post_buscador_llantas_reset_styles', array( $this, 'handle_reset_styles' ) );
			add_action( 'init', array( $this, 'register_taxonomies' ) );
			add_shortcode( 'buscador_llantas', array( $this, 'render_shortcode' ) );
			add_action( 'wp_ajax_buscador_llantas_modelos_vehiculo', array( $this, 'ajax_get_vehicle_models' ) );
			add_action( 'wp_ajax_nopriv_buscador_llantas_modelos_vehiculo', array( $this, 'ajax_get_vehicle_models' ) );
			add_action( 'pre_get_posts', array( $this, 'apply_filters_to_shop_query' ), 20 );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_plugin_update' ) );
			add_filter( 'plugins_api', array( $this, 'provide_plugin_information' ), 20, 3 );
			add_filter( 'http_request_args', array( $this, 'inject_github_auth_headers' ), 10, 2 );

			// Hook principal para el loop de productos WooCommerce (tienda/shop page).
			add_action( 'woocommerce_product_query', array( $this, 'apply_filters_to_wc_product_query' ), 20 );

			// Compatibilidad con widget de WooCommerce "Filtros Activos".
			add_action( 'woocommerce_widget_layered_nav_filters_start', array( $this, 'render_active_filters_widget_items' ), 10, 2 );
			add_filter( 'woocommerce_widget_get_current_page_url', array( $this, 'append_custom_query_args_to_widget_links' ), 10, 2 );
		}

		/**
		 * Registra taxonomias personalizadas no jerarquicas para productos.
		 */
		public function register_taxonomies() {
			$common_args = array(
				'hierarchical'          => false,
				'public'                => true,
				'show_ui'               => true,
				'show_admin_column'     => false,
				'show_in_quick_edit'    => true,
				'show_in_nav_menus'     => false,
				'show_tagcloud'         => false,
				'show_in_rest'          => true,
				'query_var'             => true,
				'rewrite'               => false,
				'update_count_callback' => '_update_post_term_count',
			);

			register_taxonomy(
				'ancho_llanta',
				'product',
				array_merge(
					$common_args,
					array(
						'labels' => array(
							'name'                       => __( 'Anchos de Llanta', 'buscador-llantas' ),
							'singular_name'              => __( 'Ancho de Llanta', 'buscador-llantas' ),
							'search_items'               => __( 'Buscar anchos', 'buscador-llantas' ),
							'popular_items'              => __( 'Anchos populares', 'buscador-llantas' ),
							'all_items'                  => __( 'Todos los anchos', 'buscador-llantas' ),
							'edit_item'                  => __( 'Editar ancho', 'buscador-llantas' ),
							'update_item'                => __( 'Actualizar ancho', 'buscador-llantas' ),
							'add_new_item'               => __( 'Agregar ancho', 'buscador-llantas' ),
							'new_item_name'              => __( 'Nuevo ancho', 'buscador-llantas' ),
							'separate_items_with_commas' => __( 'Separar anchos con comas', 'buscador-llantas' ),
							'add_or_remove_items'        => __( 'Agregar o quitar anchos', 'buscador-llantas' ),
							'choose_from_most_used'      => __( 'Elegir entre los mas usados', 'buscador-llantas' ),
							'menu_name'                  => __( 'Ancho Llanta', 'buscador-llantas' ),
						),
					)
				)
			);

			register_taxonomy(
				'perfil_llanta',
				'product',
				array_merge(
					$common_args,
					array(
						'labels' => array(
							'name'                       => __( 'Perfiles de Llanta', 'buscador-llantas' ),
							'singular_name'              => __( 'Perfil de Llanta', 'buscador-llantas' ),
							'search_items'               => __( 'Buscar perfiles', 'buscador-llantas' ),
							'popular_items'              => __( 'Perfiles populares', 'buscador-llantas' ),
							'all_items'                  => __( 'Todos los perfiles', 'buscador-llantas' ),
							'edit_item'                  => __( 'Editar perfil', 'buscador-llantas' ),
							'update_item'                => __( 'Actualizar perfil', 'buscador-llantas' ),
							'add_new_item'               => __( 'Agregar perfil', 'buscador-llantas' ),
							'new_item_name'              => __( 'Nuevo perfil', 'buscador-llantas' ),
							'separate_items_with_commas' => __( 'Separar perfiles con comas', 'buscador-llantas' ),
							'add_or_remove_items'        => __( 'Agregar o quitar perfiles', 'buscador-llantas' ),
							'choose_from_most_used'      => __( 'Elegir entre los mas usados', 'buscador-llantas' ),
							'menu_name'                  => __( 'Perfil Llanta', 'buscador-llantas' ),
						),
					)
				)
			);

			register_taxonomy(
				'diametro_rin',
				'product',
				array_merge(
					$common_args,
					array(
						'labels' => array(
							'name'                       => __( 'Diametros de Rin', 'buscador-llantas' ),
							'singular_name'              => __( 'Diametro de Rin', 'buscador-llantas' ),
							'search_items'               => __( 'Buscar diametros', 'buscador-llantas' ),
							'popular_items'              => __( 'Diametros populares', 'buscador-llantas' ),
							'all_items'                  => __( 'Todos los diametros', 'buscador-llantas' ),
							'edit_item'                  => __( 'Editar diametro', 'buscador-llantas' ),
							'update_item'                => __( 'Actualizar diametro', 'buscador-llantas' ),
							'add_new_item'               => __( 'Agregar diametro', 'buscador-llantas' ),
							'new_item_name'              => __( 'Nuevo diametro', 'buscador-llantas' ),
							'separate_items_with_commas' => __( 'Separar diametros con comas', 'buscador-llantas' ),
							'add_or_remove_items'        => __( 'Agregar o quitar diametros', 'buscador-llantas' ),
							'choose_from_most_used'      => __( 'Elegir entre los mas usados', 'buscador-llantas' ),
							'menu_name'                  => __( 'Diametro Rin', 'buscador-llantas' ),
						),
					)
				)
			);

			register_taxonomy(
				self::VEHICLE_BRAND_TAXONOMY,
				'product',
				array_merge(
					$common_args,
					array(
						'labels' => array(
							'name'          => __( 'Marcas de Vehiculos', 'buscador-llantas' ),
							'singular_name' => __( 'Marca de Vehiculo', 'buscador-llantas' ),
							'all_items'     => __( 'Todas las marcas', 'buscador-llantas' ),
							'edit_item'     => __( 'Editar marca', 'buscador-llantas' ),
							'add_new_item'  => __( 'Agregar marca', 'buscador-llantas' ),
							'menu_name'     => __( 'Marca de Vehiculo', 'buscador-llantas' ),
						),
					)
				)
			);

			register_taxonomy(
				self::VEHICLE_MODEL_TAXONOMY,
				'product',
				array_merge(
					$common_args,
					array(
						'labels' => array(
							'name'          => __( 'Modelos de Vehiculos', 'buscador-llantas' ),
							'singular_name' => __( 'Modelo de Vehiculo', 'buscador-llantas' ),
							'all_items'     => __( 'Todos los modelos', 'buscador-llantas' ),
							'edit_item'     => __( 'Editar modelo', 'buscador-llantas' ),
							'add_new_item'  => __( 'Agregar modelo', 'buscador-llantas' ),
							'menu_name'     => __( 'Modelo de Vehiculo', 'buscador-llantas' ),
						),
					)
				)
			);
		}

		/**
		 * Registra y define estilos del formulario.
		 */
		public function register_assets() {
			$css = '
			.buscador-llantas-wrap {
				max-width: var(--bl-wrap-max-width);
				margin: 1.25rem auto;
				padding: var(--bl-wrap-padding);
				background: linear-gradient(180deg, var(--bl-wrap-bg-start) 0%, var(--bl-wrap-bg-end) 100%);
				border: 1px solid var(--bl-wrap-border);
				border-radius: var(--bl-wrap-radius);
				box-shadow: var(--bl-wrap-shadow);
			}

			.buscador-llantas-form {
				display: flex;
				gap: 0.75rem;
				align-items: center;
				flex-wrap: wrap;
			}

			.buscador-llantas-field {
				flex: 1 1 190px;
				min-width: 0;
			}

			.buscador-llantas-field label {
				display: block;
				font-family: var(--bl-font);
				font-size: var(--bl-label-size);
				font-weight: 600;
				letter-spacing: 0.03em;
				text-transform: var(--bl-label-transform);
				color: var(--bl-label-color);
				margin: 0 0 0.35rem;
			}

			.buscador-llantas-field select {
				width: 100%;
				height: var(--bl-select-height);
				padding: 0 0.85rem;
				border-radius: var(--bl-select-radius);
				border: 1px solid var(--bl-select-border);
				background: var(--bl-select-bg);
				font-family: var(--bl-font);
				font-size: var(--bl-select-size);
				color: var(--bl-select-color);
				outline: none;
				transition: border-color 0.2s ease, box-shadow 0.2s ease;
			}

			.buscador-llantas-field select:focus {
				border-color: var(--bl-focus-color);
				box-shadow: 0 0 0 3px var(--bl-focus-ring);
			}

			.buscador-llantas-submit {
				flex: 0 0 auto;
				height: var(--bl-select-height);
				margin-top: 1.35rem;
				padding: 0 1.35rem;
				border: 0;
				border-radius: var(--bl-button-radius);
				background: var(--bl-submit-bg);
				color: var(--bl-submit-color);
				font-family: var(--bl-font);
				font-size: var(--bl-button-size);
				font-weight: 700;
				cursor: pointer;
				transition: transform 0.15s ease, box-shadow 0.2s ease, background-color 0.2s ease;
				box-shadow: var(--bl-submit-shadow);
			}

			.buscador-llantas-submit:hover,
			.buscador-llantas-submit:focus {
				background: var(--bl-submit-bg-hover);
				transform: translateY(-1px);
			}

			.buscador-llantas-wrap--compacto {
				max-width: none;
				margin: 0;
				padding: var(--bl-wrap-padding-compact);
				border-radius: var(--bl-wrap-radius-compact);
			}

			.buscador-llantas-tabs {
				display: flex;
				gap: 0.4rem;
				margin-bottom: 0.7rem;
				border-bottom: 1px solid var(--bl-tab-border);
			}

			.buscador-llantas-tab {
				padding: 0.55rem 0.85rem;
				border: 0;
				border-bottom: 3px solid transparent;
				background: transparent;
				color: var(--bl-tab-color);
				font: 600 var(--bl-tab-size) var(--bl-font);
				cursor: pointer;
			}

			.buscador-llantas-tab[aria-selected="true"] {
				border-bottom-color: var(--bl-tab-active-border);
				color: var(--bl-tab-active-color);
			}

			.buscador-llantas-panel[hidden] {
				display: none;
			}

			.buscador-llantas-form--compacto {
				display: grid;
				grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
				gap: 0.55rem;
				flex-wrap: nowrap;
			}

			.buscador-llantas-form--compacto-vehiculo {
				grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
			}

			.buscador-llantas-form--compacto .buscador-llantas-field {
				min-width: 0;
			}

			.buscador-llantas-form--compacto .buscador-llantas-field label {
				margin-bottom: 0.25rem;
				font-size: var(--bl-label-size-compact);
			}

			.buscador-llantas-form--compacto .buscador-llantas-field select,
			.buscador-llantas-form--compacto .buscador-llantas-submit {
				height: var(--bl-select-height-compact);
				border-radius: var(--bl-select-radius-compact);
				font-size: var(--bl-select-size-compact);
			}

			.buscador-llantas-form--compacto .buscador-llantas-submit {
				margin-top: 1.05rem;
				padding: 0 0.9rem;
				white-space: nowrap;
			}

			.buscador-llantas-clear {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				height: var(--bl-select-height);
				margin-top: 1.35rem;
				padding: 0 1.1rem;
				border-radius: var(--bl-button-radius);
				border: 1px solid var(--bl-clear-border);
				background: var(--bl-clear-bg);
				color: var(--bl-clear-color);
				font-family: var(--bl-font);
				font-size: var(--bl-button-size);
				font-weight: 600;
				text-decoration: none;
				transition: all 0.2s ease;
			}

			.buscador-llantas-clear:hover,
			.buscador-llantas-clear:focus {
				background: var(--bl-clear-bg-hover);
				color: var(--bl-clear-color);
			}

			@media (max-width: 768px) {
				.buscador-llantas-wrap {
					padding: 0.9rem;
				}

				.buscador-llantas-form {
					flex-direction: column;
					align-items: stretch;
					gap: 0.6rem;
				}

				.buscador-llantas-field,
				.buscador-llantas-submit,
				.buscador-llantas-clear {
					width: 100%;
					flex: 1 1 auto;
				}

				.buscador-llantas-submit,
				.buscador-llantas-clear {
					margin-top: 0.3rem;
				}

				.buscador-llantas-wrap--compacto {
					padding: 0.55rem;
				}

				.buscador-llantas-form--compacto {
					grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
					flex-direction: initial;
					align-items: end;
					gap: 0.35rem;
				}

				.buscador-llantas-form--compacto .buscador-llantas-field,
				.buscador-llantas-form--compacto .buscador-llantas-submit {
					width: auto;
					min-width: 0;
				}

				.buscador-llantas-form--compacto .buscador-llantas-field select {
					padding: 0 0.3rem;
					font-size: 0.72rem;
				}

				.buscador-llantas-form--compacto .buscador-llantas-submit {
					margin-top: 1.05rem;
					padding: 0 0.55rem;
					font-size: 0.72rem;
				}

				.buscador-llantas-form--compacto-vehiculo {
					grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
				}
			}
			';

			$css = $this->get_css_variables_block() . $css;

			wp_register_style( 'buscador-llantas-style', false, array(), self::VERSION );
			wp_add_inline_style( 'buscador-llantas-style', $css );

			$script = '
			(function () {
				function initBuscador(wrapper) {
					var tabs = wrapper.querySelectorAll("[data-buscador-tab]");
					var panels = wrapper.querySelectorAll("[data-buscador-panel]");
					var brand = wrapper.querySelector("[name=\\"' . self::VEHICLE_BRAND_TAXONOMY . '\\"]");
					var model = wrapper.querySelector("[name=\\"' . self::VEHICLE_MODEL_TAXONOMY . '\\"]");

					tabs.forEach(function (tab) {
						tab.addEventListener("click", function () {
							var selectedPanel = tab.getAttribute("aria-controls");
							tabs.forEach(function (item) {
								var selected = item === tab;
								item.setAttribute("aria-selected", selected ? "true" : "false");
								item.setAttribute("tabindex", selected ? "0" : "-1");
							});
							panels.forEach(function (panel) {
								panel.hidden = panel.id !== selectedPanel;
							});
						});
					});

					if (!brand || !model) {
						return;
					}

					var modelRequest = 0;
					brand.addEventListener("change", function () {
						var selectedBrand = brand.value;
						var requestId = ++modelRequest;
						model.disabled = true;
						model.innerHTML = "";
						var placeholder = document.createElement("option");
						placeholder.value = "";
						placeholder.textContent = selectedBrand ? "Cargando modelos..." : "Primero selecciona una marca";
						model.appendChild(placeholder);

						if (!selectedBrand) {
							return;
						}

						var body = new URLSearchParams();
						body.append("action", "buscador_llantas_modelos_vehiculo");
						body.append("nonce", window.buscadorLlantasVehicleSearch.nonce);
						body.append("marca_slug", selectedBrand);

						fetch(window.buscadorLlantasVehicleSearch.ajaxUrl, {
							method: "POST",
							credentials: "same-origin",
							headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
							body: body.toString()
						})
							.then(function (response) {
								if (!response.ok) {
									throw new Error("No se pudieron cargar los modelos.");
								}
								return response.json();
							})
							.then(function (response) {
								if (requestId !== modelRequest) {
									return;
								}
								if (!response.success || !Array.isArray(response.data)) {
									throw new Error("No se pudieron cargar los modelos.");
								}
								model.innerHTML = "";
								var emptyOption = document.createElement("option");
								emptyOption.value = "";
								emptyOption.textContent = response.data.length ? "Selecciona modelo" : "No hay modelos para esta marca";
								model.appendChild(emptyOption);
								response.data.forEach(function (item) {
									var option = document.createElement("option");
									option.value = item.slug;
									option.textContent = item.name;
									model.appendChild(option);
								});
								model.disabled = response.data.length === 0;
							})
							.catch(function () {
								if (requestId !== modelRequest) {
									return;
								}
								model.innerHTML = "";
								var errorOption = document.createElement("option");
								errorOption.value = "";
								errorOption.textContent = "Error al cargar modelos; vuelve a intentarlo";
								model.appendChild(errorOption);
							});
					});
				}

				document.querySelectorAll(".buscador-llantas-wrap--compacto").forEach(initBuscador);
			}());
			';

			wp_register_script( 'buscador-llantas-script', false, array(), self::VERSION, true );
			wp_add_inline_script( 'buscador-llantas-script', 'window.buscadorLlantasVehicleSearch = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::VEHICLE_MODELS_NONCE ),
				)
			) . ';', 'before' );
			wp_add_inline_script( 'buscador-llantas-script', $script );
		}

		/**
		 * Renderiza el shortcode [buscador_llantas].
		 *
		 * @return string
		 */
		public function render_shortcode( $atts = array() ) {
			$atts = shortcode_atts(
				array(
					'tipo' => 'normal',
				),
				$atts,
				'buscador_llantas'
			);
			$compact = 'compacto' === strtolower( sanitize_text_field( $atts['tipo'] ) );
			$instance_id = wp_unique_id( 'buscador-llantas-' );
			$wrap_class  = $compact ? 'buscador-llantas-wrap buscador-llantas-wrap--compacto' : 'buscador-llantas-wrap';
			$form_class  = $compact ? 'buscador-llantas-form buscador-llantas-form--compacto' : 'buscador-llantas-form';
			$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : get_post_type_archive_link( 'product' );
			$shop_url = $shop_url ? $shop_url : home_url( '/' );

			$current = array();
			foreach ( array_keys( $this->taxonomies ) as $taxonomy ) {
				$current[ $taxonomy ] = $this->get_requested_term_slug( $taxonomy );
			}
			$vehicle_tab_active = $compact && 'vehiculo' === $this->get_requested_search_type();

			wp_enqueue_style( 'buscador-llantas-style' );
			if ( $compact ) {
				wp_enqueue_script( 'buscador-llantas-script' );
			}

			ob_start();
			?>
			<div class="<?php echo esc_attr( $wrap_class ); ?>">
				<?php if ( $compact ) : ?>
					<div class="buscador-llantas-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Tipo de búsqueda', 'buscador-llantas' ); ?>">
						<button type="button" id="<?php echo esc_attr( $instance_id . 'tab-medida' ); ?>" class="buscador-llantas-tab" role="tab" data-buscador-tab aria-selected="<?php echo $vehicle_tab_active ? 'false' : 'true'; ?>" aria-controls="<?php echo esc_attr( $instance_id . 'panel-medida' ); ?>" tabindex="<?php echo $vehicle_tab_active ? '-1' : '0'; ?>"><?php esc_html_e( 'Por medida', 'buscador-llantas' ); ?></button>
						<button type="button" id="<?php echo esc_attr( $instance_id . 'tab-vehiculo' ); ?>" class="buscador-llantas-tab" role="tab" data-buscador-tab aria-selected="<?php echo $vehicle_tab_active ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $instance_id . 'panel-vehiculo' ); ?>" tabindex="<?php echo $vehicle_tab_active ? '0' : '-1'; ?>"><?php esc_html_e( 'Por vehículo', 'buscador-llantas' ); ?></button>
					</div>
					<div id="<?php echo esc_attr( $instance_id . 'panel-medida' ); ?>" class="buscador-llantas-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $instance_id . 'tab-medida' ); ?>" data-buscador-panel <?php echo $vehicle_tab_active ? 'hidden' : ''; ?>>
				<?php endif; ?>
				<form class="<?php echo esc_attr( $form_class ); ?>" method="get" action="<?php echo esc_url( $shop_url ); ?>">
					<?php if ( $compact ) : ?>
						<input type="hidden" name="tipo_busqueda" value="medida">
					<?php endif; ?>
					<?php foreach ( $this->taxonomies as $taxonomy => $label ) : ?>
						<?php $this->render_taxonomy_select( $instance_id, $taxonomy, $label, $current[ $taxonomy ] ); ?>
					<?php endforeach; ?>
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<button type="submit" class="buscador-llantas-submit"><?php esc_html_e( 'Buscar Llantas', 'buscador-llantas' ); ?></button>
					<?php if ( ! $compact ) : ?>
						<a class="buscador-llantas-clear" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Limpiar filtros', 'buscador-llantas' ); ?></a>
					<?php endif; ?>
				</form>
				<?php if ( $compact ) : ?>
					</div>
					<div id="<?php echo esc_attr( $instance_id . 'panel-vehiculo' ); ?>" class="buscador-llantas-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $instance_id . 'tab-vehiculo' ); ?>" data-buscador-panel <?php echo $vehicle_tab_active ? '' : 'hidden'; ?>>
						<form class="<?php echo esc_attr( $form_class . ' buscador-llantas-form--compacto-vehiculo' ); ?>" method="get" action="<?php echo esc_url( $shop_url ); ?>">
							<input type="hidden" name="tipo_busqueda" value="vehiculo">
							<?php
							$selected_brand = $this->get_requested_term_slug( self::VEHICLE_BRAND_TAXONOMY );
							$selected_model = $this->get_requested_term_slug( self::VEHICLE_MODEL_TAXONOMY );
							$this->render_taxonomy_select( $instance_id, self::VEHICLE_BRAND_TAXONOMY, 'Marca', $selected_brand, '', true );
							$this->render_taxonomy_select( $instance_id, self::VEHICLE_MODEL_TAXONOMY, 'Modelo', $selected_model, $selected_brand, true );
							?>
							<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
							<button type="submit" class="buscador-llantas-submit"><?php esc_html_e( 'Buscar Llantas', 'buscador-llantas' ); ?></button>
						</form>
					</div>
				<?php endif; ?>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Renderiza un selector de una taxonomia del buscador.
		 *
		 * @param string $instance_id  Identificador unico del shortcode.
		 * @param string $taxonomy     Taxonomia.
		 * @param string $label        Etiqueta.
		 * @param string $selected   Slug seleccionado.
		 * @param string $brand_slug Slug de marca para limitar los modelos.
		 * @param bool   $required   Si el selector es obligatorio.
		 */
		private function render_taxonomy_select( $instance_id, $taxonomy, $label, $selected, $brand_slug = '', $required = false ) {
			$terms = array();

			if ( self::VEHICLE_MODEL_TAXONOMY === $taxonomy && '' !== $brand_slug ) {
				$brand = get_term_by( 'slug', $brand_slug, self::VEHICLE_BRAND_TAXONOMY );
				if ( $brand && ! is_wp_error( $brand ) ) {
					$terms = $this->get_vehicle_models_for_brand( $brand->term_id );
				}
			} elseif ( self::VEHICLE_MODEL_TAXONOMY !== $taxonomy ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);
			}

			$is_error = is_wp_error( $terms );
			if ( $is_error ) {
				$terms = array();
			} elseif ( $this->is_tire_taxonomy( $taxonomy ) ) {
				$terms = $this->sort_terms_for_tire_search( $terms );
			}

			$is_model  = self::VEHICLE_MODEL_TAXONOMY === $taxonomy;
			$disabled  = $is_model && ( '' === $brand_slug || $is_error || empty( $terms ) );
			$placeholder = $is_error
				? __( 'No se pudieron cargar las opciones', 'buscador-llantas' )
				: ( $is_model && '' === $brand_slug
					? __( 'Primero selecciona una marca', 'buscador-llantas' )
					: ( $is_model && empty( $terms )
						? __( 'No hay modelos para esta marca', 'buscador-llantas' )
						: sprintf( __( 'Selecciona %s', 'buscador-llantas' ), strtolower( $label ) ) ) );
			?>
			<div class="buscador-llantas-field">
				<label for="<?php echo esc_attr( $instance_id . $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label>
				<select id="<?php echo esc_attr( $instance_id . $taxonomy ); ?>" name="<?php echo esc_attr( $taxonomy ); ?>" <?php disabled( $disabled ); ?> <?php echo $required ? 'required' : ''; ?>>
					<option value=""><?php echo esc_html( $placeholder ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
		}

		/**
		 * Retorna los modelos asignados a productos publicados de una marca.
		 *
		 * @param int $brand_id ID del termino de marca.
		 * @return array<int,WP_Term>|WP_Error
		 */
		private function get_vehicle_models_for_brand( $brand_id ) {
			global $wpdb;

			$sql = $wpdb->prepare(
				"SELECT DISTINCT model_terms.term_id
				FROM {$wpdb->term_relationships} AS brand_relationships
				INNER JOIN {$wpdb->term_taxonomy} AS brand_terms
					ON brand_terms.term_taxonomy_id = brand_relationships.term_taxonomy_id
					AND brand_terms.taxonomy = %s
				INNER JOIN {$wpdb->term_relationships} AS model_relationships
					ON model_relationships.object_id = brand_relationships.object_id
				INNER JOIN {$wpdb->term_taxonomy} AS model_terms
					ON model_terms.term_taxonomy_id = model_relationships.term_taxonomy_id
					AND model_terms.taxonomy = %s
				INNER JOIN {$wpdb->posts} AS products
					ON products.ID = brand_relationships.object_id
					AND products.post_type = 'product'
					AND products.post_status = 'publish'
				WHERE brand_terms.term_id = %d",
				self::VEHICLE_BRAND_TAXONOMY,
				self::VEHICLE_MODEL_TAXONOMY,
				absint( $brand_id )
			);
			$model_ids = $wpdb->get_col( $sql );

			if ( '' !== $wpdb->last_error ) {
				return new WP_Error( 'vehicle_models_query_failed', __( 'No se pudieron cargar los modelos.', 'buscador-llantas' ) );
			}

			if ( empty( $model_ids ) ) {
				return array();
			}

			return get_terms(
				array(
					'taxonomy'   => self::VEHICLE_MODEL_TAXONOMY,
					'hide_empty' => false,
					'include'    => array_map( 'absint', $model_ids ),
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
		}

		/**
		 * Retorna los modelos disponibles para una marca.
		 */
		public function ajax_get_vehicle_models() {
			check_ajax_referer( self::VEHICLE_MODELS_NONCE, 'nonce' );

			$brand_slug = isset( $_POST['marca_slug'] ) && is_string( $_POST['marca_slug'] ) ? sanitize_title( wp_unslash( $_POST['marca_slug'] ) ) : '';
			$brand      = '' !== $brand_slug ? get_term_by( 'slug', $brand_slug, self::VEHICLE_BRAND_TAXONOMY ) : false;

			if ( ! $brand || is_wp_error( $brand ) ) {
				wp_send_json_error( array( 'message' => __( 'Selecciona una marca válida.', 'buscador-llantas' ) ), 400 );
			}

			$models = $this->get_vehicle_models_for_brand( $brand->term_id );
			if ( is_wp_error( $models ) ) {
				wp_send_json_error( array( 'message' => __( 'No se pudieron cargar los modelos.', 'buscador-llantas' ) ), 500 );
			}

			$response = array();
			foreach ( $models as $model ) {
				$response[] = array(
					'name' => $model->name,
					'slug' => $model->slug,
				);
			}

			wp_send_json_success( $response );
		}

		/**
		 * Indica si una taxonomia pertenece al buscador de medidas.
		 */
		private function is_tire_taxonomy( $taxonomy ) {
			return isset( $this->taxonomies[ $taxonomy ] );
		}

		/**
		 * Devuelve los filtros de la busqueda activa, sin mezclar medidas y vehiculos.
		 *
		 * @return array<string,string>
		 */
		private function get_selected_search_filters() {
			$search_type = $this->get_requested_search_type();
			$taxonomies  = 'vehiculo' === $search_type ? $this->vehicle_taxonomies : ( 'medida' === $search_type ? $this->taxonomies : array() );
			$selected    = array();

			foreach ( array_keys( $taxonomies ) as $taxonomy ) {
				$value = $this->get_requested_term_slug( $taxonomy );
				if ( '' !== $value ) {
					$selected[ $taxonomy ] = $value;
				}
			}

			return $selected;
		}

		/**
		 * Retorna el tipo de busqueda solicitado; los enlaces anteriores siguen siendo por medida.
		 *
		 * @return string
		 */
		private function get_requested_search_type() {
			if ( ! isset( $_GET['tipo_busqueda'] ) ) {
				return 'medida';
			}

			$requested_type = wp_unslash( $_GET['tipo_busqueda'] );
			if ( ! is_string( $requested_type ) ) {
				return '';
			}

			$requested_type = sanitize_key( $requested_type );
			return in_array( $requested_type, array( 'medida', 'vehiculo' ), true ) ? $requested_type : '';
		}

		/**
		 * Aplica el filtrado por taxonomias en la consulta principal de la tienda.
		 *
		 * @param WP_Query $query Consulta principal.
		 */
		public function apply_filters_to_shop_query( $query ) {
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}

			if ( ! $this->is_shop_related_query( $query ) ) {
				return;
			}

			$selected = $this->get_selected_search_filters();

			if ( empty( $selected ) ) {
				return;
			}

			if ( ! $this->is_valid_filter_request() ) {
				return;
			}

			$query->set( 'post_type', 'product' );

			$new_tax_query = array();
			foreach ( $selected as $taxonomy => $slug ) {
				$new_tax_query[] = array(
					'taxonomy'         => $taxonomy,
					'field'            => 'slug',
					'terms'            => array( $slug ),
					'operator'         => 'IN',
					'include_children' => false,
				);
			}

			$existing_tax_query = $query->get( 'tax_query' );
			if ( ! is_array( $existing_tax_query ) ) {
				$existing_tax_query = array();
			}

			$existing_tax_query[] = array( 'relation' => 'AND' );
			$existing_tax_query   = array_merge( $existing_tax_query, $new_tax_query );

			$query->set( 'tax_query', $this->normalize_tax_query( $existing_tax_query ) );
		}

		/**
		 * Aplica los filtros al loop de productos de WooCommerce (hook woocommerce_product_query).
		 *
		 * @param WP_Query $q Query del loop de productos WooCommerce.
		 */
		public function apply_filters_to_wc_product_query( $q ) {
			$selected = $this->get_selected_search_filters();

			if ( empty( $selected ) ) {
				return;
			}

			if ( ! $this->is_valid_filter_request() ) {
				return;
			}

			$new_tax_query = array();
			foreach ( $selected as $taxonomy => $slug ) {
				$new_tax_query[] = array(
					'taxonomy'         => $taxonomy,
					'field'            => 'slug',
					'terms'            => array( $slug ),
					'operator'         => 'IN',
					'include_children' => false,
				);
			}

			$existing_tax_query = $q->get( 'tax_query' );
			if ( ! is_array( $existing_tax_query ) ) {
				$existing_tax_query = array();
			}

			$existing_tax_query = array_merge( $existing_tax_query, $new_tax_query );
			$q->set( 'tax_query', $this->normalize_tax_query( $existing_tax_query ) );
		}

		/**
		 * Agrega los filtros del plugin al widget de WooCommerce "Filtros Activos".
		 */
		public function render_active_filters_widget_items( $args = array(), $instance = array() ) {
			unset( $args, $instance );

			if ( ! $this->is_valid_filter_request() ) {
				return;
			}

			$labels   = array_merge( $this->taxonomies, $this->vehicle_taxonomies );
			$selected = $this->get_selected_search_filters();
			foreach ( $selected as $taxonomy => $term_slug ) {
				$term = get_term_by( 'slug', $term_slug, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				$remove_url = $this->build_remove_filter_url( $taxonomy, array() );

				echo '<li class="chosen chosen-' . esc_attr( $taxonomy ) . ' chosen-' . esc_attr( $taxonomy . '-' . $term_slug ) . '">';
				echo '<a rel="nofollow" aria-label="' . esc_attr__( 'Quitar filtro', 'buscador-llantas' ) . '" href="' . esc_url( $remove_url ) . '">';
				echo esc_html( $labels[ $taxonomy ] . ': ' . $term->name );
				echo '</a>';
				echo '</li>';
			}

		}

		/**
		 * Mantiene los query args del plugin cuando WooCommerce genera URLs del widget.
		 *
		 * @param string $link URL base del widget.
		 * @return string
		 */
		public function append_custom_query_args_to_widget_links( $link, $widget = null ) {
			unset( $widget );

			$args = $this->get_selected_search_filters();

			if ( ! empty( $args ) ) {
				$args['tipo_busqueda']       = $this->get_requested_search_type();
				$args[ self::NONCE_NAME ] = wp_create_nonce( self::NONCE_ACTION );
				$link                     = add_query_arg( $args, $link );
			}

			return $link;
		}

		/**
		 * Inyecta información de actualización del plugin desde GitHub Releases.
		 *
		 * @param object $transient Transient de actualizaciones.
		 * @return object
		 */
		public function inject_plugin_update( $transient ) {
			if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
				return $transient;
			}

			$release = $this->get_latest_github_release();
			if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
				return $transient;
			}

			if ( version_compare( self::VERSION, $release['version'], '>=' ) ) {
				return $transient;
			}

			$plugin_slug                            = plugin_basename( __FILE__ );
			$transient->response[ $plugin_slug ]    = (object) array(
				'slug'        => dirname( $plugin_slug ),
				'plugin'      => $plugin_slug,
				'new_version' => $release['version'],
				'package'     => $release['package'],
				'url'         => $this->get_github_repository_url(),
				'tested'      => isset( $release['tested'] ) ? $release['tested'] : '',
				'requires'    => isset( $release['requires'] ) ? $release['requires'] : '',
			);

			return $transient;
		}

		/**
		 * Entrega información del plugin al modal de detalles de actualización.
		 *
		 * @param false|object|array $result Resultado actual.
		 * @param string             $action Acción solicitada.
		 * @param object             $args   Argumentos de la API.
		 * @return false|object|array
		 */
		public function provide_plugin_information( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'buscador_llantas' !== $args->slug ) {
				return $result;
			}

			$release = $this->get_latest_github_release();
			if ( empty( $release['version'] ) ) {
				return $result;
			}

			return (object) array(
				'name'          => 'Buscador de Llantas Pro por Taxonomias',
				'slug'          => 'buscador_llantas',
				'plugin_name'   => 'Buscador de Llantas Pro por Taxonomias',
				'version'       => $release['version'],
				'author'        => 'Equipo Senior PULPO',
				'homepage'      => $this->get_github_repository_url(),
				'short_description' => 'Buscador avanzado de llantas por taxonomias para WooCommerce.',
				'sections'      => array(
					'description' => 'Buscador avanzado de llantas por taxonomias para WooCommerce.',
					'changelog'   => ! empty( $release['body'] ) ? wp_kses_post( wpautop( $release['body'] ) ) : 'Sin changelog disponible.',
				),
				'download_link' => isset( $release['package'] ) ? $release['package'] : '',
			);
		}

		/**
		 * Normaliza tax_query para asegurar relacion AND global.
		 *
		 * @param array $tax_query tax_query acumulada.
		 * @return array
		 */
		private function normalize_tax_query( $tax_query ) {
			$normalized = array( 'relation' => 'AND' );

			foreach ( $tax_query as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				if ( isset( $entry['relation'] ) ) {
					continue;
				}

				$normalized[] = $entry;
			}

			return $normalized;
		}

		/**
		 * Determina si la query corresponde a tienda o archivo de productos.
		 *
		 * @param WP_Query $query Query principal.
		 * @return bool
		 */
		private function is_shop_related_query( $query ) {
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				return true;
			}

			if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
				return true;
			}

			$post_type = $query->get( 'post_type' );
			if ( 'product' === $post_type ) {
				return true;
			}

			if ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Sanitiza y retorna valor de taxonomia recibido por GET.
		 *
		 * @param string $taxonomy Taxonomia.
		 * @return string
		 */
		private function get_requested_term_slug( $taxonomy ) {
			$slugs = $this->get_requested_term_slugs( $taxonomy );
			return empty( $slugs ) ? '' : $slugs[0];
		}

		/**
		 * Sanitiza y retorna multiples slugs de taxonomia por GET.
		 *
		 * @param string $taxonomy Taxonomia.
		 * @return array<int,string>
		 */
		private function get_requested_term_slugs( $taxonomy ) {
			if ( ! isset( $_GET[ $taxonomy ] ) ) {
				return array();
			}

			$raw_value = wp_unslash( $_GET[ $taxonomy ] );
			$items     = array();

			if ( is_array( $raw_value ) ) {
				$items = $raw_value;
			} elseif ( is_string( $raw_value ) && '' !== $raw_value ) {
				$items = explode( ',', $raw_value );
			}

			$slugs = array();
			foreach ( $items as $item ) {
				if ( ! is_scalar( $item ) ) {
					continue;
				}

				$slug = sanitize_title( (string) $item );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}

			return array_values( array_unique( $slugs ) );
		}

		/**
		 * Ordena terminos priorizando valores numericos (ej. 15, 16, 17).
		 *
		 * @param array<int,WP_Term> $terms Lista de terminos.
		 * @return array<int,WP_Term>
		 */
		private function sort_terms_for_tire_search( $terms ) {
			usort(
				$terms,
				function ( $a, $b ) {
					$a_num = $this->extract_numeric_value( $a->name );
					$b_num = $this->extract_numeric_value( $b->name );

					if ( null !== $a_num && null !== $b_num && $a_num !== $b_num ) {
						return ( $a_num < $b_num ) ? -1 : 1;
					}

					if ( null !== $a_num && null === $b_num ) {
						return -1;
					}

					if ( null === $a_num && null !== $b_num ) {
						return 1;
					}

					return strnatcasecmp( $a->name, $b->name );
				}
			);

			return $terms;
		}

		/**
		 * Extrae primer valor numerico de un texto.
		 *
		 * @param string $text Texto de entrada.
		 * @return float|null
		 */
		private function extract_numeric_value( $text ) {
			if ( preg_match( '/(\d+(?:[\.,]\d+)?)/', $text, $matches ) ) {
				$normalized = str_replace( ',', '.', $matches[1] );
				return (float) $normalized;
			}

			return null;
		}

		/**
		 * Construye URL para remover un filtro puntual manteniendo el resto.
		 *
		 * @param string            $taxonomy  Taxonomia actual.
		 * @param array<int,string> $remaining Slugs restantes.
		 * @return string
		 */
		private function build_remove_filter_url( $taxonomy, $remaining ) {
			$remove_args = array( $taxonomy );
			if ( self::VEHICLE_BRAND_TAXONOMY === $taxonomy ) {
				$remove_args[] = self::VEHICLE_MODEL_TAXONOMY;
			}
			$url = remove_query_arg( $remove_args, $this->get_current_url() );

			if ( ! empty( $remaining ) ) {
				$url = add_query_arg( array( $taxonomy => $remaining ), $url );
			}

			return $url;
		}

		/**
		 * Obtiene la URL del repositorio GitHub desde constante o filtro.
		 *
		 * @return string
		 */
		private function get_github_repository_url() {
			$url = defined( 'BUSCADOR_LLANTAS_GITHUB_REPOSITORY' ) ? BUSCADOR_LLANTAS_GITHUB_REPOSITORY : '';

			/**
			 * Permite definir la URL del repositorio GitHub del plugin.
			 *
			 * @param string $url URL del repositorio.
			 */
			$url = apply_filters( 'buscador_llantas_github_repository_url', $url );

			return is_string( $url ) ? trim( $url ) : '';
		}

		/**
		 * Obtiene un token opcional para consultas y descargas privadas desde GitHub.
		 *
		 * @return string
		 */
		private function get_github_token() {
			$token = defined( 'BUSCADOR_LLANTAS_GITHUB_TOKEN' ) ? BUSCADOR_LLANTAS_GITHUB_TOKEN : '';

			/**
			 * Permite definir un token GitHub para repositorios privados.
			 *
			 * @param string $token Token GitHub.
			 */
			$token = apply_filters( 'buscador_llantas_github_token', $token );

			return is_string( $token ) ? trim( $token ) : '';
		}

		/**
		 * Inyecta cabeceras de autenticacion cuando WordPress consulta o descarga desde GitHub.
		 *
		 * @param array  $args Argumentos HTTP.
		 * @param string $url  URL de la peticion.
		 * @return array
		 */
		public function inject_github_auth_headers( $args, $url ) {
			$token     = $this->get_github_token();
			$repo_path = $this->extract_github_repo_path( $this->get_github_repository_url() );

			if ( '' === $token || '' === $repo_path || ! is_string( $url ) ) {
				return $args;
			}

			if ( false === strpos( $url, 'github.com' ) ) {
				return $args;
			}

			if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
				$args['headers'] = array();
			}

			$args['headers']['Authorization'] = 'Bearer ' . $token;
			$args['headers']['User-Agent']    = 'buscador-llantas-plugin-updater';

			if ( false !== strpos( $url, 'api.github.com/repos/' . $repo_path . '/releases/assets/' ) ) {
				$args['headers']['Accept'] = 'application/octet-stream';
			} elseif ( false !== strpos( $url, 'api.github.com/repos/' . $repo_path . '/releases/latest' ) ) {
				$args['headers']['Accept'] = 'application/vnd.github+json';
			}

			return $args;
		}

		/**
		 * Obtiene la informacion de la ultima release en GitHub.
		 *
		 * @return array<string,string>
		 */
		private function get_latest_github_release() {
			$repo_url = $this->get_github_repository_url();
			if ( '' === $repo_url ) {
				return array();
			}

			$repo_path = $this->extract_github_repo_path( $repo_url );
			if ( '' === $repo_path ) {
				return array();
			}

			$cache_key = 'buscador-llantas_release_' . md5( $repo_path );
			$cached    = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$response = wp_remote_get(
				'https://api.github.com/repos/' . $repo_path . '/releases/latest',
				array(
					'headers' => $this->get_github_api_headers(),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return array();
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
				return array();
			}

			$release = array(
				'version'  => ltrim( (string) $body['tag_name'], 'vV' ),
				'body'     => isset( $body['body'] ) ? (string) $body['body'] : '',
				'package'  => $this->find_release_package_url( $body ),
				'requires' => '',
				'tested'   => '',
			);

			set_transient( $cache_key, $release, HOUR_IN_SECONDS );

			return $release;
		}

		/**
		 * Extrae owner/repo desde una URL GitHub SSH o HTTPS.
		 *
		 * @param string $url URL del repositorio.
		 * @return string
		 */
		private function extract_github_repo_path( $url ) {
			if ( 0 === strpos( $url, 'git@github.com:' ) ) {
				$path = substr( $url, strlen( 'git@github.com:' ) );
				$path = preg_replace( '/\.git$/', '', $path );

				return is_string( $path ) ? trim( $path, '/' ) : '';
			}

			$parts = wp_parse_url( $url );
			if ( empty( $parts['host'] ) || false === strpos( (string) $parts['host'], 'github.com' ) || empty( $parts['path'] ) ) {
				return '';
			}

			$path = trim( (string) $parts['path'], '/' );
			$path = preg_replace( '/\.git$/', '', $path );

			return is_string( $path ) ? $path : '';
		}

		/**
		 * Busca un asset instalable para WordPress dentro de la release.
		 *
		 * @param array<string,mixed> $release_data Datos de la release.
		 * @return string
		 */
		private function find_release_package_url( $release_data ) {
			$repo_path  = $this->extract_github_repo_path( $this->get_github_repository_url() );
			$has_token  = '' !== $this->get_github_token();

			if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
				foreach ( $release_data['assets'] as $asset ) {
					if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
						continue;
					}

					if ( 'buscador_llantas.zip' === $asset['name'] ) {
						if ( $has_token && '' !== $repo_path && ! empty( $asset['id'] ) ) {
							return 'https://api.github.com/repos/' . $repo_path . '/releases/assets/' . absint( $asset['id'] );
						}

						return (string) $asset['browser_download_url'];
					}
				}
			}

			return '';
		}

		/**
		 * Cabeceras base para consumir la API de GitHub.
		 *
		 * @return array<string,string>
		 */
		private function get_github_api_headers() {
			$headers = array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'buscador-llantas-plugin-updater',
			);

			$token = $this->get_github_token();
			if ( '' !== $token ) {
				$headers['Authorization'] = 'Bearer ' . $token;
			}

			return $headers;
		}

		/**
		 * Valida nonce para aplicar filtros.
		 *
		 * @return bool
		 */
		private function is_valid_filter_request() {
			if ( ! isset( $_GET[ self::NONCE_NAME ] ) ) {
				return false;
			}

			$nonce = sanitize_text_field( wp_unslash( $_GET[ self::NONCE_NAME ] ) );
			return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
		}

		/**
		 * Devuelve URL actual completa.
		 *
		 * @return string
		 */
		private function get_current_url() {
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
			$uri = is_string( $uri ) ? $uri : '/';

			return home_url( $uri );
		}
	}

	new Buscador_Llantas_Pro_Taxonomias();
}
