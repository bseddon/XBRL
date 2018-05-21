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
use lyquidity\XPath2\XEqualComparer;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\XPath2NodeIterator\SingleIterator;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getUnit.php";

/**
 * Sets are formed from the items in each sequence, removing duplicates in the process of forming sets.
 * Returns true if for every item in the left sequence's set there is a u-equal item in the right sequence's
 * set, and the sets have the same count of members. (Because u-equal is defined by XBRL only for items,
 * operation is restricted to nodes that are items.)
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return bool	Sets are formed from the items in each sequence, removing duplicates in the process of
 * 				forming sets. Returns true if for every item in the left sequence's set there is a
 * 				u-equal item in the right sequence's set, and the sets have the same count of members.
 *
 * @throws xfie:NodeIsNotXbrlItem	A dynamic error must be raised if any input element is not derived from xbrli:item.
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getUEqual( $context, $provider, $args )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator
		// There shold be the same count in each node.

		if ( $args[0] instanceof DOMXPathNavigator )
		{
			$args[0] = SingleIterator::Create( $args[0] );
		}
		else if ( ! $args[0] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException();
		}

		if ( $args[1] instanceof DOMXPathNavigator )
		{
			$args[1] = SingleIterator::Create( $args[1] );
		}
		else if ( ! $args[1] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException();
		}

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
			 * @var XPathNavigator $leftUnit
			 */
			$leftUnit = getUnit( $context, $provider, array( $args[0]->getCurrent()->CloneInstance() ), false );

			/**
			 * @var XPathNavigator $rightUnit
			 */
			$rightUnit = getUnit( $context, $provider, array( $args[1]->getCurrent()->CloneInstance() ), false );

			if ( $leftUnit instanceof EmptyIterator && $rightUnit instanceof EmptyIterator )
			{
				return CoreFuncs::$True;
			}

			if ( ! ( $leftUnit instanceof XPathNavigator && $rightUnit instanceof XPathNavigator ) )
			{
				return CoreFuncs::$False;
			}

			$comparer = new XEqualComparer( $context );
			$comparer->useValueCompare = true;
			$result = $comparer->DeepEqualByNavigator( $leftUnit, $rightUnit );
			if ( ! $result )
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
		if ( $ex->ErrorCode == "xfie:NodeIsXbrlTuple" )
		{
			$ex->ErrorCode = "xfie:NodeIsNotXbrlItem";
			throw $ex;
		}
	}
	catch ( \InvalidArgumentException $ex)
	{
		// Do nothing
	}
	catch ( \Exception $ex)
	{
		throw XPath2Exception::withErrorCode( "xfie:NodeIsNotXbrlItem", "argument is " );
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
