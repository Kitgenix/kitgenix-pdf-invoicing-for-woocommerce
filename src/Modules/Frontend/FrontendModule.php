<?php

namespace Kitgenix\PDF_Invoicing\Modules\Frontend;

use Kitgenix\PDF_Invoicing\Core\ModuleInterface;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\PdfGenerator;

defined( 'ABSPATH' ) || exit;

class FrontendModule implements ModuleInterface {

    protected PdfGenerator $pdf;

    public function __construct( PdfGenerator $pdf ) {
        $this->pdf = $pdf;
    }

    public function get_id(): string {
        return 'frontend';
    }

    public function register(): void {
        add_action(
            'woocommerce_order_details_after_order_table',
            [ $this, 'add_download_button' ]
        );
        add_action(
            'woocommerce_order_details_after_order_table',
            [ $this, 'add_credit_note_buttons' ]
        );
        add_filter(
            'woocommerce_my_account_my_orders_actions',
            [ $this, 'add_my_account_order_actions' ],
            10,
            2
        );
    }

    public function add_download_button( \WC_Order $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $order_id = $order->get_id();

        // Only show for logged-in customer who owns the order.
        if ( ! is_user_logged_in() || (int) get_current_user_id() !== (int) $order->get_user_id() ) {
            return;
        }

        $enabled = apply_filters(
            'kitgenix_pdf_document_enabled',
            true,
            $order,
            \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE
        );

        if ( ! $enabled ) {
            return;
        }

        $url = add_query_arg(
            [
                'kitgenix_pdf'  => 1,
                'kitgenix_doc'  => \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE,
                'order_id' => $order_id,
                '_wpnonce' => wp_create_nonce( 'kitgenix_download_' . \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE . '_' . $order_id ),
            ],
            home_url( '/' )
        );
        ?>
        <p class="kitgenix-pdf-invoicing-for-woocommerce-download-invoice-wrapper">
            <a class="button kitgenix-pdf-invoicing-for-woocommerce-download-invoice"
               href="<?php echo esc_url( $url ); ?>">
                <?php esc_html_e( 'Download Invoice (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Show credit note download button(s) when the order has refunds.
     * Customers who own the order will see a "Download Credit Note (PDF)" link
     * if at least one refund has been processed for the order.
     */
    public function add_credit_note_buttons( \WC_Order $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $order_id = $order->get_id();

        // Only show for logged-in customer who owns the order.
        if ( ! is_user_logged_in() || (int) get_current_user_id() !== (int) $order->get_user_id() ) {
            return;
        }

        $refunds = $order->get_refunds();
        $refund_count = is_array( $refunds ) ? count( $refunds ) : 0;

        if ( $refund_count <= 0 ) {
            return;
        }

        $doc_type = \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::CREDIT_NOTE;
        $url = add_query_arg(
            [
                'kitgenix_pdf'  => 1,
                'kitgenix_doc'  => $doc_type,
                'order_id' => $order_id,
                '_wpnonce' => wp_create_nonce( 'kitgenix_download_' . $doc_type . '_' . $order_id ),
            ],
            home_url( '/' )
        );
        ?>
        <p class="kitgenix-pdf-invoicing-for-woocommerce-download-credit-note-wrapper">
            <a class="button kitgenix-pdf-invoicing-for-woocommerce-download-credit-note"
               href="<?php echo esc_url( $url ); ?>">
                <?php esc_html_e( 'Download Credit Note (PDF)', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Add "View Invoice" and "View Credit Note" actions on the
     * My Account → Orders table, next to the standard "View" action.
     *
     * @param array     $actions Existing actions.
     * @param \WC_Order $order   Order object.
     *
     * @return array
     */
    public function add_my_account_order_actions( array $actions, \WC_Order $order ): array {
        if ( ! $order instanceof \WC_Order ) {
            return $actions;
        }

        // Only for the logged-in customer who owns the order.
        if ( ! is_user_logged_in() || (int) get_current_user_id() !== (int) $order->get_user_id() ) {
            return $actions;
        }

        $order_id = $order->get_id();

        // Invoice action.
        $invoice_enabled = apply_filters(
            'kitgenix_pdf_document_enabled',
            true,
            $order,
            \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE
        );

        if ( $invoice_enabled ) {
            $invoice_url = add_query_arg(
                [
                    'kitgenix_pdf'  => 1,
                    'kitgenix_doc'  => \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE,
                    'order_id'      => $order_id,
                    '_wpnonce'      => wp_create_nonce( 'kitgenix_download_' . \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE . '_' . $order_id ),
                ],
                home_url( '/' )
            );

            $actions['kitgenix_view_invoice'] = [
                'url'    => $invoice_url,
                'name'   => __( 'View Invoice', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'action' => 'view-invoice',
            ];
        }

        // Credit note action (only if refunds exist).
        $refunds = $order->get_refunds();
        $refund_count = is_array( $refunds ) ? count( $refunds ) : 0;

        if ( $refund_count > 0 ) {
            $doc_type    = \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::CREDIT_NOTE;
            $credit_url  = add_query_arg(
                [
                    'kitgenix_pdf'  => 1,
                    'kitgenix_doc'  => $doc_type,
                    'order_id'      => $order_id,
                    '_wpnonce'      => wp_create_nonce( 'kitgenix_download_' . $doc_type . '_' . $order_id ),
                ],
                home_url( '/' )
            );

            $actions['kitgenix_view_credit_note'] = [
                'url'    => $credit_url,
                'name'   => __( 'View Credit Note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'action' => 'view-credit-note',
            ];
        }

        return $actions;
    }
}
