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
 * @version 0.9
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

use lyquidity\xml\xpath\XPathNodeType;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getEntity.php";
require_once "getEntitySegment.php";

/**
 * Return the content of a segment that is not reporting a XBRL Dimensions Specification based dimension value.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return XPath2NodeIterator	Returns the sequence of elements in the segment for the specified item that do not report
 * 								values for XBRL dimensions. The sequence of elements has the same order as the document
 * 								order of the elements in the item's segment. The elements and their descendant nodes have
 * 								the appropriate type based on the Post Schema Validation Infoset. If the item does not report
 * 								a segment then the function returns the empty sequence.
 *
 * This function has one real argument:
 *
 * item	schema-element(xbrli:item)
 *
 */
function getFactSegmentRemainder( $context, $provider, $args )
{
	try
	{
		if ( ! $args[0] instanceof XPath2NodeIterator && ! $args[0] instanceof DOMXPathNavigator )
		{
			throw new \InvalidArgumentException();
		}

		$entity = getEntity( $context, $provider, $args );

		if ( ! $entity )
		{
			throw new \InvalidArgumentException( "Entity for fact not found" );
		}

		$segmentIterator = getEntitySegment( $context, $provider, array( XPath2NodeIterator::Create( $entity ) ) );

		if ( $segmentIterator->getCount() != 1 )
		{
			return EmptyIterator::$Shared;
		}

		if ( ! $segmentIterator->MoveNext() )
		{
			return null;
		}

		$current = $segmentIterator->getCurrent()->CloneInstance();

		if ( ! $current->MoveToChild( XPathNodeType::Element ) )
		{
			return null;
		}

		$remainders = array();

		do
		{
			// The node should not be xbrldi
			if ( $current->getNamespaceURI() != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] )
			{
				$remainders[] = $current->CloneInstance();
			}

			if ( ! $current->MoveToNext( XPathNodeType::Element) )
			{
				break;
			}

		} while( true );

		$result = DocumentOrderNodeIterator::fromItemset( $remainders );
		return $result;

	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
