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
 */

namespace XBRL\Formulas\Resources\Assertions;

$utiltiesPath = isset( $_ENV['UTILITY_LIBRARY_PATH'] )
	? $_ENV['UTILITY_LIBRARY_PATH']
	: ( defined( 'UTILITY_LIBRARY_PATH' ) ? UTILITY_LIBRARY_PATH : __DIR__ . "/../../../../utilities/" );
require_once $utiltiesPath . "tuple-dictionary.php";

use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\CoreFuncs;
use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\xml\MS\XmlNamespaceManager;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\IContextProvider;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Exception;

 /**
  * A class to process a formula definitions
  * http://www.xbrl.org/Specification/existenceAssertions/REC-2009-06-22/existenceAssertions-REC-2009-06-22.html#sec-syntax-ea-resources
  */
class ExistenceAssertion extends VariableSetAssertion
{
	/**
	 * (Optional) Existence-assertion XPath expression
	 * @var string $test
	 */
	public $test;

	/**
	 * A compiled version of the test
	 * @var XPath2Expression
	 */
	private $testXPath2Expression = null;

	/**
	 * Indicates whether implicit filtering is active.  If implicit
	 * filtering is not active then all aspects must be covered.
	 * @var string
	 */
	public $implicitFiltering = true;

	/**
	 * A list of satified evaluation results
	 * @var array $satisfied
	 */
	public $satisfied = array();

	/**
	 * A list of satified evaluation results
	 * @var array $notSatisfied
	 */
	public $notSatisfied = array();

	/**
	 * The final success state
	 * @var bool $success
	 */
	public $success = false;

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->testRequired = false;
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
		$result['variablesetType'] = 'existenceAssertion';

		$this->testRequired = false;

		$attributes = $node->attributes();
		if ( ! property_exists( $attributes, "implicitFiltering" ) )
		{
			$log->existenceassertion_validation( "Variables", "Missing implicit filtering attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->implicitFiltering = filter_var( $attributes->implicitFiltering, FILTER_VALIDATE_BOOLEAN );
		$result['implicitFiltering'] = $this->implicitFiltering;

		return $result;
	}

	/**
	 * If a test property exists it MUST NOT have dependencies on fact or general variables
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		if ( ! $this->test ) return true;

		try
		{
			$expression = XPath2Expression::Compile( $this->test, $nsMgr );
			$this->testXPath2Expression = $expression;

			$variableRefs = $this->getVariableRefs();
			foreach ( $variableRefs as $qname => $variableRef )
			{
				// Look for general or fact variables with this qname
				if ( isset( $this->variablesByQName[ (string)$variableRef ] ) )
				{
					\XBRL_Log::getInstance()->existenceassertion_validation(
						"Existent assertion",
						"An existance assertion test cannot reference fact or general variables",
						array(
							'label' => $this->label,
							'error' => 'xbrleae:variableReferenceNotAllowed'
						)
					);
				}
			}

			return true;
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

	private function evaluationInternal( $exists )
	{
		// \XBRL_Log::getInstance()->info( "Process existence assertion" );

		// Although I cannot find any reference to it in the documentation, it seems that only unique derived facts
		// should be compared.  Uniquenesss in this case appears to be derived facts that have the same contributing variables.
		// Conformance test 34120 V-13 has this issue.  In this test there are 7 facts produced but 1 is a duplicates.
		// The TupleDictionary works well for this scenario
		if ( ! $this->pastEvaluationHashes ) $this->pastEvaluationHashes = new \TupleDictionary();

		$vars = $this->getBindingsAsVars();
		$varsCopy = $vars;
		foreach ( $varsCopy as $name => /** @var DOMXPathNavigator $var */ $var )
		{
			$varsCopy[ $name ] = $var instanceof DOMXPathNavigator
				? $var->getUnderlyingObject()->getNodePath()
				: ( $var instanceof Undefined
						? ""
						: ( is_object( $var ) ?  $var->getTypedValue() : $var ) );
		}

		if ( $this->pastEvaluationHashes->exists( $varsCopy ) )
		{
			return;
		}

		$this->pastEvaluationHashes->addValue( $varsCopy, 1 );

		$evaluationResult = array( 'vars' => $vars, 'lastFactBinding' => $this->getLastFactBinding() );
		$evaluationResult['uncoveredAspectFacts'] = $evaluationResult['lastFactBinding'] instanceof FactVariableBinding
			? $evaluationResult['lastFactBinding']->uncoveredAspectFacts
			: array();

