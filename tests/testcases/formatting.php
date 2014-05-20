<?php

/**
 * @group formatting
 */
class BPGES_Tests_Formatting extends BP_UnitTestCase {
	/**
	 * @group ass_convert_links
	 */
	public function test_ass_convert_links_no_links() {
		$text = 'Foooooo!';
		$this->assertSame( $text, ass_convert_links( $text ) );
	}

	/**
	 * @group ass_convert_links
	 */
	public function test_ass_convert_single_link() {
		$text = 'Foo <a href="http://bar.com">bar</a>';
		$expected = 'Foo bar <http://bar.com>';

		$this->assertSame( $expected, ass_convert_links( $text ) );
	}

	/**
	 * @group ass_convert_links
	 */
	public function test_ass_convert_multiple_links() {
		$text = 'Foo <a href="http://bar.com">bar</a>

		<a href="http://google.com">Check this out too</a>, oh yeah.';
		$expected = 'Foo bar <http://bar.com>

		Check this out too <http://google.com>, oh yeah.';

		$this->assertSame( $expected, ass_convert_links( $text ) );
	}

	/**
	 * @group ass_convert_links
	 */
	public function test_ass_convert_single_link_multiple_attributes() {
		$text = 'Foo <a target="_blank" href="http://bar.com" class="link">bar</a>';
		$expected = 'Foo bar <http://bar.com>';

		$this->assertSame( $expected, ass_convert_links( $text ) );
	}

	/**
	 * @group ass_convert_links
	 */
	public function test_ass_convert_single_link_no_href() {
		$text = 'Foo <a id="foo">bar</a>';
		$this->assertSame( $text, ass_convert_links( $text ) );
	}
}
