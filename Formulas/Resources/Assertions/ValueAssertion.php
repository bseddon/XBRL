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

use lyquidity\XPath2\XPath2Expression;
use lyquidity\xml\MS\IXmlNamespaceResolver;
use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Undefined;
use XBRL\Formulas\Exceptions\FormulasException;
use XBRL\Formulas\Resources\Resource;
use lyquidity\xml\interfaces\IXmlSchemaType;

 /**
  * A class to process a formula definitions
  * http://www.xbrl.org/Specification/valueAssertions/REC-2009-06-22/valueAssertions-REC-2009-06-22.html#sec-syntax-va-resources
  */
class ValueAssertion extends VariableSetAssertion
{
	/**
	 * (Required) Existence-assertion XPath expression
	 * @var string $test
	 */
	public $test;

	/**
	 * A compiled expression of the $test value (if there is one)
	 * @var XPath2Expression $testXPath2Expression
	 */
	public $testXPath2Expression;

	/**
	 * Flag indicating whether implicit filtering should be used
	 * @var bool $implicitFiltering
	 */
	public $implicitFiltering = false;

	/**
	 * A list of satified evaluation results
	 * @var array[bool] $satisfied
	 */
	public $satisfied = array();

	/**
	 * A list of unsatified evaluation results
	 * @var array[bool] $unsatisfied
	 */
	public $unsatisfied = array();

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
		$result['variablesetType'] = 'valueAssertion';

		$attributes = $node->attributes();

