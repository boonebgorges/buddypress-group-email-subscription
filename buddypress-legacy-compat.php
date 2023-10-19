<?php

/**
 * Polyfills for URL-related functions introduced in BuddyPress 12.0.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the URL of a group.
 *
 * @param int|BP_Groups_Group $group       The group ID or the group object.
 * @param array               $path_chunks {
 *     An array of path chunks to append to the URL. Optional.
 *
 *     @type string $single_item_action           The slug of the action (the first chunk of
 *                                                the URL following the group URL).
 *     @type array  $single_item_action_variables An array of additional URL chunks.
 * }
 * @return string The URL of the group.
 */
function bp_get_group_url( $group = 0, $path_chunks = array() ) {
	$group_url = bp_get_group_permalink( $group );

	if ( ! empty( $path_chunks['single_item_action'] ) ) {
		$group_url = trailingslashit( $group_url . $path_chunks['single_item_action'] );

		if ( ! empty( $path_chunks['single_item_action_variables'] ) ) {
			$group_url .= implode( '/', $path_chunks['single_item_action_variables'] );
		}
	}

	return trailingslashit( $group_url );
}

/**
 * Gets the URL of the manage screen for a group.
 *
 * @param int|BP_Groups_Group $group       The group ID or the group object.
 * @param array               $path_chunks {
 *     An array of path chunks to append to the URL. Optional.
 *
 *     @type array $single_item_action_variables An array of additional URL chunks.
 * }
 * @return string The URL of the manage screen for the group.
 */
function bp_get_group_manage_url( $group = 0, $path_chunks = array() ) {
	$full_path_chunks = array_merge( array( 'admin' ), $path_chunks );
	return bp_get_group_url( $group, $full_path_chunks );
}

/**
 * Gets the URL of a user.
 *
 * @param int   $user        The user ID or the user object.
 * @param array $path_chunks {
 *    An array of path chunks to append to the URL. Optional.
 *
 *    @type string $single_item_component		 The slug of the component (the first chunk
 *                                               of the URL after the user URL).
 *    @type string $single_item_action           The slug of the action (the second chunk of
 *                                               the URL after the user URL).
 *    @type array  $single_item_action_variables An array of additional URL chunks.
 * }
 * @return string The URL of the user.
 */
function bp_members_get_user_url( $user_id = 0, $path_chunks = array() ) {
	$user_url = bp_core_get_user_domain( $user_id );

	if ( ! empty( $path_chunks['single_item_component'] ) ) {
		$user_url = trailingslashit( $user_url . $path_chunks['single_item_component'] );

		if ( ! empty( $path_chunks['single_item_action'] ) ) {
			$user_url = trailingslashit( $user_url . $path_chunks['single_item_action'] );

			if ( ! empty( $path_chunks['single_item_action_variables'] ) ) {
				$user_url .= implode( '/', $path_chunks['single_item_action_variables'] );
			}
		}
	}

	return trailingslashit( $user_url );
}

/**
 * Gets the URL of the logged-in user.
 *
 * @param int   $user_id     The user ID.
 * @param array $path_chunks Optional array of path chunks. See bp_members_get_user_url().
 * @return string The URL of the logged-in user.
 */
function bp_loggedin_user_url( $path_chunks = array() ) {
	return bp_members_get_user_url( bp_loggedin_user_id(), $path_chunks );
}

/**
 * Builds an array of path chunks in the format expected by bp_members_get_user_url().
 *
 * The BP 12.0 version checks rewrite slugs in this function. This polyfill simply
 * trusts the values passed to it.
 *
 * @param array $chunks Array of URL path chunks.
 * @return array Array of path chunks in the format expected by bp_members_get_user_url().
 */
function bp_members_get_path_chunks( $chunks ) {
	$path_chunks = array();

	$single_item_component = array_shift( $chunks );
	if ( $single_item_component ) {
		$path_chunks['single_item_component'] = $single_item_component;
	}

	$single_item_action = array_shift( $chunks );
	if ( $single_item_action ) {
		$path_chunks['single_item_action'] = $single_item_action;
	}

	// If action variables were added as an array, reset chunks to it.
	if ( isset( $chunks[0] ) && is_array( $chunks[0] ) ) {
		$chunks = reset( $chunks );
	}

	if ( $chunks ) {
		foreach ( $chunks as $chunk ) {
			$path_chunks['single_item_action_variables'][] = $chunk;
		}
	}

	return $path_chunks;
}

/**
 * Builds an array of path chunks in the format expected by bp_members_get_user_url().
 *
 * The BP 12.0 version checks rewrite slugs in this function. This polyfill simply
 * trusts the values passed to it.
 *
 * @param array $chunks Array of URL path chunks.
 * @return array Array of path chunks in the format expected by bp_members_get_user_url().
 */
function bp_groups_get_path_chunks( $chunks ) {
	$path_chunks = array();

	$single_item_action = array_shift( $chunks );
	if ( $single_item_action ) {
		$path_chunks['single_item_action'] = $single_item_action;
	}

	// If action variables were added as an array, reset chunks to it.
	if ( isset( $chunks[0] ) && is_array( $chunks[0] ) ) {
		$chunks = reset( $chunks );
	}

	if ( $chunks ) {
		foreach ( $chunks as $chunk ) {
			$path_chunks['single_item_action_variables'][] = $chunk;
		}
	}

	return $path_chunks;
}
