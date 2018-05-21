<?php

/**
 * From: http://php.net/manual/en/class.dateinterval.php#113091
 * @author Bill Seddon
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * This DateInterval extension allows you to write a formatted timestamp but omit the "zero values" and handle things like listing, plurals, etc.
 * @author Bill Seddon
 *
 */
class XBRL_DateInterval extends DateInterval
{
	/**
	 * Must be exactly 2 characters long.  The first character is the opening brace, the second the closing brace
	 * Text between these braces will be used if > 1, or replaced with $this->singularReplacement if = 1
	 * @var string $singularReplacement
	 */
	public $pluralCheck = '()';
	/**
	 * Replaces $this->pluralCheck if = 1
	 * hour(s) -> hour
	 * @var string $separator
	 */
	public $singularReplacement = '';
	/**
	 * Delimiter between units
	 * 3 hours, 2 minutes
	 * @var string $finalSeparator
	 */
	public $separator = ', ';
	/**
	 * Delimeter between next-to-last unit and last unit
	 * 3 hours, 2 minutes, and 1 second
	 * @var string $finalSeparator2
	 */
	public $finalSeparator = ', and ';
	/**
	 * Delimeter between units if there are only 2 units
	 * 3 hours and 2 minutes
	 * @var string $finalSeparator2
	 */
	public $finalSeparator2 = ' and ';

	/**
	 * Create an instance of this class from an existing DateInterval instance
	 * @param DateInterval $interval
	 * @return XBRL_DateInterval
	 */
	public static function createFromDateInterval(DateInterval $interval)
	{
		$obj = new self( 'PT0S' );
		foreach ( $interval as $property => $value ) {
			$obj->$property = $value;
		}
		return $obj;
	}

	/**
	 * Each argument may have only one % parameter
	 * Result does not handle %R or %r -- but you can retrieve that information using $this->format('%R') and using your own logic
	 * @return string
	 *
	 * <code>
	 * 	input: '%y year(s)', '%m month(s)', '%d day(s)', '%h hour(s)', '%i minute(s)', '%s second(s)'
	 * 	output: 1 year, 2 months, 16 days, 1 minute, and 15 seconds
	 * </code>
	 */
	public function formatWithoutZeroes ()
	{
		$parts = array();
		foreach ( func_get_args() as $arg ) {
			$pre = mb_substr( $arg, 0, mb_strpos( $arg, '%' ) );
			$param = mb_substr( $arg, mb_strpos( $arg, '%' ), 2 );
			$post = mb_substr( $arg, mb_strpos( $arg, $param ) + mb_strlen( $param ) );
			$num = intval( parent::format( $param ) );

			$open = preg_quote( $this->pluralCheck[0], '/' );
			$close = preg_quote( $this->pluralCheck[1], '/' );
			$pattern = "/$open(.*)$close/";
			list ( $pre, $post ) = preg_replace( $pattern, $num == 1 ? $this->singularReplacement : '$1', array( $pre, $post ) );

			if ( $num != 0 ) {
				$parts[] = $pre.$num.$post;
			}
		}

		$output = '';
		$l = count( $parts );
		foreach ( $parts as $i => $part ) {
			$output .= $part.($i < $l - 2 ? $this->separator : ($l == 2 ? $this->finalSeparator2 : ($i == $l - 2 ? $this->finalSeparator : '' ) ) );
		}
		return $output;
	}
}