		if ( ! $exists )
		{
			$this->notSatisfied[] = $evaluationResult;
			return;
		}

		try
		{
			// Check all variables are not all fallback and that preconditions are met
			if ( ! parent::evaluateResult() )
			{
				throw new \Exception();
			}

		}
		catch( \Exception $ex )
		{
			$this->notSatisfied[] = $evaluationResult;
			return;
		}

		$this->satisfied[] = $evaluationResult;

	}

	/**
	 * Called to register a not satified evaluation
	 */
	public function evaluationNotSatisfied()
	{
		$this->evaluationInternal( false );
	}

	/**
	 * Give the variable set instance an opportunity to process the facts
	 */
	public function evaluateResult()
	{
		$this->evaluationInternal( true );
	}

	/**
	 * Function to output the number of satisfied and not statisfied evaluations
	 * @param \XBRL_Log $log
	 */
	public function ProcessEvaluationResult( $log )
	{
		$countSatisfied = count( $this->satisfied );
		$countNotSatisfied = count( $this->notSatisfied );
		$vars = array_map( function( $param ) { return $param->result; }, $this->parameters );
		$provider = new XPath2ItemProvider( XPath2Item::fromValue( $countSatisfied ) );

		if ( ! $this->test )
		{
			$this->success = $countSatisfied > 0 || $countNotSatisfied > 0;
			$message = $this->success ? "succeeded" : "failed";
			\XBRL_Log::getInstance()->info("Existence assertion result for '{$this->label}' $message: There are {$countSatisfied} satisfied and {$countNotSatisfied} not satisfied existence evaluations." );
		}
		else
		{
			$result = CoreFuncs::BooleanValue( $this->evaluateXPathExpression( $this, $this->testXPath2Expression, $vars, null, $provider ) );
			$this->success = $result instanceof CoreFuncs::$True;
			$message = $this->success ? "succeeded" : "failed";
			$this->satisfied = $this->success ? array( true ) : array();
			$this->notSatisfied = $this->success ? array() : array( true );
			$countSatisfied = count( $this->satisfied );
			$countNotSatisfied = count( $this->notSatisfied );
			\XBRL_Log::getInstance()->info("Existence assertion result for '{$this->label}' with test expression $message: There are {$countSatisfied} satisfied and {$countNotSatisfied} not satisfied existence evaluations." );
		}

		$vars[ "{{$this->namespace}}test-expression" ] = $this->test;

		if ( $this->success )
		{
			$this->generatedSatisifedMessages = $this->processMessages( $this->satisfiedMessages, $vars, $provider );
		}
		else
		{
			$this->generatedUnsatisifiedMessages = $this->processMessages( $this->unsatisfiedMessages, $vars, $provider );
		}
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

		$countSatisfied = count( $this->satisfied );
		$countNotSatisfied = count( $this->notSatisfied );

		if ( $assertionTestAttributes )
		{
			$satisfied = intval( $assertionTestAttributes->countSatisfied );
			$unsatisfied = intval( $assertionTestAttributes->countNotSatisfied );

			if ( $satisfied && ! $countSatisfied )
			{
				return "Expected $satisfied satisfied facts (have $countSatisfied)";
			}

			if ( $unsatisfied && ! $countNotSatisfied )
			{
				return "Expected $unsatisfied unsatisfied facts (have $countNotSatisfied)";
			}
		}

		if ( $messageTests )
		{
			if ( property_exists( $messageTests, "satisfied" ) )
			{
				foreach ( $messageTests->satisfied as $message )
				{
					$found = false;
					foreach ( $this->generatedSatisifedMessages as $generatedMessage )
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

/**
 * NodeProvider (public)
 */
class XPath2ItemProvider implements IContextProvider
{
	/**
	 * @var XPath2Item $item
	 */
	private $item;

	/**
	 * Constructor
	 * @param XPath2Item $item
	 */
	public function __construct( $item )
	{
		$this->item = $item;
	}

	/**
	 * @var XPathItem $Context
	 */
	public function getContext()
	{
		return $this->item;
	}

	/**
	 * Get the current position.  Always return 1.
	 * @return int
	 */
	public function getCurrentPosition()
	{
		return 1;
	}

	/**
	 * Get the last position.  Always returns 1.
	 * @return int
	 */
	public function getLastPosition()
	{
		return 1;
	}
}

