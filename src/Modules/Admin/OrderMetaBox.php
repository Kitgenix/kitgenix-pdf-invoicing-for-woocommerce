<?php

namespace Kitgenix\PDF_Invoicing\Modules\Admin;

use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\PdfGenerator;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

final class OrderMetaBox {

	private static ?PdfGenerator $pdf = null;

	/**
	 * Initialise order meta box. Accept an injected PdfGenerator for shared
	 * configuration; if none is provided a fallback instance is created.
	 */
	public static function init( ?PdfGenerator $pdf = null ): void {
		if ( $pdf ) {
			self::$pdf = $pdf;
		} else {
			// Fallback for backward compatibility — avoid duplicate creation
			// in normal bootstrap where AdminModule injects the shared instance.
			$renderer = new TemplateRenderer();
			self::$pdf = new PdfGenerator( $renderer );
		}

		add_action( 'add_meta_boxes', [ self::class, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );

		// Admin-post endpoints for meta box actions (downloads).
		add_action( 'admin_post_kitgenix_admin_stream_invoice', [ self::class, 'handle_admin_stream_invoice' ] );
		// Receipt admin endpoint (download).
		add_action( 'admin_post_kitgenix_admin_stream_receipt', [ self::class, 'handle_admin_stream_receipt' ] );
		// Packing slip admin endpoint (download).
		add_action( 'admin_post_kitgenix_admin_stream_packing_slip', [ self::class, 'handle_admin_stream_packing_slip' ] );
		// Credit note admin endpoint (download).
		add_action( 'admin_post_kitgenix_admin_stream_credit_note', [ self::class, 'handle_admin_stream_credit_note' ] );
		add_action( 'admin_post_kitgenix_admin_stream_pro_forma_invoice', [ self::class, 'handle_admin_stream_pro_forma_invoice' ] );
		add_action( 'admin_post_kitgenix_admin_stream_delivery_note', [ self::class, 'handle_admin_stream_delivery_note' ] );
		add_action( 'admin_post_kitgenix_admin_stream_statement', [ self::class, 'handle_admin_stream_statement' ] );
	}

	public static function add_meta_box(): void {
		$screen_id = 'shop_order';

		if ( function_exists( 'wc_get_container' ) ) {
			try {
				$container  = \wc_get_container();
				$controller = $container->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);

				if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
					if ( function_exists( 'wc_get_page_screen_id' ) ) {
						$screen_id = \wc_get_page_screen_id( 'shop-order' );
					}
				}
			} catch ( \Throwable $e ) {
				// noop - fall back to legacy screen id.
			}
		}

