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
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\TreeComparer;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns true if both if and only if the two argument nodes are both attribute nodes that correspond or
 * both element nodes that correspond. It returns a boolean value of false otherwise.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return bool	True if both if and only if left and right nodes are both attribute nodes that correspond or both
 * 				element nodes that correspond; false otherwise.
 *
 * Two attribute nodes, A and B, are corresponding attributes if the following conditions are all satisfied:
 *
 * A and B have the same name
 * The sequences of atomic values obtained by atomizing A and B,As and Bs, are the same length and
 * for each item Ai, at position i in As, the item Bi at position i in Bs, is such that the XPath 2.0 expression
 * (Ai eq Bi) evaluates to an effective Boolean value of true when using the empty sequence as the context item.
 *
 * Note that if the attribute nodes, A and B, both atomize to empty sequences then those attribute nodes correspond.
 *
 * Two element nodes, A and B, are corresponding elements if the following conditions are all satisfied:
 *
 * A and B have the same name.
 * If the nodes A and B have mixed or simple content, the sequences of atomic values obtained by atomizing A and B,
 * As and Bs, fulfill the following criteria:
 *
 * As and Bs are the same length.
 * For items Ai and Bi, at position i in As and Bs respectively, the XPath 2.0 expression (Ai eq Bi) evaluates to
 * an effective Boolean value of true when using the empty sequence as the context item.
 * A and B have the same number of attributes
 * For each non-id attribute on element node A, there is a corresponding attribute on element node B.
 * A and B have the same number of child elements.
 *
 * For each child element of element node A, Ac, there is a corresponding child element of element node B, Bc,
 * such that Ac and Bc have the same number of preceding sibling elements.
 *
 * Note that, as for attribute nodes, if the element nodes, A and B, both atomize to empty sequences then those
 * element nodes correspond.
 *
 * This function has two real arguments:
 *
 * left	node()	The first node or sequence of nodes.
 * right node()	The second node or sequence of nodes.
 *
 */
function getNodesCorrespond( $context, $provider, $args )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator or DateTimeValue
		// There shold be the same count in each node.

		if ( ! $args[0] instanceof XPath2NodeIterator )
		{
			if ( $args[0] instanceof DOMXPathNavigator )
			{
				$args[0] = XPath2NodeIterator::Create( $args[0]->CloneInstance() );
			}
			else
			{
				throw new \InvalidArgumentException();
			}
		}

		if ( ! $args[1] instanceof XPath2NodeIterator )
		{
			if ( $args[1] instanceof DOMXPathNavigator )
			{
				$args[1] = XPath2NodeIterator::Create( $args[1]->CloneInstance() );
			}
			else
			{
				throw new \InvalidArgumentException();
			}
		}

		$comparer = new TreeComparer( $context );
		$comparer->excludeWhitespace = true;
		$comparer->attributeToIgnore = 'id';

		$res = $comparer->DeepEqualByIterator( $args[0]->CloneInstance(), $args[1]->CloneInstance() );

		return $res
			? CoreFuncs::$True
			: CoreFuncs::$False;

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
