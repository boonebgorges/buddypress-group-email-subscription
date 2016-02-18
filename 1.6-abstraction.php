<?php

/**
 * Abstraction functions for BP 1.5 installs.
 *
 * The following functions are used in BuddyPress 1.6, which are not available
 * in v1.5.
 */

if ( !function_exists( 'bp_get_groups_current_create_step' ) ) :
	function bp_get_groups_current_create_step() {
		global $bp;

		if ( !empty( $bp->groups->current_create_step ) ) {
			$current_create_step = $bp->groups->current_create_step;
		} else {
			$current_create_step = '';
		}

		return apply_filters( 'bp_get_groups_current_create_step', $current_create_step );
	}
endif;

if ( ! function_exists( 'bp_core_enable_root_profiles' ) ) :
function bp_core_enable_root_profiles() {
	$retval = false;

	if ( defined( 'BP_ENABLE_ROOT_PROFILES' ) && ( true == BP_ENABLE_ROOT_PROFILES ) )
		$retval = true;

	return apply_filters( 'bp_core_enable_root_profiles', $retval );
}
endif;