<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Camada de acesso a dados: cria a tabela e centraliza as queries usadas
 * pelo dashboard (evita espalhar SQL pelo resto do plugin).
 */
class BDI_DB {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'bdi_downloads_log';
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_email VARCHAR(190) NOT NULL DEFAULT '',
			user_name VARCHAR(190) NOT NULL DEFAULT '',
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			download_id VARCHAR(50) NOT NULL DEFAULT '',
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			downloaded_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY user_id (user_id),
			KEY downloaded_at (downloaded_at)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'bdi_db_version', BDI_VERSION );
	}

	public static function insert_log( array $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'       => 0,
			'user_email'    => '',
			'user_name'     => '',
			'product_id'    => 0,
			'order_id'      => 0,
			'download_id'   => '',
			'ip_address'    => '',
			'downloaded_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert( self::table_name(), $data );

		return $wpdb->insert_id;
	}

	/**
	 * Monta o WHERE comum (intervalo de datas + produto) usado nas queries do dashboard.
	 */
	protected static function build_where( $date_from = '', $date_to = '', $product_id = 0 ) {
		global $wpdb;

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

		return array( $where, $params );
	}

	public static function get_total_downloads( $date_from = '', $date_to = '', $product_id = 0 ) {
		global $wpdb;
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $date_from, $date_to, $product_id );

		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	public static function get_unique_users( $date_from = '', $date_to = '', $product_id = 0 ) {
		global $wpdb;
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $date_from, $date_to, $product_id );

		$sql = "SELECT COUNT(DISTINCT user_email) FROM {$table} WHERE {$where}";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	public static function get_top_books( $limit = 10, $date_from = '', $date_to = '' ) {
		global $wpdb;
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $date_from, $date_to );

		$sql = "SELECT product_id, COUNT(*) as total
				FROM {$table}
				WHERE {$where}
				GROUP BY product_id
				ORDER BY total DESC
				LIMIT %d";

		$params[] = $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function get_recent_downloads( $per_page = 20, $page = 1, $date_from = '', $date_to = '', $product_id = 0 ) {
		global $wpdb;
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $date_from, $date_to, $product_id );

		$offset = max( 0, ( $page - 1 ) * $per_page );

		$sql = "SELECT * FROM {$table}
				WHERE {$where}
				ORDER BY downloaded_at DESC
				LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function get_recent_downloads_count( $date_from = '', $date_to = '', $product_id = 0 ) {
		return self::get_total_downloads( $date_from, $date_to, $product_id );
	}

	/**
	 * Downloads por dia, para o gráfico de tendência.
	 */
	public static function get_downloads_by_day( $date_from, $date_to ) {
		global $wpdb;
		$table = self::table_name();

		$sql = $wpdb->prepare(
			"SELECT DATE(downloaded_at) as day, COUNT(*) as total
			 FROM {$table}
			 WHERE downloaded_at BETWEEN %s AND %s
			 GROUP BY DATE(downloaded_at)
			 ORDER BY day ASC",
			$date_from . ' 00:00:00',
			$date_to . ' 23:59:59'
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Todos os product_ids que já tiveram download (usado para agregar por autor em PHP,
	 * já que autor pode ser taxonomia ou meta field, então não dá pra fazer JOIN direto).
	 */
	public static function get_download_counts_by_product( $date_from = '', $date_to = '' ) {
		global $wpdb;
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $date_from, $date_to );

		$sql = "SELECT product_id, COUNT(*) as total FROM {$table} WHERE {$where} GROUP BY product_id";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$results = $wpdb->get_results( $sql );

		$map = array();
		foreach ( $results as $row ) {
			$map[ (int) $row->product_id ] = (int) $row->total;
		}

		return $map;
	}
}
