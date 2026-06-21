<?php
/**
 * HTML document wrapper (modern template).
 *
 * Variables expected:
 * @var string   $content
 * @var string   $document_type
 * @var WC_Order $order
 * @var array    $settings
 */


defined( 'ABSPATH' ) || exit;

/*
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 *
 * Variables in this wrapper are local to the template and intentionally
 * unprefixed for readability.
 */

$lang = get_bloginfo( 'language' );
$lang = apply_filters( 'kitgenix_pdf_document_lang', $lang, $document_type, $order, $settings );

$pretty = str_replace( array( '-', '_' ), ' ', (string) $document_type );
$default_title = ucwords( $pretty );

$title = apply_filters(
	'kitgenix_pdf_document_title',
	$default_title,
	$document_type,
	$order,
	$settings
);

$body_class = apply_filters(
	'kitgenix_pdf_document_body_class',
	'kitgenix-pdf-document kitgenix-pdf-template-modern ' . sanitize_html_class( (string) $document_type ),
	$document_type,
	$order,
	$settings
);

$stylesheet_url = defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL' )
	? KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL . 'templates/modern/modern-styles.css'
	: plugin_dir_url( __FILE__ ) . 'modern-styles.css';

do_action( 'kitgenix_pdf_before_document_wrapper', $document_type, $order, $settings );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php echo esc_html( $title ); ?></title>

	<?php /* phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- PDF/document HTML is rendered standalone for Dompdf and cannot rely on WP's enqueue system. */ ?>
	<link rel="stylesheet" href="<?php echo esc_url( $stylesheet_url ); ?>">

	<?php
	// Allow small injected CSS overrides (e.g., “ink saving” mode, custom branding).
	$custom_css = (string) apply_filters( 'kitgenix_pdf_document_custom_css', '', $document_type, $order, $settings );
	if ( $custom_css !== '' ) :
		// Strip any HTML tags to prevent breaking out of the <style> context.
		$custom_css = wp_kses( $custom_css, [] );
		?>
		<style type="text/css"><?php echo esc_html( $custom_css ); ?></style>
	<?php endif; ?>
</head>
<body class="<?php echo esc_attr( $body_class ); ?>">
	<?php
	// Sanitize document body HTML to prevent unexpected tag injection.
	echo wp_kses_post( $content );
	?>
</body>
</html>
<?php do_action( 'kitgenix_pdf_after_document_wrapper', $document_type, $order, $settings ); ?>

<?php /* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals */ ?>
