<?php

namespace Tupiniquim\BookDownloadsInsights;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Gerencia a exportação de dados de downloads em formato CSV.
 *
 * Fornece funcionalidade para exportar logs de download com dados
 * de usuário, produto, autor e IP.
 *
 * @package Tupiniquim\BookDownloadsInsights
 */
class Export {

	/**
	 * Inicializa os hooks de exportação.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action('admin_post_tupbdi_export_csv', [self::class, 'export_csv']);
	}

	/**
	 * Processa a exportação de dados em CSV.
	 *
	 * Valida permissões, aplica filtros e envia arquivo CSV para download.
	 *
	 * @return void
	 */
	public static function export_csv(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Você não tem permissão para exportar esses dados.', 'tupiniquim-book-downloads-insights'));
		}

		check_admin_referer('tupbdi_export_csv');

		$date_from  = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		$date_to    = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;

		global $wpdb;
		$table = DB::table_name();

		$where  = '1=1';
		$params = [];

		if ($date_from) {
			$where   .= ' AND downloaded_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}
		if ($date_to) {
			$where   .= ' AND downloaded_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ($product_id) {
			$where   .= ' AND product_id = %d';
			$params[] = $product_id;
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY downloaded_at DESC";
		if ($params) {
			$sql = $wpdb->prepare($sql, $params);
		}

		$rows = $wpdb->get_results($sql);

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=downloads-livros-' . gmdate('Y-m-d') . '.csv');

		$output = fopen('php://output', 'w');

		// BOM para abrir certinho acentuação no Excel.
		fwrite($output, "\xEF\xBB\xBF");

		fputcsv(
			$output,
			[
				__('Data/Hora', 'tupiniquim-book-downloads-insights'),
				__('Usuário', 'tupiniquim-book-downloads-insights'),
				__('E-mail', 'tupiniquim-book-downloads-insights'),
				__('Livro', 'tupiniquim-book-downloads-insights'),
				__('Autor', 'tupiniquim-book-downloads-insights'),
				__('Pedido', 'tupiniquim-book-downloads-insights'),
				__('IP', 'tupiniquim-book-downloads-insights'),
			]
		);

		if ($rows) {
			foreach ($rows as $row) {
				$product_title = get_the_title($row->product_id);
				$authors       = Author::get_display_authors($row->product_id);

				fputcsv(
					$output,
					[
						$row->downloaded_at,
						$row->user_name,
						$row->user_email,
						$product_title,
						$authors,
						$row->order_id,
						$row->ip_address,
					]
				);
			}
		}

		fclose($output);
		exit;
	}
}
