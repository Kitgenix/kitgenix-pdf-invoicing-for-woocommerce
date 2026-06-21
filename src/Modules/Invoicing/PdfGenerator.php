<?php

namespace Kitgenix\PDF_Invoicing\Modules\Invoicing;

use Dompdf\Dompdf;
use Dompdf\Options;
use Kitgenix\PDF_Invoicing\Modules\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class PdfGenerator {

    protected const ARCHIVE_META_KEY = '_kitgenix_pdf_invoicing_for_woocommerce_archived_documents';
    protected const SEQUENCE_OPTION_KEY = 'kitgenix_pdf_invoicing_for_woocommerce_number_sequences';

    protected TemplateRenderer $renderer;

    public function __construct( TemplateRenderer $renderer ) {
        $this->renderer = $renderer;
    }

    /**
     * Record a privacy-safe metric for generated PDFs.
     * Stored as integers only (no order IDs, no customer data).
     */
    protected static function record_generated_metric( string $type, int $delta = 1 ): void {
        if ( $delta === 0 ) {
            return;
        }

        $metrics = (array) get_option( 'kitgenix_pdf_invoicing_for_woocommerce_metrics', [] );

        $total = isset( $metrics['documents_total'] ) ? (int) $metrics['documents_total'] : 0;
        $metrics['documents_total'] = max( 0, $total + $delta );

        if ( ! isset( $metrics['documents_by_type'] ) || ! is_array( $metrics['documents_by_type'] ) ) {
            $metrics['documents_by_type'] = [];
        }

        $by_type = (array) $metrics['documents_by_type'];
        $current = isset( $by_type[ $type ] ) ? (int) $by_type[ $type ] : 0;
        $by_type[ $type ] = max( 0, $current + $delta );
        $metrics['documents_by_type'] = $by_type;

        update_option( 'kitgenix_pdf_invoicing_for_woocommerce_metrics', $metrics, false );
    }

    protected function is_persistent_archive_enabled( \WC_Order $order, string $type ): bool {
        return (bool) apply_filters(
            'kitgenix_pdf_persistent_archive_enabled',
            true,
            $order,
            $type
        );
    }

    protected function get_archived_documents_meta( \WC_Order $order ): array {
        $documents = $order->get_meta( self::ARCHIVE_META_KEY, true );

        return is_array( $documents ) ? $documents : [];
    }

    protected function get_archive_root_paths( \WC_Order $order, string $type ): ?array {
        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
            return null;
        }

        if ( ! is_string( $upload_dir['basedir'] ) || ! is_string( $upload_dir['baseurl'] ) ) {
            return null;
        }

        $relative_root = (string) apply_filters(
            'kitgenix_pdf_archive_relative_root',
            'kitgenix-pdf-invoicing-for-woocommerce/archive',
            $order,
            $type
        );
        $relative_root = trim( str_replace( '\\', '/', $relative_root ), '/' );

        if ( '' === $relative_root ) {
            return null;
        }

        return [
            'relative_root' => $relative_root,
            'basedir'       => trailingslashit( $upload_dir['basedir'] ) . str_replace( '/', DIRECTORY_SEPARATOR, $relative_root ),
            'baseurl'       => trailingslashit( $upload_dir['baseurl'] ) . $relative_root,
        ];
    }

    protected function get_document_archive_directory( \WC_Order $order, string $type ): ?array {
        $root = $this->get_archive_root_paths( $order, $type );
        if ( ! is_array( $root ) ) {
            return null;
        }

        $type_segment = sanitize_key( $type );
        if ( '' === $type_segment ) {
            return null;
        }

        $order_segment = 'order-' . $order->get_id();

        return [
            'relative_directory' => $root['relative_root'] . '/' . $order_segment . '/' . $type_segment,
            'absolute_directory' => trailingslashit( $root['basedir'] ) . $order_segment . DIRECTORY_SEPARATOR . $type_segment,
        ];
    }

    protected function resolve_uploads_relative_path( string $relative_path ): string {
        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) || ! is_string( $upload_dir['basedir'] ) ) {
            return '';
        }

        $normalized_relative_path = ltrim( str_replace( '\\', '/', $relative_path ), '/' );

        return trailingslashit( $upload_dir['basedir'] ) . $normalized_relative_path;
    }

    protected function get_document_prefix( array $settings, string $type ): string {
        switch ( $type ) {
            case DocumentTypes::INVOICE:
                return (string) ( $settings['invoice_prefix'] ?? '' );

            case DocumentTypes::RECEIPT:
                $receipt_prefix = isset( $settings['receipt_prefix'] ) ? (string) $settings['receipt_prefix'] : '';
                if ( '' !== $receipt_prefix ) {
                    return $receipt_prefix;
                }

                return (string) ( $settings['invoice_prefix'] ?? '' );

            case DocumentTypes::PACKING_SLIP:
                return (string) ( $settings['packing_slip_prefix'] ?? 'PS-' );

            case DocumentTypes::CREDIT_NOTE:
                return (string) ( $settings['credit_note_prefix'] ?? 'CN-' );
        }

        return '';
    }

    protected function get_document_number_format( array $settings, string $type ): string {
        switch ( $type ) {
            case DocumentTypes::INVOICE:
                $format = isset( $settings['invoice_number_format'] ) ? (string) $settings['invoice_number_format'] : '';
                return '' !== $format ? $format : '{prefix}{order_number}';

            case DocumentTypes::RECEIPT:
                $format = isset( $settings['receipt_number_format'] ) ? (string) $settings['receipt_number_format'] : '';
                return '' !== $format ? $format : '{prefix}{order_number}';

            case DocumentTypes::PACKING_SLIP:
                $format = isset( $settings['packing_slip_number_format'] ) ? (string) $settings['packing_slip_number_format'] : '';
                return '' !== $format ? $format : '{prefix}{order_number}';

            case DocumentTypes::CREDIT_NOTE:
                $format = isset( $settings['credit_note_number_format'] ) ? (string) $settings['credit_note_number_format'] : '';
                return '' !== $format ? $format : '{prefix}{order_number}-{refund_sequence}';
        }

        return '{prefix}{order_number}';
    }

    protected function get_sequence_settings( array $settings ): array {
        $allowed_scopes = [ 'none', 'calendar_year', 'fiscal_year' ];
        $reset_scope    = isset( $settings['number_sequence_reset'] ) ? sanitize_key( (string) $settings['number_sequence_reset'] ) : 'none';
        if ( ! in_array( $reset_scope, $allowed_scopes, true ) ) {
            $reset_scope = 'none';
        }

        $padding = isset( $settings['number_sequence_padding'] ) ? absint( $settings['number_sequence_padding'] ) : 4;
        if ( $padding < 1 ) {
            $padding = 4;
        }
        if ( $padding > 12 ) {
            $padding = 12;
        }

        $start_month = isset( $settings['fiscal_year_start_month'] ) ? absint( $settings['fiscal_year_start_month'] ) : 1;
        if ( $start_month < 1 || $start_month > 12 ) {
            $start_month = 1;
        }

        return [
            'reset_scope' => $reset_scope,
            'padding'     => $padding,
            'start_month' => $start_month,
        ];
    }

    protected function get_site_datetime( string $date_time = 'now' ): \DateTimeImmutable {
        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );

        return new \DateTimeImmutable( $date_time, $timezone );
    }

    protected function get_issued_datetime( string $issued_at ): \DateTimeImmutable {
        if ( '' !== $issued_at ) {
            try {
                return $this->get_site_datetime( $issued_at );
            } catch ( \Exception $exception ) {
                return $this->get_site_datetime();
            }
        }

        return $this->get_site_datetime();
    }

    protected function get_fiscal_year_context( \DateTimeImmutable $issued_at, int $start_month ): array {
        $year  = (int) $issued_at->format( 'Y' );
        $month = (int) $issued_at->format( 'n' );

        $start_year = $year;
        if ( $start_month > 1 && $month < $start_month ) {
            $start_year--;
        }

        $end_year = $start_year + 1;

        if ( 1 === $start_month ) {
            $label       = (string) $start_year;
            $short_label = substr( (string) $start_year, -2 );
        } else {
            $label       = $start_year . '-' . $end_year;
            $short_label = substr( (string) $start_year, -2 ) . '-' . substr( (string) $end_year, -2 );
        }

        return [
            'start_year'  => $start_year,
            'end_year'    => $end_year,
            'label'       => $label,
            'short_label' => $short_label,
        ];
    }

    protected function number_format_requires_sequence( string $format ): bool {
        return str_contains( $format, '{sequence}' );
    }

    protected function get_sequence_period_key( string $type, \DateTimeImmutable $issued_at, array $sequence_settings ): string {
        $scope = isset( $sequence_settings['reset_scope'] ) ? (string) $sequence_settings['reset_scope'] : 'none';

        if ( 'calendar_year' === $scope ) {
            return 'year-' . $issued_at->format( 'Y' );
        }

        if ( 'fiscal_year' === $scope ) {
            $fiscal = $this->get_fiscal_year_context( $issued_at, (int) ( $sequence_settings['start_month'] ?? 1 ) );
            return 'fiscal-' . $fiscal['start_year'] . '-' . $fiscal['end_year'];
        }

        return 'global';
    }

    protected function get_sequence_counters(): array {
        $counters = get_option( self::SEQUENCE_OPTION_KEY, [] );

        return is_array( $counters ) ? $counters : [];
    }

    protected function get_next_sequence_number( string $type, \DateTimeImmutable $issued_at, array $sequence_settings, bool $persist ): int {
        $period_key = $this->get_sequence_period_key( $type, $issued_at, $sequence_settings );
        $counters   = $this->get_sequence_counters();
        $current    = isset( $counters[ $type ][ $period_key ] ) ? (int) $counters[ $type ][ $period_key ] : 0;
        $next       = $current + 1;

        if ( $persist ) {
            if ( ! isset( $counters[ $type ] ) || ! is_array( $counters[ $type ] ) ) {
                $counters[ $type ] = [];
            }

            $counters[ $type ][ $period_key ] = $next;
            update_option( self::SEQUENCE_OPTION_KEY, $counters, false );
        }

        return $next;
    }

    protected function build_document_number_context( \WC_Order $order, string $type, string $issued_at, array $settings, int $refund_sequence, bool $persist_sequence ): array {
        $format            = $this->get_document_number_format( $settings, $type );
        $prefix            = $this->get_document_prefix( $settings, $type );
        $sequence_settings = $this->get_sequence_settings( $settings );
        $issued_datetime   = $this->get_issued_datetime( $issued_at );
        $fiscal            = $this->get_fiscal_year_context( $issued_datetime, (int) $sequence_settings['start_month'] );
        $sequence          = 0;

        if ( $this->number_format_requires_sequence( $format ) ) {
            $sequence = $this->get_next_sequence_number( $type, $issued_datetime, $sequence_settings, $persist_sequence );
        }

        $billing_country  = strtoupper( (string) $order->get_billing_country() );
        $shipping_country = strtoupper( (string) $order->get_shipping_country() );
        $country          = '' !== $billing_country ? $billing_country : $shipping_country;

        $context = [
            'format'              => $format,
            'prefix'              => $prefix,
            'document_type'       => $type,
            'order_id'            => (string) $order->get_id(),
            'order_number'        => (string) $order->get_order_number(),
            'sequence'            => $sequence,
            'sequence_padded'     => $sequence > 0 ? str_pad( (string) $sequence, (int) $sequence_settings['padding'], '0', STR_PAD_LEFT ) : '',
            'refund_sequence'     => $refund_sequence > 0 ? (string) $refund_sequence : '',
            'issued_at'           => $issued_at,
            'year'                => $issued_datetime->format( 'Y' ),
            'yy'                  => $issued_datetime->format( 'y' ),
            'month'               => $issued_datetime->format( 'm' ),
            'day'                 => $issued_datetime->format( 'd' ),
            'billing_country'     => $billing_country,
            'shipping_country'    => $shipping_country,
            'country'             => $country,
            'fiscal_year'         => (string) $fiscal['label'],
            'fiscal_year_short'   => (string) $fiscal['short_label'],
            'fiscal_year_start'   => (string) $fiscal['start_year'],
            'fiscal_year_end'     => (string) $fiscal['end_year'],
        ];

        return (array) apply_filters( 'kitgenix_pdf_document_number_context', $context, $order, $type );
    }

    protected function format_document_identifier( array $context, \WC_Order $order, string $type ): string {
        $format = isset( $context['format'] ) ? (string) $context['format'] : '{prefix}{order_number}';
        $format = (string) apply_filters( 'kitgenix_pdf_document_number_format', $format, $order, $type, $context );

        $replacements = [
            '{prefix}'            => (string) ( $context['prefix'] ?? '' ),
            '{order_id}'          => (string) ( $context['order_id'] ?? '' ),
            '{order_number}'      => (string) ( $context['order_number'] ?? '' ),
            '{sequence}'          => (string) ( $context['sequence_padded'] ?? '' ),
            '{refund_sequence}'   => (string) ( $context['refund_sequence'] ?? '' ),
            '{year}'              => (string) ( $context['year'] ?? '' ),
            '{yy}'                => (string) ( $context['yy'] ?? '' ),
            '{month}'             => (string) ( $context['month'] ?? '' ),
            '{day}'               => (string) ( $context['day'] ?? '' ),
            '{country}'           => (string) ( $context['country'] ?? '' ),
            '{billing_country}'   => (string) ( $context['billing_country'] ?? '' ),
            '{shipping_country}'  => (string) ( $context['shipping_country'] ?? '' ),
            '{fiscal_year}'       => (string) ( $context['fiscal_year'] ?? '' ),
            '{fiscal_year_short}' => (string) ( $context['fiscal_year_short'] ?? '' ),
            '{fiscal_year_start}' => (string) ( $context['fiscal_year_start'] ?? '' ),
            '{fiscal_year_end}'   => (string) ( $context['fiscal_year_end'] ?? '' ),
        ];

        $identifier = strtr( $format, $replacements );
        $identifier = preg_replace( '/\{[a-z0-9_]+\}/i', '', $identifier );
        $identifier = is_string( $identifier ) ? trim( preg_replace( '/\s+/', ' ', $identifier ) ) : '';

        $identifier = (string) apply_filters( 'kitgenix_pdf_document_identifier', $identifier, $order, $type, $context );
        $identifier = sanitize_text_field( $identifier );

        if ( '' === $identifier ) {
            $identifier = (string) ( $replacements['{prefix}'] ?? '' ) . (string) $order->get_order_number();
        }

        return $identifier;
    }

    protected function generate_document_identifier( \WC_Order $order, string $type, string $issued_at, array $settings, int $refund_sequence = 0, bool $persist_sequence = true ): string {
        $context = $this->build_document_number_context( $order, $type, $issued_at, $settings, $refund_sequence, $persist_sequence );

        return $this->format_document_identifier( $context, $order, $type );
    }

    protected function ensure_document_identity( \WC_Order $order, string $type ): array {
        $settings   = Settings::get_all();
        $did_update = false;
        $identifier = '';
        $issued_at  = '';

        if ( DocumentTypes::INVOICE === $type ) {
            $identifier = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_number', true );
            $issued_at  = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_date', true );

            if ( '' === $issued_at ) {
                $issued_at = current_time( 'mysql' );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_date', $issued_at );
                $did_update = true;
            }

            if ( '' === $identifier ) {
                $identifier = $this->generate_document_identifier( $order, $type, $issued_at, $settings );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_invoice_number', $identifier );
                $did_update = true;
            }
        } elseif ( DocumentTypes::RECEIPT === $type ) {
            $identifier = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_receipt_number', true );
            $issued_at  = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_receipt_date', true );

            if ( '' === $issued_at ) {
                $issued_at = current_time( 'mysql' );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_receipt_date', $issued_at );
                $did_update = true;
            }

            if ( '' === $identifier ) {
                $identifier = $this->generate_document_identifier( $order, $type, $issued_at, $settings );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_receipt_number', $identifier );
                $did_update = true;
            }
        } elseif ( DocumentTypes::CREDIT_NOTE === $type ) {
            $refunds = $order->get_refunds();
            $history = $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history', true );

            if ( ! is_array( $history ) ) {
                $history = [];
            }

            $refund_count           = is_array( $refunds ) ? count( $refunds ) : 0;
            $existing_history_count = count( $history );

            if ( $refund_count > $existing_history_count ) {
                $count         = (int) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_count', true );
                $to_create     = $refund_count - $existing_history_count;
                $new_refunds   = array_slice( is_array( $refunds ) ? $refunds : [], $existing_history_count );

                for ( $i = 0; $i < $to_create; $i++ ) {
                    $count++;

                    $refund_sequence = $existing_history_count + $i + 1;
                    $credit_issued_at = current_time( 'mysql' );

                    if ( isset( $new_refunds[ $i ] ) && $new_refunds[ $i ] instanceof \WC_Order_Refund ) {
                        $created_at = $new_refunds[ $i ]->get_date_created();
                        if ( $created_at ) {
                            $credit_issued_at = $created_at->date( 'Y-m-d H:i:s' );
                        }
                    }

                    $credit_identifier = $this->generate_document_identifier( $order, $type, $credit_issued_at, $settings, $refund_sequence );

                    $history[] = [
                        'number' => $credit_identifier,
                        'date'   => $credit_issued_at,
                    ];
                }

                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_count', $count );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_credit_note_history', $history );
                $did_update = true;
            }

            if ( ! empty( $history ) ) {
                $last_entry  = end( $history );
                $identifier  = isset( $last_entry['number'] ) ? (string) $last_entry['number'] : '';
                $issued_at   = isset( $last_entry['date'] ) ? (string) $last_entry['date'] : '';
                reset( $history );
            }
        } elseif ( DocumentTypes::PACKING_SLIP === $type ) {
            $identifier = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_number', true );
            $issued_at  = (string) $order->get_meta( '_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_date', true );

            if ( '' === $issued_at ) {
                $issued_at = current_time( 'mysql' );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_date', $issued_at );
                $did_update = true;
            }

            if ( '' === $identifier ) {
                $identifier = $this->generate_document_identifier( $order, $type, $issued_at, $settings );
                $order->update_meta_data( '_kitgenix_pdf_invoicing_for_woocommerce_packing_slip_number', $identifier );
                $did_update = true;
            }
        }

        if ( $did_update ) {
            $order->save();
        }

        if ( '' === $identifier ) {
            $identifier = $type . '-' . $order->get_order_number();
        }

        if ( '' === $issued_at ) {
            $issued_at = current_time( 'mysql' );
        }

        $archive_key = sanitize_key( str_replace( '-', '_', sanitize_file_name( strtolower( $identifier ) ) ) );
        if ( '' === $archive_key ) {
            $archive_key = sanitize_key( $type . '_' . $order->get_id() );
        }

        return [
            'identifier'  => $identifier,
            'issued_at'   => $issued_at,
            'archive_key' => $archive_key,
        ];
    }

    protected function get_current_archived_document_entry( \WC_Order $order, string $type ): ?array {
        if ( ! $this->is_persistent_archive_enabled( $order, $type ) ) {
            return null;
        }

        $identity       = $this->ensure_document_identity( $order, $type );
        $archive_key    = isset( $identity['archive_key'] ) ? (string) $identity['archive_key'] : '';
        $all_documents  = $this->get_archived_documents_meta( $order );
        $type_documents = isset( $all_documents[ $type ] ) && is_array( $all_documents[ $type ] ) ? $all_documents[ $type ] : [];
        $entries        = isset( $type_documents['entries'] ) && is_array( $type_documents['entries'] ) ? $type_documents['entries'] : [];

        if ( '' === $archive_key || ! isset( $entries[ $archive_key ] ) || ! is_array( $entries[ $archive_key ] ) ) {
            return null;
        }

        $entry         = $entries[ $archive_key ];
        $relative_path = isset( $entry['relative_path'] ) ? (string) $entry['relative_path'] : '';
        $absolute_path = '' !== $relative_path ? $this->resolve_uploads_relative_path( $relative_path ) : '';

        if ( '' === $absolute_path || ! file_exists( $absolute_path ) ) {
            return null;
        }

        $entry['absolute_path'] = $absolute_path;
        $entry['archive_key']   = $archive_key;

        return $entry;
    }

    protected function get_archived_storage_filename( string $type, array $identity ): string {
        $archive_key = isset( $identity['archive_key'] ) ? sanitize_key( (string) $identity['archive_key'] ) : '';
        if ( '' === $archive_key ) {
            $archive_key = sanitize_key( $type );
        }

        return sanitize_file_name( sanitize_key( $type ) . '-' . $archive_key . '.pdf' );
    }

    protected function persist_document_archive( \WC_Order $order, string $type, string $download_filename, string $output ): ?array {
        if ( ! $this->is_persistent_archive_enabled( $order, $type ) ) {
            return null;
        }

        $existing_entry = $this->get_current_archived_document_entry( $order, $type );
        if ( is_array( $existing_entry ) ) {
            return $existing_entry;
        }

        $identity  = $this->ensure_document_identity( $order, $type );
        $directory = $this->get_document_archive_directory( $order, $type );
        if ( ! is_array( $directory ) ) {
            return null;
        }

        $absolute_directory = isset( $directory['absolute_directory'] ) ? (string) $directory['absolute_directory'] : '';
        $relative_directory = isset( $directory['relative_directory'] ) ? (string) $directory['relative_directory'] : '';

        if ( '' === $absolute_directory || '' === $relative_directory ) {
            return null;
        }

        if ( ! wp_mkdir_p( $absolute_directory ) ) {
            return null;
        }

        $storage_filename = $this->get_archived_storage_filename( $type, $identity );
        $absolute_path    = trailingslashit( $absolute_directory ) . $storage_filename;

        if ( ! file_exists( $absolute_path ) ) {
            /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- Writing immutable archived PDFs into the plugin-managed uploads archive directory. */
            $bytes = file_put_contents( $absolute_path, $output );
            if ( false === $bytes ) {
                return null;
            }
        }

        $filesize      = filesize( $absolute_path );
        $hash          = hash_file( 'sha256', $absolute_path );
        $relative_path = trailingslashit( $relative_directory ) . $storage_filename;

        $entry = [
            'relative_path'      => $relative_path,
            'storage_filename'   => $storage_filename,
            'download_filename'  => sanitize_file_name( $download_filename ),
            'document_identifier'=> isset( $identity['identifier'] ) ? (string) $identity['identifier'] : '',
            'issued_at'          => isset( $identity['issued_at'] ) ? (string) $identity['issued_at'] : '',
            'generated_at'       => current_time( 'mysql' ),
            'filesize'           => false !== $filesize ? (int) $filesize : 0,
            'hash'               => is_string( $hash ) ? $hash : '',
        ];

        $all_documents  = $this->get_archived_documents_meta( $order );
        $type_documents = isset( $all_documents[ $type ] ) && is_array( $all_documents[ $type ] ) ? $all_documents[ $type ] : [];
        $entries        = isset( $type_documents['entries'] ) && is_array( $type_documents['entries'] ) ? $type_documents['entries'] : [];
        $archive_key    = isset( $identity['archive_key'] ) ? (string) $identity['archive_key'] : '';

        if ( '' === $archive_key ) {
            return null;
        }

        $entries[ $archive_key ]           = $entry;
        $type_documents['entries']         = $entries;
        $type_documents['latest_key']      = $archive_key;
        $all_documents[ $type ]            = $type_documents;

        $order->update_meta_data( self::ARCHIVE_META_KEY, $all_documents );
        $order->save();

        $entry['absolute_path'] = $absolute_path;
        $entry['archive_key']   = $archive_key;

        do_action( 'kitgenix_pdf_document_archived', $entry, $order, $type );

        return $entry;
    }

    protected function generate_document_output( \WC_Order $order, string $type, string &$filename ): ?string {
        $dompdf = $this->create_dompdf_for_document( $order, $type, $filename );
        $output = $dompdf->output();

        return is_string( $output ) && '' !== $output ? $output : null;
    }

    protected function copy_file_to_temp_path( string $source_path, string $filename ): ?string {
        if ( '' === $source_path || ! file_exists( $source_path ) ) {
            return null;
        }

        $tmp_path = $this->create_temp_file_path( $filename, 'kitgenix_pdf_document_' );
        if ( ! $tmp_path ) {
            return null;
        }

        /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_copy -- Copying a generated archived PDF into a temp file for one-request email or ZIP use. */
        $copied = copy( $source_path, $tmp_path );
        if ( ! $copied ) {
            self::delete_temp_file( $tmp_path );
            return null;
        }

        $this->register_temp_file_cleanup( $tmp_path );

        return $tmp_path;
    }

    protected function output_pdf_contents( string $contents, string $filename, bool $attachment ): void {
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        $download_name = sanitize_file_name( $filename );
        if ( '' === $download_name ) {
            $download_name = 'document.pdf';
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: ' . ( $attachment ? 'attachment' : 'inline' ) . '; filename="' . $download_name . '"' );
        header( 'Content-Length: ' . strlen( $contents ) );

        echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF output.
    }

    protected function output_pdf_file( string $path, string $filename, bool $attachment ): void {
        if ( '' === $path || ! file_exists( $path ) ) {
            return;
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        $download_name = sanitize_file_name( $filename );
        if ( '' === $download_name ) {
            $download_name = 'document.pdf';
        }

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: ' . ( $attachment ? 'attachment' : 'inline' ) . '; filename="' . $download_name . '"' );

        $filesize = filesize( $path );
        if ( false !== $filesize ) {
            header( 'Content-Length: ' . (string) $filesize );
        }

        try {
            $stream = new \SplFileObject( $path, 'rb' );
        } catch ( \RuntimeException $exception ) {
            return;
        }

        while ( ! $stream->eof() ) {
            echo $stream->fread( 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF output stream.
        }
    }

    protected function can_generate_document_for_order( \WC_Order $order, string $type ): bool {
        if ( ! in_array( $type, DocumentTypes::all(), true ) ) {
            return false;
        }

        if ( DocumentTypes::CREDIT_NOTE === $type ) {
            $refunds      = $order->get_refunds();
            $refund_count = is_array( $refunds ) ? count( $refunds ) : 0;

            if ( $refund_count < 1 ) {
                return false;
            }
        }

        return (bool) apply_filters(
            'kitgenix_pdf_document_enabled',
            true,
            $order,
            $type
        );
    }

    public function is_document_available_for_order( \WC_Order $order, string $type ): bool {
        return $this->can_generate_document_for_order( $order, $type );
    }

    protected function get_document_filename( \WC_Order $order, string $type ): string {
        $default_filename = sprintf(
            '%s-%s.pdf',
            $type,
            $order->get_order_number()
        );

        $filename = (string) apply_filters(
            'kitgenix_pdf_document_filename',
            $default_filename,
            $order,
            $type
        );

        if ( DocumentTypes::INVOICE === $type ) {
            $filename = (string) apply_filters(
                'kitgenix_pdf_invoice_filename',
                $filename,
                $order
            );
        }

        $filename = sanitize_file_name( $filename );

        return '' !== $filename ? $filename : sanitize_file_name( $default_filename );
    }

    protected function create_temp_file_path( string $filename, string $prefix ): ?string {
        $safe_filename = sanitize_file_name( $filename );
        if ( '' === $safe_filename ) {
            $safe_filename = sanitize_file_name( $prefix . wp_generate_password( 8, false, false ) );
        }

        $tmp_path = function_exists( 'wp_tempnam' ) ? wp_tempnam( $safe_filename ) : false;
        if ( $tmp_path ) {
            return $tmp_path;
        }

        $safe_prefix = preg_replace( '/[^A-Za-z0-9_]/', '_', $prefix );
        $safe_prefix = is_string( $safe_prefix ) ? substr( $safe_prefix, 0, 20 ) : 'kitgenix_pdf_';
        if ( '' === $safe_prefix ) {
            $safe_prefix = 'kitgenix_pdf_';
        }

        $tmp_path = tempnam( sys_get_temp_dir(), $safe_prefix );
        if ( ! $tmp_path ) {
            return null;
        }

        $extension = pathinfo( $safe_filename, PATHINFO_EXTENSION );
        if ( '' === $extension ) {
            return $tmp_path;
        }

        $target_path = $tmp_path . '.' . $extension;

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if ( function_exists( 'WP_Filesystem' ) ) {
            WP_Filesystem();
        }

        global $wp_filesystem;
        if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'move' ) ) {
            $moved = $wp_filesystem->move( $tmp_path, $target_path, true );
            if ( $moved ) {
                return $target_path;
            }
        }

        /* phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback when WP_Filesystem is not available or move fails. */
        @rename( $tmp_path, $target_path );

        return file_exists( $target_path ) ? $target_path : $tmp_path;
    }

    protected static function delete_temp_file( string $path ): void {
        if ( '' === $path || ! file_exists( $path ) ) {
            return;
        }

        if ( function_exists( 'wp_delete_file' ) ) {
            @wp_delete_file( $path );
            return;
        }

        /* phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Fallback when wp_delete_file() is unavailable. */
        @unlink( $path );
    }

    protected function register_temp_file_cleanup( string $path ): void {
        register_shutdown_function(
            static function() use ( $path ) {
                self::delete_temp_file( $path );
            }
        );
    }

    protected function get_unique_archive_entry_name( string $filename, array &$used_names ): string {
        $filename = sanitize_file_name( $filename );
        if ( '' === $filename ) {
            $filename = 'document.pdf';
        }

        $candidate = $filename;
        $key       = strtolower( $candidate );
        if ( ! isset( $used_names[ $key ] ) ) {
            $used_names[ $key ] = true;
            return $candidate;
        }

        $extension = pathinfo( $filename, PATHINFO_EXTENSION );
        $basename  = pathinfo( $filename, PATHINFO_FILENAME );
        $counter   = 2;

        do {
            $candidate = $basename . '-' . $counter;
            if ( '' !== $extension ) {
                $candidate .= '.' . $extension;
            }

            $key = strtolower( $candidate );
            $counter++;
        } while ( isset( $used_names[ $key ] ) );

        $used_names[ $key ] = true;

        return $candidate;
    }

    protected function get_batch_archive_filename( string $type, int $document_count ): string {
        $default_filename = sprintf(
            '%s-batch-%s.zip',
            str_replace( '_', '-', sanitize_key( $type ) ),
            gmdate( 'Ymd-His' )
        );

        $filename = (string) apply_filters(
            'kitgenix_pdf_batch_archive_filename',
            $default_filename,
            $type,
            $document_count
        );

        $filename = sanitize_file_name( $filename );
        if ( '' === $filename ) {
            $filename = $default_filename;
        }

        if ( ! str_ends_with( strtolower( $filename ), '.zip' ) ) {
            $filename .= '.zip';
        }

        return $filename;
    }

    protected function create_batch_archive_file( string $archive_path, array $files ): bool {
        if ( class_exists( \ZipArchive::class ) ) {
            $archive = new \ZipArchive();
            $result  = $archive->open( $archive_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
            if ( true !== $result ) {
                return false;
            }

            foreach ( $files as $file ) {
                $path = isset( $file['path'] ) ? (string) $file['path'] : '';
                $name = isset( $file['name'] ) ? (string) $file['name'] : '';

                if ( '' === $path || '' === $name || ! file_exists( $path ) ) {
                    $archive->close();
                    return false;
                }

                if ( ! $archive->addFile( $path, $name ) ) {
                    $archive->close();
                    return false;
                }
            }

            return $archive->close();
        }

        if ( ! class_exists( 'PclZip' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        }

        if ( ! class_exists( 'PclZip' ) ) {
            return false;
        }

        $archive_files = [];
        foreach ( $files as $file ) {
            $path = isset( $file['path'] ) ? (string) $file['path'] : '';
            $name = isset( $file['name'] ) ? (string) $file['name'] : '';

            if ( '' === $path || '' === $name || ! file_exists( $path ) ) {
                return false;
            }

            $archive_files[] = [
                PCLZIP_ATT_FILE_NAME      => $path,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => $name,
            ];
        }

        $archive = new \PclZip( $archive_path );
        $result  = $archive->create( $archive_files );

        return is_int( $result ) && $result > 0 && file_exists( $archive_path );
    }

    /**
     * Central helper: build Dompdf instance for a document and return filename.
     */
    protected function create_dompdf_for_document( \WC_Order $order, string $type, string &$filename ): Dompdf {
        // Persist official document identifiers before rendering so the first
        // generated file becomes the canonical immutable archive copy.
        $this->ensure_document_identity( $order, $type );
        $settings = Settings::get_all();

        $html = $this->renderer->render_document( $order, $type );

        // If we have a plugin-local stylesheet for the selected template style,
        // inline it into the HTML so Dompdf (which may not fetch external files)
        // receives the CSS.
        $style = isset( $settings['template_style'] ) ? sanitize_key( (string) $settings['template_style'] ) : 'standard';
        $allowed_styles = [ 'standard', 'simple', 'modern', 'business' ];
        if ( ! in_array( $style, $allowed_styles, true ) ) {
            $style = 'standard';
        }

        $css_basename = $style . '-styles.css';

        $css_path = defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH' )
            ? KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH . 'templates/' . $style . '/' . $css_basename
            : null;

        if ( $css_path && file_exists( $css_path ) ) {
            $css = (string) file_get_contents( $css_path );
            // Defensive: ensure stylesheet contents cannot break out of <style>.
            // Do NOT HTML-escape the CSS (e.g. `>` becomes `&gt;` and can break selectors).
            // Instead, strip any HTML tags and neutralize any closing style tags.
            $css = wp_strip_all_tags( $css );
            $css = str_ireplace( '</style', '</st' . 'yle', $css );

            $style_block = '<style type="text/css">' . $css . '</style>';

            // Replace any link to the selected stylesheet with an inline style block.
            $css_pattern = preg_quote( $css_basename, '#' );
            $html = preg_replace(
                '#<link[^>]+' . $css_pattern . '[^>]*>#i',
                $style_block,
                $html
            );

            // If no link tag was found, inject before </head>.
            if ( false === strpos( $html, $style_block ) ) {
                $html = preg_replace( '#</head>#i', $style_block . '</head>', $html, 1 );
            }
            // Inject runtime CSS variables for dynamic colours so templates
            // can reference them. Provide sensible fallbacks matching defaults.
            $primary           = sanitize_hex_color( $settings['primary_color'] ?? '' ) ?: '#111827';
            $accent            = sanitize_hex_color( $settings['accent_color'] ?? '' ) ?: '#2563eb';
            $text              = sanitize_hex_color( $settings['text_color'] ?? '' ) ?: '#111827';
            $muted_text        = sanitize_hex_color( $settings['muted_text_color'] ?? '' ) ?: '#6b7280';
            $border            = sanitize_hex_color( $settings['border_color'] ?? '' ) ?: '#e5e7eb';
            $table_header_bg   = sanitize_hex_color( $settings['table_header_bg'] ?? '' ) ?: '#000000';
            $table_header_text = sanitize_hex_color( $settings['table_header_text_color'] ?? '' ) ?: '#ffffff';
            $background        = sanitize_hex_color( $settings['background_color'] ?? '' ) ?: '#ffffff';
            $footer_bg         = sanitize_hex_color( $settings['footer_bg_color'] ?? '' ) ?: $background;
            $footer_text       = sanitize_hex_color( $settings['footer_text_color'] ?? '' ) ?: $text;

            // Create :root variables (for engines that support them) and a
            // DOMPDF-friendly fallback with concrete rules so colours apply
            // even if custom properties aren't supported.
            $vars_css = '<style type="text/css">:root{' .
                '--kitgenix-pdf-invoicing-for-woocommerce-primary:' . esc_html( $primary ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-accent:' . esc_html( $accent ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-text:' . esc_html( $text ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-muted-text:' . esc_html( $muted_text ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-border:' . esc_html( $border ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-table-header-bg:' . esc_html( $table_header_bg ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-table-header-text:' . esc_html( $table_header_text ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-background:' . esc_html( $background ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-footer-bg:' . esc_html( $footer_bg ) . ';' .
                '--kitgenix-pdf-invoicing-for-woocommerce-footer-text:' . esc_html( $footer_text ) . ';' .
            '}</style>';

            $fallback_css = '<style type="text/css">' .
                'body{background:' . esc_html( $background ) . ';color:' . esc_html( $text ) . ';}' .
                '.order-details thead th{background:' . esc_html( $table_header_bg ) . ';color:' . esc_html( $table_header_text ) . ';border:1px solid ' . esc_html( $border ) . ';}' .
                '.order-details td{border-left:1px solid ' . esc_html( $border ) . ';border-right:1px solid ' . esc_html( $border ) . ';border-bottom:1px solid ' . esc_html( $border ) . ';}' .
                '.order-details tbody{border-bottom:1px solid ' . esc_html( $border ) . ';}' .
                '.item-meta{color:' . esc_html( $muted_text ) . ';}' .
                'table.totals th,table.totals td{border-top:1px solid ' . esc_html( $border ) . ';border-bottom:1px solid ' . esc_html( $border ) . ';}' .
                'table.totals tr.order_total th,table.totals tr.order_total td{border-top:2px solid ' . esc_html( $primary ) . ';border-bottom:2px solid ' . esc_html( $primary ) . ';}' .
                '#footer{background-color:' . esc_html( $footer_bg ) . ';}' .
                '#footer .footer-cell{color:' . esc_html( $footer_text ) . ';}' .
            '</style>';

            $inject = $vars_css . $fallback_css;

            if ( false === strpos( $html, $inject ) ) {
                $html = preg_replace( '#</head>#i', $inject . '</head>', $html, 1 );
            }
        }

        // Convert any uploads URLs (e.g., attachment image URLs) to local
        // filesystem paths so Dompdf can load images with remote fetching
        // disabled. This maps upload baseurl -> file://{basedir}.
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['baseurl'] ) && ! empty( $upload_dir['basedir'] ) ) {
            $baseurl = rtrim( $upload_dir['baseurl'], '/' );
            $basedir = rtrim( $upload_dir['basedir'], '/' );

            // Replace occurrences of the upload baseurl with a file:// path.
            // This will convert e.g. https://site/wp-content/uploads/2025/01/logo.png
            // to file:///var/www/wp-content/uploads/2025/01/logo.png which Dompdf
            // can read when chroot includes the uploads basedir.
            $html = str_replace( $baseurl, 'file://' . $basedir, $html );
        }

        $options = new Options();

        // Lock DOMPDF to safe filesystem roots (plugin + uploads)
        $upload_dir = wp_upload_dir();
        $roots      = [];

        if ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH' ) && KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH ) {
            $roots[] = KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_PATH;
        }

        if ( ! empty( $upload_dir['basedir'] ) && is_string( $upload_dir['basedir'] ) ) {
            $roots[] = $upload_dir['basedir'];
        }

        if ( ! empty( $roots ) ) {
            $options->setChroot( $roots );
        }

        // Remote fetch OFF by default (prevents SSRF)
        $options->setIsRemoteEnabled( false );

        // Enable HTML5 parser — improves CSS handling (including counters).
        if ( method_exists( $options, 'setIsHtml5ParserEnabled' ) ) {
            $options->setIsHtml5ParserEnabled( true );
        }
        // Enable PHP processing in Dompdf only when explicitly allowed.
        // Executing PHP inside Dompdf's HTML is dangerous (it uses eval())
        // and can be flagged during plugin review. Default to disabled to
        // follow the principle of least privilege; site owners can opt-in
        // via the `kitgenix_dompdf_enable_php` filter if they understand the
        // risks and require the feature.
        if ( method_exists( $options, 'setIsPhpEnabled' ) ) {
            $options->setIsPhpEnabled( (bool) apply_filters( 'kitgenix_dompdf_enable_php', false ) );
        }

        // If you *must* allow remote, use both:
        // $options->setIsRemoteEnabled( true );
        // $options->setAllowedRemoteHosts( [ wp_parse_url( home_url(), PHP_URL_HOST ) ] );

        $dompdf = new Dompdf( $options );

        // (Page-numbering placeholders and canvas overlays removed.)

        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        // Page numbering removed — document content is left unmodified.

        

        $filename = $this->get_document_filename( $order, $type );

        return $dompdf;
    }

    /**
     * Stream any document type to the browser.
     */
    public function stream_document( int $order_id, string $type ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'Order not found.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        if ( ! class_exists( Dompdf::class ) ) {
            wp_die(
                esc_html__(
                    'Dompdf library is not available. Please run composer install.',
                    'kitgenix-pdf-invoicing-for-woocommerce'
                )
            );
        }

        if ( ! $this->can_generate_document_for_order( $order, $type ) ) {
            wp_die(
                esc_html__(
                    'PDF generation is disabled for this document type.',
                    'kitgenix-pdf-invoicing-for-woocommerce'
                )
            );
        }

        $filename = $this->get_document_filename( $order, $type );

        /**
         * Before stream hook.
         */
        do_action(
            'kitgenix_before_stream_pdf_document',
            $order,
            $type,
            $filename
        );

        if ( DocumentTypes::INVOICE === $type ) {
            do_action(
                'kitgenix_before_stream_pdf_invoice',
                $order,
                $filename
            );
        }

        // Allow filters to control whether the PDF is forced as a download
        // (Attachment = true) or displayed inline in the browser (Attachment = false).
        // Default to false so clicking "Download PDF" opens it in-browser first.
        $attachment = (bool) apply_filters(
            'kitgenix_pdf_document_attachment',
            false,
            $order,
            $type
        );

        $archived_entry = $this->get_current_archived_document_entry( $order, $type );

        if ( is_array( $archived_entry ) && ! empty( $archived_entry['download_filename'] ) ) {
            $filename = (string) $archived_entry['download_filename'];
        }

        if ( is_array( $archived_entry ) && ! empty( $archived_entry['absolute_path'] ) ) {
            $this->output_pdf_file( (string) $archived_entry['absolute_path'], $filename, $attachment );
        } else {
            $output = $this->generate_document_output( $order, $type, $filename );
            if ( null === $output ) {
                wp_die( esc_html__( 'Failed to generate the PDF document.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
            }

            $archived_entry = $this->persist_document_archive( $order, $type, $filename, $output );

            if ( is_array( $archived_entry ) && ! empty( $archived_entry['download_filename'] ) ) {
                $filename = (string) $archived_entry['download_filename'];
            }

            if ( is_array( $archived_entry ) && ! empty( $archived_entry['absolute_path'] ) ) {
                $this->output_pdf_file( (string) $archived_entry['absolute_path'], $filename, $attachment );
            } else {
                $this->output_pdf_contents( $output, $filename, $attachment );
            }
        }

        // Count successful stream attempts as “generated”.
        self::record_generated_metric( $type, 1 );

        /**
         * After stream hook.
         */
        do_action(
            'kitgenix_after_stream_pdf_document',
            $order,
            $type,
            $filename
        );

        if ( DocumentTypes::INVOICE === $type ) {
            do_action(
                'kitgenix_after_stream_pdf_invoice',
                $order,
                $filename
            );
        }

        exit;
    }

    /**
     * Backwards-compatible invoice convenience method.
     */
    public function stream_invoice( int $order_id ): void {
        $this->stream_document( $order_id, DocumentTypes::INVOICE );
    }

    /**
     * Generate a PDF for any document type and save to a file.
     *
     * Returns absolute file path or null on failure.
     */
    public function generate_document_to_file( int $order_id, string $type ): ?string {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return null;
        }

        if ( ! class_exists( Dompdf::class ) ) {
            return null;
        }

        if ( ! $this->can_generate_document_for_order( $order, $type ) ) {
            return null;
        }

        $filename = $this->get_document_filename( $order, $type );
        $archived_entry = $this->get_current_archived_document_entry( $order, $type );

        if ( is_array( $archived_entry ) && ! empty( $archived_entry['download_filename'] ) ) {
            $filename = (string) $archived_entry['download_filename'];
        }

        if ( is_array( $archived_entry ) && ! empty( $archived_entry['absolute_path'] ) ) {
            $tmp_path = $this->copy_file_to_temp_path( (string) $archived_entry['absolute_path'], $filename );
            if ( $tmp_path ) {
                self::record_generated_metric( $type, 1 );
            }

            return $tmp_path;
        }

        $output = $this->generate_document_output( $order, $type, $filename );

        if ( null === $output ) {
            return null;
        }

        $archived_entry = $this->persist_document_archive( $order, $type, $filename, $output );

        if ( is_array( $archived_entry ) && ! empty( $archived_entry['download_filename'] ) ) {
            $filename = (string) $archived_entry['download_filename'];
        }

        if ( is_array( $archived_entry ) && ! empty( $archived_entry['absolute_path'] ) ) {
            $tmp_path = $this->copy_file_to_temp_path( (string) $archived_entry['absolute_path'], $filename );
            if ( $tmp_path ) {
                self::record_generated_metric( $type, 1 );
            }

            return $tmp_path;
        }

        $tmp_path = $this->create_temp_file_path( $filename, 'kitgenix_pdf_document_' );

        if ( ! $tmp_path ) {
            return null;
        }

        /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- Writing to a secure temp file path created by wp_tempnam()/tempnam(). */
        $bytes = file_put_contents( $tmp_path, $output );
        if ( false === $bytes ) {
            self::delete_temp_file( $tmp_path );
            return null;
        }

        // Count successful file generations as “generated”.
        self::record_generated_metric( $type, 1 );

        $this->register_temp_file_cleanup( $tmp_path );

        return $tmp_path;
    }

    /**
     * Generate a ZIP archive for a set of orders and one document type.
     *
     * Returns metadata about the generated archive, or null if the archive
     * could not be created at all.
     *
     * @return array<string,mixed>|null
     */
    public function generate_document_batch_archive( array $order_ids, string $type ): ?array {
        if ( ! class_exists( Dompdf::class ) ) {
            return null;
        }

        if ( ! in_array( $type, DocumentTypes::all(), true ) ) {
            return null;
        }

        $normalized_order_ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'absint', $order_ids )
                )
            )
        );

        if ( empty( $normalized_order_ids ) ) {
            return [
                'path'              => '',
                'filename'          => '',
                'document_count'    => 0,
                'included_order_ids'=> [],
                'skipped_order_ids' => [],
            ];
        }

        $archive_files       = [];
        $included_order_ids  = [];
        $skipped_order_ids   = [];
        $used_archive_names  = [];

        foreach ( $normalized_order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order instanceof \WC_Order ) {
                $skipped_order_ids[] = $order_id;
                continue;
            }

            if ( ! $this->can_generate_document_for_order( $order, $type ) ) {
                $skipped_order_ids[] = $order_id;
                continue;
            }

            $document_path = $this->generate_document_to_file( $order_id, $type );
            if ( ! $document_path ) {
                $skipped_order_ids[] = $order_id;
                continue;
            }

            $archive_files[] = [
                'path' => $document_path,
                'name' => $this->get_unique_archive_entry_name( $this->get_document_filename( $order, $type ), $used_archive_names ),
            ];
            $included_order_ids[] = $order_id;
        }

        if ( empty( $archive_files ) ) {
            return [
                'path'               => '',
                'filename'           => '',
                'document_count'     => 0,
                'included_order_ids' => [],
                'skipped_order_ids'  => $skipped_order_ids,
            ];
        }

        $archive_filename = $this->get_batch_archive_filename( $type, count( $archive_files ) );
        $archive_path     = $this->create_temp_file_path( $archive_filename, 'kitgenix_pdf_batch_' );

        if ( ! $archive_path ) {
            return null;
        }

        if ( ! $this->create_batch_archive_file( $archive_path, $archive_files ) ) {
            self::delete_temp_file( $archive_path );
            return null;
        }

        $this->register_temp_file_cleanup( $archive_path );

        return [
            'path'               => $archive_path,
            'filename'           => $archive_filename,
            'document_count'     => count( $archive_files ),
            'included_order_ids' => $included_order_ids,
            'skipped_order_ids'  => $skipped_order_ids,
        ];
    }
}
