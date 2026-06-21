<?php
/**
 * Invoice template (business folder).
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
$tax_id          = $kitgenix_pdf_invoicing_for_woocommerce_s( $settings['tax_id'] ?? '' );

$logo_id  = isset( $settings['logo_id'] ) ? (int) $settings['logo_id'] : 0;
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

$document_type = $document_type ?: 'invoice';
$document_title        = DocumentDisplay::get_document_title( $document_type, $settings );
$document_number_label = DocumentTypes::get_reference_label( $document_type );
$document_number       = DocumentTypes::get_identifier( $order, $settings, $document_type );
$document_date_label   = DocumentTypes::get_date_label( $document_type );
$document_date_ts      = DocumentTypes::get_issue_timestamp( $order, $document_type );
$billing_addr   = $order->get_formatted_billing_address();
$shipping_addr  = $order->get_formatted_shipping_address();

$show_shipping = ! empty( $shipping_addr );
$show_shipping = (bool) apply_filters( 'kitgenix_pdf_show_shipping_address', $show_shipping, $document_type, $order, $settings );

$totals = DocumentDisplay::get_order_totals( $order, $settings );
$order_data_rows = DocumentDisplay::get_order_data_rows( $order, $settings );
$note_blocks = DocumentDisplay::get_note_blocks( $order, $settings );

do_action( 'kitgenix_pdf_before_document', $document_type, $order, $settings );
?>
<div class="kitgenix-pdf-invoicing-for-woocommerce-wrap invoice kitgenix-business">

	<table class="kitgenix-pdf-invoicing-for-woocommerce-business-letterhead">
		<tr>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-business-company">
				<?php if ( $logo_url ) : ?>
					<div class="kitgenix-pdf-invoicing-for-woocommerce-logo">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>">
					</div>
				<?php endif; ?>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-company-name"><?php echo esc_html( $company_name ); ?></div>
				<?php if ( $company_address ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-business-company-address"><?php echo wp_kses_post( nl2br( esc_html( $company_address ) ) ); ?></div><?php endif; ?>
				<?php if ( $company_phone ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-business-company-phone"><?php echo esc_html( $company_phone ); ?></div><?php endif; ?>
				<?php if ( $company_email ) : ?><div class="kitgenix-pdf-invoicing-for-woocommerce-business-company-email"><?php echo esc_html( $company_email ); ?></div><?php endif; ?>
				<?php if ( $tax_id ) : ?>
					<div class="kitgenix-pdf-invoicing-for-woocommerce-business-company-tax-id">
						<?php echo esc_html( DocumentDisplay::format_tax_registration( $tax_id, $settings ) ); ?>
					</div>
				<?php endif; ?>
			</td>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-business-docbox">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-title"><?php echo esc_html( $document_title ); ?></div>
				<table class="kitgenix-pdf-invoicing-for-woocommerce-business-meta">
					<tr>
						<th><?php echo esc_html( $document_number_label ); ?></th>
						<td><?php echo esc_html( $document_number ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html( $document_date_label ); ?></th>
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
					<?php do_action( 'kitgenix_pdf_after_order_data_rows', $document_type, $order, $settings ); ?>
				</table>
			</td>
		</tr>
	</table>

	<table class="kitgenix-pdf-invoicing-for-woocommerce-business-addresses">
		<tr>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-business-address">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-address-title"><?php echo esc_html__( 'Bill To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-address-body">
					<?php echo wp_kses_post( $billing_addr ); ?>
					<?php if ( $order->get_billing_email() ) : ?><div class="billing-email"><?php echo esc_html( $order->get_billing_email() ); ?></div><?php endif; ?>
					<?php if ( $order->get_billing_phone() ) : ?><div class="billing-phone"><?php echo esc_html( $order->get_billing_phone() ); ?></div><?php endif; ?>
				</div>
			</td>
			<td class="kitgenix-pdf-invoicing-for-woocommerce-business-address">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-address-title"><?php echo esc_html__( 'Ship To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-address-body">
					<?php if ( $show_shipping ) : ?>
						<?php echo wp_kses_post( $shipping_addr ); ?>
						<?php if ( $order->get_shipping_phone() ) : ?><div class="shipping-phone"><?php echo esc_html( $order->get_shipping_phone() ); ?></div><?php endif; ?>
					<?php else : ?>
						—
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>

	<table class="order-details">
		<thead>
			<tr>
				<th class="product"><?php echo esc_html__( 'Product', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
				<th class="quantity"><?php echo esc_html__( 'Qty', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
				<th class="price"><?php echo esc_html( DocumentDisplay::get_price_column_label( $settings ) ); ?></th>
				<th class="total"><?php echo esc_html( DocumentDisplay::get_total_column_label( $settings ) ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
				<?php
				$product = $item->get_product();
				$item_details = DocumentDisplay::get_line_item_details( $item, $product, $order, $settings );

				$qty        = (int) $item->get_quantity();
				$line_total = DocumentDisplay::get_line_item_total_amount( $item, $settings );
				$unit_price = DocumentDisplay::get_line_item_unit_amount( $item, $settings );
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
					<td class="quantity"><?php echo esc_html( (string) $qty ); ?></td>
					<td class="price"><?php echo wp_kses_post( DocumentDisplay::format_amount( $unit_price, $order, $settings ) ); ?></td>
					<td class="total"><?php echo wp_kses_post( DocumentDisplay::format_amount( $line_total, $order, $settings ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="kitgenix-pdf-invoicing-for-woocommerce-business-bottom">
		<div class="kitgenix-pdf-invoicing-for-woocommerce-business-notes">
			<?php foreach ( $note_blocks as $note_block ) : ?>
				<div class="document-notes">
					<h3><?php echo esc_html( $note_block['title'] ); ?></h3>
					<?php echo wp_kses_post( wpautop( esc_html( $note_block['content'] ) ) ); ?>
				</div>
			<?php endforeach; ?>

			<div class="kitgenix-pdf-invoicing-for-woocommerce-business-signature">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-signature-line"></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-business-signature-label"><?php echo esc_html__( 'Authorised Signature', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></div>
			</div>

			<?php do_action( 'kitgenix_pdf_after_notes', $document_type, $order, $settings ); ?>
		</div>
		<div class="kitgenix-pdf-invoicing-for-woocommerce-business-totals">
			<table class="totals">
				<tfoot>
					<?php foreach ( $totals as $key => $total ) : ?>
						<tr class="<?php echo esc_attr( $key ); ?>">
							<th class="description"><?php echo esc_html( wp_strip_all_tags( $total['label'] ) ); ?></th>
							<td class="price"><span class="totals-price"><?php echo wp_kses_post( $total['value'] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tfoot>
			</table>
		</div>
	</div>

	<div id="footer">
		<div class="footer-inner">
			<div class="footer-cell">
				<?php
				$footer_text = (string) ( $settings['footer_text'] ?? '' );
				$footer_text = trim( $footer_text );

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
