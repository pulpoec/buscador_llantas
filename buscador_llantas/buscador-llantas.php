<?php
/**
 * Plugin Name: Buscador de Llantas Pro por Taxonomias
 * Plugin URI: https://pulpo.ec
 * Description: Buscador avanzado de llantas por taxonomias (ancho, perfil y diametro) para WooCommerce.
 * Version: 1.2.0
 * Author: Equipo Senior PULPO
 * License: GPLv2 or later
 * Text Domain: buscador-llantas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Buscador_Llantas_Pro_Taxonomias' ) ) {
	final class Buscador_Llantas_Pro_Taxonomias {
		const VERSION      = '1.2.0';
		const NONCE_ACTION = 'buscador_llantas_filtrar';
		const NONCE_NAME   = 'buscador_llantas_nonce';

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

		public function __construct() {
			add_action( 'init', array( $this, 'register_taxonomies' ) );
			add_shortcode( 'buscador_llantas', array( $this, 'render_shortcode' ) );
			add_action( 'pre_get_posts', array( $this, 'apply_filters_to_shop_query' ), 20 );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_plugin_update' ) );
			add_filter( 'plugins_api', array( $this, 'provide_plugin_information' ), 20, 3 );

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
		}

		/**
		 * Registra y define estilos del formulario.
		 */
		public function register_assets() {
			$css = '
			.buscador-llantas-wrap {
				max-width: 1080px;
				margin: 1.25rem auto;
				padding: 1.1rem;
				background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
				border: 1px solid #e6edf5;
				border-radius: 16px;
				box-shadow: 0 8px 28px rgba(9, 30, 66, 0.08);
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
				font-family: "Segoe UI", "Helvetica Neue", sans-serif;
				font-size: 0.79rem;
				font-weight: 600;
				letter-spacing: 0.03em;
				text-transform: uppercase;
				color: #4d5b6a;
				margin: 0 0 0.35rem;
			}

			.buscador-llantas-field select {
				width: 100%;
				height: 48px;
				padding: 0 0.85rem;
				border-radius: 12px;
				border: 1px solid #d8e1eb;
				background: #fff;
				font-family: "Segoe UI", "Helvetica Neue", sans-serif;
				font-size: 0.96rem;
				color: #1d2a38;
				outline: none;
				transition: border-color 0.2s ease, box-shadow 0.2s ease;
			}

			.buscador-llantas-field select:focus {
				border-color: #1f7bd8;
				box-shadow: 0 0 0 3px rgba(31, 123, 216, 0.18);
			}

			.buscador-llantas-submit {
				flex: 0 0 auto;
				height: 48px;
				margin-top: 1.35rem;
				padding: 0 1.35rem;
				border: 0;
				border-radius: 12px;
				background: #ff5a2a;
				color: #fff;
				font-family: "Segoe UI", "Helvetica Neue", sans-serif;
				font-size: 0.95rem;
				font-weight: 700;
				cursor: pointer;
				transition: transform 0.15s ease, box-shadow 0.2s ease, background-color 0.2s ease;
				box-shadow: 0 8px 18px rgba(255, 90, 42, 0.25);
			}

			.buscador-llantas-submit:hover,
			.buscador-llantas-submit:focus {
				background: #e5481d;
				transform: translateY(-1px);
				box-shadow: 0 10px 20px rgba(229, 72, 29, 0.33);
			}

			.buscador-llantas-clear {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				height: 48px;
				margin-top: 1.35rem;
				padding: 0 1.1rem;
				border-radius: 12px;
				border: 1px solid #c7d4e2;
				background: #ffffff;
				color: #2f4050;
				font-family: "Segoe UI", "Helvetica Neue", sans-serif;
				font-size: 0.92rem;
				font-weight: 600;
				text-decoration: none;
				transition: all 0.2s ease;
			}

			.buscador-llantas-clear:hover,
			.buscador-llantas-clear:focus {
				background: #f3f7fb;
				border-color: #afc2d8;
				color: #1f2b38;
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
			}
			';

			wp_register_style( 'buscador-llantas-style', false, array(), self::VERSION );
			wp_add_inline_style( 'buscador-llantas-style', $css );
		}

		/**
		 * Renderiza el shortcode [buscador_llantas].
		 *
		 * @return string
		 */
		public function render_shortcode() {
			$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : get_post_type_archive_link( 'product' );
			$shop_url = $shop_url ? $shop_url : home_url( '/' );

			$current = array();
			foreach ( array_keys( $this->taxonomies ) as $taxonomy ) {
				$current[ $taxonomy ] = $this->get_requested_term_slug( $taxonomy );
			}

			wp_enqueue_style( 'buscador-llantas-style' );

			ob_start();
			?>
			<div class="buscador-llantas-wrap">
				<form class="buscador-llantas-form" method="get" action="<?php echo esc_url( $shop_url ); ?>">
					<?php foreach ( $this->taxonomies as $taxonomy => $label ) : ?>
						<div class="buscador-llantas-field">
							<label for="<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label>
							<select id="<?php echo esc_attr( $taxonomy ); ?>" name="<?php echo esc_attr( $taxonomy ); ?>">
								<option value=""><?php echo esc_html( sprintf( __( 'Selecciona %s', 'buscador-llantas' ), strtolower( $label ) ) ); ?></option>
								<?php
								$terms = get_terms(
									array(
										'taxonomy'   => $taxonomy,
										'hide_empty' => false,
										'orderby'    => 'name',
										'order'      => 'ASC',
									)
								);

								if ( ! is_wp_error( $terms ) ) :
									$terms = $this->sort_terms_for_tire_search( $terms );
									foreach ( $terms as $term ) :
										?>
										<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current[ $taxonomy ], $term->slug ); ?>>
											<?php echo esc_html( $term->name ); ?>
										</option>
										<?php
									endforeach;
								endif;
								?>
							</select>
						</div>
					<?php endforeach; ?>

					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<input type="hidden" name="post_type" value="product" />
					<button type="submit" class="buscador-llantas-submit"><?php esc_html_e( 'Buscar Llantas', 'buscador-llantas' ); ?></button>
					<a class="buscador-llantas-clear" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Limpiar filtros', 'buscador-llantas' ); ?></a>
				</form>
			</div>
			<?php
			return (string) ob_get_clean();
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

			$selected = array();
			foreach ( array_keys( $this->taxonomies ) as $taxonomy ) {
				$value = $this->get_requested_term_slug( $taxonomy );
				if ( '' !== $value ) {
					$selected[ $taxonomy ] = $value;
				}
			}

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

			$existing_tax_query = $query->get( 'tax_query' );
			if ( ! is_array( $existing_tax_query ) ) {
				$existing_tax_query = array();
			}

			$existing_tax_query[] = array( 'relation' => 'AND' );
			$existing_tax_query   = array_merge( $existing_tax_query, $new_tax_query );

			$query->set( 'tax_query', $this->normalize_tax_query( $existing_tax_query ) );
		}

		/**
		 * Agrega los filtros del plugin al widget de WooCommerce "Filtros Activos".
		 */
		public function render_active_filters_widget_items( $args = array(), $instance = array() ) {
			unset( $args, $instance );

			if ( ! $this->is_valid_filter_request() ) {
				return;
			}

			foreach ( $this->taxonomies as $taxonomy => $label ) {
				$term_slug = $this->get_requested_term_slug( $taxonomy );
				if ( '' === $term_slug ) {
					continue;
				}

				$term = get_term_by( 'slug', $term_slug, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				$remove_url = $this->build_remove_filter_url( $taxonomy, array() );

				echo '<li class="chosen chosen-' . esc_attr( $taxonomy ) . ' chosen-' . esc_attr( $taxonomy . '-' . $term_slug ) . '">';
				echo '<a rel="nofollow" aria-label="' . esc_attr__( 'Quitar filtro', 'buscador-llantas' ) . '" href="' . esc_url( $remove_url ) . '">';
				echo esc_html( $label . ': ' . $term->name );
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

			$args = array();

			foreach ( array_keys( $this->taxonomies ) as $taxonomy ) {
				$value = $this->get_requested_term_slug( $taxonomy );
				if ( '' !== $value ) {
					$args[ $taxonomy ] = $value;
				}
			}

			if ( ! empty( $args ) ) {
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
			$url = remove_query_arg( array( $taxonomy ), $this->get_current_url() );

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
		 * Obtiene la informacion de la ultima release publica en GitHub.
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

			$cache_key = 'buscador_llantas_release_' . md5( $repo_path );
			$cached    = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$response = wp_remote_get(
				'https://api.github.com/repos/' . $repo_path . '/releases/latest',
				array(
					'headers' => array(
						'Accept'     => 'application/vnd.github+json',
						'User-Agent' => 'buscador-llantas-plugin-updater',
					),
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
		 * Extrae owner/repo desde una URL GitHub.
		 *
		 * @param string $url URL del repositorio.
		 * @return string
		 */
		private function extract_github_repo_path( $url ) {
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
			if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
				foreach ( $release_data['assets'] as $asset ) {
					if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
						continue;
					}

					if ( 'buscador_llantas.zip' === $asset['name'] ) {
						return (string) $asset['browser_download_url'];
					}
				}
			}

			return '';
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
