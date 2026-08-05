<?php
if (! defined('ABSPATH')) {
	exit;
}

class BDI_Admin {

	const CAPABILITY = 'manage_woocommerce';

	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'add_menu'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('admin_init', array(__CLASS__, 'handle_settings_save'));
	}

	public static function add_menu() {
		add_menu_page(
			__('Downloads de Livros', 'book-download-insights'),
			__('Downloads de Livros', 'book-download-insights'),
			self::CAPABILITY,
			'bdi-dashboard',
			array(__CLASS__, 'render_dashboard'),
			'dashicons-download',
			56
		);

		add_submenu_page(
			'bdi-dashboard',
			__('Dashboard', 'book-download-insights'),
			__('Dashboard', 'book-download-insights'),
			self::CAPABILITY,
			'bdi-dashboard',
			array(__CLASS__, 'render_dashboard')
		);

		add_submenu_page(
			'bdi-dashboard',
			__('Configurações', 'book-download-insights'),
			__('Configurações', 'book-download-insights'),
			self::CAPABILITY,
			'bdi-settings',
			array(__CLASS__, 'render_settings')
		);
	}

	public static function enqueue_assets($hook) {
		if (strpos($hook, 'bdi-dashboard') === false && strpos($hook, 'bdi-settings') === false) {
			return;
		}

		wp_enqueue_style('bdi-admin', BDI_PLUGIN_URL . 'assets/css/dashboard.css', array(), BDI_VERSION);

		if (strpos($hook, 'bdi-dashboard') !== false) {
			wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.min.js', array(), '4.5.0', true);
			wp_enqueue_script('bdi-dashboard', BDI_PLUGIN_URL . 'assets/js/dashboard.js', array('chart-js', 'jquery'), BDI_VERSION, true);
		}
	}

	protected static function get_filters() {
		return array(
			'date_from'  => isset($_GET['bdi_date_from']) ? sanitize_text_field(wp_unslash($_GET['bdi_date_from'])) : gmdate('Y-m-d', strtotime('-30 days')),
			'date_to'    => isset($_GET['bdi_date_to']) ? sanitize_text_field(wp_unslash($_GET['bdi_date_to'])) : gmdate('Y-m-d'),
			'product_id' => isset($_GET['bdi_product_id']) ? absint($_GET['bdi_product_id']) : 0,
			'paged'      => isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1,
		);
	}

	public static function render_dashboard() {
		if (! current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'book-download-insights'));
		}

		$filters = self::get_filters();

		$total_downloads = BDI_DB::get_total_downloads($filters['date_from'], $filters['date_to'], $filters['product_id']);
		$unique_users    = BDI_DB::get_unique_users($filters['date_from'], $filters['date_to'], $filters['product_id']);
		$top_books_raw   = BDI_DB::get_top_books(10, $filters['date_from'], $filters['date_to']);

		$downloads_by_product = BDI_DB::get_download_counts_by_product($filters['date_from'], $filters['date_to']);
		$by_author            = BDI_Author::aggregate_by_author($downloads_by_product);
		$top_author           = $by_author ? array_key_first($by_author) : '';
		$top_author_total     = $by_author ? reset($by_author) : 0;

		$per_page       = 20;
		$recent         = BDI_DB::get_recent_downloads($per_page, $filters['paged'], $filters['date_from'], $filters['date_to'], $filters['product_id']);
		$recent_total   = BDI_DB::get_recent_downloads_count($filters['date_from'], $filters['date_to'], $filters['product_id']);
		$total_pages    = (int) ceil($recent_total / $per_page);

		$daily = BDI_DB::get_downloads_by_day($filters['date_from'], $filters['date_to']);

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'bdi_export_csv',
					'date_from'  => $filters['date_from'],
					'date_to'    => $filters['date_to'],
					'product_id' => $filters['product_id'],
				),
				admin_url('admin-post.php')
			),
			'bdi_export_csv'
		);

		include BDI_PLUGIN_DIR . 'includes/views/dashboard.php';
	}

	public static function render_settings() {
		if (! current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'book-download-insights'));
		}

		$settings   = BDI_Author::get_settings();
		$taxonomies = BDI_Author::get_product_taxonomies();

		include BDI_PLUGIN_DIR . 'includes/views/settings.php';
	}

	public static function handle_settings_save() {
		if (! isset($_POST['bdi_settings_nonce'])) {
			return;
		}

		if (! check_admin_referer('bdi_save_settings', 'bdi_settings_nonce')) {
			return;
		}

		if (! current_user_can(self::CAPABILITY)) {
			return;
		}

		$type = isset($_POST['bdi_author_type']) && 'meta' === $_POST['bdi_author_type'] ? 'meta' : 'taxonomy';
		$key  = isset($_POST['bdi_author_key']) ? sanitize_text_field(wp_unslash($_POST['bdi_author_key'])) : 'autor';

		update_option(
			'bdi_author_settings',
			array(
				'type' => $type,
				'key'  => $key,
			)
		);

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>' . esc_html__('Configurações salvas.', 'book-download-insights') . '</p></div>';
			}
		);
	}
}
