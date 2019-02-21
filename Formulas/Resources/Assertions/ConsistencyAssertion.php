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

namespace XBRL\Formulas\Resources\Assertions;

$utiltiesPath = isset( $_ENV['UTILITY_LIBRARY_PATH'] )
	? $_ENV['UTILITY_LIBRARY_PATH']
	: ( defined( 'UTILITY_LIBRARY_PATH' ) ? UTILITY_LIBRARY_PATH : __DIR__ . "/../../../../utilities/" );
require_once $utiltiesPath . "tuple-dictionary.php";

use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\FactValues;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\XPath2Item;

 /**
  * A class to process a formula definitions
  * http://www.xbrl.org/Specification/consistencyAssertions/REC-2009-06-22/consistencyAssertions-REC-2009-06-22.html#sec-processing-model
  */
class ConsistencyAssertion extends AssertionSet
{
	/**
	 * (Required) Flag to indicate influences which derived facts can be used to test a consistency assertion.
	 * @var boolean $strict
	 */
	public $strict = false;

	/**
	 * The acceptance radius of a consistency assertion is a number that represents the maximum
	 * difference between the numerical value of two facts for them to be considered consistent.
	 * @var string $absoluteAcceptanceRadius
	 */
	public $absoluteAcceptanceRadius;

	/**
	 * A list of formulas used by this assertion
	 * @var array $formulas
	 */
	public $formulas = array();

	/**
	 * The acceptance radius of a consistency assertion is a number that represents the maximum
	 * difference between the numerical value of two facts for them to be considered consistent.
	 * Alternatively, the acceptance radius can be defined as a proportion of the derived fact value.
	 * @var string $proportionalAcceptanceRadius
	 */
	public $proportionalAcceptanceRadius;

	/**
	 * A list of satified evaluation results
	 * @var int $satisfied
	 */
	private $satisfied = 0;

	/**
	 * A list of unsatified evaluation results
	 * @var int $unsatisfied
	 */
	private $unsatisfied = 0;

	/**
	 * A count of the evaluations that have derived facts
	 * @var integer $pastEvaluationSatisfied
	 */
	public $pastEvaluationSatisfied = 0;

	/**
	 * A count of the evaluations that have derived facts
	 * @var integer $pastEvaluationSatisfied
	 */
	public $pastEvaluationUnsatisfied = 0;

	/**
	 * A count of the evaluations that have unique input fact combinations
	 * @var integer $uniqueInputFactSatisfied
	 */
	public $uniqueInputFactSatisfied = 0;

	/**
	 * A count of the evaluations that have unique input fact combinations
	 * @var integer $uniqueInputFactUnsatisfied
	 */
	public $uniqueInputFactUnsatisfied = 0;

	/**
	 * A list of any parameters assigned to the assertion
	 * @var array $parameters
	 */
	public $parameters = array();

	/**
	 * A list of facts indexed by a unique evaluation result id that match a derived fact
	 * @var array
	 */
	public $aspectMatchedInputFacts = array();

	/**
	 * The value of an id attribute if one exists.  The attribute is read by the Resources class
	 * @var unknown
	 */
	public $id;

