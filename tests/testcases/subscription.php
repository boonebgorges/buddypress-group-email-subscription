<?php

/**
 * @group subscription
 */
class BPGES_Tests_Subscription extends BP_UnitTestCase {
	protected static $user_ids;
	protected static $group_ids;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_ids  = $factory->user->create_many( 2 );
		self::$group_ids = $factory->group->create_many( 2, array(
			'creator_id' => self::$user_ids[0],
		) );

		self::add_user_to_group( self::$user_ids[0], self::$group_ids[0] );
		self::add_user_to_group( self::$user_ids[1], self::$group_ids[0] );
		self::add_user_to_group( self::$user_ids[0], self::$group_ids[1] );
		self::add_user_to_group( self::$user_ids[0], self::$group_ids[1] );
	}

	public function test_get_subscriptions_for_group() {
		// Wipe clean.
		$subs = ass_get_subscriptions_for_group( self::$group_ids[0] );
		foreach ( $subs as $user_id => $_ ) {
			ass_group_subscription( 'delete', $user_id, self::$group_ids[0] );
		}

		$subs = ass_get_subscriptions_for_group( self::$group_ids[0] );
		$this->assertSame( array(), $subs );

		ass_group_subscription( 'sum', self::$user_ids[0], self::$group_ids[0] );
		ass_group_subscription( 'dig', self::$user_ids[1], self::$group_ids[0] );

		$subs = ass_get_subscriptions_for_group( self::$group_ids[0] );
		$expected = array(
			self::$user_ids[0] => 'sum',
			self::$user_ids[1] => 'dig',
		);
		$this->assertSame( $expected, $subs );

		$this->assertSame( 'sum', ass_get_group_subscription_status( self::$user_ids[0], self::$group_ids[0] ) );
		$this->assertSame( 'dig', ass_get_group_subscription_status( self::$user_ids[1], self::$group_ids[0] ) );
	}

	public function test_digest_record_activity() {
		$activity_id = 123;

		ass_digest_record_activity( $activity_id, self::$user_ids[0], self::$group_ids[0], 'dig' );

		$queue = bpges_get_digest_queue_for_user( self::$user_ids[0], 'dig' );
		$expected = array(
			self::$group_ids[0] => array(
				$activity_id,
			)
		);

		$this->assertSame( $expected, $queue );
	}

	public function test_record_duplicate_activity_should_not_be_allowed() {
		$activity_id = 123;

		$activity = array(
			'user_id'       => self::$user_ids[0],
			'group_id'      => self::$group_ids[0],
			'activity_id'   => $activity_id,
			'type'          => 'immediate',
			'date_recorded' => date( 'Y-m-d H:i:s', time() - 3600 ),
		);

		// Add the item to the queue.
		$add = array( $activity );
		BPGES_Queued_Item::bulk_insert( $add );

		// Add the same item to the queue with a slightly different date.
		$activity['date_recorded'] = date( 'Y-m-d H:i:s' );
		$add = array( $activity );
		BPGES_Queued_Item::bulk_insert( $add );

		// Fetch queued items for our group.
		$sub = new BPGES_Queued_Item_Query( array(
			'group_id' => self::$group_ids[0],
		) );
		$sub = $sub->get_results();

		// Assert that only 1 item was added to the queued items DB table.
		$this->assertSame( 1, count( $sub ) );
	}
}
