<?php

namespace Kitgenix\PDF_Invoicing\Modules\Admin;

use Kitgenix\PDF_Invoicing\Core\ModuleInterface;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\PdfGenerator;

defined( 'ABSPATH' ) || exit;

class AdminModule implements ModuleInterface {

    protected const BULK_ACTION_PREFIX = 'kitgenix_pdf_batch_export_';

    protected PdfGenerator $pdf;

    public function __construct( PdfGenerator $pdf ) {
        $this->pdf = $pdf;
    }

    public function get_id(): string {
        return 'admin';
    }

    public function register(): void {
        add_filter(
            'woocommerce_order_actions',
            [ $this, 'add_order_action' ],
            10,
            1
        );

        add_action(
            'woocommerce_order_action_kitgenix_download_pdf_invoice',
            [ $this, 'handle_admin_order_action' ]
        );

        add_filter(
            'bulk_actions-edit-shop_order',
            [ $this, 'register_order_bulk_actions' ]
        );
        add_filter(
            'handle_bulk_actions-edit-shop_order',
            [ $this, 'handle_order_bulk_action' ],
            10,
            3
        );

        add_filter(
            'bulk_actions-woocommerce_page_wc-orders',
            [ $this, 'register_order_bulk_actions' ]
        );
        add_filter(
            'handle_bulk_actions-woocommerce_page_wc-orders',
            [ $this, 'handle_order_bulk_action' ],
            10,
            3
        );

        add_action(
            'admin_notices',
            [ $this, 'render_batch_export_notice' ]
        );

        // Register the HPOS-aware PDF Documents order meta box and inject
        // the shared PdfGenerator instance to avoid duplicate construction.
        OrderMetaBox::init( $this->pdf );
    }

    protected function get_bulk_action_name( string $type ): string {
        return self::BULK_ACTION_PREFIX . sanitize_key( $type );
    }

    protected function get_document_type_from_bulk_action( string $action ): string {
        if ( 0 !== strpos( $action, self::BULK_ACTION_PREFIX ) ) {
            return '';
        }

        $type = substr( $action, strlen( self::BULK_ACTION_PREFIX ) );

        return in_array( $type, DocumentTypes::all(), true ) ? $type : '';
    }

    public function register_order_bulk_actions( array $actions ): array {
        foreach ( DocumentTypes::all() as $type ) {
            $actions[ $this->get_bulk_action_name( $type ) ] = sprintf(
                /* translators: %s: document label. */
                __( 'Export %s batch ZIP', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                DocumentTypes::get_label( $type )
            );
        }

        return $actions;
    }

    public function handle_order_bulk_action( string $redirect_to, string $action, array $ids ): string {
        $type = $this->get_document_type_from_bulk_action( $action );
        if ( '' === $type ) {
            return $redirect_to;
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            return add_query_arg(
                [
                    'kitgenix_pdf_batch_error' => 'insufficient_permissions',
                    'kitgenix_pdf_batch_type'  => $type,
                ],
                $redirect_to
            );
        }

        $order_ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'absint', $ids )
                )
            )
        );

        if ( empty( $order_ids ) ) {
            return add_query_arg(
                [
                    'kitgenix_pdf_batch_error' => 'no_orders',
                    'kitgenix_pdf_batch_type'  => $type,
                ],
                $redirect_to
            );
        }

        $archive = $this->pdf->generate_document_batch_archive( $order_ids, $type );
        if ( ! is_array( $archive ) ) {
            return add_query_arg(
                [
                    'kitgenix_pdf_batch_error' => 'archive_failed',
                    'kitgenix_pdf_batch_type'  => $type,
                ],
                $redirect_to
            );
        }

        $document_count = isset( $archive['document_count'] ) ? (int) $archive['document_count'] : 0;
        $archive_path   = isset( $archive['path'] ) ? (string) $archive['path'] : '';
        $archive_name   = isset( $archive['filename'] ) ? (string) $archive['filename'] : '';

        if ( $document_count < 1 || '' === $archive_path || '' === $archive_name ) {
            return add_query_arg(
                [
                    'kitgenix_pdf_batch_error' => 'no_documents',
                    'kitgenix_pdf_batch_type'  => $type,
                ],
                $redirect_to
            );
        }

        $this->stream_batch_archive( $archive_path, $archive_name );

        return $redirect_to;
    }

    protected function stream_batch_archive( string $archive_path, string $archive_name ): void {
        if ( '' === $archive_path || ! file_exists( $archive_path ) ) {
            wp_die( esc_html__( 'Batch PDF archive not found.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        $download_name = sanitize_file_name( $archive_name );
        if ( '' === $download_name ) {
            $download_name = 'documents-batch.zip';
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );

        $size = filesize( $archive_path );
        if ( false !== $size ) {
            header( 'Content-Length: ' . (string) $size );
        }

        try {
            $stream = new \SplFileObject( $archive_path, 'rb' );
        } catch ( \RuntimeException $exception ) {
            wp_die( esc_html__( 'Unable to open the batch ZIP archive.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        while ( ! $stream->eof() ) {
            echo $stream->fread( 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP output stream.
        }
        exit;
    }

    public function render_batch_export_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query args.
        if ( empty( $_GET['kitgenix_pdf_batch_error'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query args.
        $error = sanitize_key( wp_unslash( $_GET['kitgenix_pdf_batch_error'] ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query args.
        $type  = isset( $_GET['kitgenix_pdf_batch_type'] ) ? sanitize_key( wp_unslash( $_GET['kitgenix_pdf_batch_type'] ) ) : '';

        $label = in_array( $type, DocumentTypes::all(), true )
            ? DocumentTypes::get_label( $type )
            : __( 'document', 'kitgenix-pdf-invoicing-for-woocommerce' );

        switch ( $error ) {
            case 'insufficient_permissions':
                $message = __( 'You do not have permission to export batch PDF documents.', 'kitgenix-pdf-invoicing-for-woocommerce' );
                break;

            case 'no_orders':
                $message = __( 'Select at least one WooCommerce order before running a batch PDF export.', 'kitgenix-pdf-invoicing-for-woocommerce' );
                break;

            case 'no_documents':
                $message = sprintf(
                    /* translators: %s: document label. */
                    __( 'No %s PDFs were available for the selected orders.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    $label
                );
                break;

            case 'archive_failed':
                $message = sprintf(
                    /* translators: %s: document label. */
                    __( 'The %s batch ZIP could not be created on this server.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    $label
                );
                break;

            default:
                $message = __( 'The batch PDF export could not be completed.', 'kitgenix-pdf-invoicing-for-woocommerce' );
                break;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    public function add_order_action( array $actions ): array {
        $actions['kitgenix_download_pdf_invoice'] = __(
            'Download PDF Invoice',
            'kitgenix-pdf-invoicing-for-woocommerce'
        );

        return $actions;
    }

    public function handle_admin_order_action( \WC_Order $order ): void {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( esc_html__( 'You are not allowed to do this.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        $this->pdf->stream_document(
            $order->get_id(),
            \Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes::INVOICE
        );
        exit;
    }

    public function add_order_meta_box(): void {
        // Intentionally left empty; order meta box is registered by
        // Kitgenix\PDF_Invoicing\Modules\Admin\OrderMetaBox::init() from
        // the main plugin bootstrap, mirroring the Kitgenix Order Tracking
        // plugin pattern for maximum compatibility with HPOS.
    }
}
