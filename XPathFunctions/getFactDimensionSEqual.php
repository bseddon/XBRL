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

use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\DEqualComparer;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getFactDimensions.php";

/**
 * Returns a sequence of QNames of all typed dimensions that are reported in the segment or scenario of the item.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName*	Returns sequence of QNames of all typed dimensions that are reported in the segment or scenario
 * 						of the item. Result is NOT in any specific order. If item has no typed dimensions empty sequence
 * 						is returned.
 *
 * This function has three real arguments:
 *
 * left-item		schema-element(xbrli:item)	The item that the dimension value is to be retrieved for.
 * right-item		schema-element(xbrli:item)	The item that the dimension value is to be retrieved for.
 * dimension-name	xs:QName					The QName of the dimension for which the value is required.	 *
 */
function getFactDimensionSEqual( $context, $provider, $args )
{
	try
	{
		if ( ! $args[0] instanceof XPath2NodeIterator )
		{
			$args[0] = XPath2NodeIterator::Create( $args[0] );
		}

		if ( ! $args[1] instanceof XPath2NodeIterator )
		{
			$args[1] = XPath2NodeIterator::Create( $args[1] );
		}

		if ( ! $args[0] instanceof XPath2NodeIterator ||
			 ! $args[1] instanceof XPath2NodeIterator ||
			 ! $args[2] instanceof QNameValue )
		{
			throw new \InvalidArgumentException();
		}

		if ( $args[0]->getCount() != 1 || $args[1]->getCount() != 1 )
		{
			throw new \InvalidArgumentException( "There can only be one fact element" );
		}

		$leftDimensions = getFactDimensions( $context, $provider, array( $args[0] ), false );
		$leftItem = null;
		foreach ( $leftDimensions as $dimensionId => $dimension )
		{
			// if ( $dimension['qname'] != $args[2] )
			if ( ! $dimension['qname']->equals( $args[2] ) )
			{
				continue;
			}
			$leftItem = $dimension;
			break;
		}

		if ( is_null( $leftItem ) )
		{
			$args[0]->Reset();
			$leftDimensions = getFactDimensions( $context, $provider, array( $args[0] ), true );
			foreach ( $leftDimensions as $dimensionId => $dimension )
			{
				// if ( $dimension['qname'] != $args[2] )
				if ( ! $dimension['qname']->equals( $args[2] ) )
				{
					continue;
				}
				$leftItem = $dimension;
				break;
			}
		}

		$rightDimensions = getFactDimensions( $context, $provider, array( $args[1] ), false );
		$rightItem = null;
		foreach ( $rightDimensions as $dimensionId => $dimension )
		{
			// if ( $dimension['qname'] != $args[2] )
			if ( ! $dimension['qname']->equals( $args[2] ) )
			{
				continue;
			}
			$rightItem = $dimension;
			break;
		}

		if ( is_null( $rightItem ) )
		{
			$args[1]->Reset();
			$rightDimensions = getFactDimensions( $context, $provider, array( $args[1] ), true );
			foreach ( $rightDimensions as $dimensionId => $dimension )
			{
				// if ( $dimension['qname'] != $args[2] )
				if ( ! $dimension['qname']->equals( $args[2] ) )
				{
					continue;
				}
				$rightItem = $dimension;
				break;
			}
		}

		if ( is_null( $leftItem ) && is_null( $rightItem ) )
		{
			return CoreFuncs::$True;
		}

		if ( ( ! is_null( $leftItem ) && is_null( $rightItem ) ) || ( is_null( $leftItem ) && ! is_null( $rightItem ) ) )
		{
			return CoreFuncs::$False;
		}

		if ( $leftItem['typed'] != $rightItem['typed'] )
		{
			return CoreFuncs::$False;
		}

		if ( $leftItem['typed'] )
		{
			$comparer = new DEqualComparer( $context );
			if ( $comparer->DeepEqualByNavigator( $leftItem['value'], $rightItem['value'] ) )
			{
				return CoreFuncs::$True;
			}
		}
		else
		{
			if ( $leftItem['value'] == $rightItem['value'] )
			{
				return CoreFuncs::$True;
			}
		}

		return CoreFuncs::$False;

	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidTypedDimensionQName" )
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
