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
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getFactDimensions.php";

/**
 * Returns a sequence of QNames of all explicit dimensions that are reported in the segment or scenario of the item.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array 		$args
 * @return xs:QName*	Returns sequence of QNames of all explicit dimensions that are reported in the segment or
 * 						scenario of the item. Result is NOT in any specific order. If item has no explicit dimensions
 * 						empty sequence is returned. The dimension default is deemed applicable when the dimension is
 * 						not reported for the item, regardless of the dimensional validity of this default value for
 * 						the item (e.g., the default is reported even when the primary item concept, of an item lacking
 * 						the dimension, is in a closed hypercube that does not allow the dimension or does not allow
 * 						the default member).
 *
 * This function has one real argument:
 *
 * item			schema-element(xbrli:item)	The item that the explicit dimension QNames are required for.
 *
 */
function getFactExplicitDimensions( $context, $provider, $args )
{
	try
	{
		$result = getFactDimensions($context, $provider, $args, false );
		if ( $result instanceof Undefined || $result instanceof EmptyIterator )
		{
			return EmptyIterator::$Shared;
		}

		$result = array_map( function( $item ) { return $item['qname']; }, $result );

		return DocumentOrderNodeIterator::fromItemset( array_values( $result ) );
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
