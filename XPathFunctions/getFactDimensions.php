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

use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\xml\xpath\XPathNodeType;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\XPath2\DOM\DOMXPathNavigator;

// Make sure any required functions are imported
require_once "getSegment.php";
require_once "getScenario.php";
require_once "getFactExplicitDimensions.php";
require_once "getFactTypedDimensions.php";
require_once "xbrlInstance.php";

/**
 * A core function supporting getFactExplicitDimensions and getFactTypedDimensions
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @param bool $typed
 * @return array An array of dimension members indexed by their dimension
 */
function getFactDimensions( $context, $provider, $args, $typed = null )
{
	try
	{
		if ( ! $args[0] instanceof XPath2NodeIterator && ! $args[0] instanceof DOMXPathNavigator  )
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
				return EmptyIterator::$Shared;
			}

			/**
			 * @var XPathNavigator $fact
			 */
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

		// The primary item must be valid
		$piElement = $taxonomy->getElementByName( $fact->getLocalName() );
		if ( ! $piElement )
		{
			throw new \InvalidArgumentException( "The fact schema element cannot be found" );
		}
		else if ( $piElement['substitutionGroup'] != \XBRL_Constants::$xbrliItem )
		{
			return EmptyIterator::$Shared;
		}

		$primaryItemName = "{$taxonomy->getTaxonomyXSD()}#{$piElement['id']}";

		$primaryItems = $taxonomy->getDefinitionPrimaryItems( false );
		if ( ! isset( $primaryItems[ $primaryItemName ] ) )
		{
			return EmptyIterator::$Shared;
		}

		$test = function( $item, $typed ) use ( &$context, &$taxonomy )
		{
			$result = array();

			/**
			 * @var XPath2NodeIterator $item
			 */
			while ( $item->MoveNext( XPathNodeType::Element ) )
			{
				/**
				 * @var XPathNavigator $member
				 */
				$member = $item->getCurrent()->CloneInstance();
				if ( ! $member->MoveToChild( XPathNodeType::Element ) )
				{
					break;
				}

				do
				{
					// Check to see if this is an explicit dimension
					if ( (
							( ! $typed && $member->getLocalName() == "explicitMember" ) ||
						 	( $typed && $member->getLocalName() == "typedMember" )
						  ) &&
						  $member->getNamespaceURI() == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] &&
						  $member->getHasAttributes()
					)
					{
						// Check the dimension
						$attributes = $member->CloneInstance();
						if ( ! $attributes->MoveToAttribute( "dimension", null ) )
						{
							break 2;
						}

						/**
						 * @var QNameValue $qn
						 */
						$qn = QNameValue::fromNCName( $attributes->getValue(), $context->NamespaceManager );

						// Check the dimension supplied is valid
						$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $qn->NamespaceUri );
						if ( ! $dimTaxonomy )
						{
							continue;
						}

						// The dimension reference MUST be valid
						$dimElement = $dimTaxonomy->getElementByName( $qn->LocalName );
						if ( ! $dimElement )
						{
							continue;
						}

						$dimensionName = "{$dimTaxonomy->getTaxonomyXSD()}#{$dimElement['id']}";

						$result[ $dimensionName ] = array( 'qname' => $qn, 'value' => $typed ? $member->CloneInstance() : $member->getValue(), 'typed' => $typed, 'default' => false );
					}
				} while ( $member->MoveToNext( XPathNodeType::Element ) );
			}

			return $result;
		};

		/**
		 * @var XPath2NodeIterator $segment
		 */
		$segment = getSegment( $context, $provider, array( XPath2NodeIterator::Create( $fact ) ) );

		$result = $test( $segment, $typed );
		if ( ! count( $result ) )
		{
			// Maybe the scenario
			/**
			 * @var XPath2NodeIterator $scenario
			 */
			$scenario = getScenario( $context, $provider, array( XPath2NodeIterator::Create( $fact ) ) );

			$result1 = $test( $scenario, $typed );
			if ( count( $result1 ) )
			{
				$result = array_merge( $result, $result1 );
			}
		}

		if ( ! $typed )
		{

			// Add any default dimensions that apply
			// Obtain the DRS
			$drs = $taxonomy->getPrimaryItemDRS( $primaryItems[ $primaryItemName ] );

			foreach ( $drs as $hypercubeId => $roles )
			{
				foreach ( $roles as $roleId => $hypercube )
				{
					// Look at the dimensions
					foreach ( $hypercube['dimensions'] as $dimensionId => $dimension )
					{
						if ( isset( $dimension['default'] ) && ! isset( $result[ $dimensionId ] ) )
						{
							$dimTaxonomy = $taxonomy->getTaxonomyForXSD( $dimensionId );
							if ( ! $dimTaxonomy )
							{
								continue;
							}

							$dimElementId = ltrim( strstr( $dimensionId, "#"), "#" );
							$dimElement = $dimTaxonomy->getElementById( $dimElementId );

							$memTaxonomy = $taxonomy->getTaxonomyForXSD( $dimension['default']['label'] );
							if ( ! $memTaxonomy )
							{
								continue;
							}

							$dimensionName = "{$dimTaxonomy->getTaxonomyXSD()}#{$dimElement['id']}";

							$memElementId = ltrim( strstr( $dimension['default']['label'], "#"), "#" );
							$memElement = $memTaxonomy->getElementById( $memElementId );

							/**
							 * @var QNameValue $qnDim
							 */
							$qnDim = QNameValue::fromNCName( "{$dimTaxonomy->getPrefix()}:{$dimElement['name']}", $context->NamespaceManager );

							/**
							 * @var QNameValue $qnMem
							 */
							$qnMem = QNameValue::fromNCName( "{$memTaxonomy->getPrefix()}:{$memElement['name']}", $context->NamespaceManager );

							$result[ $dimensionName ] = array( 'qname' => $qnDim, 'value' => (string)$qnMem, 'typed' => $typed, 'default' => true );
						}
					}
				}
			}

		}

		return $result;

	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidTypedDimensionQName" )
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
