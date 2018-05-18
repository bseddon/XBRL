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

use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\Iterator\ChildOverDescendantsNodeIterator\NodeTest;
use lyquidity\xml\MS\XmlQualifiedNameTest;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\AttributeNodeIterator;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\TrueValue;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getIsNonNumeric.php";
require_once "checkIsNotTuple.php";

/**
 * Return the unit whose id attribute value is equal to the item's unitRef attribute value.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @param bool $convertException (default: true) If true, exceptions will be converted to XPTY0004.
 * 												 If false, the exception will be passed to the caller
 * @return XPathNavigator
 *
 * This function has one real argument:
 *
 * item	schema-element(xbrli:item)	The item that the unit is required for.
 *
 */
function getUnit( $context, $provider, $args, $convertException = true )
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

	try
	{
		/**
		 * @var XPathNavigator $nav
		 */
		$nav = CoreFuncs::CastArg( $context, $arg, SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::Node, XmlTypeCardinality::One ) );

		// This should be an element System.Xml.XPathNavigator descendent
		if ( ! $nav instanceof XPathNavigator )
		{
			throw new \InvalidArgumentException();
		}

		checkIsNotTuple( $context, $nav );
		if ( getIsNonNumeric( $context, $provider, array( "{$nav->getPrefix()}:{$nav->getLocalName()}" ) ) instanceof TrueValue )
		{
			return EmptyIterator::$Shared;
		}

		// Look for the contextRef
		$nodeTest = XmlQualifiedNameTest::Create( "unitRef" );
		$attribs = AttributeNodeIterator::fromNodeTest( $context, $nodeTest, XPath2NodeIterator::Create( $nav ) );
		if ( $attribs->getCount() == 0 )
		{
			return EmptyIterator::$Shared;
		}

		$attribute = CoreFuncs::NodeValue( $attribs );
		if ( ! is_null( $attribute ) )
		{
			$unitRef = $attribute->getValue();

			// Must be valid
			if ( ! empty( $unitRef ) )
			{
				// Use native XPath 1.0 for this step because it is written in C and, so will be faster
				$xpath = new \DOMXPath( $attribute->getUnderlyingObject()->ownerDocument );
				$xpath->registerNamespace( "xbrli", \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] );
				$nodeList = $xpath->query( "//xbrli:unit[@id='$unitRef']" );
				if ( $nodeList && $nodeList->length )
				{
					$result = new DOMXPathNavigator( $nodeList[0] );
					return $result;
				}
			}
		}
	}
	catch ( \Exception $ex)
	{
		if ( ! $convertException )
		{
			throw $ex;
		}
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