	/**
	 * Cached value of the evaluated radius
	 * @var float $radiusValue
	 */
	private $radiusValue = null;

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->testRequired = true;
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
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );
		$result['assertionsetType'] = 'consistencyAssertion';

		$attributes = $node->attributes();

		if ( ! property_exists( $attributes, 'strict' ) )
		{
			$log->consistencyassertion_validation( "Consistency assertion", "There is no strict attribute in the consistency assertion element", array(
				'roleuri' => $roleUri,
				'label' => $label,
				'localname' => $localName,
			) );
		}
		else
		{
			$this->strict = filter_var( $attributes->strict, FILTER_VALIDATE_BOOLEAN);
			$result['strict'] = $this->strict;
		}

		if ( property_exists( $attributes, 'absoluteAcceptanceRadius' ) )
		{
			$this->absoluteAcceptanceRadius = trim( $attributes->absoluteAcceptanceRadius );
			$result['absoluteAcceptanceRadius'] = $this->absoluteAcceptanceRadius;
		}

		if ( property_exists( $attributes, 'proportionalAcceptanceRadius' ) )
		{
			$this->proportionalAcceptanceRadius = trim( $attributes->proportionalAcceptanceRadius );
			$result['proportionalAcceptanceRadius'] = $this->proportionalAcceptanceRadius;
		}

		if ( isset( $result['absoluteAcceptanceRadius'] ) && isset( $result['proportionalAcceptanceRadius'] ) )
		{
			$log->formula_validation( "Consistency assertion", "The consistency assertion contains both absoluteAcceptanceRadius and proportionalAcceptanceRadius definitions",
				array( 'error' => 'xbrlcae:acceptanceRadiusConflict' )
			);
		}

		return $result;
	}

	/**
	 * Provide a list of arcroles that are valid this resource
	 */
	public function getDescendantArcroles()
	{
		return array( \XBRL_Constants::$arcRoleAssertionConsistencyFormula );
	}

	/**
	 * Return the cached computed radius value
	 * @return float
	 */
	public function getRadiusValue()
	{
		return $this->radiusValue;
	}

	/**
	 * Return the count of the number of satified instances
	 * @return float
	 */
	public function getSatisfied()
	{
		return $this->satisfied;
	}

	/**
	 * Return the count of the number of unsatified instances
	 * @return float
	 */
	public function getUnsatisfied()
	{
		return $this->unsatisfied;
	}

	/**
	 * Give the variable set instance an opportunity to process the facts
	 */
	public function evaluateResult()
	{
		echo "Process consistency assertion";
	}

	/**
	 * A list of hash of each past evaluation. This hash is of all the $derivedFact array elements.
	 * @var \TupleDictionary $pastEvaluationHashes
	 */
	private $pastEvaluationHashes = null;

	/**
	 * A list of hash of the input facts of each past evaluation. This hash uses the position of each input fact
	 * but excludes
	 * @var \TupleDictionary $pastEvaluationHashes
	 */
	private $uniqueInputFactHashes = null;

	/**
	 * Performs the consistency check for a fact
	 * @param Formula $formula
	 * @param array $derivedFact [concept(QName), contextRef(string), value(mixed), decimals(int), precision(int), unitRef(string)]
	 * @param array $vars An array of input facts indexed by the clarkname of the respective variable
	 * @param string $key The unique id used to reference the evaluation result
	 * @param \XBRL_Log $log
	 */
	public function checkFactConsistency( $formula, $derivedFact, $vars, $key, $log )
	{
		// Although I cannot find any reference to it in the documentation, it seems that only unique derived facts
		// should be compared.  Uniquenesss in this case appears to be derived facts that have the same attributes and values.
		// Conformance test 34120-DifferentEvaluationAssertion-Processing V-02 has this issue.  In this test there are 6 facts
		// produced but 2 are duplicates.
		// The TupleDictionary works well for this scenario
		if ( ! $this->pastEvaluationHashes ) $this->pastEvaluationHashes = new \TupleDictionary();
		if ( ! $this->uniqueInputFactHashes ) $this->uniqueInputFactHashes = new \TupleDictionary();
		// Stops a warning on line 446
		// $uniqueInputFactHashes = false;
		$pastEvaluationHashes = false;

		// This test looks for unique input fact combinations
		// $varsCopy = array();
		foreach ( $vars as $name => /** @var DOMXPathNavigator $var */ $var )
		{
			if ( $var instanceof XPath2NodeIterator )
			{
				$hash = "";
				foreach ( $var as $node )
				{
					$hash = hash( "sha256", $hash . $node->getUnderlyingObject()->getNodePath() );
				}
				$varsCopy[ $name ] = $hash;
			}
			else if ( $var instanceof DOMXPathNavigator )
			{
				$varsCopy[ $name ] = $var->getUnderlyingObject()->getNodePath();
			}
			else
			{
				$varsCopy[ $name ] = is_object( $var ) ?  (string)$var : $var;
			}
		}

		// Record this for use later
		$uniqueInputFactHashes = ! $this->uniqueInputFactHashes->exists( $varsCopy );

		$this->uniqueInputFactHashes->addValue( $varsCopy, 1 );

		// This test ensure that only unique derived facts are considered
		// if ( $this->pastEvaluationHashes->exists( $derivedFact ) )
		// {
		// 	return;
		// }
        //
		// $this->pastEvaluationHashes->addValue( $derivedFact, 2 );

		// This test should give the same results as the one using derived facts (above)
		// It should be better and may be used in the future.
		// $varsCopy = $vars;
		// foreach ( $varsCopy as $name => /** @var DOMXPathNavigator $var */ $var )
		// {
		// 	$varsCopy[ $name ] = $var instanceof DOMXPathNavigator
		// 		? $var->getUnderlyingObject()->getNodePath()
		// 		: ( is_object( $var ) ?  $var->getTypedValue() : $var );
		// }
        //
		// // Record this for use later
		// $pastEvaluationHashes = ! $this->pastEvaluationHashes->exists( $varsCopy );
        //
		// $this->pastEvaluationHashes->addValue( $varsCopy, 1 );

		$factsContainer = $formula->factsContainer;
		$hasProportionalAcceptanceRadius = ! is_null( $this->proportionalAcceptanceRadius );
		$hasAbsoluteAcceptanceRadius = ! is_null( $this->absoluteAcceptanceRadius );
		$derivedFactPrecision = null;

		if ( ! is_null( $derivedFact['value'] ) && is_numeric( $derivedFact['value'] ) )
		{
			$derivedFactPrecision = isset( $derivedFact['precision'] )
				? $derivedFact['precision']
				: ( isset( $derivedFact['decimals'] )
					? \XBRL_Instance::inferPrecision( $derivedFact['value'], $derivedFact['decimals'] )
					: INF // strlen( str_replace( ".", "", $fact['value'] ) )
				  );
			if ( is_string( $derivedFactPrecision ) && $derivedFactPrecision == 'INF') $derivedFactPrecision = INF;
			if ( $derivedFactPrecision == 0 && ! $hasProportionalAcceptanceRadius && ! $hasAbsoluteAcceptanceRadius )
			{
				return;
			}
		}

		$derivedContexts = $formula->factsContainer->contexts;
		$derivedUnits = $formula->factsContainer->units;

		$derivedContext = $derivedContexts[ $derivedFact['contextRef'] ];
		$derivedUnit = isset( $derivedFact['unitRef'] ) ? $derivedUnits[ $derivedFact['unitRef'] ] : false;

		$aspectMatchedInputFacts = array();

		// if ( ! is_null( $derivedFact['value'] ) )
        foreach ( $formula->getFactsWithoutNils() as /** @var DOMXPathNavigator $inputFact */ $inputFact )
        {
			$inputContextRef = FactValues::getContextRef( $inputFact );
			$inputContext = $formula->xbrlInstance->getContext( $inputContextRef );
			$inputUnitRef = FactValues::getUnitRef( $inputFact );
    	    $inputUnit = $inputUnitRef ? $formula->xbrlInstance->getUnit( $inputUnitRef ) : false;

			if ( $inputFact->getLocalName() == $derivedFact['concept']->localName &&
				 $inputFact->getNamespaceURI() == $derivedFact['concept']->namespaceURI &&
				 (
				 	$derivedFact['contextRef'] == $inputContextRef ||
				 	\XBRL_Equality::context_equal( $inputContext, $derivedContext )
				 ) &&
				 (
				   ! is_numeric( $derivedFact['value'] ) ||
				   (
				   	  $derivedFact['unitRef'] == $inputUnitRef ||
				   	  \XBRL_Equality::unit_equal( $derivedUnit, $inputUnit, $types, $namespaces )
				   )
				 )
			   )
			{
				$aspectMatchedInputFacts[] = $inputFact;
			}
		}

		// Record this set of facts in case they are needed for reporting later
		$this->aspectMatchedInputFacts[ $key ] = $aspectMatchedInputFacts;

		$isSatisfied = null;

		if ( ! $aspectMatchedInputFacts )
		{
			if ( $this->strict )
			{
				if ( is_null( $derivedFact['value'] ) )
				{
					$isSatisfied = true;
				}
				else
				{
					$isSatisfied = false;
				}
			}
			else
			{
				return;
			}
		}
		else if ( is_null( $derivedFact['value'] ) )
		{
			$isSatisfied = false;
		}
		else
		{
			$isSatisfied = true;
		}

		// Add support for in-scope variables here (see FormulaConsisAsser.py from line 60)

		$acceptance = null;

		foreach ( $aspectMatchedInputFacts as $fact )
		{
			if ( ! $isSatisfied )
			{
				break;
			}

			if ( is_null( $fact->getValue() ) )
			{
				if ( ! is_null( $derivedFact['value'] ) )
				{
					$isSatisfied = false;
				}
			}
			else if ( is_numeric( $derivedFact['value'] ) )
			{
				$decimals = FactValues::getDecimals( $fact );
				if ( $decimals )
				{
					$factInferredPrecision = \XBRL_Instance::inferPrecision( $fact->getValue(), $decimals );
				}
				else
				{
					$precision = FactValues::getPrecision( $fact );
					$factInferredPrecision = $precision
						? $precision
						: 'INF';
				}
				if ( is_string( $factInferredPrecision ) && $factInferredPrecision == 'INF') $factInferredPrecision = INF;
				if ( $factInferredPrecision == 0 && ! $hasProportionalAcceptanceRadius && ! $hasAbsoluteAcceptanceRadius )
				{
					$isSatisfied = null;
					break;
				}

				if ( $hasProportionalAcceptanceRadius || $hasAbsoluteAcceptanceRadius )
				{
					$acceptance = $this->evaluateAcceptanceRadius( $formula, $derivedFact['value'], $fact, $vars );
					if ( ! is_null( $acceptance ) )
					{
						$isSatisfied = abs( $derivedFact['value'] - $fact->getValue() ) <= abs( $acceptance );
					}
					else
					{
						$isSatisfied = null;  # no radius
					}
				}
				else
				{
					$precision = min( $derivedFactPrecision, $factInferredPrecision );
					if (
							$precision == 0 ||
							$this->roundValue( $derivedFact['value'], $precision ) != $this->roundValue($fact->getValue(), $precision )
					   )
					{
						$isSatisfied = false;
					}
				}
			}
			else
			{
				// BMS 2018-02-18 Need to look at this because the types are necessary for the test to make sense.
				if ( ! \XBRL_Equality::xequal( $fact->getValue(), $derivedFact['value'] ) )
				{
					$isSatisfied = false;
				}
			}
		}

		if ( ! is_null( $isSatisfied ) )  # None means no evaluation
		{
			$message = $this->description;
			if ( $message )
			{
				// Need to add in-scope var to evaluate the message
				$vars = array(
					'{http://xbrl.org/2008/assertion/consistency}aspect-matched-facts' => $aspectMatchedInputFacts,
					'{http://xbrl.org/2008/assertion/consistency}acceptance-radius' => $acceptance,
					'{http://xbrl.org/2008/assertion/consistency}absolute-acceptance-radius-expression' => $this->absoluteAcceptanceRadius,
					'{http://xbrl.org/2008/assertion/consistency}proportional-acceptance-radius-expression' => $this->proportionalAcceptanceRadius,
				);

				$message = $this->evaluateXPath( $formula, $message, $vars );
				$log->info( $message );
			}

			if ( $isSatisfied )
			{
				$this->satisfied += 1;
				if ( $uniqueInputFactHashes )
				{
					$this->uniqueInputFactSatisfied += 1;
				}
				if ( $pastEvaluationHashes )
				{
					$this->pastEvaluationSatisfied += 1;
				}
			}
			else
			{
				$this->unsatisfied += 1;
				if ( $uniqueInputFactHashes )
				{
					$this->uniqueInputFactUnsatisfied += 1;
				}
				if ( $pastEvaluationHashes )
				{
					$this->pastEvaluationUnsatisfied += 1;
				}
			}
		}

		return;
	}

	/**
	 * Rounds a fact value takng into account the fact precision
	 * @param float $value
	 * @param int $precision
	 * @return unknown|number
	 */
	private function roundValue( $value, $precision )
	{
		if ( $precision == INF ) return $value;
		return round( $value, \XBRL_Instance::inferDecimals( $value, $precision ) );
	}

	/**
	 * Check the value is wthin the acceptance radius
	 * @param Formula $variableSet
	 * @param mixed $derivedValue
	 * @param XPathNavigator $fact
	 * @param array $factVars
	 * @return number|NULL
	 */
	private function evaluateAcceptanceRadius( $variableSet, $derivedValue, $fact, $factVars = array() )
	{
		if ( is_null( $this->radiusValue ) )
		{
			if ( ! is_null( $this->absoluteAcceptanceRadius ) || ! is_null( $this->proportionalAcceptanceRadius ) )
			{
				$expression = ! is_null( $this->absoluteAcceptanceRadius )
					? $this->absoluteAcceptanceRadius
					: $this->proportionalAcceptanceRadius;

				$expression = "$expression cast as xs:float";

				$vars = array_map( function( $param ) { return $param->result; }, $this->parameters );
				$vars = array_merge( $factVars, $vars );
				// $result = $this->evaluateXPath( $variableSet, $expression, $vars );
				$provider = new NodeProvider( $fact );

				$xpathExpression = XPath2Expression::Compile( $expression, $variableSet->nsMgr );

				$xpathExpression->AddToContext( "xbrlInstance", $variableSet->xbrlInstance );
				$xpathExpression->AddToContext( "xbrlTaxonomy", $variableSet->xbrlTaxonomy );
				$xpathExpression->AddToContext( "base", $variableSet->base );
				$result = $xpathExpression->EvaluateWithVars( $provider, $vars );

				$this->radiusValue = $result instanceof XPath2Item ? $result->getTypedValue() : $result;
			}
			else
			{
				$this->radiusValue = false;
			}
		}

		if ( ! is_null( $this->absoluteAcceptanceRadius ) )
		{
			return $this->radiusValue;
		}

		if ( ! is_null( $this->proportionalAcceptanceRadius ) )
		{
			return $this->radiusValue * $derivedValue;
		}

		return null;
	}

	/**
	 * Allows the class to compare the expected result node to the result created
	 * Returns false if there is no problem or an error string to report if there is.
	 * @param string $testCaseFolder
	 * @param \SimpleXMLElement $expectedResultNode	The content of the <result> node from the test case.
	 * 												The relevant test class will know how to handle its content.
	 * @return bool|string False if there is no error or a string that describes the failure
	 */
	public function compareResult( $testCaseFolder, $expectedResultNode )
	{
		// Get the expected values
		if ( ! property_exists( $expectedResultNode, "assertionTests" ) ) return false;

		$assertionTestAttributes = null;

		if ( count( $expectedResultNode->assertionTests ) > 1 )
		{
			foreach ( $expectedResultNode->assertionTests as $assertionTest )
			{
				$attributes = $assertionTest->attributes();
				if ( $attributes->assertionID != $this->id ) continue;
				$assertionTestAttributes = $attributes;
				break;
			}
		}
		else if ( count( $expectedResultNode->assertionTests ) )
		{
			$assertionTestAttributes = $expectedResultNode->assertionTests->attributes();
		}

		if ( ! $assertionTestAttributes )
		{
			return false;
		}

		$satisfied = intval( $assertionTestAttributes->countSatisfied );
		$unsatisfied = intval( $assertionTestAttributes->countNotSatisfied );
		if ( $satisfied == $this->satisfied && $unsatisfied == $this->unsatisfied )
		{
			\XBRL_Log::getInstance()->debug( "Consistency assertion succeeded" );
			return false;
		}

		return "Expected $satisfied satisfied facts (have {$this->satisfied}) and expected $unsatisfied unsatisfied facts (have {$this->unsatisfied})";
	}

}
