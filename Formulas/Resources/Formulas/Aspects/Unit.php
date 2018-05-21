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

use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\FactValues;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\xml\QName;

/**
 * A class to represent unit aspect rule information
 */
class Unit extends Aspect
{
	/**
	 * Flag indicating whether the existing unit should be augmented (true) or replaced (false)
	 * @var string $augment
	 */
	public $augment = true; // True is the default condition

	/**
	 * A list of multiplication functions
	 * @var array $multiplyBy
	 */
	public $multiplyBy = array();

	/**
	 * A list of division functions
	 * @var array $divideBy
	 */
	public $divideBy = array();

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

		if ( property_exists( $attributes, 'augment' ) )
		{
			$this->augment = filter_var( $attributes->augment, FILTER_VALIDATE_BOOLEAN );
		}

		$unitChildren = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA] );
		if ( is_null( $this->source ) && ! count( $unitChildren ) )
		{
			$log->formula_validation( "Aspect rule", "The unit rule does not have any children but there is no source value",
				array(
					'error' => 'xbrlfe:missingSAVForUnitRule',
				)
			);

			return $result;
		}

		$result['augment'] = $this->augment;

		foreach ( $unitChildren as $child )
		{
			$source = $this->source;
			$measure = null;
			$childAttributes = $child->attributes();

			if ( property_exists( $childAttributes, 'source' ) )
			{
				$qName = strpos( trim( $childAttributes->source ), ":" )
					? qname( trim( $childAttributes->source ), $namespaces )
					: new QName( "", null, trim( $childAttributes->source ) );
				$source = array(
					'name' => is_null( $qName ) ? $source : $qName->localName,
					'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
					'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
				);
			}

			switch( $child->getName() )
			{
				case 'multiplyBy':

					$this->multiplyBy[] = array( 'source' => $source, 'measure' => (string)$childAttributes->measure );
					break;

				case 'divideBy':

					$this->divideBy[] = array( 'source' => $source, 'measure' => (string)$childAttributes->measure);
					break;
			}
		}

		$result['multiplyBy'] = $this->multiplyBy;
		$result['divideBy'] = $this->divideBy;

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}

	/**
	 * Returns an array of additional prefixes and namespaces used by measures defined
	 * @param Formula $variableSet
	 * @param array $evaluationResult
	 * @return array
	 */
	public function getAdditionalNamespaces( $variableSet, $evaluationResult )
	{
		$namespaces = array();

		foreach ( $this->multiplyBy as $numerator )
		{
			if ( ! isset( $numerator['measure'] ) || ! $numerator['measure'] ) continue;

			// This is an expression that needs evaluating
			$result = $this->evaluateXPath( $variableSet, "{$numerator['measure']}", $evaluationResult['vars'] );
			if ( $result instanceof XPath2Item )
			{
				$result = $result->getTypedValue();
			}
			// Should report an error here
			if ( ! $result instanceof QNameValue ) continue;
			$namespaces[ $result->Prefix ] = $result->NamespaceUri;
		}

		foreach ( $this->divideBy as $denominator )
		{
			if ( ! isset( $denominator['measure'] ) || ! $denominator['measure'] ) continue;

			// This is an expression that needs evaluating
			$result = $this->evaluateXPath( $variableSet, "{$denominator['measure']}", $evaluationResult['vars'] );
			if ( $result instanceof XPath2Item )
			{
				$result = $result->getTypedValue();
			}
			// Should report an error here
			if ( ! $result instanceof QNameValue ) continue;
			$namespaces[ $result->Prefix ] = $result->NamespaceUri;
		}

		return $namespaces;
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
		$numerators = array();
		$denominators = array();
		$unitRef = "";

		// Create a list of numerators and denominators
		if ( $this->augment )
		{
			$this->getFactorsFromSource( $this->source, $variableSet, $evaluationResult, $log, $numerators, $denominators, $unitRef );
		}

		foreach ( $this->multiplyBy as $numerator )
		{
			$hasMeasure = isset( $numerator['measure'] ) && $numerator['measure'];

			if ( ! $hasMeasure && isset( $numerator['source'] ) )
			{
				$this->getFactorsFromSource( $numerator['source'], $variableSet, $evaluationResult, $log, $numerators, $denominators );
			}

			if ( $hasMeasure )
			{
				// This is an expression that needs evaluating
				$result = $this->evaluateXPath( $variableSet, "{$numerator['measure']} cast as xs:string", $evaluationResult['vars'] );
				$numerators[] = $result->getValue();
			}
		}

		foreach ( $this->divideBy as $denominator )
		{
			$hasMeasure = isset( $denominator['measure'] ) && $denominator['measure'];

			if ( ! $hasMeasure && isset( $denominator['source'] ) )
			{
				$this->getFactorsFromSource( $denominator['source'], $variableSet, $evaluationResult, $log, $denominators, $numerators );
			}

			if ( $hasMeasure )
			{
				// This is an expression that needs evaluating
				$result = $this->evaluateXPath( $variableSet, "{$denominator['measure']} cast as xs:string", $evaluationResult['vars'] );
				$denominators[] = $result->getValue();
			}
		}

		// Cancel mutual numerators and denominators
		$this->cancel( $numerators, $denominators );

		// Return an array appropriately structured
		$result = array();
		if ( ! $denominators )
		{
			if ( count( $numerators ) == 1 )
			{
				$result = reset( $numerators );
			}
			else
			{
				$result = array( 'measures' => $numerators );
			}
		}
		else
		{
			$result = array(
				'divide' => array( 'numerator' => $numerators, 'denominator' => $denominators )
			);
		}

		if ( ! $unitRef ) $unitRef = "unit";
		return array( $unitRef => $result );
	}

	/**
	 * Cancel out the common items in the set of numerators and denominators
	 * @param array $numerators
	 * @param array $denominators
	 */
	private function cancel( &$numerators, &$denominators )
	{
		if ( ! $denominators ) return;

		foreach ( $numerators as $key => $numerator )
		{
			$found = array_search( $numerator, $denominators );
			if ( $found === false ) continue;
			unset( $numerators[ $key ] );
			unset( $denominators[ $found ] );
		}
	}

	/**
	 * Fill the numerators and denominators arrays from the specified source
	 * @param array $source An array containing the qname of the source to use
	 * @param Formula $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @param array $numerators List to populate with numerators
	 * @param array $denominators List to populate with denominators
	 * @param string The unitRef of the source (if there is one)
	 * @return null
	 * @throws An exception if the source is not valid
	 */
	private function getFactorsFromSource( $source, $variableSet, $evaluationResult, $log, &$numerators, &$denominators, &$unitRef = null )
	{
		$sourceFact = $variableSet->getSourceFactWithErrorLogging( $source, $evaluationResult, ASPECT_UNIT, $log );

		// If the rule is to augment the existing values fill the numerators and denominator arrays
		$unitRef = FactValues::getUnitRef( $sourceFact );
		if (! $unitRef ) return;

		$unit = $variableSet->xbrlInstance->getUnit( $unitRef );

		if ( is_array( $unit ) )
		{
			if ( isset( $unit['measures'] ) )
			{
				$numerators = array_merge( $numerators, $unit['measures'] );
			}

			if ( isset( $unit['divide'] ) )
			{
				if ( $unit['divide']['numerators'] )
				{
					$numerators = array_merge( $numerators, $unit['divide']['numerators'] );
				}

				if ( $unit['divide']['denominators'] )
				{
					$denominators = array_merge( $denominators, $unit['divide']['denominators'] );
				}
			}
		}
		else
		{
			$numerators[] = $unit;
		}
	}

}
