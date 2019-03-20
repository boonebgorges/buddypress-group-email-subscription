<?php

/**
 * @group deletion
 */
class BPGES_Tests_Deletion extends BP_UnitTestCase {
	/**
	 * @ticket 146
	 */
	public function test_deleted_activity_should_delete_queued_items() {
		$user_id = 54321;

		// Offset for activity/queued-item IDs.
		$activity_ids = self::factory()->activity->create_many(
			2,
			array(
				'component' => 'groups',
				'type'      => 'activity_update',
				'item_id'   => 12345,
			)
		);

		ass_queue_activity_item( $activity_ids[1], $user_id, 12345, 'immediate' );

		$query = new BPGES_Queued_Item_Query( array(
			'user_id' => $user_id,
			'type'    => 'immediate',
		) );

		$found = array_map( function( $item ) {
			return $item->activity_id;
		}, $query->get_results() );

		$this->assertEqualSets( array( $activity_ids[1] ), $found );

		$deleted = bp_activity_delete(
			array(
				'id' => $activity_ids[1],
			)
		);

		$this->assertTrue( $deleted );

		$query = new BPGES_Queued_Item_Query( array(
			'user_id' => $user_id,
			'type'    => 'immediate',
		) );

		$found = array_map( function( $item ) {
			return $item->activity_id;
		}, $query->get_results() );

		$this->assertEqualSets( array(), $found );
	}

	/**
	 * @ticket 146
	 */
	public function test_deleted_group_should_delete_queued_items() {
		$user_id = 54321;

		$g = self::factory()->group->create();

		// Offset for activity/queued-item IDs.
		$activity_ids = self::factory()->activity->create_many(
			2,
			array(
				'component' => 'groups',
				'type'      => 'activity_update',
				'item_id'   => $g,
			)
		);

		ass_queue_activity_item( $activity_ids[1], $user_id, 12345, 'immediate' );

		$query = new BPGES_Queued_Item_Query( array(
			'user_id' => $user_id,
			'type'    => 'immediate',
		) );

		$found = array_map( function( $item ) {
			return $item->activity_id;
		}, $query->get_results() );

		$this->assertEqualSets( array( $activity_ids[1] ), $found );

		$deleted = groups_delete_group( $g );

		$this->assertTrue( $deleted );

		$query = new BPGES_Queued_Item_Query( array(
			'user_id' => $user_id,
			'type'    => 'immediate',
		) );

		$found = array_map( function( $item ) {
			return $item->activity_id;
		}, $query->get_results() );

		$this->assertEqualSets( array(), $found );
	}
}
