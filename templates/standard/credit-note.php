<?php
/**
 * Credit Note template (standard folder).
 *
 * @var WC_Order $order
 * @var array    $settings
 * @var string   $document_type
 */

defined( 'ABSPATH' ) || exit;

use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentDisplay;

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

$credit_prefix   = $kitgenix_pdf_invoicing_for_woocommerce_s( $settings['credit_note_prefix'] ?? 'CN-' );
$footer_text     = (string) ( $settings['footer_text'] ?? '' );

$logo_id  = isset( $settings['logo_id'] ) ? (int) $settings['logo_id'] : 0;
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

// Initialise refunds early so later code can iterate regardless of branches.
$refunds = $order->get_refunds();

$document_type = $document_type ?: 'credit-note';
$document_title = DocumentDisplay::get_document_title( $document_type, $settings );

$stored_history = $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history', true );
if ( is_array( $stored_history ) && ! empty( $stored_history ) ) {
	$last = end( $stored_history );
	$credit_note_number = $last['number'] ?? ( $credit_prefix . $order->get_order_number() );
	$credit_date_ts = isset( $last['date'] ) ? strtotime( $last['date'] ) : ( $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0 );
} else {
	$credit_note_number = $credit_prefix . $order->get_order_number();
	$credit_date_ts     = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
}
$currency           = $order->get_currency();
$billing_addr       = $order->get_formatted_billing_address();
$order_data_rows    = DocumentDisplay::get_order_data_rows( $order, $settings );
$note_blocks        = DocumentDisplay::get_note_blocks( $order, $settings );

// If no stored history date was present above, fall back to latest refund date.
if ( empty( $credit_date_ts ) ) {
	$credit_date_ts = 0;
	$refunds = $order->get_refunds();
	if ( ! empty( $refunds ) ) {
		$latest_ts = 0;
		foreach ( $refunds as $r ) {
			if ( $r instanceof WC_Order_Refund && $r->get_date_created() ) {
				$ts = $r->get_date_created()->getTimestamp();
				if ( $ts > $latest_ts ) {
					$latest_ts = $ts;
				}
			}
		}
		if ( $latest_ts ) {
			$credit_date_ts = $latest_ts;
		}
	}
	if ( empty( $credit_date_ts ) && $order->get_date_created() ) {
		$credit_date_ts = $order->get_date_created()->getTimestamp();
	}
}

// Aggregate credited lines from refunds (items + shipping + fees).
$lines = array(); // key => [name, qty, total]
foreach ( $refunds as $refund ) {
	if ( ! $refund instanceof WC_Order_Refund ) {
		continue;
	}

	foreach ( $refund->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $ref_item_id => $ref_item ) {
		$type = $ref_item->get_type();
		$name = $ref_item->get_name();

		$qty  = (int) $ref_item->get_quantity(); // line_item has qty, shipping/fee often 0
		$amt  = (float) $ref_item->get_total();  // totals on refund items are negative in Woo
		$amt  = abs( $amt );

		// If it’s shipping/fee and name is empty, label it.
		if ( $type !== 'line_item' && $name === '' ) {
			$name = ( $type === 'shipping' )
				? __( 'Shipping', 'kitgenix-pdf-invoicing-for-woocommerce' )
				: __( 'Fee', 'kitgenix-pdf-invoicing-for-woocommerce' );
		}

		$key = $type . ':' . (string) $ref_item_id . ':' . md5( $name );
		$product = method_exists( $ref_item, 'get_product' ) ? $ref_item->get_product() : null;
		$details = DocumentDisplay::get_line_item_details( $ref_item, $product, $order, $settings );

		if ( ! isset( $lines[ $key ] ) ) {
			$lines[ $key ] = array(
				'name'  => $name,
				'type'  => $type,
				'qty'   => 0,
				'total' => 0.0,
				'details' => $details,
			);
		}

		// Qty only makes sense for line items.
		if ( $type === 'line_item' && $qty !== 0 ) {
			$lines[ $key ]['qty'] += absint( $qty );
		}

		$lines[ $key ]['total'] += $amt;
	}
}

// If there are no refunds, keep it presentable.
$has_lines = ! empty( $lines );

// Total credited (Woo gives this as negative total refunded sometimes; normalize).
$total_credited = abs( (float) $order->get_total_refunded() );

