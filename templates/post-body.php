<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php if ( ! empty( $sample_movie_url ) ) : ?>
	<div class="yy-dmm-sample-movie" style="margin: 0 0 24px;">
		<video controls playsinline preload="metadata" src="<?php echo esc_url( $sample_movie_url ); ?>" style="display:block;width:100%;height:auto;max-width:900px;margin:0 auto;"></video>
	</div>
<?php endif; ?>

<?php if ( ! empty( $affiliate_url ) ) : ?>
	<p class="yy-dmm-affiliate-button" style="text-align:center;margin:24px 0;">
		<a href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 22px;background:#d63638;color:#fff;text-decoration:none;border-radius:4px;font-weight:700;">公式ページを見る</a>
	</p>
<?php endif; ?>

<table class="yy-dmm-product-info" style="width:100%;border-collapse:collapse;margin:24px 0;">
	<tbody>
		<tr>
			<th style="width:30%;padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">タイトル</th>
			<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $title ); ?></td>
		</tr>
		<?php if ( ! empty( $label_name ) ) : ?>
			<tr>
				<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">メーカー / レーベル</th>
				<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $label_name ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( ! empty( $date ) ) : ?>
			<tr>
				<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">発売日・配信日</th>
				<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $date ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( ! empty( $volume ) ) : ?>
			<tr>
				<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">収録時間</th>
				<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $volume ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( ! empty( $price ) ) : ?>
			<tr>
				<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">価格</th>
				<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $price ); ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">品番</th>
			<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( $content_id ); ?></td>
		</tr>
		<?php if ( ! empty( $genres ) ) : ?>
			<tr>
				<th style="padding:10px;border:1px solid #ddd;text-align:left;background:#f6f7f7;">ジャンル</th>
				<td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html( implode( ' / ', $genres ) ); ?></td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( ! empty( $description ) ) : ?>
	<div class="yy-dmm-description" style="margin:24px 0;">
		<?php echo wp_kses_post( wpautop( esc_html( $description ) ) ); ?>
	</div>
<?php endif; ?>

<?php if ( ! empty( $sample_image_urls ) ) : ?>
	<div class="yy-dmm-sample-gallery" style="display:grid;gap:16px;margin:24px 0;">
		<?php foreach ( $sample_image_urls as $sample_image_url ) : ?>
			<figure style="margin:0;text-align:center;">
				<img src="<?php echo esc_url( $sample_image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" style="max-width:100%;height:auto;">
			</figure>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( ! empty( $affiliate_url ) ) : ?>
	<p class="yy-dmm-affiliate-button yy-dmm-affiliate-button-bottom" style="text-align:center;margin:24px 0;">
		<a href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 22px;background:#d63638;color:#fff;text-decoration:none;border-radius:4px;font-weight:700;">公式ページを見る</a>
	</p>
<?php endif; ?>