		add_meta_box(
			'kitgenix-pdf-invoicing-meta-box',
			__( 'Kitgenix PDF Invoicing', 'kitgenix-pdf-invoicing-for-woocommerce' ),
			[ self::class, 'render_meta_box' ],
			$screen_id,
			'side',
			'default'
		);
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed_screens = [ 'shop_order' ];
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$allowed_screens[] = \wc_get_page_screen_id( 'shop-order' );
		}

		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		if ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL' ) ) {
			$base_url = KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL;
		} elseif ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE' ) ) {
			$base_url = plugin_dir_url( KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE );
		} else {
			$base_url = plugin_dir_url( __FILE__ );
		}

		if ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH' ) ) {
			$base_dir = KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH;
		} elseif ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE' ) ) {
			$base_dir = plugin_dir_path( KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE );
		} else {
			$base_dir = plugin_dir_path( __FILE__ );
		}

		// Enqueue admin order meta styles.
		$css_path = trailingslashit( $base_dir ) . 'assets/css/admin-order-meta.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_VERSION;

		wp_enqueue_style(
			'kitgenix-pdf-invoicing-admin-order-meta',
			$base_url . 'assets/css/admin-order-meta.css',
			[],
			$css_ver
		);

		wp_enqueue_style( 'kitgenix-admin-ui' );
	}

	/**
	 * Render the meta box contents.
	 *
	 * @param mixed $post_or_order WP_Post|WC_Order|int
	 */
	public static function render_meta_box( $post_or_order ): void {
		$order = null;

		if ( $post_or_order instanceof \WC_Order ) {
			$order = $post_or_order;
		} elseif ( $post_or_order instanceof \WP_Post ) {
			$order = wc_get_order( $post_or_order->ID );
		} elseif ( is_numeric( $post_or_order ) ) {
			$order = wc_get_order( (int) $post_or_order );
		}

		if ( ! $order instanceof \WC_Order ) {
			echo esc_html__( 'Order not found.', 'kitgenix-pdf-invoicing-for-woocommerce' );
			return;
		}

		$stored_invoice_number = $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_number', true );
		$invoice_number = $stored_invoice_number ? $stored_invoice_number : __( 'Assigned on first generation', 'kitgenix-pdf-invoicing-for-woocommerce' );

		$stream_url  = admin_url( 'admin-post.php?action=kitgenix_admin_stream_invoice&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );
		$receipt_stream_url = admin_url( 'admin-post.php?action=kitgenix_admin_stream_receipt&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );
		$packing_stream_url = admin_url( 'admin-post.php?action=kitgenix_admin_stream_packing_slip&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );
		$cn_stream_url = admin_url( 'admin-post.php?action=kitgenix_admin_stream_credit_note&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );
		$pro_forma_stream_url = admin_url( 'admin-post.php?action=kitgenix_admin_stream_pro_forma_invoice&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );
		$delivery_note_stream_url = admin_url( 'admin-post.php?action=kitgenix_admin_stream_delivery_note&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );
		$statement_stream_url = admin_url( 'admin-post.php?action=kitgenix_admin_stream_statement&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce( 'kitgenix_admin_pdf' ) );

		if ( ! self::$pdf ) {
			$renderer = new TemplateRenderer();
			self::$pdf = new PdfGenerator( $renderer );
		}

		$can_stream_invoice = self::$pdf->is_document_available_for_order( $order, DocumentTypes::INVOICE );
		$can_stream_receipt = self::$pdf->is_document_available_for_order( $order, DocumentTypes::RECEIPT );
		$can_stream_packing = self::$pdf->is_document_available_for_order( $order, DocumentTypes::PACKING_SLIP );
		$can_stream_credit_note = self::$pdf->is_document_available_for_order( $order, DocumentTypes::CREDIT_NOTE );
		$can_stream_pro_forma = self::$pdf->is_document_available_for_order( $order, DocumentTypes::PRO_FORMA_INVOICE );
		$can_stream_delivery_note = self::$pdf->is_document_available_for_order( $order, DocumentTypes::DELIVERY_NOTE );
		$can_stream_statement = self::$pdf->is_document_available_for_order( $order, DocumentTypes::STATEMENT );

		?>
		<div class="kitgenix-metabox kitgenix-pdf-invoicing-for-woocommerce-order-meta">
			<p><strong><?php esc_html_e( 'Invoice', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></strong><br>
				<span class="kitgenix-pdf-invoicing-invoice-number"><?php echo esc_html( $invoice_number ); ?></span></p>

			<?php if ( $can_stream_invoice ) : ?>
				<div class="kitgenix-pdf-invoicing-for-woocommerce-buttons kitgenix-compact-buttons kitgenix-field-row">
					<a href="<?php echo esc_url( $stream_url ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'Download Invoice (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				</div>
			<?php endif; ?>

			<div class="kitgenix-pdf-invoicing-for-woocommerce-buttons kitgenix-compact-buttons kitgenix-field-row">
				<?php if ( $can_stream_packing ) : ?>
					<a href="<?php echo esc_url( $packing_stream_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Download Packing Slip (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<?php if ( $can_stream_receipt ) : ?>
					<a href="<?php echo esc_url( $receipt_stream_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Download Receipt (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</div>

			<div class="kitgenix-pdf-invoicing-for-woocommerce-buttons kitgenix-compact-buttons kitgenix-field-row">
				<?php if ( $can_stream_pro_forma ) : ?>
					<a href="<?php echo esc_url( $pro_forma_stream_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Download Pro Forma Invoice (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<?php if ( $can_stream_delivery_note ) : ?>
					<a href="<?php echo esc_url( $delivery_note_stream_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Download Delivery Note (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<?php if ( $can_stream_statement ) : ?>
					<a href="<?php echo esc_url( $statement_stream_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Download Statement (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</div>

		<?php
		// Credit note section: show when the order has any refunds processed.
		$refunds = $order->get_refunds();
		$refund_count = is_array( $refunds ) ? count( $refunds ) : 0;
		if ( $refund_count > 0 && $can_stream_credit_note ) :
			$stored_history = $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history', true );
			if ( is_array( $stored_history ) && ! empty( $stored_history ) ) {
				$last = end( $stored_history );
				$credit_note_number = $last['number'] ?? __( 'Assigned on first generation', 'kitgenix-pdf-invoicing-for-woocommerce' );
			} else {
				$credit_note_number = __( 'Assigned on first generation', 'kitgenix-pdf-invoicing-for-woocommerce' );
			}
			?>
			<div class="kitgenix-pdf-invoicing-for-woocommerce-order-meta kitgenix-credit-note-meta kitgenix-field-row">
				<p><strong><?php esc_html_e( 'Credit Note', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></strong><br>
					<span class="kitgenix-pdf-invoicing-credit-note-number"><?php echo esc_html( $credit_note_number ); ?></span></p>

				<div class="kitgenix-pdf-invoicing-for-woocommerce-buttons kitgenix-compact-buttons">
					<a href="<?php echo esc_url( $cn_stream_url ); ?>" class="button button-secondary" target="_blank"><?php esc_html_e( 'Download Credit Note (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
				</div>
			</div>
		<?php endif; ?>
		</div>
		<?php
	}

	private static function stream_requested_document( string $type ): void {
		if ( ! isset( $_GET['order_id'] ) ) {
			wp_die( esc_html__( 'Missing order ID.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
		}

		$order_id = absint( wp_unslash( $_GET['order_id'] ) );
		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( '' === $nonce ) {
			wp_die( esc_html__( 'Invalid nonce.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
		}
		if ( ! wp_verify_nonce( $nonce, 'kitgenix_admin_pdf' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
		}

		if ( ! self::$pdf ) {
			$renderer = new TemplateRenderer();
			self::$pdf = new PdfGenerator( $renderer );
		}

		self::$pdf->stream_document( $order_id, $type );
		exit;
	}

	public static function handle_admin_stream_invoice(): void {
		self::stream_requested_document( DocumentTypes::INVOICE );
	}

	public static function handle_admin_stream_credit_note(): void {
		self::stream_requested_document( DocumentTypes::CREDIT_NOTE );
	}

	public static function handle_admin_stream_receipt(): void {
		self::stream_requested_document( DocumentTypes::RECEIPT );
	}

	public static function handle_admin_stream_packing_slip(): void {
		self::stream_requested_document( DocumentTypes::PACKING_SLIP );
	}

	public static function handle_admin_stream_pro_forma_invoice(): void {
		self::stream_requested_document( DocumentTypes::PRO_FORMA_INVOICE );
	}

	public static function handle_admin_stream_delivery_note(): void {
		self::stream_requested_document( DocumentTypes::DELIVERY_NOTE );
	}

	public static function handle_admin_stream_statement(): void {
		self::stream_requested_document( DocumentTypes::STATEMENT );
	}
}