do_action( 'kitgenix_pdf_before_document', $document_type, $order, $settings );
?>
<div class="kitgenix-pdf-invoicing-for-woocommerce-wrap credit-note">

	<table class="head container">
		<tr>
			<td class="header">
				<?php if ( $logo_url ) : ?>
					<div class="kitgenix-pdf-invoicing-for-woocommerce-logo">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>">
					</div>
				<?php else : ?>
					<?php echo esc_html( $document_title ); ?>
				<?php endif; ?>
			</td>
			<td class="shop-info">
				<div class="kitgenix-pdf-invoicing-for-woocommerce-shop-name"><h3><?php echo esc_html( $company_name ); ?></h3></div>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-shop-meta">
					<?php if ( $company_address ) : ?>
						<div class="shop-address"><?php echo wp_kses_post( nl2br( esc_html( $company_address ) ) ); ?></div>
					<?php endif; ?>
					<?php if ( $company_phone ) : ?><div class="shop-phone-number"><?php echo esc_html( $company_phone ); ?></div><?php endif; ?>
					<?php if ( $company_email ) : ?><div class="shop-email-address"><?php echo esc_html( $company_email ); ?></div><?php endif; ?>
					<?php if ( $tax_id ) : ?>
						<div class="shop-tax-id">
							<?php echo esc_html( DocumentDisplay::format_tax_registration( $tax_id, $settings ) ); ?>
						</div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>

	<h1 class="document-type-label"><?php echo esc_html( $document_title ); ?></h1>

	<table class="order-data-addresses">
		<tr>
			<td class="address billing-address">
				<h3><?php echo esc_html__( 'Bill To', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
				<p><?php echo wp_kses_post( $billing_addr ); ?></p>
				<?php if ( $order->get_billing_email() ) : ?>
					<div class="billing-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
				<?php endif; ?>
				<?php if ( $order->get_billing_phone() ) : ?>
					<div class="billing-phone"><?php echo esc_html( $order->get_billing_phone() ); ?></div>
				<?php endif; ?>
			</td>

			<td class="address shipping-address">
				<h3><?php echo esc_html__( 'Related Order', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
				<p><?php echo esc_html( $order->get_order_number() ); ?></p>
			</td>

			<td class="order-data">
				<table>
					<tr class="credit-note-number">
						<th><?php echo esc_html__( 'Credit Note Number', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $credit_note_number ); ?></td>
					</tr>
					<tr class="credit-note-date">
						<th><?php echo esc_html__( 'Credit Note Date', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( DocumentDisplay::format_timestamp( $credit_date_ts, $settings ) ); ?></td>
					</tr>
					<tr class="order-date">
						<th><?php echo esc_html__( 'Order Date', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( DocumentDisplay::format_order_created_date( $order, $settings ) ); ?></td>
					</tr>
					<tr class="currency">
						<th><?php echo esc_html__( 'Currency', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $currency ); ?></td>
					</tr>
					<?php foreach ( $order_data_rows as $order_data_row ) : ?>
						<tr class="<?php echo esc_attr( sanitize_title( $order_data_row['label'] ) ); ?>">
							<th><?php echo esc_html( $order_data_row['label'] ); ?></th>
							<td><?php echo esc_html( $order_data_row['value'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</td>
		</tr>
	</table>

	<table class="order-details">
		<thead>
			<tr>
				<th class="product"><?php echo esc_html__( 'Description', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
				<th class="quantity"><?php echo esc_html__( 'Qty', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
				<th class="price"><?php echo esc_html( DocumentDisplay::get_credited_amount_label( $settings ) ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $has_lines ) : ?>
				<?php foreach ( $lines as $line ) : ?>
					<tr>
						<td class="product">
							<p class="item-name"><?php echo esc_html( $line['name'] ); ?></p>
							<?php if ( ! empty( $line['details'] ) ) : ?>
								<div class="item-meta">
									<?php foreach ( $line['details'] as $line_detail ) : ?>
										<?php echo wp_kses_post( $line_detail ); ?>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</td>
						<td class="quantity"><?php echo esc_html( $line['type'] === 'line_item' ? (string) $line['qty'] : '—' ); ?></td>
						<td class="price"><?php echo wp_kses_post( DocumentDisplay::format_amount( (float) $line['total'], $order, $settings ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td class="product"><?php echo esc_html__( 'No refund lines are available for this order.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></td>
					<td class="quantity">—</td>
					<td class="price"><?php echo wp_kses_post( DocumentDisplay::format_amount( 0, $order, $settings ) ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<table class="notes-totals">
		<tbody>
			<tr class="no-borders">
				<td class="no-borders notes-cell">
					<div class="document-notes">
						<h3><?php echo esc_html__( 'Credit Note Note', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
						<p><?php echo esc_html__( 'This document confirms the credited amounts shown above.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
					</div>
					<?php foreach ( $note_blocks as $note_block ) : ?>
						<div class="document-notes">
							<h3><?php echo esc_html( $note_block['title'] ); ?></h3>
							<?php echo wp_kses_post( wpautop( esc_html( $note_block['content'] ) ) ); ?>
						</div>
					<?php endforeach; ?>
				</td>

				<td class="no-borders totals-cell">
					<table class="totals">
						<tfoot>
							<tr class="order_total">
								<th class="description"><?php echo esc_html__( 'Total Credited', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
								<td class="price"><span class="totals-price"><?php echo wp_kses_post( DocumentDisplay::format_amount( $total_credited, $order, $settings ) ); ?></span></td>
							</tr>
						</tfoot>
					</table>
				</td>
			</tr>
		</tbody>
	</table>

	<div id="footer">
		<div class="footer-inner">
			<div class="footer-cell">
				<?php if ( trim( $footer_text ) !== '' ) : ?>
					<?php echo wp_kses_post( nl2br( esc_html( $footer_text ) ) ); ?>
				<?php endif; ?>
                
			</div>
		</div>
	</div>

</div>
<?php do_action( 'kitgenix_pdf_after_document', $document_type, $order, $settings ); ?>

<?php /* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals */ ?>
