<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php';

final class G010GravityFlowTimelinePrintOwnershipTest extends TestCase {

	public function test_print_is_attributed_only_outside_the_nearest_timeline_invocation(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'is_print_inheritance_trace' );
		$timeline   = array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'timeline' );
		$print      = array( 'class' => 'Gravity_Flow_Print_Entries', 'type' => '::', 'function' => 'render' );
		$normal     = array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'maybe_show_timeline' );

		$this->assertTrue( $method->invoke( $adapter, array( $timeline, $print ) ) );
		$this->assertFalse( $method->invoke( $adapter, array( $timeline, $normal ) ) );
		$this->assertFalse( $method->invoke( $adapter, array( $print, $normal ) ) );
	}

	public function test_nested_timeline_cannot_borrow_print_from_an_outer_timeline(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'is_print_inheritance_trace' );
		$timeline   = array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'timeline' );
		$print      = array( 'class' => 'Gravity_Flow_Print_Entries', 'type' => '::', 'function' => 'render' );
		$normal     = array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'maybe_show_timeline' );

		$this->assertFalse(
			$method->invoke(
				$adapter,
				array(
					$timeline,
					$normal,
					$timeline,
					$print,
				)
			)
		);
	}
}
