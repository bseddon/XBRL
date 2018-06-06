<?php

/**
 * These examples are taken from the XBRL Formula suite.
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use lyquidity\xml\QName;
use lyquidity\XPath2\XPath2Exception;

// The tests are organized to reference core schemas in the test suite hierarchy
// This function will adjust the paths to core schemas to use the ones in this package
global $mapUrl;
$mapUrl = function( $url )
{
	$url = preg_replace( "!/core_schemas/!", "/examples/core_schemas/", $url );
	return $url;
};

global $debug_statements;
// Controls whether verbose debug information is written to the console
$debug_statements = false;

ini_set('xdebug.max_nesting_level', 512);

define( 'UTILITY_LIBRARY_PATH', __DIR__ . '/../../utilities/' );
define( 'XML_LIBRARY_PATH', __DIR__ . '/../../xml/' );
define( 'XPATH20_LIBRARY_PATH',  __DIR__ . '/../../XPath2/' );
define( 'LOG_LIBRARY_PATH', __DIR__ . '/../../log/' );

if ( ! class_exists( "\\XBRL", true ) )
{
	require_once( __DIR__ . '/../XBRL.php' );
}

$log = XBRL_Log::getInstance();
$log->debugLog();

// $types = XBRL_Types::getInstance();
// $types->log = $log;
XBRL::setValidationState();

global $conformance_base;
$conformance_base = __DIR__;

/**
 * This array is used to record any conformance warnings
 * @var array $issues
 */
global $issues;
$issues = array();

/* 1  */ performTestcase( $log, '0001', '0001 Boolean test of balance sheet/index.xml' );
/* 1  */ performTestcase( $log, '0002', '0002 Assets equals liabilities plus equity/index.xml' );
/* 1  */ performTestcase( $log, '0003', '0003 End stock derivation from start stock and flows/index.xml' );
/* 2  */ performTestcase( $log, '0004', '0004 End stock with restatement date dimension/index.xml' );
/* 1  */ performTestcase( $log, '0005', '0005 Aggregate across dimension/index.xml' );
/* 1  */ performTestcase( $log, '0005', '0005 Aggregate across dimension/index-FranceSpain-GroupFilter.xml' );
/* 1  */ performTestcase( $log, '0006', '0006 Parameters for filtering/index.xml' );
/* 1  */ performTestcase( $log, '0007', '0007 Concept data type and precondition filtering/index.xml' );
/* 1  */ performTestcase( $log, '0008', '0008 Tuple filtering/index.xml' );
/* 1  */ performTestcase( $log, '0009', '0009 Typed dimension filtering/index.xml' );
/* 2  */ performTestcase( $log, '0010', '0010 Concept name filter/index.xml' );
/* 2  */ performTestcase( $log, '0011', '0011 Concept filter with tuple filter/index.xml' );
/* 2  */ performTestcase( $log, '0012', '0012 Concept filter with period filter/index.xml' );
/* 2  */ performTestcase( $log, '0013', '0013 Concept filter with unit filter/index.xml' );
/* 2  */ performTestcase( $log, '0014', '0014 Concept filter with entity filters/index.xml' );
/* 4  */ performTestcase( $log, '0014', '0014 Concept filter with scenario and segment filters/index.xml' );
/* 2  */ performTestcase( $log, '0014', '0014 Wagetax example part 5/index.xml' );
/* 1  */ performTestcase( $log, '0015', "0015 Movement Pattern/movement-pattern-testcase.xml" );
/* 1  */ performTestcase( $log, '0015', '0015 Movement Pattern/index.xml' );
/* 1  */ performTestcase( $log, '0016', '0016 Pharmaceutical Dimension Aggregation/index.xml' );
/* 1  */ performTestcase( $log, '0017', '0017 COREP 18 Dimensional Weighted Avg/index.xml' );
// /* 3  */ performTestcase( $log, '0018', '0018 GL Examples/index.xml' );
/* 1  */ performTestcase( $log, '0019', '0019 Value Assertion/index.xml' );
/* 1  */ performTestcase( $log, '0020', '0020 Existence Assertion/index.xml' );
/* 1  */ performTestcase( $log, '0021', '0021 Consistency Assertion/index.xml' );
/* 2  */ performTestcase( $log, '0022', '0022 Assertion Set/index.xml' );
/* 4  */ performTestcase( $log, '0023', '0023 Context and Unit checking/index.xml' );
/* 1  */ performTestcase( $log, '0024', '0024 FactVariables multi purpose/index.xml' );
/* 2  */ performTestcase( $log, '0025', '0025 Consistency Assertion Variables/index.xml' );
// These seem to use old element names and arc roles
// performTestcase( $log, '0026', '0026 Formula Chaining (Extension)/index.xml' );
// performTestcase( $log, '0027', '0027 Tuple Generation (Extension)/index.xml' );
// performTestcase( $log, '0028', '0028 Multi-Instance (Extension)/index.xml' );
// performTestcase( $log, '0029', '0029 GL Generation (Extension)/index.xml' );
// performTestcase( $log, '0030', '0030 Functions in XPath (Extension)/index.xml' );

