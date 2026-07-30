<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Como cada site organiza o campo "Autor" de um jeito (taxonomia própria,
 * ACF, meta simples), essa classe centraliza a resolução do autor a partir
 * de um product_id, usando a configuração salva em Configurações do plugin.
 */
class BDI_Author {

	public static function get_settings() {
		$defaults = array(
			'type' => 'taxonomy', // 'taxonomy' ou 'meta'
			'key'  => 'autor',
		);

		$saved = get_option( 'bdi_author_settings', array() );

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Retorna um array de nomes de autor para o produto (pode ter mais de um
	 * autor se for taxonomia com múltiplos termos).
	 */
	public static function get_authors_for_product( $product_id ) {
		$settings = self::get_settings();
		$authors  = array();

		if ( 'taxonomy' === $settings['type'] ) {
			$terms = get_the_terms( $product_id, $settings['key'] );

			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$authors[] = $term->name;
				}
			}
		} else {
			$value = get_post_meta( $product_id, $settings['key'], true );

			if ( $value ) {
				$authors[] = is_array( $value ) ? implode( ', ', $value ) : $value;
			}
		}

		return $authors;
	}

	public static function get_display_authors( $product_id ) {
		$authors = self::get_authors_for_product( $product_id );

		return $authors ? implode( ', ', $authors ) : __( 'Sem autor definido', 'book-download-insights' );
	}

	/**
	 * Agrega um mapa [product_id => total_downloads] em [autor => total_downloads].
	 * Um produto com múltiplos autores soma o total pra cada autor listado.
	 */
	public static function aggregate_by_author( array $downloads_by_product ) {
		$by_author = array();

		foreach ( $downloads_by_product as $product_id => $total ) {
			$authors = self::get_authors_for_product( $product_id );

			if ( ! $authors ) {
				$authors = array( __( 'Sem autor definido', 'book-download-insights' ) );
			}

			foreach ( $authors as $author ) {
				if ( ! isset( $by_author[ $author ] ) ) {
					$by_author[ $author ] = 0;
				}
				$by_author[ $author ] += $total;
			}
		}

		arsort( $by_author );

		return $by_author;
	}

	/**
	 * Lista as taxonomias registradas em produtos, pra popular o select
	 * na tela de configurações (facilita achar a taxonomia certa de Autor).
	 */
	public static function get_product_taxonomies() {
		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		$options    = array();

		foreach ( $taxonomies as $tax ) {
			$options[ $tax->name ] = $tax->label;
		}

		return $options;
	}
}
