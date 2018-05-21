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

/**
 * A class to process a general variable definitions
 */
class ExplicitDimension extends Dimension
{
	/**
	 * A member qname
	 * @var array $member
	 */
	public $member = array();

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
		if ( isset( $element['typedDomainRef'] ) )
		{
			$log->formula_validation( "Explicit dimension aspect rule", "The dimension MUST be explicit",
				array(
					'dimension' => "{$this->dimension['originalPrefix']}:{$this->dimension['name']}",
					'error' => 'xbrlfe:badUsageOfExplicitDimensionRule'
				)
			);
		}

		$member = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] )->member;
		if ( $member )
		{
			$mem = array();

			if ( property_exists( $member, "qname" ) )
			{
				$mem["qname"] = trim( $member->qname );
			}
			else if ( property_exists( $member, "qnameExpression" ) )
			{
				$mem["qnameExpression"] = trim( $member->qnameExpression );
			}

			$this->member = $mem;
		}
		else if ( is_null( $this->source ) )
		{
			$log->formula_validation( "Explicit dimension aspect rule", "The explicit dimension aspect rule is incomplete because it has no nearest source and there are no members defined",
				array(
					'dimension' => "{$this->dimension['originalPrefix']}:{$this->dimension['name']}",
					'error' => 'xbrlfe:missingSAVForExplicitDimensionRule'
				)
			);
		}

		$result['member'] = $this->member;

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}

	/**
	 * Get the explicity dimension context information for this rule
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
		$sourceContext = $variableSet->getComponentForPath( $this->source, $variableSet->explicitDimensionsComponentPath, 'explicitMember', $evaluationResult, $this->aspectDimension, $log, $contextRef );
		$sourceContext = Dimension::contextToIndex( $sourceContext, true, $variableSet, $this->aspectDimension );

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
		else if ( $this->member )
		{
			// If a member is defined then the dimension/member is being added or updated
			if ( isset( $this->member['qname'] ) )
			{
				// Make sure the prefix is compatible with the instance document
				$qname = qname( $this->member['qname'], $variableSet->nsMgr->getNamespaces() );
				if ( ! $variableSet->xbrlInstance->getNamespaceForPrefix( $qname->prefix ) )
				{
					$qname->prefix = $variableSet->xbrlInstance->getPrefixForNamespace( $qname->namespaceURI );
				}
				$name = "{$qname->prefix}:{$qname->localName}";
				$context[ $dimensionQName ] = array( $name => $name );
			}
			else if ( $this->member['qnameExpression'] )
			{
				$vars = $evaluationResult['vars'];
				$result = $this->evaluateXPath( $variableSet, "{$this->member['qnameExpression']} cast as xs:string", $vars );

				$qname = qname( $result->getValue(), $variableSet->nsMgr->getNamespaces() );
				if ( ! $variableSet->xbrlInstance->getNamespaceForPrefix( $qname->prefix ) )
				{
					$qname->prefix = $variableSet->xbrlInstance->getPrefixForNamespace( $qname->namespaceURI );
				}
				$name = "{$qname->prefix}:{$qname->localName}";

				$context[ $dimensionQName ] = array( $name => $name );
			}
		}
		else if ( isset( $sourceContext[ $dimensionQName ] ) )
		{
			// Add any members from the source
			$context[ $dimensionQName ] = $sourceContext[ $dimensionQName ];
		}
		else
		{
			$log->formula_validation( "Explicit dimension aspect rule", "A dimension is being added but there are no members",
				array(
					'dimension' => $dimensionQName,
					'error' => 'xbrlfe:missingSAVForExplicitDimensionRule'
				)
			);
		}

		return $context;
	}
}