return;

/**
 * Perform a specific test case
 *
 * @param XBRL_Log $log
 * @param string $testid
 * @param string $testCaseXmlFilename
 */
function performTestcase( $log, $testid, $testCaseXmlFilename )
{
	global $conformance_base;

	$testCaseFolder = dirname( "$conformance_base/$testCaseXmlFilename" );
	$testCase = simplexml_load_file( "$conformance_base/$testCaseXmlFilename" );

	$attributes = $testCase->attributes();
	$outpath = (string) $attributes->outpath;
	$name = (string) $attributes->name;

	$testName = trim( $testCase->name );
	$testDescription = trim( $testCase->description );
	$log->info("Test {$testid}: $testName");
	$log->info("    $testDescription");
	$count = count( $testCase->children()->variation );
	$log->info(     "There are $count tests");

	foreach ( $testCase->children()->variation as $key => /** @var SimpleXMLElement $variation */ $variation )
	{
		$variationAttributes = $variation->attributes();
		$description = trim( (string) $variation->description );

		$source = array(
			'variation id'	=> (string) $variationAttributes->id,
			'description'	=> $description,
		);

		// === Put specific test conditions here (begin) ====

		// === (end) ========================================

		$instanceElements = $variation->data->instance;
		$instanceFile = (string) $variation->data->instance; // Set a default
		$instanceFiles = array();
		$docNamespaces = $variation->getDocNamespaces( true );

		foreach ( $instanceElements as $instanceElement )
		{
			$readMeFirst = property_exists( $instanceElement->attributes(), 'readMeFirst' )
				? filter_var( $instanceElement->attributes()->readMeFirst, FILTER_VALIDATE_BOOLEAN )
				: false;

			if ( $readMeFirst )
			{
				// If there is a name attribute use it
				$name = property_exists( $instanceElement->attributes(), 'name' )
					? (string)$instanceElement->attributes()->name
					: '{http://xbrl.org/2010/variable/instance"}standard-input-instance';

				if ( ! empty( $name ) )
				{
					$qname = qname( $name, $docNamespaces );
					$name = $qname->clarkNotation();
				}

				$instanceFiles[ $name] = "$testCaseFolder/$instanceElement";
			}
		}

		if ( isset( $instanceFiles['{http://xbrl.org/2010/variable/instance"}standard-input-instance'] ) )
		{
			$instanceFile = $instanceFiles['{http://xbrl.org/2010/variable/instance"}standard-input-instance'];
		}

		$xsdElements = $variation->data->schema;
		$xsd = (string) $xsdElements; // Set a default
		foreach ( $xsdElements as $xsdElement )
		{
			$readMeFirst = property_exists( $xsdElement->attributes(), 'readMeFirst' )
				? filter_var( $xsdElement->attributes()->readMeFirst, FILTER_VALIDATE_BOOLEAN )
				: false;

			if ( $readMeFirst )
			{
				$xsd = (string) $xsdElement;
				break;
			}
		}

		$linkbaseElements = $variation->data->linkbase;
		$linkbaseFile = "";
		foreach ( $linkbaseElements as $linkbaseElement )
		{
			$readMeFirst = property_exists( $linkbaseElement->attributes(), 'readMeFirst' )
				? filter_var( $linkbaseElement->attributes()->readMeFirst, FILTER_VALIDATE_BOOLEAN )
				: false;

			if ( $readMeFirst )
			{
				$linkbaseFile = (string) $linkbaseElement;
				break;
			}
		}

		$parameters = $instanceFiles;
		$domNode = dom_import_simplexml( $variation->data );
		$domNode = $domNode->firstChild;
		while ( $domNode )
		{
			if ( $domNode->nodeType == XML_ELEMENT_NODE )
			{
				/**
				 * @var DOMElement $domNode
				 */
				if ( $domNode->localName == 'parameter' )
				{
					$parameter = simplexml_import_dom( $domNode );
					$docNamespaces = $parameter->getDocNamespaces( true );
					$localNamespaces = $parameter->getDocNamespaces( true, false );
					$attributes = $parameter->attributes();
					$name = trim( $attributes->name );
					$qname = qname( $name, array_merge( $docNamespaces, $localNamespaces ) );
					$clark = $qname->clarkNotation();
					$dataType = property_exists( $attributes, 'datatype' ) ? trim( $attributes->datatype ) : 'xs:anyType';
					$value = property_exists( $attributes, 'value' ) ? trim( $attributes->value ) : '';
					$parameters[ $clark ] = array(
						'qname' => $qname,
						'datatype' => $dataType,
						'value' => "'$value'",
					);
				}
			}
			$domNode = $domNode->nextSibling;
		}

		if ( ! empty( $linkbaseFile ) )
		{
			// Get the xsd
			$xsd = (string) $xsdElements; // Set a default
			foreach ( $xsdElements as $xsdElement )
			{
				$readMeFirst = property_exists( $xsdElement->attributes(), 'readMeFirst' )
					? filter_var( $xsdElement->attributes()->readMeFirst, FILTER_VALIDATE_BOOLEAN )
					: false;

				if ( ! $readMeFirst )
				{
					$xsd = (string) $xsdElement;
					break;
				}
			}
		}

		/**
		 * @var SimpleXMLElement $result
		 */
		$result = $variation->result;
		if ( property_exists( $result, "instance" ) )
		{
			$resultsFilename = (string) $result->instance;
		}
		$expected = property_exists( $result, 'error' )
			? trim( $result->error )
			: ( property_exists( $result->attributes(), "expected" )
					? (string)$result->attributes()->expected
					: "valid"
			  );
		$resultsFile = property_exists( $result, 'file' ) ? true : false;

		$log->resetConformanceIssueWarning();
		XBRL_Instance::reset();
		XBRL::setValidationState();
		unset( $ex );

		try
		{
			if ( empty( $instanceFile ) )
			{
				echo "$xsd ($testid-{$variationAttributes->id} $expected) $description\n";
				$taxonomy = XBRL::load_taxonomy( "$testCaseFolder/$xsd" );

				if ( !empty( $linkbaseFile ) )
				{
					// Need to load this file
					if ( $log->hasInstanceValidationWarning() )
					{
						$log->info("Adding additional linkbase so removing existing instance warnings");
						$log->resetInstanceValidationWarning();
					}
					$taxonomy->addLinkbaseRef( $linkbaseFile, $source['description'], "", XBRL_Constants::$genericLinkbaseRef );
				}

				if ( $taxonomy->getHasFormulas() )
				{
					// Time to evaluate formulas
					$formulas = new XBRL_Formulas();
					$formulas->processFormulasForTaxonomy( $taxonomy, $testCase->getDocNamespaces( true ), $parameters );
				}
			}
			else
			{
				$instanceFile = urldecode( $instanceFile );

				$log->info( "Linkbase: " . basename( $instanceFile ) . " ($testid-{$variationAttributes->id} $expected)" );
				if ( ! empty( $description ) )
				{
					$log->info( "        $description" );
				}

				$instance = XBRL_Instance::FromInstanceDocument( "$instanceFile" );
				$taxonomy = $instance->getInstanceTaxonomy();

				if ( $taxonomy->getHasFormulas() )
				{
					// Time to evaluate formulas
					$formulas = new XBRL_Formulas();
					if ( ! $formulas->processFormulasAgainstInstances( $instance, $testCase->getDocNamespaces( true ), $parameters ) )
					{
						// Report the failure
						$log->formula_validation( "Test $testid failed", "The test failed to complete",
							array(
								'test id' => $testid,
								'instance' => $instanceFile
							)
						);
					}
					else
					{
						// Check the actual result against expected result
						$expectedResultNode = $variation->result;
						$result = $formulas->compareResult( $testCaseFolder, $expectedResultNode );
						if ( $result !== false )
						{
							// Report the failure
							$log->formula_validation( "Test $testid failed", "The test failed comparing the expected result with the generated result",
								array(
									'test id' => $testid,
									'result' => $result
								)
							);
						}
					}
				}
			}
		}
		catch ( \XBRL\Formulas\Exceptions\FormulasException $ex )
		{
			$log->warning( $ex->getMessage() );

			// Sometimes a specific error is not specified by the test (for example 60600 V-03)
			if ( $expected == 'invalid' ) continue;

			// If an expected error is reported the continue
			if ( $ex->error == $expected || "err:{$ex->error}" == $expected ) continue;

			// Record the issue for external reporting
			global $issues;
			$issues[] = array(
				'id' => $testid,
				'variation' => $source['variation id'],
				'type' => 'FormulasException',
				'expected error' => $expected,
				'actual error' => $ex->error,
				'message' => $ex->getMessage(),
			);

		}
		catch ( XPath2Exception $ex )
		{
			// $log->warning( $ex->getMessage() );
			$log->formula_validation( "XPath 2.0", "XPath 2.0 Exception",
				array(
					'error code' => $ex->ErrorCode,
					'message' => $ex->getMessage()
				)
			);
		}

		if ( $expected == 'valid' )
		{
			if ( ! $log->hasConformanceIssueWarning() ) continue;
			$log->conformance_issue( $testid, "Expected the test to be valid", $source );

			// Record the issue for external reporting
			global $issues;
			$issues[] = array(
				'id' => $testid,
				'variation' => $source['variation id'],
				'expected' => $expected,
				'actual' => 'invalid'
			);
		}
		else
		{
			if ( $log->hasConformanceIssueWarning() ) continue;
			// If there is a file *and* and error then the result is ambigous.
			// For example, 270 v-02.
			if ( $resultsFile ) continue;

			$log->conformance_issue( $testid, "Expected the test to be invalid", $source );

			// Record the issue for external reporting
			global $issues;
			$issues[] = array(
				'id' => $testid,
				'variation' => $source['variation id'],
				'expected' => $expected,
				'actual' => 'valid'
			);
		}
	}
}
