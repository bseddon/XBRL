<?php

/**
 * XPath 2.0 for PHP
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace XBRL\functions;

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\DOM\XmlSchema;
use lyquidity\XPath2\Value\DecimalValue;
use lyquidity\XPath2\XPath2Exception;

/**
 * Provides an implementation of the XSLT 2.0 format-number function (which is not part of XPath2 functions),
 * in a manner that is compatible with XBRL processors.
 *
 * The XSLT definition of format-number is at: http://www.w3.org/TR/xslt20/#format-number.
 *
 * The XSLT definition is applied to the xfi:format-number function except for XSLT's decimal format argument.
 * A processor will obtain any non-default decimal format parameters from its invoking environment (such as the
 * operating system globalization parameters, or a processor invocation globalization parameters). This is to be
 * compatible with generic-message, where the lang attribute selection matches the processor environment settings
 * (and not by specific coding of globalization choices in linkbase or other XBRL resources).
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:string	The formatted number, according to the processing description provided in:
 * 						http://www.w3.org/TR/xslt20/#formatting-the-number.
 *
 * @throws xfie:invalidPictureSyntax	This error MUST be thrown if the picture string argument does not conform
 * 										to the format-number rules provided in the XSLT reference above, paragraph
 * 										16.4.2, where this error is equivalent to XSLT error XDTE1310.
 *
 * This function has two real arguments:
 *
 * value	numeric?	The value argument may be of any numeric data type (xs:double, xs:float, xs:decimal, or
 * 						their subtypes including xs:integer). Note that if an xs:decimal is supplied, it is not
 * 						automatically promoted to an xs:double, as such promotion can involve a loss of precision.
 *
 * 						If the supplied value of the value argument is an empty sequence, the function behaves as
 * 						if the supplied value were the xs:double value NaN.
 *
 * picture	xs:string	The picture string is a sequence of characters, in which the characters assigned to the
 * 						variables:
 *
 * 							decimal-separator-sign,
 * 							grouping-sign,
 * 							zero-digit-sign,
 * 							digit-sign and
 * 							pattern-separator-sign
 *
 * 						are classified as active characters, and all other characters (including the percent-sign
 * 						and per-mille-sign) are classified as passive characters.
 */
function formatNumber( $context, $provider, $args )
{
	try
	{
		if ( $args[0] instanceof Undefined )
		{
			return XPath2Item::fromValueAndType( "NaN", XmlSchema::$String );
		}

		if ( $args[0] instanceof DecimalValue || $args[0] instanceof XPath2Item )
		{
			$args[0] = $args[0]->getValue();
		}

		if ( ! is_numeric( $args[0] ) )
		{
			throw new \InvalidArgumentException("The value argument must be decimal or numeric");
		}

		if ( ! is_string( $args[1] ) && ! $args[1] instanceof XPath2Item )
		{
			throw new \InvalidArgumentException( "Expected the picture argument to be a string" );
		}

		/**
		 * @var number $relationship
		 */
		$value = $args[0];

		/**
		 * @var string $picture
		 */
		$picture = (string)$args[1];

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;

		/*
		 * A picture-string consists either of a sub-picture, or of two sub-pictures separated by a pattern-separator-sign.
		 * A picture-string must not contain more than one pattern-separator-sign. If the picture-string contains two
		 * sub-pictures, the first is used for positive values and the second for negative values.
		 *
		 * 		A sub-picture must not contain more than one decimal-separator-sign.
		 * 		A sub-picture must not contain more than one percent-sign or per-mille-sign, and it must
		 * 			not contain one of each.
		 * 		A sub-picture must contain at least one digit-sign or zero-digit-sign.
		 *  	A sub-picture must not contain a passive character that is preceded by an active character
		 *  		and that is followed by another active character.
		 *  	A sub-picture must not contain a grouping-separator-sign adjacent to a decimal-separator-sign.
		 *
		 * The integer part of a sub-picture must not contain a zero-digit-sign that is followed by a digit-sign.
		 * The fractional part of a sub-picture must not contain a digit-sign that is followed by a zero-digit-sign.
		 */

		setlocale( LC_ALL, $taxonomy->context->locale );
		$locale = localeconv();

		$decimalSeparator = empty( $locale['decimal_point'] ) ? "." : $locale['decimal_point'];
		$groupSeparator = empty( $locale['thousands_sep'] ) ? "," : $locale['decimal_point'];
		$percentSign = "%";
		$digitSign = "#";
		$zeroDigitSign = "0";
		$perMilleSign = "\u{2030}"; // The permille looks like 0/00
		$patternSeparatorSign = ";";

		$active = "$decimalSeparator$groupSeparator$digitSign$zeroDigitSign$patternSeparatorSign"; // decimal-separator-sign, grouping-sign, zero-digit-sign, digit-sign and pattern-separator-sign
		$passive = "$percentSign$perMilleSign";

		// Split the picture into patterns
		$patterns = explode( $patternSeparatorSign, $picture );
		$fail = false;
		foreach ( $patterns as $pattern )
		{
			// Each part should contain one decimal sign
			if ( substr_count( $pattern, $decimalSeparator ) > 1 )
			{
				$fail = true;
				break;
			}

			// Check there is only one of percent or permille
			$percent = substr_count( $pattern, $percentSign );
			$permille = substr_count( $pattern, $perMilleSign );
			if ( $percent > 1 || $permille > 1 || ( $percent != 0 && $permille != 0 ) )
			{
				$fail = true;
				break;
			}

			// Each part should contain one or more of digit-sign or zero-digit-sign
			if ( substr_count( $pattern, $digitSign ) + substr_count( $pattern, $zeroDigitSign ) == 0 )
			{
				$fail = true;
				break;
			}

			// A decimal sign should not be adjacent to a grouping sign
			if ( preg_match( "/^.*\\$decimalSeparator$groupSeparator|$groupSeparator\\$decimalSeparator|$groupSeparator{2,2}/", $pattern ) )
			{
				$fail = true;
				break;
			}

			// The pattern active-passive-active is not allowed
			if ( preg_match( "/^.*[$active](?=.*[$passive].*[$active])/", $pattern ) )
			{
				$fail = true;
				break;
			}

			// Split the pattern at the decimal separator
			$parts = explode( $decimalSeparator, $pattern );
			$integer = $parts[0];

			// Use a lookahead to check for a zero-digit-sign followed by a digit-sign
			if ( preg_match( "/$zeroDigitSign(?=.*$digitSign)/", $integer ) )
			{
				$fail = false;
				break;
			}

			if ( count( $parts ) == 1 )
			{
				continue;
			}

			$fraction = $parts[1];
			// Use a lookahead to check for a digit-sign followed by a zero-digit-sign
			if ( preg_match( "/^.*$digitSign(?=.*$zeroDigitSign)/", $fraction ) )
			{
				$fail = false;
				break;
			}
		}

		if ( $fail )
		{
			throw XPath2Exception::withErrorCode( "xfie:invalidPictureSyntax", "The picture string is not valid." );
		}

		$formatter = new \NumberFormatter( $taxonomy->context->locale, \NumberFormatter::DECIMAL );
		$formatter->setPattern( $picture );

		// $result = $value instanceof DecimalValue ? $formatter->format( $value->getValue() ) : $formatter->format( $value );
		$result = $formatter->format( $value );

		return XPath2Item::fromValueAndType( $result, XmlSchema::$String );
	}
	catch( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidPictureSyntax" )
		{
			throw $ex;
		}
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
