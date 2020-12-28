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

use XBRL\Formulas\FactValues;
use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\DOM\DOMXPathNavigator;

/**
 * A class to process a general variable definitions
 */
class OCCXPath extends OCCEmpty
{
	/**
	 * A select expression
	 * @var string $select
	 */
	public $select = "";

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

		$attributes = $node->attributes();
		if ( property_exists( $attributes, 'select' ) )
		{
			$this->select = $attributes->select;
			$result['select'] = $this->select;
		}

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}

	/**
	 * Get the concept aspect value
	 * @param Formula $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return DOMXPathNavigator
	 */
	public function getValue( $variableSet, $evaluationResult, $log )
	{
		$aspect = $variableSet->aspectModel == 'dimensional'
			? ( $this->occ == 'segment' ? ASPECT_NON_XDT_SEGMENT : ASPECT_NON_XDT_SCENARIO )
			: ( $this->occ == 'segment' ? ASPECT_COMPLETE_SEGMENT : ASPECT_COMPLETE_SCENARIO );

		$sourceFact = $variableSet->getSourceFact( $this->source, $evaluationResult, $aspect, $log );
		if ( ! $sourceFact ) return null;
		$contextRef = FactValues::getContextRef( $sourceFact );
		if ( ! $contextRef ) return null;
		$context = $variableSet->xbrlInstance->getContext( $contextRef );
		// $component = isset( $context['entity'][ $this->occ ]['member'] )
		// 	? $context['entity'][ $this->occ ]['member']
		// 	: array();
		$components = array();
		if ( ! $this->select ) return $components;

		$componentNodes = $this->evaluateXPath( $variableSet, "{$this->select}", array( $this->getSourceName() => $sourceFact ) );

		foreach ( $componentNodes as $node )
		{
			if ( ( $this->occ == 'scenario' || $this->occ == 'segment' ) &&
				 $node->getNamespaceURI() == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI] )
			{
				$log->formula_validation( "OCC XPath", "The result of the XPath query is not consistent with ",
					array(
						'occ' => $this->occ,
						'xpath' => $this->select,
						'error' => 'xbrlfe:badSubsequentOCCValue'
					)
				);
			}
			$components[] = array( 'name' => $node->getName(), 'member' => '' );
		}

		return $components;
	}
}
