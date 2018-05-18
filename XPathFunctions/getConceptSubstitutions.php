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
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\XPath2Exception;

/**
 * Obtains an ordered sequence of QNames of the elements that the concept is in the substitution group for.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName+	Returns an ordered sequence of QNames of the elements that the concept is in the substitution group for.
 * 						The sequence begins with the QName that is specified in the xs:substitutionGroup attribute on the
 * 						concept declaration and ends with either the QName for the xbrli:item or the xbrli:tuple element.
 *
 * @throw xfie:invalidConceptQName	This error MUST be thrown if the concept name parameter contains a QName that
 * 									is not the QName of a concept in the reference discoverable taxonomy set.
 *
 * This function has one real argument:
 *
 * concept-name	xs:QName	The QName of the concept whose data type is being tested.
 *
 */
function getConceptSubstitutions( $context, $provider, $args )
{
	try
	{
		// There should be one argument and it should be the QName to use
		if ( ! $args[0] instanceof QNameValue )
		{
			throw new \InvalidArgumentException();
		}

		if ( ! isset( $context->xbrlTaxonomy ) )
		{
			throw new \InvalidArgumentException( "XBRL taxonomy not set in context" );
		}

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;
		if ( ! $taxonomy )
		{
			throw new \InvalidArgumentException( "XBRL taxonomy in context not valid" );
		}

		$types = \XBRL_Types::getInstance();
		$result = array();
		$localName = $args[0]->LocalName;

		do
		{
			$elementTaxonomy = $taxonomy->getTaxonomyForNamespace($args[0]->NamespaceUri);
			$taxonomyElement = $elementTaxonomy ? $elementTaxonomy->getElementByName( $localName ) : false;
			if ( ! $taxonomyElement || ! isset( $taxonomyElement['substitutionGroup'] ) || empty( $taxonomyElement['substitutionGroup'] ) )
			{
				throw XPath2Exception::withErrorCode( "xfie:invalidConceptQName", "Not a valid XBRL concept: does not have a substitution group" );
			}

			$substitutionGroup = $taxonomyElement['substitutionGroup'];

			$qname = QNameValue::fromNCName( $substitutionGroup, $context->NamespaceManager );
			$result[] = XPath2Item::fromValue( $qname );
			$localName = $qname->LocalName;

		}
		while ( ! in_array( $substitutionGroup, array( "xbrli:item", "xbrli:tuple" ) ) );

		$x = DocumentOrderNodeIterator::fromItemset( $result );
		return $x;
	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidConceptQName" )
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
