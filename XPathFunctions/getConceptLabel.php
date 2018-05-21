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

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns a string containing the label that has the specified link role, resource role, and language.
 *
 * Note
 *
 * Label is found by effective relationship after considering prohibition and overrides.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:string	Returns a label (if found) or an empty string (if no label found
 * 						for QName given as concept, or for roles or for language code).
 *
 * This function has three real arguments:
 *
 * concept		xs:QName	The QName of the subject concept whose label is to be found.
 * linkrole		xs:string?	The linkrole value that specifies the network of effective relationships of arcs in which
 * 							to find the concept's label. If omitted ("()" or "''") then the default link role is used.
 * labelrole	xs:string?	The label resource role value that is to be found. If omitted ("()" or "''") then the standard
 * 							label is returned.
 * lang			xs:string	The language code of the label to be retrieved. If a label with exact match is not found,
 * 							a closest-sublanguage is returned, e.g., if the argument to $lang is en-UK and a label is present with en, it is returned.
 *
 */
function getConceptLabel( $context, $provider, $args )
{
	try
	{
		if ( ! $args[0] instanceof QNameValue ||
			 ! ( is_string( $args[1] ) || $args[1] instanceof XPath2Item || $args[1] instanceof Undefined ) ||
			 ! ( is_string( $args[2] ) || $args[2] instanceof XPath2Item || $args[2] instanceof Undefined ) ||
			 ! ( is_string( $args[3] ) || $args[3] instanceof XPath2Item || $args[3] instanceof Undefined )
			)
		{
			throw new \InvalidArgumentException();
		}

		// Look up the node namespace in the instance taxonomy
		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;
		if ( ! $taxonomy )
		{
			throw XPath2Exception::withErrorCode( "xfie:invalidConceptQName", "Invalid QName" );
		}

		$taxonomyElement = $taxonomy->getElementByName( $args[0]->LocalName );
		if ( ! $taxonomyElement )
		{
			return "";
		}

		$types = \XBRL_Types::getInstance();
		if ( ! $types->resolveToSubstitutionGroup( $taxonomyElement['substitutionGroup'], array( \XBRL_Constants::$xbrliItem, \XBRL_Constants::$xbrliTuple ) ) )
		{
			return "";
		}

		$linkRole = empty( (string)$args[1] ) ? \XBRL_Constants::$defaultLinkRole : (string)$args[1];
		$labelRole = empty( (string)$args[2] ) ? \XBRL_Constants::$labelRoleLabel : (string)$args[2];
		$label = $taxonomy->getTaxonomyDescriptionForId( $taxonomyElement['id'], array( $labelRole ), (string)$args[3] );

		return $label ? $label : "";
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure, $ex );
}
