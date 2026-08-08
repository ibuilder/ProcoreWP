<?php
/**
 * Minimal WordPress function shims for unit testing.
 *
 * @package ProcoreConnect
 */

declare( strict_types = 1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamTag, Squiz.Commenting.FunctionComment.MissingReturn, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test scaffolding that deliberately mirrors Core's global API.

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'OPENSSL_RAW_DATA_SHIMMED', true );

/**
 * In-memory stand-ins for the option and transient tables.
 *
 * @var array<string, mixed> $procore_connect_test_options
 * @var array<string, array{value: mixed, expires: int}> $procore_connect_test_transients
 * @var array<string, array<int, callable>> $procore_connect_test_filters
 */
$GLOBALS['procore_connect_test_options']    = array();
$GLOBALS['procore_connect_test_transients'] = array();
$GLOBALS['procore_connect_test_filters']    = array();

/**
 * Reset every shimmed store between tests.
 */
function procore_connect_test_reset(): void {
	$GLOBALS['procore_connect_test_options']    = array();
	$GLOBALS['procore_connect_test_transients'] = array();
	$GLOBALS['procore_connect_test_filters']    = array();
}

/* -- Errors ------------------------------------------------------------- */

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		/** @var string */
		private $code;

		/** @var string */
		private $message;

		/** @var mixed */
		private $data;

		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/* -- Options and transients --------------------------------------------- */

function get_option( string $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['procore_connect_test_options'] )
		? $GLOBALS['procore_connect_test_options'][ $name ]
		: $default_value;
}

function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['procore_connect_test_options'][ $name ] = $value;

	return true;
}

function add_option( string $name, $value, $deprecated = '', $autoload = null ): bool {
	if ( array_key_exists( $name, $GLOBALS['procore_connect_test_options'] ) ) {
		return false;
	}

	return update_option( $name, $value );
}

function delete_option( string $name ): bool {
	unset( $GLOBALS['procore_connect_test_options'][ $name ] );

	return true;
}

function get_transient( string $name ) {
	if ( ! isset( $GLOBALS['procore_connect_test_transients'][ $name ] ) ) {
		return false;
	}

	$entry = $GLOBALS['procore_connect_test_transients'][ $name ];

	if ( $entry['expires'] > 0 && $entry['expires'] < time() ) {
		unset( $GLOBALS['procore_connect_test_transients'][ $name ] );

		return false;
	}

	return $entry['value'];
}

function set_transient( string $name, $value, int $ttl = 0 ): bool {
	$GLOBALS['procore_connect_test_transients'][ $name ] = array(
		'value'   => $value,
		'expires' => $ttl > 0 ? time() + $ttl : 0,
	);

	return true;
}

function delete_transient( string $name ): bool {
	unset( $GLOBALS['procore_connect_test_transients'][ $name ] );

	return true;
}

function wp_cache_add( string $key, $value, string $group = '', int $ttl = 0 ): bool {
	return false;
}

function wp_cache_delete( string $key, string $group = '' ): bool {
	return true;
}

function wp_using_ext_object_cache(): bool {
	return false;
}

/* -- Hooks --------------------------------------------------------------- */

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted = 1 ): bool {
	$GLOBALS['procore_connect_test_filters'][ $hook ][] = $callback;

	return true;
}

function apply_filters( string $hook, $value, ...$args ) {
	foreach ( $GLOBALS['procore_connect_test_filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}

	return $value;
}

function do_action( string $hook, ...$args ): void {
	foreach ( $GLOBALS['procore_connect_test_filters'][ $hook ] ?? array() as $callback ) {
		$callback( ...$args );
	}
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted = 1 ): bool {
	return add_filter( $hook, $callback, $priority, $accepted );
}

/* -- Sanitizing and escaping --------------------------------------------- */

function absint( $value ): int {
	return abs( (int) $value );
}

function sanitize_text_field( string $value ): string {
	return trim( wp_strip_all_tags( $value ) );
}

function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
}

function sanitize_html_class( string $value, string $fallback = '' ): string {
	$class = preg_replace( '/[^A-Za-z0-9_\-]/', '', $value ) ?? '';

	return '' === $class ? $fallback : $class;
}

function wp_strip_all_tags( string $value, bool $remove_breaks = false ): string {
	$value = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $value ) ?? '';

	return trim( strip_tags( $value ) );
}

