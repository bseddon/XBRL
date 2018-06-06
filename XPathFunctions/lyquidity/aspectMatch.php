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

namespace XBRL\functions\lyquidity;

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\Resources\Variables\FactVariable;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\XPath2NodeIterator\SingleIterator;
use lyquidity\xml\xpath\XPathNodeIterator;
use XBRL\Formulas\FactValues;
use XBRL\Formulas\Resources\Filters\RelativeFilter;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once __DIR__ . "/../checkIsItem.php";

/**
 * Return true if the fact in $args matches the comparison fact for the requested aspect
 * This function is used by the Match* filter functions
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPathNavigator
 *
 * This function has five real arguments:
 *
 * factVariable	FactVariable		The fact variable being processed
 * formula		Formula				The formula (variable set) being processed
 * fact			DOMXPathNavigator	The node being tested
 * aspectFact	DOMXPathNavigator	The node against which fact is being tested
 * aspect		string+				The name(s) of the aspect(s) being tested
 * relative		bool				True if this is a boolean (may not exist if a match test)
 */
function aspectMatch( $context, $provider, $args )
{
	if ( count( $args ) < 5 )
	{
		throw XPath2Exception::withErrorCodeAndParams( "GeneralXFIFailure", Resources::GeneralXFIFailure,
			array(
				"lyquidity",
				"aspectMatch(5)",
				\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ],
			)
		);
	}

	if ( is_null( $args[0] ) || ! $args[0] instanceof FactVariable )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
			array(
				is_object( $args[0] ) ? get_class( $args[0] ) : gettype( $args[0] ),
				'FactVariable'
			)
		);
	}

	/**
	 * The fact variable class containing the function
	 * @var FactVariable $factVariable
	 */
	$factVariable = $args[0];

	if ( is_null( $args[1] ) || ! $args[1] instanceof Formula )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
			array(
				is_object( $args[1] ) ? get_class( $args[1] ) : gettype( $args[1] ),
				'Formula'
			)
		);
	}

	/**
	 * The variable set to pass to the function
	 * @var Formula $formula
	 */
	$formula = $args[1];

	if ( is_null( $args[2] ) || ! $args[2] instanceof DOMXPathNavigator )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
			array(
				is_object( $args[2] ) ? get_class( $args[2] ) : gettype( $args[2] ),
				'DOMXPathNavigator'
			)
		);
	}

	/**
	 * The fact to be tested
	 * @var DOMXPathNavigator $fact
	 */
	$fact = $args[2];

	if ( is_null( $args[3] ) )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
			array(
				is_object( $args[3] ) ? get_class( $args[3] ) : gettype( $args[3] ),
				'DOMXPathNavigator'
			)
		);
	}
	else if ( $args[3] instanceof DOMXPathNavigator )
	{
		$args[3] = new SingleIterator( $args[3] );
	}
	else if ( $args[3] instanceof XPath2NodeIterator )
	{
		// Do nothing
	}
	else
	{
		// This will be a fallback value.  A fallback value will not match with a fact.
		return false;
	}

	/**
	 * The fact $fact is to be compared with
	 * @var DOMXPathNavigator|XPathNodeIterator $aspectFacts
	 */
	$aspectFacts = $args[3];

	if ( $args[4] instanceof XPath2Item )
	{
		$args[4] = SingleIterator::Create( $args[4] );
	}
	else if ( ! $args[4] instanceof XPath2NodeIterator )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
			array(
				is_object( $args[4] ) ? get_class( $args[4] ) : gettype( $args[4] ),
				'string or string+'
			)
		);
	}

	/**
	 * This will be true when the function is called in response to a relativeFilter instance
	 * @var bool $relative
	 */
	$relative = false;

	if ( isset( $args[5] ) && $args[5] instanceof XPath2Item )
	{
		$relative = $args[5]->getValue() == RelativeFilter::class;
	}

	$aspectItems = $args[4];

	foreach ( $aspectItems as $aspectItem )
	{
		if ( ! is_string( $aspectItem ) && ! ( $aspectItem instanceof XPath2Item && $aspectItem->getSchemaType()->TypeCode == XmlTypeCode::String ) )
		{
			throw XPath2Exception::withErrorCodeAndParams( "XPTY0004", Resources::XPTY0004,
				array(
					is_object( $args[4] ) ? get_class( $args[4] ) : gettype( $args[4] ),
					'string'
				)
			);
		}

		/**
		 * The name of the aspect to test
		 * @var string $aspect
		 */
		$aspect = $aspectItem instanceof XPath2Item ? $aspectItem->getTypedValue() : $aspectItem;

		// If the aspect is a dimension reference, make sure the fact make sure if is one of the fact's context dimension or has a default
		if ( $aspect[0] != "\\" )
		{
			$contextRef = FactValues::getContextRef( $fact );
			/**
			 * @var \XBRL_Instance $instance
			 */
			$instance = $context->xbrlInstance;
			$qname = qname( $aspect );
			$prefix = $instance->getPrefixForDocumentNamespace( $qname->namespaceURI );
			// Check if $aspect is a valid fact dimension or a default
			$dimension = "{$prefix}:{$qname->localName}";
			// BMS 2018-02-25 This works for 47212 V-05 and V-06
			//				  What about 49210 (RelativeMatch)?
			if ( $relative )
			{
				if ( ! $instance->hasDimension( $contextRef, $dimension ) ) continue;
			}
			else
			{
				if ( ! $instance->hasDimension( $contextRef, $dimension ) && $instance->hasDefaultDimensionMember( $dimension ) ) continue;
			}
		}

		// The results must all be true or all be false. A mixed result means that:
		// 	 a) the aspect fact is a sequence; and
		//   b) some of the values match while others do not.
		// This means an xbrlmfe:inconsistentMatchedVariableSequence error should be thrown
		$results = array( false => 0, true => 0 );

		foreach ( $aspectFacts as $aspectFact )
		{
			$result = $factVariable->lyquidityAspectMatch( $formula, $fact, $aspectFact, $aspect );
			$results[ $result ]++;
		}

		if ( $results[false] && $results[true] )
		{
			\XBRL_Log::getInstance()->formula_validation( "Match filter", "Mismatched filter values",
				array(
					'aspect filter' => $aspect,
					'error' => 'xbrlmfe:inconsistentMatchedVariableSequence',
				) );
		}

		if ( $results[false] ) return CoreFuncs::$False;

	}

	return CoreFuncs::$True;
}
