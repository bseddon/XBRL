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

/**
 * A class to process a general variable definitions
 */
class Period extends Aspect
{
	/**
	 * The value for the date if required
	 * @var string $value
	 */
	public $instant;

	/**
	 * The value for the date if required for a duration rav
	 * @var string $start
	 */
	public $start;

	/**
	 * The value for the end date if required for a duration rav
	 * @var string $value
	 */
	public $end;

	/**
	 * The result aspect value to use: 'instant', 'duration', 'forever'
	 * @var string $rav
	 */
	public $rav;

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

		$periodChildren = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA] );
		if ( is_null( $this->source ) && ! count( $periodChildren ) )
		{
			$log->formula_validation( "Aspect rule", "The period rule does not have any children but there is no source value",
				array(
					'error' => 'xbrlfe:incompletePeriodRule',
				)
			);

			return $result;
		}

		if ( count( $periodChildren ) )
		{
			if ( property_exists( $periodChildren, 'forever' )  )
			{
				$this->rav = 'forever';
			}
			else if ( property_exists( $periodChildren, 'instant' )  )
			{
				$this->rav = 'instant';
				if ( property_exists( $periodChildren, 'instant' ) )
				{
					$instantValue = $periodChildren->instant->attributes();
					if ( property_exists( $instantValue, 'value') )
					{
						$this->instant = (string)$instantValue->value;
					}
				}
			}
			else if ( property_exists( $periodChildren, 'duration' )  )
			{
				$this->rav = 'duration';
				$attributes = $periodChildren->attributes();
				if ( property_exists( $attributes, 'start' ) )
				{
					$this->start = (string)$attributes->start;
				}
				if ( property_exists( $attributes, 'end' ) )
				{
					$this->end = (string)$attributes->end;
				}
			}
		}
		else
		{
			$result['rav'] = 'source';
		}

		$result['rav'] = $this->rav;
		if ( ! is_null( $this->instant ) )
		{
			$result['instant'] = $this->instant;
		}

		if ( ! is_null( $this->start ) )
		{
			$result['start'] = $this->start;
		}

		if ( ! is_null( $this->end ) )
		{
			$result['end'] = $this->end;
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
		// Should already have been validated that if there is no source, there MUST be a complete period aspect is defined.
		if ( $this->source )
		{
			$sourceFact = $variableSet->getSourceFactWithErrorLogging( $this->source, $evaluationResult, ASPECT_PERIOD, $log );
			$contextRef = FactValues::getContextRef( $sourceFact );
			if ( ! $contextRef ) return null;
			$context = $variableSet->xbrlInstance->getContext( $contextRef );
			$period = isset( $context['period'] )
				? $context['period']
				: array();
		}

		switch( $this->rav )
		{
			case 'instant':

				// BMS 2018-03-23 Added the 'minimized-date-string' function to XPath and updated the query to use it
				//				  The purpose is to use short dates where possible to increase consistency and make
				//				  conformance comparisons more reliable.  See 0023 V-04
				// $start = $this->evaluateXPath( $variableSet, "({$this->start}) cast as xs:string", $evaluationResult['vars'] );
				// $instant = $this->evaluateXPath( $variableSet, "({$this->instant}) cast as xs:string", $evaluationResult['vars'] );
				$instant = $this->evaluateXPath( $variableSet, "lyquidity:minimized-date-string({$this->instant}, true()) cast as xs:string", $evaluationResult['vars'] );
				$period = array(
					'is_instant' => true,
					'type' => $this->rav,
					'startDate' => $instant->getValue(),
					'endDate' => $instant->getValue(),
				);

				break;

			case 'forever':

				$period = array(
					'is_instant' => false,
					'type' => $this->rav,
				);

				break;

			case 'duration':

				// BMS 2018-03-23 Added the 'minimized-date-string' function to XPath and updated the query to use it
				//				  The purpose is to use short dates where possible to increase consistency and make
				//				  conformance comparisons more reliable.  See 60500 V-04
				// $start = $this->evaluateXPath( $variableSet, "({$this->start}) cast as xs:string", $evaluationResult['vars'] );
				$start = $this->evaluateXPath( $variableSet, "lyquidity:minimized-date-string({$this->start}, false()) cast as xs:string", $evaluationResult['vars'] );
				// $end = $this->evaluateXPath( $variableSet, "({$this->end}) cast as xs:string", $evaluationResult['vars'] );
				$end = $this->evaluateXPath( $variableSet, "lyquidity:minimized-date-string({$this->end},true())", $evaluationResult['vars'] );

				$period = array(
					'is_instant' => false,
					'type' => $this->rav,
					'startDate' => $start->getValue(),
					'endDate' => $end->getValue(),
				);

				break;
		}

		return $period;
	}
}
