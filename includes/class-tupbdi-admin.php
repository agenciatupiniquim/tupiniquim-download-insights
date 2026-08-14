<?php

namespace Tupiniquim\BookDownloadsInsights;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Gerencia o painel administrativo do plugin.
 *
 * Responsável por registrar menus, enfileirar assets e renderizar as
 * telas de dashboard e configurações.
 *
 * @package Tupiniquim\BookDownloadsInsights
 */
class Admin {

	/**
	 * Capacidade necessária para acessar as funcionalidades do admin.
	 *
	 * @const string
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Inicializa os hooks do painel administrativo.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action('admin_menu', [self::class, 'add_menu']);
		add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
		add_action('admin_init', [self::class, 'handle_settings_save']);
	}

	/**
	 * Adiciona os menus no painel administrativo.
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_menu_page(
			__('Downloads de Livros', 'tupiniquim-book-downloads-insights'),
			__('Downloads de Livros', 'tupiniquim-book-downloads-insights'),
			self::CAPABILITY,
			'tupbdi-dashboard',
			[self::class, 'render_dashboard'],
			'dashicons-download',
			56
		);

		add_submenu_page(
			'tupbdi-dashboard',
			__('Dashboard', 'tupiniquim-book-downloads-insights'),
			__('Dashboard', 'tupiniquim-book-downloads-insights'),
			self::CAPABILITY,
			'tupbdi-dashboard',
			[self::class, 'render_dashboard']
		);

		add_submenu_page(
			'tupbdi-dashboard',
			__('Configurações', 'tupiniquim-book-downloads-insights'),
			__('Configurações', 'tupiniquim-book-downloads-insights'),
			self::CAPABILITY,
			'tupbdi-settings',
			[self::class, 'render_settings']
		);
	}

	/**
	 * Enfileira estilos e scripts necessários para as páginas do plugin.
	 *
	 * @param string $hook O hook da página atual do WordPress.
	 *
	 * @return void
	 */
	public static function enqueue_assets(string $hook): void {
		if (strpos($hook, 'tupbdi-dashboard') === false && strpos($hook, 'tupbdi-settings') === false) {
			return;
		}

		wp_enqueue_style('tupbdi-admin', TUPBDI_PLUGIN_URL . 'assets/css/dashboard.css', [], TUPBDI_VERSION);

		if (strpos($hook, 'tupbdi-dashboard') !== false) {
			wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.min.js', [], '4.5.0', true);
			wp_enqueue_script('tupbdi-dashboard', TUPBDI_PLUGIN_URL . 'assets/js/dashboard.js', ['chart-js', 'jquery'], TUPBDI_VERSION, true);
		}
	}

	/**
	 * Obtém os filtros fornecidos pela URL ($_GET).
	 *
	 * @return array<string, int|string> Array com os filtros (date_from, date_to, product_id, paged).
	 */
	protected static function get_filters(): array {
		return [
			'date_from'  => isset($_GET['tupbdi_date_from']) ? sanitize_text_field(wp_unslash($_GET['tupbdi_date_from'])) : gmdate('Y-m-d', strtotime('-30 days')),
			'date_to'    => isset($_GET['tupbdi_date_to']) ? sanitize_text_field(wp_unslash($_GET['tupbdi_date_to'])) : gmdate('Y-m-d'),
			'product_id' => isset($_GET['tupbdi_product_id']) ? absint($_GET['tupbdi_product_id']) : 0,
			'paged'      => isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1,
		];
	}

	/**
	 * Renderiza a página do dashboard.
	 *
	 * @return void
	 */
	public static function render_dashboard(): void {
		if (!current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'tupiniquim-book-downloads-insights'));
		}

		$filters = self::get_filters();

		$total_downloads = DB::get_total_downloads($filters['date_from'], $filters['date_to'], $filters['product_id']);
		$unique_users    = DB::get_unique_users($filters['date_from'], $filters['date_to'], $filters['product_id']);
		$top_books_raw   = DB::get_top_books(10, $filters['date_from'], $filters['date_to']);

		$downloads_by_product = DB::get_download_counts_by_product($filters['date_from'], $filters['date_to']);
		$by_author            = Author::aggregate_by_author($downloads_by_product);
		$top_author           = $by_author ? array_key_first($by_author) : '';
		$top_author_total     = $by_author ? reset($by_author) : 0;

		$per_page       = 20;
		$recent         = DB::get_recent_downloads($per_page, $filters['paged'], $filters['date_from'], $filters['date_to'], $filters['product_id']);
		$recent_total   = DB::get_recent_downloads_count($filters['date_from'], $filters['date_to'], $filters['product_id']);
		$total_pages    = (int) ceil($recent_total / $per_page);

		$daily = DB::get_downloads_by_day($filters['date_from'], $filters['date_to']);

		$export_url = wp_nonce_url(
			add_query_arg(
				[
					'action'     => 'tupbdi_export_csv',
					'date_from'  => $filters['date_from'],
					'date_to'    => $filters['date_to'],
					'product_id' => $filters['product_id'],
				],
				admin_url('admin-post.php')
			),
			'tupbdi_export_csv'
		);

		include TUPBDI_PLUGIN_DIR . 'includes/views/dashboard.php';
	}

	/**
	 * Renderiza a página de configurações.
	 *
	 * @return void
	 */
	public static function render_settings(): void {
		if (!current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'tupiniquim-book-downloads-insights'));
		}

		$settings   = Author::get_settings();
		$taxonomies = Author::get_product_taxonomies();

		include TUPBDI_PLUGIN_DIR . 'includes/views/settings.php';
	}

	/**
	 * Processa o salvamento de configurações do formulário.
	 *
	 * @return void
	 */
	public static function handle_settings_save(): void {
		if (!isset($_POST['tupbdi_settings_nonce'])) {
			return;
		}

		if (!check_admin_referer('tupbdi_save_settings', 'tupbdi_settings_nonce')) {
			return;
		}

		if (!current_user_can(self::CAPABILITY)) {
			return;
		}

		$type = isset($_POST['tupbdi_author_type']) && 'meta' === $_POST['tupbdi_author_type'] ? 'meta' : 'taxonomy';
		$key  = isset($_POST['tupbdi_author_key']) ? sanitize_text_field(wp_unslash($_POST['tupbdi_author_key'])) : 'autor';

		update_option(
			'tupbdi_author_settings',
			[
				'type' => $type,
				'key'  => $key,
			]
		);

		add_action(
			'admin_notices',
			function (): void {
				echo '<div class="notice notice-success"><p>' . esc_html__('Configurações salvas.', 'tupiniquim-book-downloads-insights') . '</p></div>';
			}
		);
	}
}
