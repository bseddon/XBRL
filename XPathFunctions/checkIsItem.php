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
use lyquidity\XPath2\ExtFuncs;
use lyquidity\XPath2\XPath2Exception;

/**
 * Check the element is an item. An exception will be thrown if not
 * @param ContextProvider $context
 * @param XPathItem $element
 * @param bool $raiseException
 * @throws \InvalidArgumentException If the namespace cannot be resolved to a prefix
 * @throws XPath2Exception If the element is not an xbrli:item
 *
 * This function has two real arguments
 *
 * left	node()*		The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function checkIsItem( $context, $element, $raiseException = true )
{
	$qn = ExtFuncs::GetNodeName( $context, CoreFuncs::NodeValue( $element, false ) );

	// Look up the type
	$types = \XBRL_Types::getInstance();
	$prefix = $types->getPrefixForNamespace( $qn->NamespaceUri );
	if ( ! $prefix )
	{
		throw new \InvalidArgumentException();
	}

	$element = $types->getElement( $qn->LocalName, $prefix );
	if ( ! $element )
	{
		throw new \InvalidArgumentException();
	}

	if ( ! isset( $element['substitutionGroup'] ) || ! $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( \XBRL_Constants::$xbrliItem ) ) )
	{
		if ( $raiseException )
		{
			throw XPath2Exception::withErrorCode( "xfie:NodeIsNotXbrlItem", "The concept is not an item" );
		}

		return false;
	}

	return true;
}
