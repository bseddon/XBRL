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
use lyquidity\XPath2\Iterator\ChildOverDescendantsNodeIterator\NodeTest;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\TrueValue;
use lyquidity\XPath2\FalseValue;
use lyquidity\xml\MS\XmlQualifiedNameTest;
use lyquidity\XPath2\Iterator\ChildNodeIterator;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getIdenticalNodes.php";
require_once "getVEqual.php";
require_once "checkIsTuple.php";
require_once "getDuplicateItem.php";
require_once "getPEqual.php";

/**
 * Returns true if two tuples are duplicates.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @param bool $checkPEqual Used to disable the p-equal when the function is called recursively to test the duplicate state of child nodes
 * @return bool	Returns true if the tuples are duplicates.
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getDuplicateTuple( $context, $provider, $args, $checkPEqual = true )
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

			checkIsTuple( $context, $item1->CloneInstance() );
			checkIsTuple( $context, $item2->CloneInstance() );

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

			$nodeTest = XmlQualifiedNameTest::create();
			$children1 = ChildNodeIterator::fromNodeTest( $context, $nodeTest, XPath2NodeIterator::Create( $item1->CloneInstance() ) );
			$children2 = ChildNodeIterator::fromNodeTest( $context, $nodeTest, XPath2NodeIterator::Create( $item2->CloneInstance() ) );

			$matched = array();

			foreach ( $children1 as $child1 )
			{
				$match = false;
				$index = 0;

				foreach ( $children2 as $child2 )
				{
					$index++;

					if ( in_array( $index, $matched ) )
					{
						continue;
					}

					if ( checkIsTuple( $context, $child1->CloneInstance(), false ) )
					{
						// If the child1 node is a tuple is the child2 node also a tuple?
						if ( checkIsTuple( $context, $child2->CloneInstance(), false ) )
						{
							// Yes, so match the child2 recursively
							$items = array( XPath2NodeIterator::Create( $item1->CloneInstance() ), XPath2NodeIterator::Create( $item2->CloneInstance() ) );

							if ( getDuplicateTuple( $context, $provider, $args, false ) instanceof TrueValue )
							{
								$matched[] = $index;
								$match = true;
								break;
							}
						}
					}
					else
					{
						// The child1 is not a tuple so make sure the child2 is not a tuple
						if ( checkIsTuple( $context, $child2, false ) )
						{
							continue;
						}

						$items = array( XPath2NodeIterator::Create( $child1->CloneInstance() ), XPath2NodeIterator::Create( $child2->CloneInstance() ) );

						if ( getDuplicateItem( $context, $provider, $args, false ) instanceof TrueValue )
						{
							$items = array( XPath2NodeIterator::Create( $child1->CloneInstance() ), XPath2NodeIterator::Create( $child2->CloneInstance() ) );

							if ( getVEqual( $context, $provider, $items ) instanceof TrueValue )
							{
								$matched[] = $index;
								$match = true;
								break;
							}
						}
					}
				}

				if ( ! $match )
				{
					return CoreFuncs::$False;
				}

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
