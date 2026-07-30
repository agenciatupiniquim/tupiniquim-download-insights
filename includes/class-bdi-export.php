<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDI_Export {

	public static function init() {
		add_action( 'admin_post_bdi_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para exportar esses dados.', 'book-download-insights' ) );
		}

		check_admin_referer( 'bdi_export_csv' );

		$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		global $wpdb;
		$table = BDI_DB::table_name();

		$where  = '1=1';
		$params = array();

		if ( $date_from ) {
			$where   .= ' AND downloaded_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}
		if ( $date_to ) {
			$where   .= ' AND downloaded_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ( $product_id ) {
			$where   .= ' AND product_id = %d';
			$params[] = $product_id;
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY downloaded_at DESC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$rows = $wpdb->get_results( $sql );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=downloads-livros-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		// BOM pra abrir certinho acentuação no Excel.
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv(
			$output,
			array(
				__( 'Data/Hora', 'book-download-insights' ),
				__( 'Usuário', 'book-download-insights' ),
				__( 'E-mail', 'book-download-insights' ),
				__( 'Livro', 'book-download-insights' ),
				__( 'Autor', 'book-download-insights' ),
				__( 'Pedido', 'book-download-insights' ),
				__( 'IP', 'book-download-insights' ),
			)
		);

		foreach ( $rows as $row ) {
			$product_title = get_the_title( $row->product_id );
			$authors       = BDI_Author::get_display_authors( $row->product_id );

			fputcsv(
				$output,
				array(
					$row->downloaded_at,
					$row->user_name,
					$row->user_email,
					$product_title,
					$authors,
					$row->order_id,
					$row->ip_address,
				)
			);
		}

		fclose( $output );
		exit;
	}
}
