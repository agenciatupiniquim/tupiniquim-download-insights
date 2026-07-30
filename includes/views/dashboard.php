<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array  $filters
 * @var int    $total_downloads
 * @var int    $unique_users
 * @var array  $top_books_raw
 * @var array  $by_author
 * @var string $top_author
 * @var int    $top_author_total
 * @var array  $recent
 * @var int    $total_pages
 * @var array  $daily
 * @var string $export_url
 */
?>
<div class="wrap bdi-wrap">
	<h1><?php esc_html_e( 'Downloads de Livros', 'book-download-insights' ); ?></h1>

	<form method="get" class="bdi-filters">
		<input type="hidden" name="page" value="bdi-dashboard" />

		<label>
			<?php esc_html_e( 'De', 'book-download-insights' ); ?>
			<input type="date" name="bdi_date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
		</label>

		<label>
			<?php esc_html_e( 'Até', 'book-download-insights' ); ?>
			<input type="date" name="bdi_date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
		</label>

		<label>
			<?php esc_html_e( 'Livro', 'book-download-insights' ); ?>
			<?php
			wp_dropdown_pages(
				array(
					'post_type'        => 'product',
					'selected'         => $filters['product_id'],
					'name'             => 'bdi_product_id',
					'show_option_none' => __( 'Todos os livros', 'book-download-insights' ),
					'option_none_value' => 0,
				)
			);
			?>
		</label>

		<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'book-download-insights' ); ?></button>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Exportar CSV', 'book-download-insights' ); ?></a>
	</form>

	<div class="bdi-cards">
		<div class="bdi-card">
			<span class="bdi-card-label"><?php esc_html_e( 'Total de downloads', 'book-download-insights' ); ?></span>
			<span class="bdi-card-value"><?php echo esc_html( number_format_i18n( $total_downloads ) ); ?></span>
		</div>
		<div class="bdi-card">
			<span class="bdi-card-label"><?php esc_html_e( 'Usuários únicos', 'book-download-insights' ); ?></span>
			<span class="bdi-card-value"><?php echo esc_html( number_format_i18n( $unique_users ) ); ?></span>
		</div>
		<div class="bdi-card">
			<span class="bdi-card-label"><?php esc_html_e( 'Autor mais baixado', 'book-download-insights' ); ?></span>
			<span class="bdi-card-value bdi-card-value-small">
				<?php echo $top_author ? esc_html( $top_author ) : esc_html__( '—', 'book-download-insights' ); ?>
			</span>
			<?php if ( $top_author ) : ?>
				<span class="bdi-card-sub"><?php echo esc_html( sprintf(
					/* translators: %s: número de downloads */
					_n( '%s download', '%s downloads', $top_author_total, 'book-download-insights' ),
					number_format_i18n( $top_author_total )
				) ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="bdi-panels">
		<div class="bdi-panel bdi-panel-chart">
			<h2><?php esc_html_e( 'Downloads por dia', 'book-download-insights' ); ?></h2>
			<canvas id="bdi-chart-daily" height="90"></canvas>
			<script type="application/json" id="bdi-daily-data">
				<?php
				echo wp_json_encode(
					array(
						'labels' => wp_list_pluck( $daily, 'day' ),
						'values' => array_map( 'intval', wp_list_pluck( $daily, 'total' ) ),
					)
				);
				?>
			</script>
		</div>

		<div class="bdi-panel">
			<h2><?php esc_html_e( 'Top 10 livros mais baixados', 'book-download-insights' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Livro', 'book-download-insights' ); ?></th>
						<th><?php esc_html_e( 'Autor', 'book-download-insights' ); ?></th>
						<th><?php esc_html_e( 'Downloads', 'book-download-insights' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $top_books_raw ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'Nenhum download registrado no período.', 'book-download-insights' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $top_books_raw as $row ) : ?>
						<tr>
							<td><?php echo esc_html( get_the_title( $row->product_id ) ); ?></td>
							<td><?php echo esc_html( BDI_Author::get_display_authors( $row->product_id ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row->total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="bdi-panel">
			<h2><?php esc_html_e( 'Top autores', 'book-download-insights' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Autor', 'book-download-insights' ); ?></th>
						<th><?php esc_html_e( 'Downloads', 'book-download-insights' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $by_author ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'Nenhum download registrado no período.', 'book-download-insights' ); ?></td></tr>
					<?php endif; ?>
					<?php $count = 0; foreach ( $by_author as $author => $total ) : if ( ++$count > 10 ) { break; } ?>
						<tr>
							<td><?php echo esc_html( $author ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="bdi-panel">
		<h2><?php esc_html_e( 'Downloads recentes', 'book-download-insights' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data/Hora', 'book-download-insights' ); ?></th>
					<th><?php esc_html_e( 'Usuário', 'book-download-insights' ); ?></th>
					<th><?php esc_html_e( 'E-mail', 'book-download-insights' ); ?></th>
					<th><?php esc_html_e( 'Livro', 'book-download-insights' ); ?></th>
					<th><?php esc_html_e( 'Autor', 'book-download-insights' ); ?></th>
					<th><?php esc_html_e( 'Pedido', 'book-download-insights' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $recent ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Nenhum download registrado no período.', 'book-download-insights' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $recent as $row ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $row->downloaded_at ) ); ?></td>
						<td><?php echo esc_html( $row->user_name ); ?></td>
						<td><?php echo esc_html( $row->user_email ); ?></td>
						<td><?php echo esc_html( get_the_title( $row->product_id ) ); ?></td>
						<td><?php echo esc_html( BDI_Author::get_display_authors( $row->product_id ) ); ?></td>
						<td>
							<?php if ( $row->order_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row->order_id . '&action=edit' ) ); ?>">
									#<?php echo esc_html( $row->order_id ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="bdi-pagination">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $filters['paged'],
							'total'     => $total_pages,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>
</div>
