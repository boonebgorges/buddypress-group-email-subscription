<?php

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
			'action'    => '<a href="' . bp_core_get_user_domain( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_core_get_user_domain( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
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
			'action'    => '<a href="' . bp_core_get_user_domain( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_core_get_user_domain( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
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
			'action'    => '<a href="' . bp_core_get_user_domain( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		$this->factory->activity->create_many( 3, array(
			'type'      => 'activity_update',
			'item_id'   => $g2,
			'user_id'   => $u1,
			'component' => 'groups',
			'action'    => '<a href="' . bp_core_get_user_domain( $u1 ). '" title="' . $name . '">' . $name . '</a> posted an update in the group <a href="http://localhost/groups/group/">Group</a>',
		) );

		// Send digests.
		ass_daily_digest_fire();

		//$mailer = $GLOBALS['ges_mockmailer'];
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
