<?php
/**
 * Pure Gregorian <-> Jalali calendar arithmetic for G-008 presentation.
 *
 * Algorithm lineage: Kazimierz M. Borkowski, "The Persian Calendar for 3000 Years",
 * Earth, Moon, and Planets 74 (1996), 223-230.
 *
 * This is a PHP adaptation of the Borkowski-lineage conversion logic in
 * jalaali-js 2.0.1, commit 7ff10a0a4145c84a6911e87bfacf40ddf51a2adc:
 * https://github.com/jalaali/jalaali-js
 *
 * The adapted upstream implementation is MIT licensed:
 *
 * Copyright (c) 2020 Behrang Norouzinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * The production product range is deliberately narrower than the algorithm's
 * mathematical range. PGR_Jalali_Presentation owns that support/fallback policy.
 * This class owns calendar arithmetic only; it has no WordPress, Gravity Forms,
 * timezone, string-parsing, or UI responsibility.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gregorian_Jalali_Converter {

	/** @var int[] Borkowski break years used by the reference implementation. */
	private const BREAKS = array(
		-61,
		9,
		38,
		199,
		426,
		686,
		756,
		818,
		1111,
		1181,
		1210,
		1635,
		2060,
		2097,
		2192,
		2262,
		2324,
		2394,
		2456,
		3178,
	);

	private const MIN_JALALI_YEAR = -61;
	private const MAX_JALALI_YEAR = 3177;

	/**
	 * Convert one validated Gregorian civil date to Jalali.
	 *
	 * @return array{year:int,month:int,day:int}|null
	 */
	public static function to_jalali( int $year, int $month, int $day ): ?array {
		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}

		return self::d2j( self::g2d( $year, $month, $day ) );
	}

	/**
	 * Convert one valid Jalali civil date to Gregorian.
	 *
	 * Used for bounded round-trip/property verification; it is not the G-008
	 * consumer-facing presentation API.
	 *
	 * @return array{year:int,month:int,day:int}|null
	 */
	public static function to_gregorian( int $year, int $month, int $day ): ?array {
		if ( ! self::is_valid_jalali_date( $year, $month, $day ) ) {
			return null;
		}

		return self::d2g( self::j2d( $year, $month, $day ) );
	}

	/**
	 * Test Jalali calendar validity inside the Borkowski table range.
	 */
	private static function is_valid_jalali_date( int $year, int $month, int $day ): bool {
		if (
			$year < self::MIN_JALALI_YEAR ||
			$year > self::MAX_JALALI_YEAR ||
			$month < 1 ||
			$month > 12 ||
			$day < 1
		) {
			return false;
		}

		$length = self::jalali_month_length( $year, $month );
		return null !== $length && $day <= $length;
	}

	/**
	 * @return int|null
	 */
	private static function jalali_month_length( int $year, int $month ): ?int {
		if ( $month < 1 || $month > 12 ) {
			return null;
		}
		if ( $month <= 6 ) {
			return 31;
		}
		if ( $month <= 11 ) {
			return 30;
		}

		$cal = self::jal_cal( $year );
		return null === $cal ? null : ( 0 === $cal['leap'] ? 30 : 29 );
	}

	/**
	 * @return array{leap:int,gy:int,march:int}|null
	 */
	private static function jal_cal( int $year ): ?array {
		$core = self::jal_cal_core( $year );
		if ( null === $core ) {
			return null;
		}

		return array(
			'leap'  => self::leap_from_cycle( $core['jump'], $core['n'] ),
			'gy'    => $core['gy'],
			'march' => $core['march'],
		);
	}

	/**
	 * @return array{gy:int,march:int,jump:int,n:int}|null
	 */
	private static function jal_cal_core( int $year ): ?array {
		if ( $year < self::MIN_JALALI_YEAR || $year > self::MAX_JALALI_YEAR ) {
			return null;
		}

		$gy     = $year + 621;
		$leap_j = -14;
		$jp     = self::BREAKS[0];
		$jm     = 0;
		$jump   = 0;
		$count  = count( self::BREAKS );

		for ( $index = 1; $index < $count; $index++ ) {
			$jm   = self::BREAKS[ $index ];
			$jump = $jm - $jp;
			if ( $year < $jm ) {
				break;
			}
			$leap_j += intdiv( $jump, 33 ) * 8 + intdiv( $jump % 33, 4 );
			$jp      = $jm;
		}

		$n       = $year - $jp;
		$leap_j += intdiv( $n, 33 ) * 8 + intdiv( ( $n % 33 ) + 3, 4 );
		if ( 4 === $jump % 33 && 4 === $jump - $n ) {
			++$leap_j;
		}

		$leap_g = intdiv( $gy, 4 ) - intdiv( ( intdiv( $gy, 100 ) + 1 ) * 3, 4 ) - 150;
		$march  = 20 + $leap_j - $leap_g;

		return array(
			'gy'    => $gy,
			'march' => $march,
			'jump'  => $jump,
			'n'     => $n,
		);
	}

	private static function leap_from_cycle( int $jump, int $n ): int {
		$adjusted = $n;
		if ( $jump - $n < 6 ) {
			$adjusted = $n - $jump + intdiv( $jump + 4, 33 ) * 33;
		}

		$leap = ( ( $adjusted + 1 ) % 33 - 1 ) % 4;
		return -1 === $leap ? 4 : $leap;
	}

	private static function j2d( int $jy, int $jm, int $jd ): int {
		$cal = self::jal_cal( $jy );
		if ( null === $cal ) {
			throw new OutOfRangeException( 'Jalali year is outside the Borkowski table range.' );
		}

		return self::g2d( $cal['gy'], 3, $cal['march'] )
			+ ( $jm - 1 ) * 31
			- intdiv( $jm, 7 ) * ( $jm - 7 )
			+ $jd
			- 1;
	}

	/**
	 * @return array{year:int,month:int,day:int}|null
	 */
	private static function d2j( int $jdn ): ?array {
		$gregorian = self::d2g( $jdn );
		$jy        = min( $gregorian['year'] - 621, self::MAX_JALALI_YEAR );
		$cal       = self::jal_cal( $jy );
		if ( null === $cal ) {
			return null;
		}

		$jdn_farvardin = self::g2d( $cal['gy'], 3, $cal['march'] );
		$k             = $jdn - $jdn_farvardin;

		if ( $k >= 0 ) {
			if ( $k <= 185 ) {
				return array(
					'year'  => $jy,
					'month' => 1 + intdiv( $k, 31 ),
					'day'   => ( $k % 31 ) + 1,
				);
			}
			$k -= 186;
		} else {
			--$jy;
			if ( $jy < self::MIN_JALALI_YEAR ) {
				return null;
			}
			$k += 179;
			if ( 1 === $cal['leap'] ) {
				++$k;
			}
		}

		$result = array(
			'year'  => $jy,
			'month' => 7 + intdiv( $k, 30 ),
			'day'   => ( $k % 30 ) + 1,
		);

		return self::is_valid_jalali_date( $result['year'], $result['month'], $result['day'] )
			? $result
			: null;
	}

	private static function g2d( int $gy, int $gm, int $gd ): int {
		$d = intdiv( ( $gy + intdiv( $gm - 8, 6 ) + 100100 ) * 1461, 4 )
			+ intdiv( 153 * ( ( $gm + 9 ) % 12 ) + 2, 5 )
			+ $gd
			- 34840408;

		return $d - intdiv( intdiv( $gy + 100100 + intdiv( $gm - 8, 6 ), 100 ) * 3, 4 ) + 752;
	}

	/**
	 * @return array{year:int,month:int,day:int}
	 */
	private static function d2g( int $jdn ): array {
		$j = 4 * $jdn + 139361631;
		$j = $j + intdiv( intdiv( 4 * $jdn + 183187720, 146097 ) * 3, 4 ) * 4 - 3908;
		$i = intdiv( $j % 1461, 4 ) * 5 + 308;

		$day   = intdiv( $i % 153, 5 ) + 1;
		$month = ( intdiv( $i, 153 ) % 12 ) + 1;
		$year  = intdiv( $j, 1461 ) - 100100 + intdiv( 8 - $month, 6 );

		return array(
			'year'  => $year,
			'month' => $month,
			'day'   => $day,
		);
	}
}
