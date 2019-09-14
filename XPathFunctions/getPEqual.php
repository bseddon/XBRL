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
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\MS\XmlNodeOrder;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\XPath2\DOM\DOMXPathNavigator;

// Make sure any required functions are imported
require_once "checkIsItem.php";
require_once "checkIsTuple.php";

/**
 * Returns true if two node sequences are p-equal.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return bool	Returns true if the two sequences of nodes are p-equal and false otherwise.
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getPEqual( $context, $provider, $args )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator
		if ( $args[0] instanceof DOMXPathNavigator )
			$args[0] = XPath2NodeIterator::Create( $args[0] );

		if ( $args[1] instanceof DOMXPathNavigator )
			$args[1] = XPath2NodeIterator::Create( $args[1] );

		if ( ! $args[0] instanceof XPath2NodeIterator || ! $args[1] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException();
		}

		// There should be the same count in each node.
		if ( $args[0]->getCount() != $args[1]->getCount() )
		{
			return CoreFuncs::$False;
		}

		$args[0]->Reset();
		$args[1]->Reset();

		do
		{
			$flag1 = $args[0]->MoveNext();
			$flag2 = $args[1]->MoveNext();

			if ( $flag1 != $flag2 )
			{
				return CoreFuncs::$False;
			}

			if ( ! $flag1 )
			{
				break;
			}

			/**
			 * @var XPathNavigator $item1
			 */
			$item1 = $args[0]->getCurrent()->CloneInstance();
			/**
			 * @var XPathNavigator $item2
			 */
			$item2 = $args[1]->getCurrent()->CloneInstance();

			if (
				 ( ! checkIsItem( $context, $item1->CloneInstance(), false ) && ! checkIsTuple( $context, $item1->CloneInstance(), false ) ) ||
				 ( ! checkIsItem( $context, $item2->CloneInstance(), false ) && ! checkIsTuple( $context, $item2->CloneInstance(), false ) )
			)
			{
				throw XPath2Exception::withErrorCode( "xfie:ElementIsNotXbrlConcept", "The concept is not an item or tuple" );
			}

			$item1->MoveToParent();
			$item2->MoveToParent();

			if ( $item1->ComparePosition( $item2, false ) != XmlNodeOrder::Same )
			{
				return CoreFuncs::$False;
			}

		}
		while( true );

		return CoreFuncs::$True;
	}
	catch ( XPath2Exception $ex)
	{
		if ( $ex->ErrorCode == "xffe:invalidFunctionUse" || $ex->ErrorCode == "xfie:ElementIsNotXbrlConcept" )
		{
			throw $ex;
		}
		if ( $ex->ErrorCode == "xfie:NodeIsNotXbrlItem" )
		{
			$ex->ErrorCode = 'xfie:ElementIsNotXbrlConcept';
			throw $ex;
		}
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
