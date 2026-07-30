<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Escuta o momento em que o WooCommerce efetivamente entrega o arquivo pro
 * cliente (não apenas quando o pedido é criado) e grava uma linha no log.
 *
 * Isso é importante: um usuário pode "comprar" (gerar permissão) o livro
 * grátis e nunca clicar em baixar, ou baixar várias vezes. O dashboard
 * mostra downloads reais, não pedidos.
 */
class BDI_Tracker {

	public static function init() {
		// Disparado pelo WooCommerce sempre que um arquivo de download é servido.
		add_action('woocommerce_download_product', array(__CLASS__, 'log_download'), 10, 5);
	}

	public static function log_download($user_email, $user_id, $product_id, $order_id, $download_id) {
		$user_name = '';

		if ($user_id) {
			$user = get_userdata($user_id);
			if ($user) {
				$user_name = $user->display_name;
			}
		}

		// Fallback: se não tem usuário logado, tenta pegar o nome de faturamento do pedido.
		if (! $user_name && $order_id) {
			$order = wc_get_order($order_id);
			if ($order) {
				$user_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
				if (! $user_email) {
					$user_email = $order->get_billing_email();
				}
			}
		}

		BDI_DB::insert_log(
			array(
				'user_id'       => $user_id,
				'user_email'    => $user_email,
				'user_name'     => $user_name,
				'product_id'    => $product_id,
				'order_id'      => $order_id,
				'download_id'   => $download_id,
				'ip_address'    => self::get_client_ip(),
				'downloaded_at' => current_time('mysql'),
			)
		);
	}

	protected static function get_client_ip() {
		$keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

		foreach ($keys as $key) {
			if (! empty($_SERVER[$key])) {
				$ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
				// HTTP_X_FORWARDED_FOR pode vir com uma lista de IPs.
				$ip = trim(explode(',', $ip)[0]);
				return $ip;
			}
		}

		return '';
	}
}
