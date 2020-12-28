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

use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\FalseValue;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\TrueValue;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getIdenticalNodes.php";
require_once 'getSEqual.php';

/**
 * Sets are formed from the nodes in each sequence, removing duplicates in the process of forming sets.
 * Returns true if for every node in the left sequence's set there is an s-equal node in the right
 * sequence's set, and the sets have the same count of members.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return FalseValue|TrueValue	Sets are formed from the nodes in each sequence, removing duplicates in
 * 								the process of forming sets. Returns true if for every node in the left
 * 								sequence's set there is an s-equal node in the right sequence's set, and
 * 								the sets have the same count of members.
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getSEqualSet( $context, $provider, $args )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator
		// There shold be the same count in each node.
		if ( $args[0] instanceof Undefined )
		{
			$args[0] = EmptyIterator::$Shared;
		}

		if ( $args[1] instanceof Undefined )
		{
			$args[1] = EmptyIterator::$Shared;
		}

		if ( ! $args[0] instanceof XPath2NodeIterator || ! $args[1] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException();
		}

		// Remove duplicates first
		// The DocumentOrderNodeIterator makes sure the nodes are in document order and also eliminates duplicates
		$doni1 = DocumentOrderNodeIterator::fromBaseIter( $args[0] );
		$doni2 = DocumentOrderNodeIterator::fromBaseIter( $args[1] );

		if ( $doni1->getCount() != $doni2->getCount() )
		{
			return CoreFuncs::$False;
		}

		$matches = array();

		foreach ( $doni1 as $item1 )
		{
			$match = false;
			$index = 0;

			foreach ( $doni2 as $item2 )
			{
				$index++;

				if ( in_array( $index, $matches ) )
				{
					continue;
				}

				if ( getSEqual( $context, $provider, array( XPath2NodeIterator::Create( $item1->CloneInstance() ), XPath2NodeIterator::Create( $item2->CloneInstance() ) ) ) instanceof CoreFuncs::$True )
				{
					$matches[] = $index;
					$match = true;
					break;
				}
			}
			if ( ! $match )
			{
				return CoreFuncs::$False;
			}
		}

		return CoreFuncs::$True;

		// return getIdenticalNodes( $context, $provider, array( $doni1, $doni2 ) );
	}
	catch ( XPath2Exception $ex)
	{
		if ( $ex->ErrorCode == "xfie:NodeIsNotXbrlItem" )
		{
			throw $ex;
		}
	}
	catch( \InvalidArgumentException $ex )
	{
		// Do nothing
	}
	catch ( \Exception $ex)
	{
		throw XPath2Exception::withErrorCode( "xfie:NodeIsNotXbrlItem", "argument is " );
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
