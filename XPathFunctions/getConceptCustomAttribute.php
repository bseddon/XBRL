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
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\XPath2Convert;
use lyquidity\XPath2\DOM\DOMSchemaType;
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\DOM\XmlSchema;
use lyquidity\XPath2\XPath2Exception;

/**
 * Obtain the value of of an attribute on an XBRL concept declaration that is not in the XBRL instance or XML
 * Schema namespaces.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return xs:anyAtomicType?	Returns the value of the custom attribute on the XBRL concept if the attribute
 * 								is on the concept declaration element. If the custom attribute is omitted from
 * 								the concept declaration, then an empty sequence is returned.
 *
 * @throw xfie:invalidConceptQName	This error MUST be thrown if the concept name parameter contains a QName that
 * 									is not the QName of a concept in the reference discoverable taxonomy set.
 *
 * This function has two real arguments:
 *
 * concept-name		xs:QName	This parameter is the QName of the XBRL concept for which the custom attribute
 * 								value is being sought.
 * attribute-name	xs:QName	This parameter is the QName of the custom attribute whose value is being sought.
 *
 */
function getConceptCustomAttribute( $context, $provider, $args )
{
	try
	{
		if ( count( $args ) < 2 )
		{
			return EmptyIterator::$Shared;
		}

		// There should be two arguments and they should be the QNames to use
		if ( ! $args[0] instanceof QNameValue || ! $args[1] instanceof QNameValue )
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

		$taxonomyElement = $taxonomy->getElementByName( $args[0]->LocalName );
		if ( ! $taxonomyElement )
		{
			return "";
		}

		$taxonomy = $taxonomy->getTaxonomyForNamespace( $args[1]->NamespaceUri );
		if ( $taxonomy )
		{
			$args[1]->Prefix = $taxonomy->getPrefix();
		}

		$customName = (string)$args[1];

		// This node MUST have a substitution group attribute and the attribute MUST resolve to xbrli:tuple or xbrli:item
		if ( ! isset( $taxonomyElement['custom'][ $customName ] ) )
		{
			return EmptyIterator::$Shared;
		}

		$result = $taxonomyElement['custom'][ $customName ];
		if ( strpos( $result, ":" ) !== false )
		{
			return QNameValue::fromNCName( $result, $context->NamespaceManager );
		}

		$schemaType = DOMSchemaType::fromSchemaType( qname( $customName, $context->NamespaceManager->getNamespaces() ) );
		$sequenceType = SequenceType::WithTypeCode( $schemaType->TypeCode );
		$value = XPath2Convert::ChangeType( XmlSchema::$String, $taxonomyElement['custom'][ $customName ], $sequenceType, $context->NameTable, $context->NamespaceManager );
		return $value;
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
