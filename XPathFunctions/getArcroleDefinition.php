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
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns a string containing the definition of the arcrole, or an empty sequence if none.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:string?	Returns the definition (if found) or an empty sequence (if not found, or if no
 * 						definition specified). If multiple arcroleTypes for this arcrole are present in
 * 						the DTS any one is returned at the discretion of the implementation.
 *
 * This function has one real argument:
 *
 * arcrole		xs:string?	The subject arcrole.
 *
 */
function getArcroleDefinition( $context, $provider, $args )
{
	try
	{
		if ( ! ( is_string( $args[0] ) || $args[0] instanceof XPath2Item ) )
		{
			throw new \InvalidArgumentException();
		}

		if ( empty( (string)$args[0] ) )
		{
			return EmptyIterator::$Shared;
		}

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;
		if ( ! $taxonomy )
		{
			throw XPath2Exception::withErrorCode( "xfie:invalidConceptQName", "Invalid QName" );
		}

		foreach ( $taxonomy->getArcroleTypes() as $linkRole => $arcroles )
		{
			if ( ! isset( $arcroles[ (string)$args[0] ]['definition'] ) )
			{
				continue;
			}

			$definition = empty( $arcroles[ (string)$args[0] ]['definition'] )
				? EmptyIterator::$Shared
				: $arcroles[ (string)$args[0] ]['definition'];

			return $definition;
		}

		return EmptyIterator::$Shared;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
