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
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns a QName of the arc element of an effective relationship's arc. May be helpful to designate
 * base set when multiple arc elements can be used on an arcrole in same link element.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName	Returns the typed QName value of the relationship's arc element.
 *
 * This function has one real argument:
 *
 * relationship	xfi:relationship.type	A relationship (implementation-defined object, node, or surrogate),
 * 										from xfi.concept-relationships or equivalent.
 */
function relationshipName( $context, $provider, $args )
{
	try
	{
		if ( $args[0] instanceof XPath2NodeIterator )
		{
			if ( ! $args[0]->getCount() || ! $args[0]->MoveNext() )
			{
				throw new \InvalidArgumentException();
			}

			$args[0] = $args[0]->getCurrent();
		}

		if ( $args[0] instanceof XPath2Item )
		{
			$args[0] = $args[0]->getTypedValue();
		}

		if ( ! $args[0] instanceof conceptRelationship )
		{
			throw new \InvalidArgumentException( "Expected the argument to be an item return by concept-relationships" );
		}

		return $args[0]->arcType;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
