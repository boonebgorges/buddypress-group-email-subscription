<?php

class BPGES_Tests_Functions extends BP_UnitTestCase {
	/**
	 * @ticket 119
	 */
	public function test_user_unsubscribed_on_groups_leave_group() {
		$u = $this->factory->user->create();
		$g = $this->factory->group->create();
		groups_join_group( $g, $u );
		ass_group_subscription( 'supersub', $u, $g );

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertSame( 'supersub', $status );

		groups_leave_group( $g, $u );

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertFalse( $status );
	}

	/**
	 * @ticket 119
	 */
	public function test_user_unsubscribed_on_remove() {
		$u = $this->factory->user->create();
		$g = $this->factory->group->create();
		groups_join_group( $g, $u );
		ass_group_subscription( 'supersub', $u, $g );

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertSame( 'supersub', $status );

		$gm = new BP_Groups_Member( $u, $g );
		$gm->remove();

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertFalse( $status );
	}

	/**
	 * @ticket 119
	 */
	public function test_user_unsubscribed_on_delete() {
		$u = $this->factory->user->create();
		$g = $this->factory->group->create();
		groups_join_group( $g, $u );
		ass_group_subscription( 'supersub', $u, $g );

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertSame( 'supersub', $status );

		$gm = new BP_Groups_Member( $u, $g );
		BP_Groups_Member::delete( $u, $g );

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertFalse( $status );
	}

	/**
	 * @ticket 119
	 */
	public function test_user_unsubscribed_on_ban() {
		$u = $this->factory->user->create();
		$g = $this->factory->group->create();
		groups_join_group( $g, $u );
		ass_group_subscription( 'supersub', $u, $g );

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertSame( 'supersub', $status );

		$gm = new BP_Groups_Member( $u, $g );
		$gm->ban();

		$status = ass_get_group_subscription_status( $u, $g );
		$this->assertFalse( $status );
	}
}
