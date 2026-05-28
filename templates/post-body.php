<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yy_dmm_render_info_items = static function ( $items ) {
	$items = is_array( $items ) ? $items : array();
	$parts = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || empty( $item['name'] ) ) {
			continue;
		}

		$name = sanitize_text_field( $item['name'] );
		$url  = ! empty( $item['url'] ) ? esc_url( $item['url'] ) : '';
		$parts[] = $url ? sprintf( '<a href="%s">%s</a>', $url, esc_html( $name ) ) : esc_html( $name );
	}

	echo wp_kses_post( implode( ' / ', $parts ) );
};

$yy_dmm_render_url = static function ( $url, $label, $button = false ) {
	$url = esc_url( $url );
	if ( '' === $url ) {
		return;
	}

	$style = $button ? ' style="display:inline-block;padding:10px 18px;background:#d63638;color:#fff;text-decoration:none;border-radius:4px;font-weight:700;"' : '';
	printf( '<a href="%s" target="_blank" rel="noopener noreferrer"%s>%s</a>', $url, $style, esc_html( $label ) );
};

$yy_dmm_render_text_list = static function ( $items ) {
	$items = is_array( $items ) ? $items : array();
	$items = array_filter( array_map( 'sanitize_text_field', $items ) );
	echo esc_html( implode( ' / ', $items ) );
};

$ordered_body_sections = array_keys( is_array( $body_sections ?? null ) ? $body_sections : array() );
usort(
	$ordered_body_sections,
	static function ( $a, $b ) use ( $body_section_order ) {
		$a_order = absint( $body_section_order[ $a ] ?? 999 );
		$b_order = absint( $body_section_order[ $b ] ?? 999 );
		if ( $a_order === $b_order ) {
			return strcmp( $a, $b );
		}

		return $a_order <=> $b_order;
	}
);

$ordered_product_info_fields = array_keys( is_array( $product_info_fields ?? null ) ? $product_info_fields : array() );
usort(
	$ordered_product_info_fields,
	static function ( $a, $b ) use ( $product_info_field_order ) {
		$a_order = absint( $product_info_field_order[ $a ] ?? 999 );
		$b_order = absint( $product_info_field_order[ $b ] ?? 999 );
		if ( $a_order === $b_order ) {
			return strcmp( $a, $b );
		}

		return $a_order <=> $b_order;
	}
);
?>

<?php foreach ( $ordered_body_sections as $body_section_key ) : ?>
	<?php if ( empty( $body_sections[ $body_section_key ] ) ) : ?>
		<?php continue; ?>
	<?php endif; ?>

	<?php if ( 'sample_movie' === $body_section_key && ! empty( $sample_movie_url ) ) : ?>
		<?php echo YY_DMM_Auto_Post_Sample_Movie::build_shortcode( $sample_movie_url ); ?>
	<?php elseif ( in_array( $body_section_key, array( 'top_affiliate_button', 'middle_affiliate_button', 'bottom_affiliate_button' ), true ) && ! empty( $affiliate_url ) ) : ?>
		<p class="yy-dmm-affiliate-button" style="text-align:center;margin:24px 0;">
			<a href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 22px;background:#d63638;color:#fff;text-decoration:none;border-radius:4px;font-weight:700;"><?php echo esc_html( $affiliate_button_texts[ $body_section_key ] ?? '公式ページを見る' ); ?></a>
		</p>
	<?php elseif ( 'product_info' === $body_section_key ) : ?>
		<table class="yy-dmm-product-info" style="width:100%;border-collapse:collapse;margin:24px 0;">
			<tbody>
				<?php foreach ( $ordered_product_info_fields as $field_key ) : ?>
					<?php if ( empty( $product_info_fields[ $field_key ] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>

					<?php if ( 'title' === $field_key ) : ?>
						<tr>
							<th style="width:30%;padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">タイトル</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $title ); ?></td>
						</tr>
					<?php elseif ( 'product_id' === $field_key && ! empty( $product_id ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">商品ID</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $product_id ); ?></td>
						</tr>
					<?php elseif ( 'content_id' === $field_key ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">品番</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $content_id ); ?></td>
						</tr>
					<?php elseif ( 'service' === $field_key && ! empty( $service_text ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">サービス</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $service_text ); ?></td>
						</tr>
					<?php elseif ( 'floor' === $field_key && ! empty( $floor_text ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">フロア</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $floor_text ); ?></td>
						</tr>
					<?php elseif ( 'category_name' === $field_key && ! empty( $category_name ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">カテゴリ名</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $category_name ); ?></td>
						</tr>
					<?php elseif ( 'maker' === $field_key && ! empty( $maker_items ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">メーカー</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php $yy_dmm_render_info_items( $maker_items ); ?></td>
						</tr>
					<?php elseif ( 'label' === $field_key && ! empty( $label_items ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">レーベル</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php $yy_dmm_render_info_items( $label_items ); ?></td>
						</tr>
					<?php elseif ( 'date' === $field_key && ! empty( $date ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">発売日・配信日</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $date ); ?></td>
						</tr>
					<?php elseif ( 'volume' === $field_key && ! empty( $volume ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">収録時間</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $volume ); ?></td>
						</tr>
					<?php elseif ( 'price' === $field_key && ! empty( $price ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">価格</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $price ); ?></td>
						</tr>
					<?php elseif ( 'list_price' === $field_key && ! empty( $list_price ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">定価</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $list_price ); ?></td>
						</tr>
					<?php elseif ( 'delivery_prices' === $field_key && ! empty( $delivery_prices ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">配信価格</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php $yy_dmm_render_text_list( $delivery_prices ); ?></td>
						</tr>
					<?php elseif ( 'product_url' === $field_key && ! empty( $affiliate_url ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;"><?php echo esc_html( $product_info_product_url_label ); ?></th>
							<td style="padding:10px;border:1px solid #ddd;"><?php $yy_dmm_render_url( $affiliate_url, $product_info_product_url_link_text, ! empty( $product_info_product_url_button ) ); ?></td>
						</tr>
					<?php elseif ( 'genres' === $field_key && ! empty( $genre_items ) ) : ?>
						<tr>
							<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">ジャンル</th>
							<td style="padding:10px;border:1px solid #ddd;"><?php $yy_dmm_render_info_items( $genre_items ); ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php elseif ( 'description' === $body_section_key && ! empty( $description ) ) : ?>
		<div class="yy-dmm-description" style="margin:24px 0;">
			<?php echo wp_kses_post( wpautop( esc_html( $description ) ) ); ?>
		</div>
	<?php elseif ( 'sample_images' === $body_section_key && ! empty( $sample_image_urls ) ) : ?>
		<div class="yy-dmm-sample-gallery" style="display:grid;gap:16px;margin:24px 0;">
			<?php foreach ( $sample_image_urls as $sample_image_url ) : ?>
				<figure style="margin:0;text-align:center;">
					<img src="<?php echo esc_url( $sample_image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" style="max-width:100%;height:auto;">
				</figure>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $show_sample_image_continue_button ) && ! empty( $affiliate_url ) && ! empty( $sample_image_continue_button_text ) ) : ?>
			<p class="yy-dmm-affiliate-button yy-dmm-sample-continue-button" style="text-align:center;margin:24px 0;">
				<a href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 22px;background:#d63638;color:#fff;text-decoration:none;border-radius:4px;font-weight:700;"><?php echo esc_html( $sample_image_continue_button_text ); ?></a>
			</p>
		<?php endif; ?>
	<?php endif; ?>
<?php endforeach; ?>
