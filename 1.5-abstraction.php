<?php

/**
 * Abstraction function for BP < 1.5
 *
 * These 1.5-specific functions are loaded when the current version of BP is less than 1.5. This
 * file enables us to use BP 1.5 functions on earlier versions.
 */

if ( !function_exists( 'bp_is_group' ) ) :
	function bp_is_group() {
		global $bp;
		
		return !empty( $bp->groups->current_group->id );
	}
endif;

if ( !function_exists( 'bp_is_groups_component' ) ) :
	function bp_is_groups_component() {
		global $bp;
		
		$slug = isset( $bp->groups->root_slug ) ? $bp->groups->root_slug : $bp->groups->slug;
		
		$is_groups_component = isset( $bp->current_component ) && $slug == $bp->current_component;
		
		return $is_groups_component;
	}
endif;

if ( !function_exists( 'bp_is_action_variable' ) ) :
	function bp_is_action_variable( $value, $position = false ) {
		global $bp;

		if ( false === $position ) {
			$is_action_variable = !empty( $bp->action_variables ) && in_array( $value, $bp->action_variables );
		} else {
			$is_action_variable = !empty( $bp->action_variables ) && isset( $bp->action_variables[$position] ) && $value == $bp->action_variables[$position];
		}

		return apply_filters( 'bp_is_action_variable', $is_action_variable );
	}
endif;

if ( !function_exists( 'bp_is_current_action' ) ) :
	function bp_is_current_action( $action ) {
		global $bp;

		return apply_filters( 'bp_is_current_action', $action == $bp->current_action );
	}
endif;

if ( !function_exists( 'bp_action_variable' ) ) :
	function bp_action_variable( $position = 0 ) {
		global $bp;
		
		$action_variables = isset( $bp->action_variables ) ? $bp->action_variables : array();
		$action_variable  = isset( $action_variables[$position] ) ? $action_variables[$position] : false;
	
		return apply_filters( 'bp_action_variable', $action_variable, $position );
	}
endif;

if ( !function_exists( 'bp_actions' ) ) :
	function bp_actions() {
		do_action( 'bp_actions' );
	}
	add_action( 'wp', 'bp_actions', 2 );
endif;


if ( !function_exists( 'bp_screens' ) ) :
	function bp_screens() {
		do_action( 'bp_screens' );
	}
	add_action( 'wp', 'bp_screens', 3 );
endif;