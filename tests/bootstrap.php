<?php

if ( ! defined( 'BP_TESTS_DIR' ) ) {
	define( 'BP_TESTS_DIR', dirname( __FILE__ ) . '/../../buddypress/tests/phpunit' );
}

if ( file_exists( BP_TESTS_DIR . '/bootstrap.php' ) ) {
	$develop_dir = getenv( 'WP_DEVELOP_DIR' );
	if ( ! $develop_dir ) {
		echo "Cannot find develop.wordpress checkout.\n";
		die();
	}

	$_tests_dir = $develop_dir . '/tests/phpunit';
	if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
		echo "Cannot find develop.wordpress tests.\n";
		die();
	}

	require_once $_tests_dir . '/includes/functions.php';

	function _bootstrap_plugins() {
		require BP_TESTS_DIR . '/includes/loader.php';
		require dirname( __FILE__ ) . '/../bp-activity-subscription.php';
	}
	tests_add_filter( 'muplugins_loaded', '_bootstrap_plugins' );

	require $_tests_dir . '/includes/bootstrap.php';

	require BP_TESTS_DIR . '/includes/testcase.php';
}
