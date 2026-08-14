<?php

namespace Tupiniquim\BookDownloadsInsights;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Camada de acesso a dados.
 *
 * Cria a tabela e centraliza as queries usadas pelo dashboard
 * (evita espalhar SQL pelo resto do plugin).
 *
 * @package Tupiniquim\BookDownloadsInsights
 */
class DB {

	/**
	 * Obtém o nome da tabela usada pelo plugin.
	 *
	 * @return string Nome qualificado da tabela (com prefixo do WordPress).
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'tupbdi_downloads_log';
	}

	/**
	 * Cria a tabela customizada para armazenar logs de download.
	 *
	 * @return void
	 */
	public static function create_table(): void {
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
			file_name VARCHAR(190) NOT NULL DEFAULT '',
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			downloaded_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY user_id (user_id),
			KEY downloaded_at (downloaded_at)
		) {$charset_collate};";

		dbDelta($sql);
		self::ensure_table_columns();

		update_option('tupbdi_db_version', TUPBDI_VERSION);
	}

	/**
	 * Garante que colunas extras do log existam em instalações existentes.
	 *
	 * @return void
	 */
	public static function ensure_table_columns(): void {
		global $wpdb;

		$table_name = self::table_name();
		$columns    = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);

		if (!$columns) {
			return;
		}

		$existing = [];
		foreach ($columns as $column) {
			$existing[] = $column['Field'];
		}

		if (!in_array('download_id', $existing, true)) {
			$wpdb->query("ALTER TABLE {$table_name} ADD COLUMN download_id VARCHAR(50) NOT NULL DEFAULT ''");
		}

		if (!in_array('file_name', $existing, true)) {
			$wpdb->query("ALTER TABLE {$table_name} ADD COLUMN file_name VARCHAR(190) NOT NULL DEFAULT ''");
		}
	}

	/**
	 * Insere um novo registro de download no log.
	 *
	 * @param array<string, mixed> $data Dados do download (user_id, user_email, user_name, product_id, order_id, download_id, ip_address, downloaded_at).
	 *
	 * @return int|false O ID da linha inserida, ou false em caso de erro.
	 */
	public static function insert_log(array $data) {
		global $wpdb;

		$defaults = [
			'user_id'       => 0,
			'user_email'    => '',
			'user_name'     => '',
			'product_id'    => 0,
			'order_id'      => 0,
			'download_id'   => '',
			'ip_address'    => '',
			'downloaded_at' => current_time('mysql'),
		];

		$data = wp_parse_args($data, $defaults);

		$wpdb->insert(self::table_name(), $data);

		return $wpdb->insert_id;
	}

	/**
	 * Monta a cláusula WHERE comum (intervalo de datas + produto) usada nas queries do dashboard.
	 *
	 * @param string $date_from Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to   Data final no formato 'Y-m-d' (opcional).
	 * @param int    $product_id ID do produto para filtrar (opcional).
	 *
	 * @return array<int, mixed> Array com [0 => $where_string, 1 => $params_array].
	 */
	protected static function build_where(string $date_from = '', string $date_to = '', int $product_id = 0): array {
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

		return [$where, $params];
	}

	/**
	 * Obtém o número total de downloads em um período.
	 *
	 * @param string $date_from   Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to     Data final no formato 'Y-m-d' (opcional).
	 * @param int    $product_id  ID do produto para filtrar (opcional).
	 *
	 * @return int Número total de downloads.
	 */
	public static function get_total_downloads(string $date_from = '', string $date_to = '', int $product_id = 0): int {
		global $wpdb;
		$table = self::table_name();

		[$where, $params] = self::build_where($date_from, $date_to, $product_id);

		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		if ($params) {
			$sql = $wpdb->prepare($sql, $params);
		}

		return (int) $wpdb->get_var($sql);
	}

	/**
	 * Obtém o número de usuários únicos que baixaram nos filtros especificados.
	 *
	 * @param string $date_from   Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to     Data final no formato 'Y-m-d' (opcional).
	 * @param int    $product_id  ID do produto para filtrar (opcional).
	 *
	 * @return int Número de usuários únicos.
	 */
	public static function get_unique_users(string $date_from = '', string $date_to = '', int $product_id = 0): int {
		global $wpdb;
		$table = self::table_name();

		[$where, $params] = self::build_where($date_from, $date_to, $product_id);

		$sql = "SELECT COUNT(DISTINCT user_email) FROM {$table} WHERE {$where}";
		if ($params) {
			$sql = $wpdb->prepare($sql, $params);
		}

		return (int) $wpdb->get_var($sql);
	}

	/**
	 * Obtém os produtos mais baixados em um período.
	 *
	 * @param int    $limit     Número máximo de resultados.
	 * @param string $date_from Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to   Data final no formato 'Y-m-d' (opcional).
	 *
	 * @return array<\stdClass> Array com objects contendo product_id e total.
	 */
	public static function get_top_books(int $limit = 10, string $date_from = '', string $date_to = ''): array {
		global $wpdb;
		$table = self::table_name();

		[$where, $params] = self::build_where($date_from, $date_to);

		$sql = "SELECT product_id, COUNT(*) as total
				FROM {$table}
				WHERE {$where}
				GROUP BY product_id
				ORDER BY total DESC
				LIMIT %d";

		$params[] = $limit;

		return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
	}

	/**
	 * Obtém os downloads mais recentes com paginação.
	 *
	 * @param int    $per_page   Itens por página.
	 * @param int    $page       Número da página (começa em 1).
	 * @param string $date_from  Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to    Data final no formato 'Y-m-d' (opcional).
	 * @param int    $product_id ID do produto para filtrar (opcional).
	 *
	 * @return array<\stdClass> Array com registros de download.
	 */
	public static function get_recent_downloads(int $per_page = 20, int $page = 1, string $date_from = '', string $date_to = '', int $product_id = 0): array {
		global $wpdb;
		$table = self::table_name();

		[$where, $params] = self::build_where($date_from, $date_to, $product_id);

		$offset = max(0, ($page - 1) * $per_page);

		$sql = "SELECT * FROM {$table}
				WHERE {$where}
				ORDER BY downloaded_at DESC
				LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
	}

	/**
	 * Obtém o número total de downloads recentes.
	 *
	 * Alias para get_total_downloads para manter compatibilidade.
	 *
	 * @param string $date_from   Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to     Data final no formato 'Y-m-d' (opcional).
	 * @param int    $product_id  ID do produto para filtrar (opcional).
	 *
	 * @return int Número total de downloads.
	 */
	public static function get_recent_downloads_count(string $date_from = '', string $date_to = '', int $product_id = 0): int {
		return self::get_total_downloads($date_from, $date_to, $product_id);
	}

	/**
	 * Obtém downloads agrupados por dia para gráfico de tendência.
	 *
	 * @param string $date_from Data inicial no formato 'Y-m-d'.
	 * @param string $date_to   Data final no formato 'Y-m-d'.
	 *
	 * @return array<\stdClass> Array com objects contendo 'day' e 'total'.
	 */
	public static function get_downloads_by_day(string $date_from, string $date_to): array {
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

		return $wpdb->get_results($sql) ?: [];
	}

	/**
	 * Obtém contagem de downloads por produto.
	 *
	 * Útil para agregar dados por autor em PHP, já que autor pode ser taxonomia
	 * ou meta field, impossibilitando JOIN direto.
	 *
	 * @param string $date_from Data inicial no formato 'Y-m-d' (opcional).
	 * @param string $date_to   Data final no formato 'Y-m-d' (opcional).
	 *
	 * @return array<int, int> Mapa de product_id => total de downloads.
	 */
	public static function get_download_counts_by_product(string $date_from = '', string $date_to = ''): array {
		global $wpdb;
		$table = self::table_name();

		[$where, $params] = self::build_where($date_from, $date_to);

		$sql = "SELECT product_id, COUNT(*) as total FROM {$table} WHERE {$where} GROUP BY product_id";
		if ($params) {
			$sql = $wpdb->prepare($sql, $params);
		}

		$results = $wpdb->get_results($sql);

		$map = [];
		if ($results) {
			foreach ($results as $row) {
				$map[(int) $row->product_id] = (int) $row->total;
			}
		}

		return $map;
	}
}
