<?php

if ( ! defined( 'BP_TESTS_DIR' ) ) {
	define( 'BP_TESTS_DIR', dirname( __FILE__ ) . '/../../buddypress/tests' );
}

if ( file_exists( BP_TESTS_DIR . '/bootstrap.php' ) ) {
	$_tests_dir = getenv('WP_TESTS_DIR');
	if ( ! $_tests_dir ) {
		$_tests_dir = '/tmp/wordpress-tests-lib';
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
