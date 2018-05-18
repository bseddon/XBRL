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
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\DOM\XmlSchema;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getConceptRelationships.php";

/**
 * Returns a sequence of relationship parents that represent non-abstract concepts and have non-abstract children.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName*	All relationships from the root in the indicated network are checked for any that have
 * 						non-abstract from (parent) and to (child). The set of distinct QNames of parents is
 * 						returned. This is a helper function for summing children and checking to parent value,
 * 						when either the parent, or children, may be missing (falling back).
 *
 * 						It is used in conjunction with a conceptRelation filter.
 *
 * 						Here is the equivalent XPath expression for this function, in terms of the
 * 						xfi:concept-relations function:
 *
 * 							distinct-values(
 * 								for $relationship in xfi:concept-relationships(
 * 									QName('http://www.xbrl.org/2008/function/instance','root'),
 * 									'http://abc.com/role/link1',
 * 									'http://www.xbrl.org/2003/arcrole/parent-child',
 * 									'sibling-or-descendant'
 * 								),
 * 								$from-concept in xfi:relationship-from-concept($relationship),
 * 								$to-concept in xfi:relationship-to-concept($relationship)
 * 								return (
 * 									if (
 * 										xfi:concept-custom-attribute($from-concept, QName("","abstract")) or
 * 										xfi:concept-custom-attribute($to-concept, QName("","abstract"))
 * 									then ()
 * 									else $from-concept)
 * 								)
 * 							)
 *
 * This function has two real arguments:
 *
 * linkrole	xs:string?	The linkrole value that specifies the network of effective relationships.
 * 						If omitted ("()" or "''") then the default link role is used.
 * arcrole	xs:string	The arcrole value that specifies the network of effective relationships.
 */
function getDistinctNonAbstractParentConcepts( $context, $provider, $args )
{
	try
	{
		if ( $args[0] instanceof XPath2Item )
		{
			$args[0] = $args[0]->getTypedValue();
		}
		else if ( $args[0] instanceof Undefined )
		{
			$args[0] = \XBRL_Constants::$defaultLinkRole;
		}

		if ( ! is_string( $args[0] ) )
		{
			throw new \InvalidArgumentException("The link role argument MUST be a string or an empty sequence");
		}

		if ( $args[1] instanceof XPath2Item )
		{
			$args[1] = $args[1]->getTypedValue();
		}

		if ( ! is_string( $args[1] ) || empty( $args[1] ) )
		{
			throw new \InvalidArgumentException("The arcrole argument must be a string and cannot be empty");
		}

		$relationships = getConceptRelationships(
			$context,
			$provider,
			array(
				QNameValue::fromNCName("xfi:root", $context->NamespaceManager ),
				$args[0],
				$args[1],
				"sibling-or-descendant",
			)
		);

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;

		$result = array();

		foreach ( $relationships as /** @var XPath2Item $item */ $item )
		{
			/**
			 * @var conceptRelationship $relationship
			 */
			$relationship = $item->getTypedValue();

			$from = $relationship->parent;
			$to = $relationship->type;

			$fromElement = $taxonomy->getElementByName( $from->LocalName );
			$isFromAbstract = isset( $fromElement['abstract'] ) && $fromElement['abstract'];
			if ( $isFromAbstract )
			{
				continue;
			}

			$toElement = $taxonomy->getElementByName( $to->LocalName );
			$isToAbstract = isset( $toElement['abstract'] ) && $toElement['abstract'];
			if ( $isToAbstract )
			{
				continue;
			}

			// Use the name as the index to create a distinct list
			$result[ $from->LocalName ] = $from;
		}

		$concepts = array_map( function( /** @var conceptRelationship $item */ $item )
		{
			return XPath2Item::fromValueAndType( $item, XmlSchema::$AnyType );
		}, array_values( $result ) );

		return DocumentOrderNodeIterator::fromItemset( $concepts );
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