function esc_html( $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_textarea( $value ): string {
	return esc_html( $value );
}

function esc_url( string $value ): string {
	return esc_attr( esc_url_raw( $value ) );
}

function esc_url_raw( string $value, array $protocols = array( 'http', 'https' ) ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	$scheme = strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) );

	if ( '' === $scheme || ! in_array( $scheme, $protocols, true ) ) {
		return '';
	}

	return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
}

function wp_kses_post( string $value ): string {
	return $value;
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function is_email( $value ): bool {
	return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

function antispambot( string $email ): string {
	return $email;
}

/* -- Formatting and i18n -------------------------------------------------- */

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}

function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
	return 1 === $number ? $single : $plural;
}

function number_format_i18n( float $number, int $decimals = 0 ): string {
	return number_format( $number, $decimals );
}

function wp_date( string $format, ?int $timestamp = null ): string {
	return gmdate( $format, $timestamp ?? time() );
}

function human_time_diff( int $from, int $to ): string {
	return (string) max( 0, $to - $from ) . ' seconds';
}

/* -- Arrays and misc ------------------------------------------------------ */

function wp_parse_args( $args, array $defaults = array() ): array {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	}

	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function wp_json_encode( $value, int $flags = 0, int $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function shortcode_atts( array $pairs, $atts, string $shortcode = '' ): array {
	$atts = (array) $atts;
	$out  = array();

	foreach ( $pairs as $name => $default_value ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default_value;
	}

	return $out;
}

function add_shortcode( string $tag, callable $callback ): void {
	$GLOBALS['procore_connect_test_shortcodes'][ $tag ] = $callback;
}

function do_shortcode( string $content ): string {
	return $content;
}

function has_shortcode( string $content, string $tag ): bool {
	return false !== strpos( $content, '[' . $tag );
}

function trailingslashit( string $value ): string {
	return rtrim( $value, '/\\' ) . '/';
}

function wp_rand( int $min = 0, int $max = 0 ): int {
	return $min;
}

function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ): string {
	return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 4 ), 0, $length );
}

function wp_salt( string $scheme = 'auth' ): string {
	return 'procore-connect-test-salt-' . $scheme;
}

function get_bloginfo( string $show = '' ): string {
	return '6.9';
}

function home_url( string $path = '' ): string {
	return 'https://example.test' . $path;
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function get_stylesheet_directory(): string {
	return sys_get_temp_dir() . '/procore-connect-child-theme';
}

function get_template_directory(): string {
	return sys_get_temp_dir() . '/procore-connect-parent-theme';
}

function get_current_user_id(): int {
	return (int) ( $GLOBALS['procore_connect_test_user'] ?? 1 );
}

function current_user_can( string $capability ): bool {
	return (bool) ( $GLOBALS['procore_connect_test_can'] ?? false );
}

function add_query_arg( array $args, string $url ): string {
	$separator = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $separator . http_build_query( $args );
}

/* -- HTTP response accessors ---------------------------------------------- */

function wp_remote_retrieve_response_code( $response ): int {
	return (int) ( $response['response']['code'] ?? 0 );
}

function wp_remote_retrieve_body( $response ): string {
	return (string) ( $response['body'] ?? '' );
}

function wp_remote_retrieve_header( $response, string $header ) {
	$headers = array_change_key_case( (array) ( $response['headers'] ?? array() ), CASE_LOWER );

	return $headers[ strtolower( $header ) ] ?? '';
}

function wp_remote_post( string $url, array $args = array() ) {
	return apply_filters( 'procore_connect_test_http', new WP_Error( 'no_transport', 'No transport configured.' ), $url, $args );
}

function wp_remote_request( string $url, array $args = array() ) {
	return apply_filters( 'procore_connect_test_http', new WP_Error( 'no_transport', 'No transport configured.' ), $url, $args );
}

// phpcs:enable
