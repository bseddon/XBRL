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
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getContextEntity.php";
require_once "getIdentifier.php";
require_once "getIdentifierSchemeValue.php";

/**
 * Returns the value of the identifier token for the fact item's context's identifier.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPath2NodeIterator	The token that is the fact's context's entity identifier value.
 *
 * This function has one real parameter:
 *
 * item	schema-element(xbrli:item)	The item that the identifier value is to be reported for.
 */
function getFactIdentifierScheme( $context, $provider, $args )
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

		// This should be an element System.Xml.XPathNavigator descendent
		if ( ! $nav instanceof XPathNavigator ) return null;

		// Look for the contextRef
		if ( ! $nav->MoveToFirstAttribute() )
		{
			throw new \InvalidArgumentException();
		}

		while ( $nav->getLocalName() != "contextRef" )
		{
			if ( ! $nav->MoveToNext( XPathNodeType::Attribute ) )
			{
				throw new \InvalidArgumentException();
			}
		}

		$contextRef = $nav->getValue();

		if ( ! $nav->MoveToDocumentElement() || $nav->getLocalName() != "xbrl" )
		{
			throw new \InvalidArgumentException();
		}

		if ( ! $nav->MoveToFirstChild() )
		{
			throw new \InvalidArgumentException();
		}

		while ( true )
		{
			while ( $nav->getLocalName() != "context" )
			{
				if ( ! $nav->MoveToNext( XPathNodeType::Element ) )
				{
					throw new \InvalidArgumentException();
				}
			}

			$attribute = $nav->CloneInstance();

			if ( ! $attribute->MoveToFirstAttribute() )
			{
				throw new \InvalidArgumentException();
			}

			while ( $attribute->getLocalName() != "id")
			{
				if ( ! $attribute->MoveToNext( XPathNodeType::Attribute ) )
				{
					throw new \InvalidArgumentException();
				}
			}

			if ( $attribute->getValue() == $contextRef )
			{
				break;
			}

			if ( ! $nav->MoveToNext( XPathNodeType::Element ) )
			{
				throw new \InvalidArgumentException();
			}
		}

		$entity = getContextEntity( $context, $provider, array( $nav ) );
		$result = getIdentifierSchemeValue( $context, $provider, array( $entity ) );

		return $result;
	}
	catch ( XPath2Exception $ex)
	{
		// Do nothing
		$x = 1;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
		$x = 1;
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );

}
