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