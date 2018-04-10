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
}
