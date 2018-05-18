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

 namespace XBRL\Formulas\Resources\Formulas\Aspects;

use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\FactValues;

/**
 * A class to process a general variable definitions
 */
class EntityIdentifier extends Aspect
{
	/**
	 * The entity identifier value
	 * @var string $scheme
	 */
	public $scheme = null;

	/**
	 * The entity identifier value
	 * @var string $value
	 */
	public $value = null;

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
		$result = array();

		// Get the relevant source for this aspect
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$attributes = $node->attributes();

		if ( property_exists( $attributes, 'scheme' ) )
		{
			$this->scheme = (string)$attributes->scheme;
		}

		if ( property_exists( $attributes, 'value' ) )
		{
			$this->value = (string)$attributes->value;
		}

		if ( is_null( $this->source ) && ( is_null( $this->scheme ) || is_null( $this->value ) ) )
		{
			$message = is_null( $this->scheme ) && is_null( $this->value )
				? "scheme or value"
				: ( is_null( $this->scheme )
						? "scheme"
						: "value"
				  );

			$log->formula_validation( "Aspect rule", "There is no 'nearest source' and the entity identifier rule does not have a $message",
				array(
					'error' => 'xbrlfe:incompleteEntityIdentifierRule',
				)
			);
		}

		$result['scheme'] = $this->scheme;
		$result['value'] = $this->value;

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}

	/**
	 *
	 * @param Formula $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return array
	 */
	public function getValue( $variableSet, $evaluationResult, $log )
	{
		// Should already have been validated that if there is no source, there MUST be a complete entity identifier aspect is defined.
		if ( $this->source )
		{
			$sourceFact = $variableSet->getSourceFact( $this->source, $evaluationResult, ASPECT_ENTITY_IDENTIFIER, $log );
			if ( is_null( $sourceFact ) )
			{
				$source = is_array( $this->source ) ? "{$this->source['originalPrefix']}:{$this->source['name']}" : $this->source;
				$log->formula_validation( "Formula", "Invalid or missing source fact for entity identifier",
					array(
						'source' => $source,
						'error' => 'xbrlfe:undefinedSAV'
					)
				);
			}

			$contextRef = FactValues::getContextRef( $sourceFact );
			if ( ! $contextRef ) return null;
			$context = $variableSet->xbrlInstance->getContext( $contextRef );
			$identifier = isset( $context['entity']['identifier'] )
				? $context['entity']['identifier']
				: array();
		}

		if ( $this->scheme )
		{
			$result = $this->evaluateXPath( $variableSet, "{$this->scheme} cast as xs:string", $evaluationResult['vars'] );
			$identifier['scheme'] = is_object( $result ) ? $result->getValue() : $result;
		}

		if ( $this->value )
		{
			$result = $this->evaluateXPath( $variableSet, "({$this->value}) cast as xs:string", $evaluationResult['vars'] );
			$identifier['value'] = is_object( $result ) ? $result->getValue() : $result;
		}

		return $identifier;
	}
}
