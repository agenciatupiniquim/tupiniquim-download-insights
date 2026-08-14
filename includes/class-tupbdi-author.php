<?php

namespace Tupiniquim\BookDownloadsInsights;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Classe responsável por resolver e agregar informações de autores.
 *
 * Como cada site organiza o campo "Autor" de um jeito (taxonomia própria,
 * ACF, meta simples), essa classe centraliza a resolução do autor a partir
 * de um product_id, usando a configuração salva em Configurações do plugin.
 *
 * @package Tupiniquim\BookDownloadsInsights
 */
class Author {

	/**
	 * Obtém as configurações de autor armazenadas nas opções do WordPress.
	 *
	 * @return array<string, string> Array com 'type' ('taxonomy' ou 'meta') e 'key' (nome do campo).
	 */
	public static function get_settings(): array {
		$defaults = [
			'type' => 'taxonomy', // 'taxonomy' ou 'meta'
			'key'  => 'autor',
		];

		$saved = get_option('tupbdi_author_settings', []);

		return wp_parse_args($saved, $defaults);
	}

	/**
	 * Retorna um array de nomes de autor para o produto.
	 *
	 * Pode ter mais de um autor se for taxonomia com múltiplos termos.
	 *
	 * @param int $product_id ID do produto.
	 *
	 * @return string[] Array com nomes de autores.
	 */
	public static function get_authors_for_product(int $product_id): array {
		$settings = self::get_settings();
		$authors  = [];

		if ('taxonomy' === $settings['type']) {
			$terms = get_the_terms($product_id, $settings['key']);

			if ($terms && !is_wp_error($terms)) {
				foreach ($terms as $term) {
					$authors[] = $term->name;
				}
			}
		} else {
			$value = get_post_meta($product_id, $settings['key'], true);

			if ($value) {
				$authors[] = is_array($value) ? implode(', ', $value) : $value;
			}
		}

		return $authors;
	}

	/**
	 * Obtém os autores de um produto em formato para exibição.
	 *
	 * @param int $product_id ID do produto.
	 *
	 * @return string String com nomes de autores separados por vírgula.
	 */
	public static function get_display_authors(int $product_id): string {
		$authors = self::get_authors_for_product($product_id);

		return $authors ? implode(', ', $authors) : __('Sem autor definido', 'tupiniquim-book-downloads-insights');
	}

	/**
	 * Agrega um mapa [product_id => total_downloads] em [autor => total_downloads].
	 *
	 * Um produto com múltiplos autores soma o total pra cada autor listado.
	 *
	 * @param array<int, int> $downloads_by_product Mapa de product_id => total de downloads.
	 *
	 * @return array<string, int> Mapa de nome do autor => total de downloads, ordenado decrescente.
	 */
	public static function aggregate_by_author(array $downloads_by_product): array {
		$by_author = [];

		foreach ($downloads_by_product as $product_id => $total) {
			$authors = self::get_authors_for_product($product_id);

			if (!$authors) {
				$authors = [__('Sem autor definido', 'tupiniquim-book-downloads-insights')];
			}

			foreach ($authors as $author) {
				if (!isset($by_author[$author])) {
					$by_author[$author] = 0;
				}
				$by_author[$author] += $total;
			}
		}

		arsort($by_author);

		return $by_author;
	}

	/**
	 * Lista as taxonomias registradas em produtos.
	 *
	 * Usada para popular o select na tela de configurações, facilitando achar
	 * a taxonomia certa de Autor.
	 *
	 * @return array<string, string> Mapa de taxonomy name => label.
	 */
	public static function get_product_taxonomies(): array {
		$taxonomies = get_object_taxonomies('product', 'objects');
		$options    = [];

		foreach ($taxonomies as $tax) {
			$options[$tax->name] = $tax->label;
		}

		return $options;
	}
}
