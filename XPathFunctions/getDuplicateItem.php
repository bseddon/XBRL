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
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\TrueValue;
use lyquidity\XPath2\FalseValue;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getIdenticalNodes.php";
require_once "getUEqual.php";
require_once "checkIsItem.php";
require_once "getCEqual.php";
require_once "getPEqual.php";

/**
 * Returns true if two items are duplicates.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @param bool $checkPEqual Used to disable the p-equal when the function is called recursively to test the duplicate state of child nodes
 * @return bool	Returns true if the items are duplicates.
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getDuplicateItem( $context, $provider, $args, $checkPEqual = true )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator
		// There shold be the same count in each node.

		if ( ! $args[0] instanceof XPath2NodeIterator || ! $args[1] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException();
		}

		if ( $args[0]->getCount() != $args[1]->getCount() )
		{
			return CoreFuncs::$False;
		}

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

			checkIsItem( $context, $item1->CloneInstance() );
			checkIsItem( $context, $item2->CloneInstance() );

			if ( $item1->getLocalName() != $item2->getLocalName() || $item1->getNamespaceURI() != $item2->getNamespaceURI() )
			{
				return CoreFuncs::$False;
			}

			$items = array( XPath2NodeIterator::Create( $item1->CloneInstance() ), XPath2NodeIterator::Create( $item2->CloneInstance() ) );

			if ( getIdenticalNodes( $context, $provider, $items ) instanceof TrueValue )
			{
				return CoreFuncs::$False;
			}

			if ( $checkPEqual )
			{
				$items = array( XPath2NodeIterator::Create( $item1->CloneInstance() ), XPath2NodeIterator::Create( $item2->CloneInstance() ) );

				if ( getPEqual( $context, $provider, $items ) instanceof FalseValue )
				{
					return CoreFuncs::$False;
				}
			}

			$items = array( XPath2NodeIterator::Create( $item1->CloneInstance() ), XPath2NodeIterator::Create( $item2->CloneInstance() ) );

			if ( getUEqual( $context, $provider, $items ) instanceof FalseValue )
			{
				return CoreFuncs::$False;
			}

			$items = array( XPath2NodeIterator::Create( $item1->CloneInstance() ), XPath2NodeIterator::Create( $item2->CloneInstance() ) );

			if ( getCEqual( $context, $provider, $items ) instanceof FalseValue )
			{
				return CoreFuncs::$False;
			}

		}
		while( true );

		return CoreFuncs::$True;
	}
	catch ( XPath2Exception $ex)
	{
		if ( $ex->ErrorCode == "xffe:invalidFunctionUse" )
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