		if ( ! property_exists( $attributes, "implicitFiltering" ) )
		{
			$log->valueassertion_validation( "Variables", "Missing implicit filtering attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->implicitFiltering = filter_var( $attributes->implicitFiltering, FILTER_VALIDATE_BOOLEAN );
		$result['implicitFiltering'] = $this->implicitFiltering;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param IXmlNamespaceResolver $nsMgr
	 */
	/**
	 * Check the 'select' and 'as'
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 * @return bool
	 * @throws FormulasException
	 */
	public function validate( $variableSet, $nsMgr )
	{
		if ( isset( $this->test ) && ! empty( $this->test ) )
		{
			try
			{
				$expression = XPath2Expression::Compile( $this->test, $nsMgr );
				$this->testXPath2Expression = $expression;
			}
			catch ( Exception $ex )
			{
				\XBRL_Log::getInstance()->valueassertion_validation( "Value assertion", "Failed to compile test expression",
					array(
						'test expression' => $this->test,
						'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);

				return false;
			}

		}

		return true;
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		if ( isset( $this->testXPath2Expression ) )
		{
			$variableRefs = array_merge( $variableRefs, $this->testXPath2Expression->getParameterQNames() );
		}

		return $variableRefs;
	}

	/**
	 * A list of hash of each past evaluation
	 * @var \TupleDictionary $pastEvaluationHashes
	 */
	private $pastEvaluationHashes = null;

	/**
	 * Give the variable set instance an opportunity to process the facts
	 */
	public function evaluateResult()
	{
		// \XBRL_Log::getInstance()->info( "Process value assertion" );

		// Although I cannot find any reference to it in the documentation, it seems that only unique derived facts
		// should be compared.  Uniquenesss in this case appears to be derived facts that have the same contributing variables.
		// Conformance test 34120 V-13 has this issue.  In this test there are 7 facts produced but 1 is a duplicates.
		// The TupleDictionary works well for this scenario
		if ( ! $this->pastEvaluationHashes ) $this->pastEvaluationHashes = new \TupleDictionary();

		try
		{
			// Check all variables are not all fallback and that preconditions are met
			if ( ! parent::evaluateResult() )
			{
				return;
			}

			$vars = $this->getBindingsAsVars();

			$varsCopy = $vars;
			foreach ( $varsCopy as $name => /** @var DOMXPathNavigator $var */ $var )
			{
				$varsCopy[ $name ] = $var instanceof DOMXPathNavigator
					? $var->getUnderlyingObject()->getNodePath()
					: ( $var instanceof XPath2NodeIterator // Should the list of nodes and or values in the iterator be added?
							? $var
							: ( $var instanceof Undefined
									? ""
									: ( $var instanceof IXmlSchemaType
										? $var
										: ( is_object( $var ) ? $var->getTypedValue() : $var ) ) )
					  );
			}

			if ( $this->pastEvaluationHashes->exists( $varsCopy ) )
			{
				return;
			}

			$this->pastEvaluationHashes->addValue( $varsCopy, 1 );
			$result = CoreFuncs::BooleanValue( parent::evaluateXPathExpression( $this, $this->testXPath2Expression, $vars ) );

			// This can used by message expressions
			$vars[ "{{$this->namespace}}test-expression" ] = $this->test;
			$evaluationResult = array( 'vars' => $vars, 'lastFactBinding' => $this->getLastFactBinding() );
			$evaluationResult['uncoveredAspectFacts'] = $evaluationResult['lastFactBinding'] instanceof FactVariableBinding
				? $evaluationResult['lastFactBinding']->uncoveredAspectFacts
				: array();

			if ( $result instanceof CoreFuncs::$True )
			{
				$this->satisfied[] = $evaluationResult;
				$this->generatedSatisifiedMessages = array_merge( $this->generatedSatisifiedMessages, $this->processMessages( $this->satisfiedMessages, $vars ) );
			}
			else if ( $result instanceof CoreFuncs::$False )
			{
				$this->unsatisfied[] = $evaluationResult;
				// BMS 2019-09-09 Suggested by Tim Vandecasteele as the wrong messages array was being merged
				//				  see https://github.com/tim-vandecasteele/xbrl-experiment/commit/4de606377d7b0115322b9624612d0d666fa7439f
				$this->generatedUnsatisifiedMessages = array_merge( $this->generatedUnsatisifiedMessages, $this->processMessages( $this->unsatisfiedMessages, $vars ) );
			}
			else
			{
				\XBRL_Log::getInstance()->valueassertion_validation( "Valid Assertion result", "Expects a true or false evaluation result",
					array(
						'value type' => '',
						'text' => $this->test
					)
				);
			}
		}
		catch( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * Function to output the number of satisfied and not statisfied evaluations
	 * @param \XBRL_Log $log
	 */
	public function ProcessEvaluationResult( $log )
	{
		$satisfied = count( $this->satisfied );
		$unsatisfied = count( $this->unsatisfied );

		// \XBRL_Log::getInstance()->info("Value assertion result for '{$this->label}': There are $satisfied satisfied and $unsatisfied not satisfied evaluations.");
		\XBRL_Log::getInstance()->formula_evaluation(
			"Value assertion",
			"Result for '{$this->id} ($this->linkbase)': There are $satisfied satisfied and $unsatisfied not satisfied evaluations.",
			array(
				'formula' => $this->label,
				'satisfied' => $satisfied,
				'unsatisfied' => $unsatisfied
			)
		);
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

		$messageTests = null;
		if ( property_exists( $expectedResultNode, "messageTests" ) )
		{
			if ( count( $expectedResultNode->messageTests ) > 1 )
			{
				foreach ( $expectedResultNode->messageTests as $messageTest )
				{
					$attributes = $messageTest->attributes();
					if ( $attributes->assertionID != $this->id ) continue;
					$messageTests = $messageTest;
					break;
				}
			}
			else if ( count( $expectedResultNode->messageTests ) )
			{
				$messageTests = $expectedResultNode->messageTests;
			}
		}

		if ( $assertionTestAttributes )
		{
			$satisfied = intval( $assertionTestAttributes->countSatisfied );
			$unsatisfied = intval( $assertionTestAttributes->countNotSatisfied );
			if ( ! ( $satisfied == count( $this->satisfied ) && $unsatisfied == count( $this->unsatisfied ) ) )
			{
				$thisSatisfied = count( $this->satisfied );
				$thisUnsatisfied = count( $this->unsatisfied );
				return "Expected $satisfied satisfied facts (have $thisSatisfied) and expected $unsatisfied unsatisfied facts (have $thisUnsatisfied)";
			}
		}

		if ( $messageTests )
		{
			if ( property_exists( $messageTests, "satisfied" ) )
			{
				foreach ( $messageTests->satisfied as $message )
				{
					$found = false;
					foreach ( $this->generatedSatisifiedMessages as $generatedMessage )
					{
						// Replace tags as they are not needed for comparison with the test text
						if ( preg_replace( "/(?'tag'<(.*?)>.*?<\\/\\2>)\\s*/s", "", $generatedMessage, -1 ) != (string)$message ) continue;
						$found = true;
						break;
					}

					if ( ! $found )
					{
						return "Generated satisified message does not match the conformance test message: $message";
					}
				}
			}

			if ( property_exists( $messageTests, "notSatisfied" ) )
			{
				foreach ( $messageTests->notSatisfied as $message )
				{
					$found = false;
					foreach ( $this->generatedUnsatisifiedMessages as $generatedMessage )
					{
						// Replace tags as they are not needed for comparison with the test text
						if ( preg_replace( "/(?'tag'<(.*?)>.*?<\\/\\2>)\\s*/s", "", $generatedMessage, -1 ) != (string)$message ) continue;
						$found = true;
						break;
					}

					if ( ! $found )
					{
						return "Generated not satisified message does not match the conformance test message: $message";
					}
				}
			}
		}

		return false;
	}
}