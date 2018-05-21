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
use lyquidity\xml\xpath\XPathNodeType;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "getSegment.php";
require_once "getScenario.php";
require_once "xbrlInstance.php";

/**
 * Tests whether the fact reports a value for a dimension in either the segment or scenario of the supplied item.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:boolean	Returns true if the fact reports a value for the given dimension in either segment, scenario,
 * 						or as a default dimension member. This function does not raise an exception if the dimension
 * 						or member does not apply to the fact, but instead returns false if so. A dimension is considered
 * 						reported when an explicit dimension reportable for the fact is absent from its context, and the
 * 						default value pertains (either due to a closed hypercube specifying the dimension for the fact's
 * 						primary item or due to the primary item having open hypercube).
 *
 * @throws xfie:invalidExplicitDimensionQName	This error MUST be thrown the dimension QName is not a explicit dimension
 * 												in the reference discoverable taxonomy set. This error is only raised if
 * 												the QName is not that of a dimension. If the dimension is valid and inapplicable
 * 												to the fact, then the fact does not have that dimension, and false is returned.
 * This function has two real arguments:
 *
 * item			schema-element(xbrli:item)	The item that the dimension is to be reported for.
 * dimension	xs:QName					The QName of the dimension being tested for.
 *
 */
function getFactHasExplicitDimension( $context, $provider, $args )
{
	try
	{
		if ( ( ! $args[0] instanceof XPath2NodeIterator && ! $args[0] instanceof DOMXPathNavigator ) || ! $args[1] instanceof QNameValue )
		{
			throw new \InvalidArgumentException();
		}

		$fact = null;

		if ( $args[0] instanceof XPath2NodeIterator )
		{
			if ( $args[0]->getCount() != 1 )
			{
				throw new \InvalidArgumentException( "There can only be one fact element" );
			}

			if ( ! $args[0]->MoveNext() )
			{
				return CoreFuncs::$False;
			}

			$fact = $args[0]->getCurrent()->CloneInstance();
		}
		else
		{
			$fact = $args[0]->CloneInstance();
		}

		/**
		 * @var \XBRL_Instance $instance
		 */
		$instance = $context->xbrlInstance;
		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;

		// Check the dimension supplied is valid
		$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $args[1]->NamespaceUri );
		if ( ! $dimTaxonomy )
		{
			throw XPath2Exception::withErrorCode( "xfie:invalidExplicitDimensionQName", "The dimension must exist in the member network" );
		}

		// The dimension reference MUST be valid
		$dimElement = $dimTaxonomy->getElementByName( $args[1]->LocalName );
		if ( ! $dimElement || $dimElement['substitutionGroup'] != "xbrldt:dimensionItem" )
		{
			throw XPath2Exception::withErrorCode( "xfie:invalidExplicitDimensionQName", "The dimension must exist in the member network" );
		}

		// Does the dimension have a default member?
		$key = "{$dimTaxonomy->getTaxonomyXSD()}#{$dimElement['id']}";
		if ( isset( $dimTaxonomy->context->dimensionDefaults[ $key ] ) )
		{
			return CoreFuncs::$True;
		}

		/**
		 * @var XPath2NodeIterator $segment
		 */
		$segment = getSegment( $context, $provider, array( XPath2NodeIterator::Create( $fact ) ) );

		while ( $segment->MoveNext( XPathNodeType::Element ) )
		{
			$members = $segment->getCurrent()->CloneInstance();
			if ( ! $members->MoveToChild( XPathNodeType::Element ) )
			{
				break;
			}

			do
			{
				// Check to see if this is an explicit dimension
				if ( $members->getLocalName() == "explicitMember" &&
					 $members->getNamespaceURI() == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] )
				{
					return CoreFuncs::$True;
				}
			} while ( $members->MoveToNext( XPathNodeType::Element ) );
		}

		// Maybe the scenario
		/**
		 * @var XPath2NodeIterator $scenario
		 */
		$scenario = getScenario( $context, $provider, array( XPath2NodeIterator::Create( $fact ) ) );

		while ( $scenario->MoveNext( XPathNodeType::Element ) )
		{
			$members = $scenario->getCurrent()->CloneInstance();
			if ( ! $members->MoveToChild( XPathNodeType::Element ) )
			{
				break;
			}

			do
			{
				// Check to see if this is an explicit dimension
				if ( $members->getLocalName() == "explicitMember" &&
					 $members->getNamespaceURI() == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] )
				{
					return CoreFuncs::$True;
				}
			} while ( $members->MoveToNext( XPathNodeType::Element ) );
		}

		return CoreFuncs::$False;

	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidExplicitDimensionQName" )
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
