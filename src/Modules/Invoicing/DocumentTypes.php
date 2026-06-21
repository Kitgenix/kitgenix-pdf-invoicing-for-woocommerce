<?php

namespace Kitgenix\PDF_Invoicing\Modules\Invoicing;

defined( 'ABSPATH' ) || exit;

class DocumentTypes {
    public const INVOICE      = 'invoice';
    public const PACKING_SLIP = 'packing_slip';
    public const CREDIT_NOTE  = 'credit_note';
    public const RECEIPT      = 'receipt';
    public const PRO_FORMA_INVOICE = 'pro_forma_invoice';
    public const DELIVERY_NOTE     = 'delivery_note';
    public const STATEMENT         = 'statement';

    /**
     * All built-in document types.
     *
     * @return string[]
     */
    public static function all(): array {
        $types = [
            self::INVOICE,
            self::PACKING_SLIP,
            self::CREDIT_NOTE,
            self::RECEIPT,
            self::PRO_FORMA_INVOICE,
            self::DELIVERY_NOTE,
            self::STATEMENT,
        ];

        return (array) apply_filters( 'kitgenix_pdf_document_types', $types );
    }

    /**
     * Human label for a document type.
     */
    public static function get_label( string $type ): string {
        $labels = [
            self::INVOICE      => __( 'Invoice', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::PACKING_SLIP => __( 'Packing Slip', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::CREDIT_NOTE  => __( 'Credit Note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::RECEIPT      => __( 'Receipt', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::PRO_FORMA_INVOICE => __( 'Pro Forma Invoice', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::DELIVERY_NOTE     => __( 'Delivery Note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            self::STATEMENT         => __( 'Statement', 'kitgenix-pdf-invoicing-for-woocommerce' ),
        ];

        return $labels[ $type ] ?? $type;
    }

    public static function get_template_base_type( string $type ): string {
        switch ( $type ) {
            case self::PRO_FORMA_INVOICE:
            case self::STATEMENT:
                return self::INVOICE;

            case self::DELIVERY_NOTE:
                return self::PACKING_SLIP;
        }

        return $type;
    }

    public static function get_admin_stream_action( string $type ): string {
        return 'kitgenix_admin_stream_' . sanitize_key( $type );
    }

    public static function get_reference_label( string $type ): string {
        switch ( $type ) {
            case self::INVOICE:
                return __( 'Invoice Number', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::RECEIPT:
                return __( 'Receipt Number', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::PACKING_SLIP:
                return __( 'Packing Slip #', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::CREDIT_NOTE:
                return __( 'Credit Note Number', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::PRO_FORMA_INVOICE:
                return __( 'Pro Forma Number', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::DELIVERY_NOTE:
                return __( 'Delivery Note #', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::STATEMENT:
                return __( 'Statement Reference', 'kitgenix-pdf-invoicing-for-woocommerce' );
        }

        return __( 'Document Reference', 'kitgenix-pdf-invoicing-for-woocommerce' );
    }

    public static function get_date_label( string $type ): string {
        switch ( $type ) {
            case self::INVOICE:
                return __( 'Invoice Date', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::RECEIPT:
                return __( 'Receipt Date', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::PACKING_SLIP:
                return __( 'Packing Slip Date', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::CREDIT_NOTE:
                return __( 'Credit Note Date', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::PRO_FORMA_INVOICE:
                return __( 'Pro Forma Date', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::DELIVERY_NOTE:
                return __( 'Delivery Note Date', 'kitgenix-pdf-invoicing-for-woocommerce' );

            case self::STATEMENT:
                return __( 'Statement Date', 'kitgenix-pdf-invoicing-for-woocommerce' );
        }

        return __( 'Document Date', 'kitgenix-pdf-invoicing-for-woocommerce' );
    }

    public static function get_identifier( \WC_Order $order, array $settings, string $type ): string {
        switch ( $type ) {
            case self::INVOICE:
                $identifier = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_number', true );
                if ( '' !== $identifier ) {
                    return $identifier;
                }

                return (string) ( $settings['invoice_prefix'] ?? '' ) . $order->get_order_number();

            case self::RECEIPT:
                $identifier = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_receipt_number', true );
                if ( '' !== $identifier ) {
                    return $identifier;
                }

                $prefix = isset( $settings['receipt_prefix'] ) ? (string) $settings['receipt_prefix'] : '';
                if ( '' === $prefix ) {
                    $prefix = (string) ( $settings['invoice_prefix'] ?? '' );
                }

                return $prefix . $order->get_order_number();

            case self::PACKING_SLIP:
                $identifier = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_number', true );
                if ( '' !== $identifier ) {
                    return $identifier;
                }

                return (string) ( $settings['packing_slip_prefix'] ?? 'PS-' ) . $order->get_order_number();

            case self::CREDIT_NOTE:
                $history = $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history', true );
                if ( is_array( $history ) && ! empty( $history ) ) {
                    $last_entry = end( $history );
                    reset( $history );

                    if ( is_array( $last_entry ) && ! empty( $last_entry['number'] ) ) {
                        return (string) $last_entry['number'];
                    }
                }

                return (string) ( $settings['credit_note_prefix'] ?? 'CN-' ) . $order->get_order_number();

            case self::PRO_FORMA_INVOICE:
                return 'PF-' . $order->get_order_number();

            case self::DELIVERY_NOTE:
                return 'DN-' . $order->get_order_number();

            case self::STATEMENT:
                return 'ST-' . $order->get_order_number();
        }

        return $type . '-' . $order->get_order_number();
    }

    public static function get_issue_timestamp( \WC_Order $order, string $type ): int {
        switch ( $type ) {
            case self::INVOICE:
                return self::parse_timestamp( (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_date', true ) ) ?: self::get_order_created_timestamp( $order );

            case self::RECEIPT:
                return self::parse_timestamp( (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_receipt_date', true ) ) ?: self::get_order_created_timestamp( $order );

            case self::PACKING_SLIP:
                return self::parse_timestamp( (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_date', true ) ) ?: self::get_order_created_timestamp( $order );

            case self::CREDIT_NOTE:
                $history = $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history', true );
                if ( is_array( $history ) && ! empty( $history ) ) {
                    $last_entry = end( $history );
                    reset( $history );

                    if ( is_array( $last_entry ) && ! empty( $last_entry['date'] ) ) {
                        return self::parse_timestamp( (string) $last_entry['date'] ) ?: self::get_order_created_timestamp( $order );
                    }
                }

                return self::get_order_created_timestamp( $order );
        }

        return self::get_order_created_timestamp( $order );
    }

    protected static function parse_timestamp( string $value ): int {
        if ( '' === $value ) {
            return 0;
        }

        $timestamp = strtotime( $value );

        return false !== $timestamp ? (int) $timestamp : 0;
    }

    protected static function get_order_created_timestamp( \WC_Order $order ): int {
        return $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
    }
}
