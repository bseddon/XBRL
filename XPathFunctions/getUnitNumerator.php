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

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns either the measure elements that occur as children of the unitNumerator descendant of the unit in the case
 * where a divide element occurs; otherwise returns the measure children of the unit.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPath2NodeIterator
 *
 * This function has one real argument:
 *
 * unit	element(xbrli:unit)	The unit for which to obtain the numerator.
 *
 */
function getUnitNumerator( $context, $provider, $args )
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

		if ( $nav->getLocalName() != "unit" || $nav->getNamespaceURI() != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
		{
			throw new \InvalidArgumentException();
		}

		$call = ".//measure[not(ancestor::unitDenominator)]";
		/**
		 * @var XPath2Expression $expression
		 */
		$provider2 = new NodeProvider( $nav );
		$expression = XPath2Expression::Compile( $call, $context->NamespaceManager );
		$result = $expression->EvaluateWithVars( $provider2, array() );
		return $result;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
