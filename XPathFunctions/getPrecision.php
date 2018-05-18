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

use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\xpath\XPathNodeType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Value\Integer;
use lyquidity\XPath2\XPath2Exception;

/**
 * Return the actual or the inferred precision of a numeric fact.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return Integer|string	Return the actual or the inferred precision of a numeric fact. For "INF" the returned type
 * 							is xs:string. For numeric precision value, the returned type is xs:NonNegativeInteger.
 *
 * This function has one real parameter:
 *
 * concept	xs:QName	The QName of the concept being tested.
 *
 */
function getPrecision( $context, $provider, $args )
{
	if ( count( $args ) != 1 )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPST0017", Resources::XPST0017,
			array(
				"concat",
				count( $args ),
				\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ],
			)
		);
	}

	/**
	 * @var XPath2NodeIterator $arg
	 */
	$arg = $args[0];
	if ( is_null( $arg ) ) return $args;

	$result = null;

	try
	{
		/**
		 * @var XPathNavigator $nav
		 */
		$nav = CoreFuncs::CastArg( $context, $arg->CloneInstance(), SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::Node, XmlTypeCardinality::One ) );

		// Look up the type
		$types = \XBRL_Types::getInstance();
		$prefix = $types->getPrefixForNamespace( $nav->getNamespaceURI() );
		if ( ! $prefix )
		{
			throw new \InvalidArgumentException();
		}

		$element = $types->getElement( $nav->getLocalName(), $prefix );
		if ( ! $element )
		{
			throw new \InvalidArgumentException();
		}

		if ( ! isset( $element['substitutionGroup'] ) || ! $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( \XBRL_Constants::$xbrliItem ) ) )
		{
			throw new \InvalidArgumentException();
		}

		$elementType = $element['types'][0];

		if ( is_array( $elementType ) )
		{
			throw new \InvalidArgumentException();
		}

		if ( $types->resolvesToBaseType( $elementType, array( 'xbrli:fractionItemType' ) ) )
		{
			return "INF";
		}

		// BMS 2018-04-09 Test candidates changed.
		if ( ! $types->resolvesToBaseType( $elementType, array( 'xs:decimal', 'xs:double', 'xs:float', 'xsd:decimal', 'xsd:double', 'xsd:float' ) ) )
		{
			throw XPath2Exception::withErrorCode( "xfie:ItemIsNotNumeric", "The concept is not numeric" );
		}

		$value = $nav->getValue();

		if ( ! $nav->MoveToFirstAttribute() )
		{
			throw new \InvalidArgumentException();
		}

		while ( $nav->getLocalName() != "precision" && $nav->getLocalName() != "decimals" )
		{
			if ( ! $nav->MoveToNext( XPathNodeType::Attribute ) )
			{
				throw XPath2Exception::withErrorCode( "xfie:ItemIsNotNumeric", "The concept does not have a precision" );
			}
		}

		if ( $nav->getLocalName() == "precision" )
		{
			return Integer::FromValue( $nav->getValue() );
		}

		$result = \XBRL_Instance::inferPrecision( $value, $nav->getValue() );
		return is_infinite( $result )
			? "INF"
			: Integer::FromValue( $result );
	}
	catch ( XPath2Exception $ex)
	{
		if ( $ex->ErrorCode == "xfie:ItemIsNotNumeric" )
		{
			throw $ex;
		}
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
		array(
			SequenceType::WithTypeCodeAndCardinality( SequenceType::GetXmlTypeCodeFromObject( $result ), XmlTypeCardinality::One ),
			"xs:string"
		)
	);

}
