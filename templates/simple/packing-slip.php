<?php
/**
 * Packing Slip template (simple folder).
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
$document_title        = DocumentDisplay::get_document_title( $document_type, $settings );
$document_number_label = DocumentTypes::get_reference_label( $document_type );
$document_number       = DocumentTypes::get_identifier( $order, $settings, $document_type );
$document_date_ts      = DocumentTypes::get_issue_timestamp( $order, $document_type );

$shipping_addr = $order->get_formatted_shipping_address();
$billing_addr  = $order->get_formatted_billing_address();
$ship_to       = $shipping_addr ? $shipping_addr : $billing_addr;
$order_data_rows = DocumentDisplay::get_order_data_rows( $order, $settings );
$note_blocks = DocumentDisplay::get_note_blocks( $order, $settings );

do_action( 'kitgenix_pdf_before_document', $document_type, $order, $settings );
?>
<div class="kitgenix-pdf-invoicing-for-woocommerce-wrap packing-slip kitgenix-simple">

	<table class="kitgenix-pdf-invoicing-for-woocommerce-simple-header">
		<tr>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-brand">
				<?php if ( $logo_url ) : ?>
					<div class="kitgenix-pdf-invoicing-for-woocommerce-logo">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>">
					</div>
				<?php else : ?>
					<div class="kitgenix-pdf-invoicing-for-woocommerce-brand-name"><?php echo esc_html( $company_name ); ?></div>
				<?php endif; ?>

				<div class="kitgenix-pdf-invoicing-for-woocommerce-company-meta">
					<?php if ( $company_address ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-company-address"><?php echo wp_kses_post( nl2br( esc_html( $company_address ) ) ); ?></div><?php endif; ?>
					<?php if ( $company_phone ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-company-phone"><?php echo esc_html( $company_phone ); ?></div><?php endif; ?>
					<?php if ( $company_email ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-company-email"><?php echo esc_html( $company_email ); ?></div><?php endif; ?>
				</div>
			</td>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-docmeta">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-doc-title"><?php echo esc_html( $document_title ); ?></div>
				<table class="kitgenix-pdf-invoicing-for-woocommerce-meta-table">
					<tr>
						<th><?php echo esc_html( $document_number_label ); ?></th>
						<td><?php echo esc_html( $document_number ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html( DocumentTypes::get_date_label( $document_type ) ); ?></th>
						<td><?php echo esc_html( DocumentDisplay::format_timestamp( $document_date_ts, $settings ) ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Order #', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $order->get_order_number() ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Order Date', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( DocumentDisplay::format_order_created_date( $order, $settings ) ); ?></td>
					</tr>
					<?php foreach ( $order_data_rows as $order_data_row ) : ?>
						<tr>
							<th><?php echo esc_html( $order_data_row['label'] ); ?></th>
							<td><?php echo esc_html( $order_data_row['value'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</td>
		</tr>
	</table>

	<table class="kitgenix-pdf-invoicing-for-woocommerce-simple-addresses">
		<tr>
			<td class="address shipping-address">
				<h3><?php echo esc_html__( 'Ship To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
				<p><?php echo wp_kses_post( $ship_to ); ?></p>
			</td>
			<td class="address billing-address">
				<h3><?php echo esc_html__( 'Bill To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
				<p><?php echo wp_kses_post( $billing_addr ); ?></p>
			</td>
		</tr>
	</table>

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
		<div class="kitgenix-pdf-invoicing-for-woocommerce-simple-notes">
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
