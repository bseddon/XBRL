<?php

/**
 * XBRL Formulas
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

namespace XBRL\Formulas\Resources\Formulas\Aspects;

use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\XPath2NodeIterator;

/**
 * A class to process a general variable definitions
 */
class TypedDimension extends Dimension
{
	/**
	 * The typed dimension name
	 * @var string $dimension
	 */
	public $dimension;

	/**
	 * The typed dimension value
	 * @var string $member
	 */
	public $member;

	/**
	 * An XPath expression to return a value
	 * @var string $xpath
	 */
	public $xpath;

	/**
	 * The dimension aspect to use when evaluate $source
	 * @var unknown
	 */
	public $aspectDimension;

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->combinable = true;
	}

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		// Get the relevant source for this aspect
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

			// The dimension MUST reference an explicit dimension
		$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $this->dimension['namespace'] );
		$element = $dimTaxonomy->getElementByName( $this->dimension['name'] );
		if ( ! isset( $element['typedDomainRef'] ) )
		{
			$log->formula_validation( "Typed dimension aspect rule", "The dimension MUST be typed",
				array(
					'dimension' => "{$this->dimension['originalPrefix']}:{$this->dimension['name']}",
					'error' => 'xbrlfe:badUsageOfTypedDimensionRule'
				)
			);
		}

		$value = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] )->value;
		if ( $value )
		{
			$domNode = dom_import_simplexml( $value );
			if ( $domNode->hasChildNodes() )
			{
				$domNode = $domNode->firstChild;
				while ( $domNode )
				{
					if ( $domNode->nodeType == XML_ELEMENT_NODE )
					{
						$name = $domNode->nodeName;
						$value = (string)$domNode->nodeValue;
						$this->member = array( $name => array( $value ) );
						$result['member'] = $this->member;
						break;
					}
					$domNode = $domNode->nextSibling;
				}
			}

		}
		else if ( is_null( $this->source ) )
		{
			$log->formula_validation( "Typed dimension aspect rule", "The typed dimension aspect rule is incomplete because it has no nearest source and there is no member value defined",
				array(
					'dimension' => "{$this->dimension['originalPrefix']}:{$this->dimension['name']}",
					'error' => 'xbrlfe:missingSAVForTypedDimensionRule'
				)
			);
		}

		$xpath = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] )->xpath;
		if ( $xpath )
		{
			$this->xpath = (string)$xpath;
			$result['xpath'] = $this->xpath;
		}

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}

	/**
	 * Get the typed dimension context information for this rule
	 * @param Formula $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return DOMXPathNavigator
	 */
	public function getValue( $variableSet, $evaluationResult, $log )
	{
		// This is an error
		if ( ! isset( $variableSet->context ) ) return array();

		// Get context for this rule's source
		$sourceContext = $variableSet->getComponentForPath( $this->source, $variableSet->typedDimensionsComponentPath, 'typedMember', $evaluationResult, $this->aspectDimension, $log, $contextRef );
		$sourceContext = Dimension::contextToIndex( $sourceContext, false, $variableSet, $this->aspectDimension );

		// Get original context
		$context = $variableSet->context;

		if ( ! $variableSet->xbrlInstance->getNamespaceForPrefix( $this->dimension['originalPrefix'] ) )
		{
			$this->dimension['originalPrefix'] = $variableSet->xbrlInstance->getPrefixForNamespace( $this->dimension['namespace'] );
		}
		$dimensionQName = "{$this->dimension['originalPrefix']}:{$this->dimension['name']}";

		if ( $this->omit )
		{
			// Omit always applies to the default context
			unset( $context[ $dimensionQName ] );
		}
		else if ( $this->xpath )
		{
			$vars = $evaluationResult['vars'];
			$result = $this->evaluateXPath( $variableSet, $this->xpath, $vars );
			$error = false;
			if ( $result instanceof XPath2NodeIterator && $result->getCount() == 1 )
			{
				$result->MoveNext();
				$node = $result->getCurrent()->CloneInstance();
				$parent = $node->CloneInstance();
				$parent->MoveToParent();
				if ( $parent->getLocalName() == 'typedMember' && $parent->getNamespaceURI() == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] )
				{
					$valueElement = $node->getName();
					$member = $node->getValue();
					$context[ $dimensionQName ] = array( $valueElement => array( "<$valueElement>$member</$valueElement>" ) );
				}
				else
				{
					$error = true;
				}
			}
			else
			{
				$error = true;
			}
			if ( $error )
			{
				$log->formula_validation( "Typed dimension aspect rule", "The xpath references an node that does not define a typed dimension or returns an ambiguous result",
					array(
						'xpath' => $this->xpath,
						'dimension' => $dimensionQName,
						'error' => 'xbrlfe:wrongXpathResultForTypedDimensionRule'
					)
				);
			}
		}
		else if ( $this->dimension )
		{
			if ( $this->member )
			{
				if ( isset( $context[ $dimensionQName ] ) )
				{
					foreach ( $this->member as $memberElementKey => $names )
					{
						if ( isset( $context[ $dimensionQName ][ $memberElementKey ] ) )
						{
							foreach ( $names as $nameKey => $name )
							{
								$element = "<$memberElementKey>$name</$memberElementKey>";

								if ( ! in_array( $element, $context[ $dimensionQName ][ $memberElementKey ] ) )
								{
									$context[ $dimensionQName ][ $memberElementKey ][] = $element;
								}
							}
						}
						else
						{
							$context[ $dimensionQName ][ $memberElementKey ] = $names;
						}
					}
				}
				else
				{
					$context[ $dimensionQName ] = $this->member;
				}
			}
			else
			{
				// If there is no member, grab the member from the context if the dimension exists
				if ( isset( $sourceContext[ $dimensionQName ] ) )
				{
					$context[ $dimensionQName ] = $sourceContext[ $dimensionQName ];
				}
			}
		}
		// else if ( isset( $sourceContext[ $dimensionQName ] ) )
		// {
		// 	// Add any members from the source
		// 	$context[ $dimensionQName ] = $sourceContext[ $dimensionQName ];
		// }
		else
		{
			$log->formula_validation( "Typed dimension aspect rule", "A dimension is being added but there are no members",
				array(
					'dimension' => $dimensionQName,
					'error' => 'xbrlfe:missingSAVForExplicitDimensionRule'
				)
			);
		}

		return $context;
	}

}
