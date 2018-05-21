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
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\Value\AnyUriValue;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\XPath2Convert;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\DOM\XmlSchema;
use lyquidity\XPath2\DOM\DOMSchemaType;
use lyquidity\XPath2\XPath2Exception;

/**
 * RReturns a typed (PSVI) value of the designated attribute of an effective relationship's parent link element.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:AnyType?	Returns the typed atomic value of the specified attribute of the relationship's parent
 * 						link element, if it exists and has a schema definition, xs:untypedAtomic value if no
 * 						schema definition, or the empty sequence if attribute absent.
 *
 * This function has two real arguments:
 *
 * relationship	xfi:relationship	A relationship (implementation-defined object, node, or surrogate),
 * 									from xfi.concept-relationships or equivalent.
 * attribute	xs:QName			The QName of the attribute to return the value of.
 */
function getRelationshipLinkAttribute( $context, $provider, $args )
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

		if ( ! $args[1] instanceof $args[1] )
		{
			throw new \InvalidArgumentException("The attribute argument must be a QName");
		}

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;

		/**
		 * @var conceptRelationship $relationship
		 */
		$relationship = $args[0];

		/**
		 * @var QNameValue $attribute
		 */
		$attribute = $args[1];

		// Handle XLink attributes
		if ( $attribute->NamespaceUri == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] )
		{
			switch( $attribute->LocalName )
			{
				case "role":
					return new AnyUriValue( $relationship->roleUri );

				default:
					return EmptyIterator::$Shared;
			}
		}

		if ( ! property_exists( $relationship, "roleUri" ) || empty( $relationship->roleUri ) )
		{
			return EmptyIterator::$Shared;
		}

		$roleRefs = $relationship->isGeneric ? $taxonomy->getGenericRoleRefs( $relationship->roleUri ) : $taxonomy->getCustomRoleRefs( $relationship->roleUri );
		if ( ! count( $roleRefs ) )
		{
			return EmptyIterator::$Shared;
		}

		$roleRef = $roleRefs[ $relationship->roleUri ];

		if ( ! isset( $roleRef['attributes'][ $attribute->LocalName ] ) )
		{
			return EmptyIterator::$Shared;
		}

		$schemaType = DOMSchemaType::fromSchemaType( qname( $roleRef['attributes'][ $attribute->LocalName ]['type'], $context->NamespaceManager->getNamespaces() ) );
		$sequenceType = SequenceType::WithTypeCode( $schemaType->TypeCode );
		$value = XPath2Convert::ChangeType( XmlSchema::$String, $roleRef['attributes'][ $attribute->LocalName ]['value'], $sequenceType, $context->NameTable, $context->NamespaceManager );
		return $value;

		$result = XPath2Item::fromValueAndType( $roleRef['attributes'][ $attribute->LocalName ]['value'], $schemaType );
		return  $result;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
