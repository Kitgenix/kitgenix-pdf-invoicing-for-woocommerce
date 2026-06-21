<?php
/**
 * Packing Slip template (modern folder).
 *
 * @var WC_Order $order
 * @var array    $settings
 * @var string   $document_type
 */

defined( 'ABSPATH' ) || exit;

use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentDisplay;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes;

/*
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 *
 * Template files use many short, local variable names for readability.
 */

if ( ! $order instanceof WC_Order ) {
	return;
}

$kitgenix_pdf_invoicing_for_woocommerce_s = static function( $v ): string { return trim( wp_strip_all_tags( (string) $v ) ); };

$company_name    = $kitgenix_pdf_invoicing_for_woocommerce_s( $settings['company_name'] ?? get_bloginfo( 'name' ) );
$company_address = (string) ( $settings['company_address'] ?? '' );
$company_email   = $kitgenix_pdf_invoicing_for_woocommerce_s( $settings['company_email'] ?? '' );
$company_phone   = $kitgenix_pdf_invoicing_for_woocommerce_s( $settings['company_phone'] ?? '' );

$logo_id  = isset( $settings['logo_id'] ) ? (int) $settings['logo_id'] : 0;
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

$document_type = $document_type ?: 'packing-slip';
$document_title   = DocumentDisplay::get_document_title( $document_type, $settings );
$document_number  = DocumentTypes::get_identifier( $order, $settings, $document_type );
$document_date_ts = DocumentTypes::get_issue_timestamp( $order, $document_type );

$shipping_addr = $order->get_formatted_shipping_address();
$billing_addr  = $order->get_formatted_billing_address();
$ship_to       = $shipping_addr ? $shipping_addr : $billing_addr;
$order_data_rows = DocumentDisplay::get_order_data_rows( $order, $settings );
$note_blocks = DocumentDisplay::get_note_blocks( $order, $settings );

do_action( 'kitgenix_pdf_before_document', $document_type, $order, $settings );
?>
<div class="kitgenix-pdf-invoicing-for-woocommerce-wrap packing-slip kitgenix-modern">
	<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-topbar"></div>

	<table class="kitgenix-pdf-invoicing-for-woocommerce-modern-header">
		<tr>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-modern-brand">
				<?php if ( $logo_url ) : ?>
					<div class="kitgenix-pdf-invoicing-for-woocommerce-logo">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>">
					</div>
				<?php endif; ?>

				<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-company">
					<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-company-name"><?php echo esc_html( $company_name ); ?></div>
					<?php if ( $company_address ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-modern-company-address"><?php echo wp_kses_post( nl2br( esc_html( $company_address ) ) ); ?></div><?php endif; ?>
					<?php if ( $company_phone ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-modern-company-phone"><?php echo esc_html( $company_phone ); ?></div><?php endif; ?>
					<?php if ( $company_email ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-modern-company-email"><?php echo esc_html( $company_email ); ?></div><?php endif; ?>
				</div>
			</td>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-modern-doc">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-title"><?php echo esc_html( $document_title ); ?></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-doc-number"><?php echo esc_html( $document_number ); ?></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-doc-date"><?php echo esc_html( DocumentDisplay::format_timestamp( $document_date_ts, $settings ) ); ?></div>
			</td>
		</tr>
	</table>

	<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta">
		<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-chunk">
			<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-label"><?php echo esc_html__( 'Order #', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></span>
			<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-value"><?php echo esc_html( $order->get_order_number() ); ?></span>
		</span>
		<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-chunk">
			<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-label"><?php echo esc_html__( 'Order Date', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></span>
			<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-value"><?php echo esc_html( DocumentDisplay::format_order_created_date( $order, $settings ) ); ?></span>
		</span>
		<?php foreach ( $order_data_rows as $order_data_row ) : ?>
			<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-chunk">
				<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-label"><?php echo esc_html( $order_data_row['label'] ); ?></span>
				<span class="kitgenix-pdf-invoicing-for-woocommerce-modern-quickmeta-value"><?php echo esc_html( $order_data_row['value'] ); ?></span>
			</span>
		<?php endforeach; ?>
	</div>

	<table class="kitgenix-pdf-invoicing-for-woocommerce-modern-panels">
		<tr>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-modern-panel">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-panel-title"><?php echo esc_html__( 'Ship To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-panel-body"><?php echo wp_kses_post( $ship_to ); ?></div>
			</td>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-modern-panel">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-panel-title"><?php echo esc_html__( 'Bill To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-panel-body"><?php echo wp_kses_post( $billing_addr ); ?></div>
			</td>
		</tr>
	</table>

	<!-- Order/shipping info shown in the quick meta strip above. -->

	<table class="order-details">
		<thead>
			<tr>
				<th class="product"><?php echo esc_html__( 'Product', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
				<th class="quantity"><?php echo esc_html__( 'Qty', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $order->get_items() as $item ) : ?>
				<?php
				$product = $item->get_product();
				$item_details = DocumentDisplay::get_line_item_details( $item, $product, $order, $settings );
				?>
				<tr>
					<td class="product">
						<p class="item-name"><?php echo esc_html( $item->get_name() ); ?></p>
						<?php if ( ! empty( $item_details ) ) : ?>
							<div class="item-meta">
								<?php foreach ( $item_details as $item_detail ) : ?>
									<?php echo wp_kses_post( $item_detail ); ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</td>
					<td class="quantity"><?php echo esc_html( (string) $item->get_quantity() ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $note_blocks ) ) : ?>
		<div class="kitgenix-pdf-invoicing-for-woocommerce-modern-notes">
			<?php foreach ( $note_blocks as $note_block ) : ?>
				<div class="document-notes">
					<h3><?php echo esc_html( $note_block['title'] ); ?></h3>
					<?php echo wp_kses_post( wpautop( esc_html( $note_block['content'] ) ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div id="footer">
		<div class="footer-inner">
			<div class="footer-cell">
				<?php
				$footer_text = trim( (string) ( $settings['footer_text'] ?? '' ) );
				if ( $footer_text !== '' ) {
					echo wp_kses_post( nl2br( esc_html( $footer_text ) ) );
				}
				?>
                
			</div>
		</div>
	</div>

</div>
<?php do_action( 'kitgenix_pdf_after_document', $document_type, $order, $settings ); ?>

<?php /* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals */ ?>
