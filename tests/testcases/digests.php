﻿<?php

/**
 * @group digests
 */
class BPGES_Tests_Digests extends BP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		$this->reset_phpmailer_instance();

		// Install our email post types.
		require_once( dirname( __FILE__ ) . '/../../admin.php' );
		require_once( dirname( __FILE__ ) . '/../../updater.php' );
		new GES_Updater( true );

		add_filter( 'bp_send_email_delivery_class', array( $this, 'use_mockmailer' ) );
	}

	public function tearDown() {
		$this->reset_phpmailer_instance();
		remove_filter( 'bp_send_email_delivery_class', array( $this, 'use_mockmailer' ) );

		parent::tearDown();
	}

	public function test_stale_item_daily_digest() {
		$time = time();

		$a1 = $this->factory->activity->create( array(
			'recorded_time' => date( 'Y-m-d H:i:s', $time - ( 2 * DAY_IN_SECONDS ) ),
		) );
		$a2 = $this->factory->activity->create( array(
			'recorded_time' => date( 'Y-m-d H:i:s', $time - ( 4 * DAY_IN_SECONDS ) ),
		) );

		$this->assertTrue( bp_ges_activity_is_valid_for_digest( $a1, 'dig', null ) );
		$this->assertFalse( bp_ges_activity_is_valid_for_digest( $a2, 'dig', null ) );
	}

	/**
	 * @group new-digests
	 */
	public function test_new_digests() {
		$u1 = $this->factory->user->create();
		$users = $this->factory->user->create_many( 1 );

		$g1 = $this->factory->group->create( array( 'creator_id' => $u1 ) );
		$g2 = $this->factory->group->create( array( 'creator_id' => $u1 ) );

		// Join users to group and set subscription to daily digest.
		foreach ( $users as $user ) {
			groups_join_group( $g1, $user );
			ass_group_subscription( 'dig', $user, $g1 );
			ass_group_subscription( 'dig', $user, $g2 );
		}

		$name = bp_core_get_user_displayname( $u1 );

		// Create a few activity items to add to a user's digest.
		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g1,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_members_get_user_url( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_members_get_user_url( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		// Send digests.
		ass_daily_digest_fire();

		//$mailer = $GLOBALS['ges_mockmailer'];
		//print_r($mailer);
	}

	/**
	 * @group old-digests
	 */
	public function test_old_digests() {
		$u1 = $this->factory->user->create();
		$users = $this->factory->user->create_many( 1 );

		$g1 = $this->factory->group->create( array( 'creator_id' => $u1 ) );
		$g2 = $this->factory->group->create( array( 'creator_id' => $u1 ) );

		// Join users to group and set subscription to daily digest.
		foreach ( $users as $user ) {
			groups_join_group( $g1, $user );
			ass_group_subscription( 'dig', $user, $g1 );
			ass_group_subscription( 'dig', $user, $g2 );
		}

		$name = bp_core_get_user_displayname( $u1 );

		// Create a few activity items to add to a user's digest.
		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g1,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_members_get_user_url( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_members_get_user_url( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		add_filter( 'bp_email_use_wp_mail', '__return_true' );

		// Send digests.
		ass_daily_digest_fire();

		//print_r($GLOBALS['phpmailer']);

		remove_filter( 'bp_email_use_wp_mail', '__return_true' );
	}

	public function test_performance() {
		$this->markTestSkipped( 'Performance test skipped. Uncomment this to test large digests');

		$u1 = $this->factory->user->create();
		$users = $this->factory->user->create_many( 100 );

		$g1 = $this->factory->group->create( array( 'creator_id' => $u1 ) );
		$g2 = $this->factory->group->create( array( 'creator_id' => $u1 ) );

		// Join users to group and set subscription to daily digest.
		foreach ( $users as $user ) {
			groups_join_group( $g1, $user );
			ass_group_subscription( 'dig', $user, $g1 );
			ass_group_subscription( 'dig', $user, $g2 );
		}

		$name = bp_core_get_user_displayname( $u1 );

		// Create a few activity items to add to a user's digest.
		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g1,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_members_get_user_url( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_members_get_user_url( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		// Send digests.
		ass_daily_digest_fire();

		//$mailer = $GLOBALS['ges_mockmailer'];
	}

	/**
	 * @group issue-129
	 */
	public function test_only_sent_items_should_be_removed_from_digest_queue() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();

		$g1 = $this->factory->group->create( array( 'creator_id' => $u1 ) );
		$g2 = $this->factory->group->create( array( 'creator_id' => $u1 ) );
		$g3 = $this->factory->group->create( array( 'creator_id' => $u1 ) );

		$a1 = $this->factory->activity->create( array(
			'type'      => 'activity_update',
			'item_id'   => $g1,
			'user_id'   => $u3,
			'component' => 'groups',
			'action'    => 'Foo posted an activity update in the group Bar',
		) );

		$a2 = $this->factory->activity->create( array(
			'type'      => 'activity_update',
			'item_id'   => $g1,
			'user_id'   => $u3,
			'component' => 'groups',
			'action'    => 'Foo posted an activity update in the group Bar',
		) );

		$a3 = $this->factory->activity->create( array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u3,
			'component' => 'groups',
			'action'    => 'Foo posted an activity update in the group Bar',
		) );

		$a4 = $this->factory->activity->create( array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u3,
			'component' => 'groups',
			'action'    => 'Foo posted an activity update in the group Bar',
		) );

		$a5 = $this->factory->activity->create( array(
			'type'      => 'activity_update',
			'item_id'   => $g3,
			'user_id'   => $u3,
			'component' => 'groups',
			'action'    => 'Foo posted an activity update in the group Bar',
		) );

		$a6 = $this->factory->activity->create( array(
			'type'      => 'activity_update',
			'item_id'   => $g3,
			'user_id'   => $u3,
			'component' => 'groups',
			'action'    => 'Foo posted an activity update in the group Bar',
		) );

		ass_digest_record_activity( $a1, $u2, $g1, 'dig' );
		ass_digest_record_activity( $a2, $u2, $g1, 'dig' );
		ass_digest_record_activity( $a3, $u2, $g2, 'dig' );
		ass_digest_record_activity( $a4, $u2, $g2, 'dig' );
		ass_digest_record_activity( $a5, $u2, $g3, 'dig' );
		ass_digest_record_activity( $a6, $u2, $g3, 'dig' );

		$callback = function( $activity_ids ) use ( $a1, $a3, $a4, $g1, $g2 ) {
			return array(
				// g1 should have a2 still queued.
				$g1 => array(
					$a1,
				),

				// g2 should be removed from the queue.
				$g2 => array(
					$a3,
					$a4,
				),

				// No g3 items are sent, so we expect both to be present in the queue afterward.
			);
		};

		$query = new BPGES_Queued_Item_Query( array(
			'user_id' => $u2,
		) );
		$found = $query->get_results();

		add_filter( 'ass_digest_group_activity_ids', $callback );
		bpges_process_digest_for_user( $u2, 'dig', date( 'Y-m-d H:i:s', time() + 10 ) );
		remove_filter( 'ass_digest_group_activity_ids', $callback );

		$query = new BPGES_Queued_Item_Query( array(
			'user_id' => $u2,
		) );
		$found = $query->get_results();

		$found_g1 = $found_g2 = $found_g3 = array();
		foreach ( $found as $item ) {
			if ( $g1 === $item->group_id ) {
				$found_g1[] = $item->activity_id;
			}

			if ( $g2 === $item->group_id ) {
				$found_g2[] = $item->activity_id;
			}

			if ( $g3 === $item->group_id ) {
				$found_g3[] = $item->activity_id;
			}
		}

		$expected_g1 = array( $a2 );
		$expected_g3 = array( $a5, $a6 );

		$this->assertEqualSets( $expected_g1, $found_g1 );
		$this->assertEqualSets( $expected_g3, $found_g3 );

		$this->assertEmpty( $found_g2 );
	}

	public function test_deleted_user_should_delete_queued_items() {
		$u = $this->factory->user->create();

		$g = self::factory()->group->create();

		// Create some activity items.
		$activity_ids = self::factory()->activity->create_many(
			2,
			array(
				'user_id'   => get_current_user_id(),
				'component' => 'groups',
				'type'      => 'activity_update',
				'item_id'   => $g,
			)
		);

		// Add activity items to digest for our user.
		foreach( $activity_ids as $activity_id ) {
			ass_queue_activity_item( $activity_id, $u, $g, 'sum' );
		}

		// Now, delete user.
		wp_delete_user( $u );

		// Queued items should no longer exist.
		$query = new BPGES_Queued_Item_Query(
			array(
				'user_id' => $u,
			)
		);
		$this->assertEmpty( $query->get_results() );
	}

	public function use_mockmailer() {
		return 'GES_Mock_Mailer';
	}

	/**
	 * Helper method to reset the phpmailer instance.
	 */
	public function reset_phpmailer_instance() {
		if ( isset( $GLOBALS['ges_mockmailer'] ) && isset( $GLOBALS['ges_mockmailer']->mock_sent ) ) {
			unset( $GLOBALS['ges_mockmailer']->mock_sent );
		}

		unset( $GLOBALS['phpmailer']->mock_sent );
	}
}
