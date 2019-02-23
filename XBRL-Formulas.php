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

use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\XPath2\XPath2Expression;
use XBRL\Formulas\Resources\Variables\Parameter;
use XBRL\Formulas\Resources\Variables\Signature;
use XBRL\Formulas\Resources\Variables\Implementation;
use XBRL\Formulas\Resources\Variables\FactVariable;
use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\Resources\Variables\Variable;
use XBRL\Formulas\Formulas;
use XBRL\Formulas\Resources\Assertions\ConsistencyAssertion;
use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\Resources\Variables\Precondition;
use XBRL\Formulas\Resources\Assertions\AssertionSet;
use XBRL\Formulas\Resources\Formulas\GeneratedFacts;
use XBRL\Formulas\Resources\Resource;
use lyquidity\XPath2\FunctionTable;
use XBRL\Formulas\Resources\Variables\ScopeVariable;
use XBRL\Formulas\ScopeVariableBinding;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use XBRL\Formulas\Resources\Variables\Instance;
use lyquidity\xml\QName;

/**
 * Main class for formula evaluation
 */
class XBRL_Formulas extends Resource
{
	/**
	 * A namespace manager for this evaluator
	 * @var XmlNamespaceManager $nsMgr
	 */
	private $nsMgr = null;

	/**
	 * A reference to the current logging instance
	 * @var XBRL_Log $log
	 */
	private $log = null;

	/**
	 * A list of parameter variables indexed by qname
	 * @var array[Parameter] $parameterQnames
	 */
	private $parameterQnames = null;

	/**
	 * A list of discovered custom function signatures
	 * @var array $signatures
	 */
	private $signatures = array();

	/**
	 * Flag indicating whether or not the formula should produce an instance
	 * @var bool
	 */
	private $produceOutputXbrlInstance = false;

	/**
	 * A list of the variable sets that will produce an instance
	 * @var array
	 */
	private $instanceProducingVariableSets = array();

	/**
	 * The QName to use for the output instance
	 * @var string $instanceQName
	 */
	private $instanceQName = 'instances:standard-output-instance';

	/**
	 * A list of the instances to produce
	 * @var array $instanceQNames
	 */
	private $instanceQNames = array();

	/**
	 * A list of instances
	 * @var array(XBRL_Instance) $instances
	 */
	private $instances = array();

	/**
	 * A list of variable sets and parameters by name (not qname)
	 * @var array[VariableSet] $variableSets
	 */
	private $variableSets = array();

	/**
	 * Flag indicating whether the variable set represents an assertion
	 * @var bool
	 */
	private $isAssertion = false;

	/**
	 * Flag indicating whether the variable set represents a formula
	 * @var bool
	 */
	private $isFormula = false;

	/**
	 * The name of the assertion type or null if a formula
	 * @var string
	 */
	private $assertionType = null;

	/**
	 * A list of consistency assertions defined
	 * @var array $consistencyAssertions
	 */
	private $consistencyAssertions = array();

	/**
	 * An instance in which a formula can accumulate facts, contexts and units
	 * @var GeneratedFacts $formulaFactsContainer
	 */
	private $formulaFactsContainer;

	/**
	 * Flag to control when evauation can take place.  For exaple, evaluation is pointless if there is no instance document.
	 * @var bool $canEvaluate
	 */
	private $canEvaluate = false;

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->log = XBRL_Log::getInstance();
		$this->nsMgr = new XmlNamespaceManager();
		$this->parameterQnames = array();
	}

	/**
	 * Returns the current set of consistency assertions defined for one or more formulas
	 * @return array
	 */
	public function getConsistencyAssertions()
	{
		return $this->consistencyAssertions;
	}

	/**
	 * Return the list of variable set.  This can be useful to access generated messages on an assertion variable set
	 */
	public function getVariableSets()
	{
		return $this->variableSets;
	}

	/**
	 * Process any formulas in $taxonomy against the array of instances in $instances
	 *
	 * @param array|XBRL_Instance $instances	A single or an array of XBRL_Instance instances
	 * @param array $additionalNamespaces		(optional) An array of namespaces indexed by prefix
	 * 											These could be from a test cases or other document
	 * @param array $contextParameters			A list of parameters to be added to the context
	 *
	 *	For each formula
	 *		Determine any parameters then evaluate them and add them to the variable set
	 *			The 'name' property identifies the parameters
	 *			Parameters without a select property will access a value of the same name from the XPath static context
	 *
	 *		Evaluate any qname expressions in filters using the parameters
	 *			This is a pre-evaluation static replacement of parameter names for the parameter's evaluated value
	 *
	 *		Determine the fact variables (these form the variable set)
	 *			The 'name' property on the arc overrides the name property of any parameters
	 *
	 *		Create a dependency hierarchy
	 *			The default dependency hierarchy will be flat and contain all fact variables in the variable set
	 *			Dependencies can arise because of variable references or because of references in general variables
	 *
	 *		Create context for the formula and add any evaluated parameters
	 *
	 *		Look for any group filters  <variable:variableSetFilterArc> and an http://xbrl.org/arcrole/2008/variable-set-filter arcrole
	 *		Create a default filter (no restrictions) and add any group filters
	 *		for the first variable in the dependency hierarchy
	 *			create an XPath statement
	 *				add group filters
	 *				add variable filters
	 *			evaluate the statement
	 *			for each fact
	 *
	 *				add the fact to the context
	 *
	 *				create a new filter
	 *					add group filters
	 *
	 *				for the next variable
	 *					if the variable is dependent
	 *						from the fact add aspect values that are not covered by a filter
	 *
	 *
	 * @return void
	 */
	public function processFormulasAgainstInstances( $instances, $additionalNamespaces = null, $contextParameters = null )
	{
		$this->canEvaluate = true;
		$result = true;

		if ( ! is_null( $instances ) && ! is_array( $instances ) )
		{
			$instances = array( $instances );
		}

		if ( is_null( $contextParameters ) )
		{
			$contextParameters = array();
		}

		foreach ( $instances as /** @var XBRL_Instance $xbrlInstance */ $xbrlInstance )
		{
			// Reset for this instance
			$instanceTaxonomy = $xbrlInstance->getInstanceTaxonomy();
			if ( ! $instanceTaxonomy->getHasFormulas( true ) ) return true;

			// BMS 2018-12-13
			$schemasWithFormulas = array_filter( $instanceTaxonomy->getImportedSchemas(), function( $taxonomy ) { return $taxonomy->getHasFormulas(); } );

			// // For now, take just one of the taxonomies with formulas
			// // $taxonomy = reset( $schemasWithFormulas );

			foreach ( $schemasWithFormulas as $namespace => $taxonomy )
			{
				$this->parameterQnames = array();
				$this->nsMgr = new XmlNamespaceManager();

				// Begin loading the namespaces
				$this->addNamespaces( array( XBRL_Constants::$standardPrefixes, $additionalNamespaces, $xbrlInstance->getInstanceNamespaces() ) );

				// Set the instance
				$this->instanceQName = qname( "instances:standard-input-instance", $this->nsMgr->getNamespaces() );
				$this->instanceQNames[ "standard-input-instance" ] = $this->instanceQName ;
				$this->instances[ (string)$this->instanceQName ] = $xbrlInstance;

				// Look for any non-standard instances
				$instanceResources = $taxonomy->getGenericResource( "variable", "instance" );
				foreach ( $instanceResources as $instanceKey => $instanceResource )
				{
					/**
					 * @var Instance $instance
					 */
					$instance = Instance::fromArray( $instanceResource['variable'] );
					$this->instanceQNames[ $instance->label ] = $instance->getQName();

					// TODO Add any extra instances instance
					// $name = $instance['variable']['name'];
					// Check there is an instance name in the list of global parameters
					$clark = $instance->getQName()->clarkNotation();
					// if ( ! isset( $contextParameters[ $clark ] ) )
					// {
					// 	XBRL_Log::getInstance()->formula_validation(
					// 		"Instance",
					// 		"A resource that references an instance QName does not exist as a global property",
					// 		array(
					// 			'QName' => $clark,
					// 			// 'error' => 'xbrlvarinste:missingInstanceProperty'
					// 			'error' => 'xbrlvarscopee:differentInstances'
					// 		)
					// 	);
					// }

					// $xbrlInstance = \XBRL_Instance::FromInstanceDocument( $contextParameters[ $clark ] );
					// $this->instances[ $clark ] = $xbrlInstance;
				}

				// // DO NOT DELETE
				// // This block is handy to run XPath 2.0 processor debug tests against an instance document
				// $formula = new Formula();
				// $formula->xbrlInstance = $xbrlInstance;
				// $formula->nsMgr = $this->nsMgr;
	            //
				// // $facts = $this->evaluateXPath( $formula, "xfi:non-nil-facts-in-instance(/xbrli:xbrl)[@contextRef]", array() );
				// // $facts = $this->evaluateXPath( $formula, "/xbrli:xbrl/concept:c1/@contextRef eq /xbrli:xbrl/concept:n1/@contextRef", array() );
				// // $facts = $this->evaluateXPath( $formula, "/", array() );
				// // $facts = $this->evaluateXPath( $formula, "/xbrli:xbrl/context[1]/@id", array() );
				// // $facts = $this->evaluateXPath( $formula, "/xbrli:xbrl", array() );
				// // $facts = $this->evaluateXPath( $formula, "/xbrli:xbrl/xbrli:context[@id=/xbrli:xbrl/context[1]/@id]/entity", array() );
				// // $facts = $this->evaluateXPath( $formula, "(for \$unitRef in (concat(/xbrli:xbrl/concept:C1[1]/@unitRef, '')) return \$unitRef)[1] cast as xs:string", array() );
				// // $facts = $this->evaluateXPath( $formula, "1 instance of node()", array() );
				// // $count = $facts->getCount();
	            //
				// $facts = $this->evaluateXPath( $formula, $query, array() );
				// $count = $facts->getCount();
				// foreach ( $facts as $fact )
				// {
				// 	$x = 1;
				// }
				// exit();

				if ( ! $this->validateCommon( $taxonomy, $contextParameters ) )
				{
					$result = false;
					// return false;
				}
			}
		}

		return $result;
		// return true;
	}

	/**
	 * Process any formulas in $taxonomy against the array of instances in $instances
	 *
	 * @param XBRL $taxonomy					The taxonomy containing the formula linkbase information
	 * @param array $additionalNamespaces		(optional) An array of namespaces indexed by prefix
	 * 											These could be from a test cases or other document
	 * @param array $contextParameters			A list of parameters to be added to the context
	 * @return void
	 */
	public function processFormulasForTaxonomy( $taxonomy, $additionalNamespaces = null, $contextParameters = null )
	{
		if ( ! $taxonomy->getHasFormulas() ) return;

		if ( is_null( $contextParameters ) )
		{
			$parameters = array();
		}

		XBRL_Instance::reset();

		// Begin loading the namespaces
		$this->addNamespaces( array( XBRL_Constants::$standardPrefixes, $additionalNamespaces, $taxonomy->getDocumentNamespaces() ) );

		// Set the instance
		$this->instanceQName = qname( "instances:standard-input-instance", $this->nsMgr->getNamespaces() );
		$this->instanceQNames[ $this->instanceQName->localName ] = $this->instanceQName;
		$this->instances[ $this->instanceQName->clarkNotation() ] = null;

		if ( ! $this->validateCommon( $taxonomy, $contextParameters ) )
		{
			return false;
		}
	}

	/**
	 * Allows the controller class to pass the expected result node to the test class(es) created
	 * Returns false if there is no problem or an error string to report if there is.
	 * @param string $testCaseFolder
	 * @param array $expectedResultNode The content of the <result> node from the test case.
	 * 									  The relevant test class will know how to handle its content.
	 * @return bool|string False if there is no error or a string that describes the failure
	 */
	public function compareResult( $testCaseFolder, $expectedResultNode )
	{
		if ( $this->consistencyAssertions )
		{
			foreach ( $this->consistencyAssertions as $assertionName => $consistencyAssertion )
			{
				$result = $consistencyAssertion->compareResult( $testCaseFolder, $expectedResultNode );
				if ( $result !== false ) return $result;
			}
		}

		// BMS 2018-03-30 Changed this so formulas are compared after any consistency assertion.
		// BMS 2018-04-03 But if there have been consistency assertions then only if there is a comparison instance to use
		if ( $this->formulaFactsContainer )
		{
			if ( ( ! $this->consistencyAssertions || $this->formulaFactsContainer->hasInstanceFile( $expectedResultNode ) ) )
			{
				if ( $this->formulaFactsContainer->compareResult( $testCaseFolder, $expectedResultNode, $this->instances[ $this->instanceQName->clarkNotation() ] ) )
				{
					return false; // False means no error
				}

				return $this->formulaFactsContainer->comparisonError;
			}
		}
		else
		{
			foreach ( $this->variableSets as $variableSetQName => $variableSetForQName )
			{
				foreach ( $variableSetForQName as $variableSet )
				{
					$result = $variableSet->compareResult( $testCaseFolder, $expectedResultNode );
					if ( $result !== false ) return $result;
				}
			}
		}

		return false;
	}

	/**
	 * Process the validations common to all variable sets
	 * @param XBRL $taxonomy
	 * @param array $contextParameters A list of the parameter values to be used as sources for formula parameters
	 * @return bool
	 */
	private function validateCommon( $taxonomy, $contextParameters )
	{
		// if ( ! $this->validateParameters( $taxonomy, $contextParameters ) )
		// {
		// 	return false;
		// }

		if ( ! $this->validateCustomFunction( $taxonomy ) )
		{
			return false;
		}

		if ( ! $this->validateVariableSets( $taxonomy, $contextParameters ) )
		{
			return false;
		}

		if ( ! $this->validateConsistencyAssertions( $taxonomy ) )
		{
			return false;
		}

		// validate default dimensions in instances and accumulate multi-instance-default dimension aspects (really, look in the contexts)

		// check for variable set dependencies across output instances produced

		return true;
	}

	/**
	 * Creates a namespace manager for a formula processor
	 * @param array $additionalNamespaces An array of namespace arrays (which is an array of namespaces indexed by prefix)
	 */
	private function addNamespaces( $additionalNamespaces )
	{
		// Load any additional namespaces
		foreach ( $additionalNamespaces as $namespaces )
		{
			if ( is_null( $namespaces ) || ! is_array( $namespaces ) )
			{
				continue;
			}

			foreach ( $namespaces as $prefix => $namespace )
			{
				if ( empty( $prefix ) ) continue;
				$this->nsMgr->addNamespace( $prefix, $namespace );
			}
		}
	}

	/**
	 * Validate and process parameters (if there are any)
	 * @param XBRL $taxonomy
	 * @param array $contextParameters A list of the parameter values to be used as sources for formula parameters
	 * @param VariableSet $variableSet
	 * @return bool
	 */
	private function validateParameters( $taxonomy, $contextParameters, $variableSet )
	{
		$parameterArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleVariableSet, $variableSet->extendedLinkRoleUri, null, $variableSet->path, null, $variableSet->linkbase );

		// Get any variable sets.  These will be headed by a formula.
		$parameters = array_filter( $taxonomy->getGenericResource( 'variable',  'parameter', null, null, null /*, $variableSet->linkbase */ ),
			function( $resource ) use( $parameterArcs )
			{
				return count( array_filter( $parameterArcs, function( $arc ) use( $resource )
				{
					return $arc['tolinkbase'] == $resource['linkbase'];
				} ) ) > 0;
			}
		);

		// Check there are parameters to process
		if ( ! $parameters ) return true;

		$parameterDependencies = array();

		foreach ( $parameters as $parameterReference )
		{
			/** @var Parameter $parameter */
			$parameter = Parameter::fromArray( $parameterReference['variable'] );
			if ( ! $parameter )
			{
				$this->log->err( "Failed to create Parameter instance from an array" );
				return false;
			}

			if ( $parameter->path != $variableSet->path ) continue;

			if ( ! $parameter->validate( null, $this->nsMgr ) )
			{
				return false;
			}

			$parameterDependencies[ $parameter->getQName()->clarkNotation() ] = $parameter->getVariableRefs();

			$this->parameterQnames[ $parameter->getQName()->clarkNotation() ] = $parameter;
			$variableSet->parameters[ $parameter->getQName()->clarkNotation() ] = $parameter;
		}

		/**
		 * Examines parameter dependcies
		 * @var Function $hasCircularReference
		 */
		$parameterQnames = $this->parameterQnames;
		$hasCircularReference = function( $dependencies, $history = array() ) use( &$hasCircularReference, $parameterDependencies, $parameterQnames )
		{
			foreach ( $dependencies as /** @var QName $dependency */ $dependency )
			{
				// Check if this is a cyclic reference
				if ( in_array( $dependency, $history ) )
				{
					$this->log->formula_validation( 'Variable parameter', 'Cyclic dependencies in parameter names to names dependencies', array(
						'parameter' => end( $history ),
						'dependency' => $dependency->clarkNotation(),
						'error' => 'xbrlve:parameterCyclicDependencies'
					) );

					return true;
				}

				// The dependency MUST appear in $parameterQnames or it is undefined
				if ( ! isset( $parameterQnames[ $dependency->clarkNotation() ] ) )
				{
					$this->log->formula_validation( 'Variable parameter', 'Undefined dependencies in parameter to names dependencies', array(
						'parameter' => end( $history ),
						'dependency' => $dependency->clarkNotation(),
						'error' => 'xbrlve:unresolvedDependency'
					) );
					return true;
				}

				// Look for the dependency in $parameterDependencies
				if ( ! isset( $parameterDependencies[ $dependency->clarkNotation() ] ) )
				{
					continue;
				}

				// Check the next dependencies
				$history[] = $dependency->clarkNotation();
				if ( $hasCircularReference( $parameterDependencies[ $dependency->clarkNotation() ], $history ) )
				{
					return true;
				}
			}

			return false;
		};

		// Perform the dependency check
		foreach ( $parameterDependencies as $qname => $dependencies )
		{
			if ( $hasCircularReference( $dependencies, array( $qname ) ) )
			{
				return false;
			}
		}

		// Check there is a context parameter for each formula parameter that is marked as required
		foreach ( $this->parameterQnames as $clark => /** @var Parameter $parameter */ $parameter )
		{
			// If the parameter references a qname from the the instance then there is nothing to do here
			if ( $taxonomy->getElementByName( $parameter->getQName()->localName ) ) continue;

			try
			{
				// Check to see if the parameter is one of the context (passed in) parameters
				if ( isset( $contextParameters[ $clark ] ) )
				{
					// Test the compatibility of the context parameter type and value
					$parameter->select = $contextParameters[ $clark ]['value'];
					$expression = XPath2Expression::Compile( "{$contextParameters[ $clark ]['datatype']}({$contextParameters[ $clark ]['value']}) cast as {$contextParameters[ $clark ]['datatype']}", $this->nsMgr );
					$value = $expression->EvaluateWithVars( null, array() );
					$parameter->expression = $expression;

					// Now check the $value type is compatible with the parameter type.
					// The evaluation step will throw an exception if not
					if ( $parameter->as )
					{
						$expression = XPath2Expression::Compile( "\$value cast as {$parameter->as}", $this->nsMgr );
						$result = $expression->EvaluateWithVars( null, array( 'value' => $value ) );
						// Replace with casted expression if valid
						// $parameter->expression = $expression;
					}
				}
				else if ( $parameter->required )
				{
					$this->log->formula_validation( 'Variable parameter', 'Parameter is required but not input', array(
						'parameter' => $clark,
						'error' => 'xbrlve:missingParameterValue'
					) );

					return false;
				}
				else if ( ! isset( $parameter->select ) || ! strlen( $parameter->select ) )
				{
					$this->log->formula_validation( 'Variable parameter', 'Parameter does not have a select attribute', array(
						'parameter' => $clark,
						'error' => 'xbrlve:missingParameterValue'
					) );

					return false;
				}
			}
			catch ( Exception $ex )
			{
				$this->log->formula_validation( 'Variable parameter', 'The input parameter type is not valid', array(
					'parameter' => $clark,
					'cause' => $ex->getMessage(),
					'error' => 'xbrlve:parameterTypeMismatch'
				) );

				return false;
			}
		}

		// TODO This needs to be checked
		// There should be no dependencies so this routine should resolve all parameter expressions to result values.
		$parameters = $this->parameterQnames;
		while ( $parameters )
		{
			// Get the first parameter
			$parameter = reset( $parameters );
			unset( $parameters[ key( $parameters ) ] );

			 // Get a list of dependencies for this parameter
			$dependencies = $parameter->getVariableRefs();

			$vars = array();

			// Make sure that each dependency has been evaluated
			foreach ( $dependencies as $qname => $dependentQName )
			{
				if ( ! isset( $this->parameterQnames[ $dependentQName->clarkNotation() ] ) )
				{
					continue;
				}

				$dependentParameter = $this->parameterQnames[ $dependentQName->clarkNotation() ];
				if ( ! $dependentParameter->result ) continue;

				$vars[ $dependentParameter->name ] = $dependentParameter->result;
			}

			try
			{
				$result = $parameter->expression->EvaluateWithVars( null, $vars );
				$parameter->result = $result;
			}
			catch ( Exception $ex )
			{
				$this->log->formula_validation( 'Variable parameter', 'Parameter type is not valid', array(
					'parameter' => $parameter->getQName()->clarkNotation(),
					'select' => $parameter->getSelectAsExpression(),
					'as' => $parameter->as,
					'cause' => $ex->getMessage(),
					'error' => 'xbrlve:parameterTypeMismatch'
				) );
			}

		}

		return true;
	}

	/**
	 * Process and validate any instances defined.  The default instances is
	 * passed in automatically and is associated with the parameter with QNme
	 * '{http://xbrl.org/2010/variable/instance"}standard-input-instance'
	 * so ignore it.
	 * @param XBRL $taxonomy
	 * @param array $contextParameters A list of the parameter values to be used as sources for formula parameters
	 * @return bool
	 */

	/**
	 * Process and validate the variables in the current taxonomy
	 * @param XBRL $taxonomy
	 * @return bool
	 */
	private function validateVariableSets( $taxonomy, $contextParameters )
	{
		// Variable sets are headed by a formula or assertion
		$variableSets = $taxonomy->getGenericResource( 'variableset', null );

		foreach ( $variableSets as $index => $variableSet )
		{
			$variableSetResource = $variableSet['variableset'];

			// Workout the class for this variable set resource
			$element = $variableSetResource[ "{$variableSetResource['type']}Type" ];
			$variableSetClassName = '\\XBRL\\Formulas\\Resources\\' .
				Formulas::$formulaElements[ $element ]['part'] .
				( isset( Formulas::$formulaElements[ $element ]['className'] )
					? Formulas::$formulaElements[ $element ]['className']
					: ucfirst( $element )
				);
			if ( is_null( $variableSetClassName ) )
			{
				$this->log->err( "Variable set sub-type '{$variableSetResource['type']}Type' is not valid" );
				return false;
			}

			// Create the instance and store it
			/** @var VariableSet $variableSetInstance */
			unset( $variableSetInstance );
			$variableSetInstance = $variableSetClassName::fromArray( $variableSetResource );
			$variableSetInstance->instanceName = $this->instanceQName; // Set a default. It may be overridden by an instance arc
			$variableSetInstance->xbrlTaxonomy = $taxonomy;
			$variableSetInstance->extendedLinkRoleUri = $variableSet['roleUri'];
			$variableSetInstance->linkbase = $variableSet['linkbase'];

			$this->variableSets[ "{$variableSet['roleUri']}#{$variableSet['resourceName']}" ][] =& $variableSetInstance;

			$linkbaseInfo = $taxonomy->getLinkbase( $variableSet['linkbase'] );
			if ( $linkbaseInfo )
			{
				$this->addNamespaces( array( $linkbaseInfo['namespaces'] ) );
			}

			$this->validateLabels( $taxonomy, $variableSetInstance, $taxonomy->getDefaultLanguage() );

			$this->validateParameters( $taxonomy, $contextParameters, $variableSetInstance );

			// Find all variables related to this variable set
			$instanceArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleFormulaInstance, $variableSet['roleUri'], $variableSet['resourceName'], $variableSetInstance->path, $variableSetInstance->linkbase );
			foreach ( $instanceArcs as $instanceArc )
			{
				if ( $instanceArc['from'] != $variableSetInstance->label ) continue;
				// Look for the instance resource
				if ( ! isset( $this->instanceQNames[ $instanceArc['label'] ] ) ) continue;
				$variableSetInstance->instanceName = $this->instanceQNames[ $instanceArc['label'] ];
			}

			// Find all variables related to this variable set
			$variableSetArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleVariableSet, $variableSet['roleUri'], $variableSet['resourceName'], $variableSetInstance->path /* , $variableSetInstance->linkbase */ );
			$variableResources = array();

			foreach ( $variableSetArcs as $arcIndex => $variableSetArc )
			{
				if ( $variableSetArc['fromlinkbase'] != $variableSetInstance->linkbase ) continue;
				if ( $variableSetInstance->path != $variableSetArc['frompath'] ) continue;

				$result = $taxonomy->getGenericResource( 'variable', null, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$variableResources, $variableSetArc )
				{
					// This test is probably redundant because I believe $variableSetName is the same as $variableSetArc['label']
					if ( /* $linkbase == $variableSetArc['tolinkbase'] && */ $resource['path'] == $variableSetArc['topath'] && $variableSetName == $variableSetArc['label'] )
					{
						$resource['name'] = $variableSetArc['name'];
						$resource['linkbase'] = $linkbase;
						$variableResources[ $variableSetName ][] = $resource;
					}

					return true;
				}, $variableSetArc['toRoleUri'], $variableSetArc['to'], $variableSetArc['tolinkbase'] );
			}

			// Process each located variable resource
			foreach ( $variableResources as $variableLabel => $variableResourceSet )
			{
				foreach ( $variableResourceSet as $variableResource )
				{
					// if ( $variableSetInstance->path != $variableResource['path'] )
					// 	continue;

					if ( $variableResource['variableType'] == 'parameter' )
					{
						// Find the parameter with label $variableResource['label']. This becomes
						// the parameter with qname $variableResource['name']
						$resourceQName = new QName( $variableResource['name']['originalPrefix'], $variableResource['name']['namespace'], $variableResource['name']['name'] );
						foreach ( $this->parameterQnames as $qname => $parameter )
						{
							if ( $parameter->label == $variableResource['label'] )
							{
								// BMS 2018-03-23 One test too many
								// if ( $qname == $resourceQName->clarkNotation() )
								{
									$variableSetInstance->parameters[ $resourceQName->clarkNotation() ] = $this->parameterQnames[ $qname ];
								}
								break;
							}
						}

						continue;
					}

					$variableClassName = '\\XBRL\\Formulas\\Resources\\' .
						Formulas::$formulaElements[ $variableResource['variableType'] ]['part'] .
						( isset( Formulas::$formulaElements[ $variableResource['variableType'] ]['className'] )
							? Formulas::$formulaElements[ $variableResource['variableType'] ]['className']
							: ucfirst( $variableResource['variableType'] )
						);

					if ( is_null( $variableClassName ) )
					{
						$this->log->formula_validation( "Variables", "Invalid variable type",
							array(
								'variable type' => $resource['variableType'],
							)
						);
						return false;
					}

					/** @var \XBRL\Formulas\Resources\Variables\Variable $variable */
					$variable = $variableClassName::fromArray( $variableResource );
					$variable->extendedLinkRoleUri = $variableResource['linkRoleUri'];

					// Add labels
					$this->validateLabels( $taxonomy, $variable, $taxonomy->getDefaultLanguage() );

					// Instances
					// Find all instances related to this variable.  The variable-instance
					// arcs are back-to-front as the arcrole name suggests so get all the
					// arcs and find a suitable one if it exists
					$instanceArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleInstanceVariable, $variable->extendedLinkRoleUri, null, $variable->path, null, $variable->linkbase );
					foreach ( $instanceArcs as $instanceArc )
					{
						if ( $instanceArc['to'] != $variable->label ) continue;
						// Look for the arc source among the valid instance qnames
						if ( ! isset( $this->instanceQNames[ $instanceArc['from'] ] ) ) continue;

						$variable->instanceSourceQName = $this->instanceQNames[ $instanceArc['from'] ];
						break;
					}

					if ( is_null( $variable->instanceSourceQName ) )
					{
						$variable->instanceSourceQName = $this->instanceQNames['standard-input-instance'];
					}

					if ( ! $variableSetInstance->AddVariable( $variable ) )
					{
						return false;
					}

					// This is necessary because $variable is used as a reference.
					// Without this subsequent variables replace earlier ones.
					unset( $variable );
				}
			}

			// BMS 2018-01-25 Moved these here so they can be checked when all variables have been created in case there are interdependencies
			foreach ( $variableSetInstance->variablesByQName as $qname => $variable )
			{
				// Time to pull in the filters for fact variables (general variables do not have filters)
				if ( $variable instanceof FactVariable && ! $this->validateFilters( $taxonomy, XBRL_Constants::$arcRoleVariableFilter, $variable, $variableSetInstance ) )
				{
					return false;
				}

				if ( ! $variable->validate( $variableSetInstance, $this->nsMgr ) )
				{
					return false;
				}

			}

			// Pull in any group filters
			// if ( ! $this->validateGroupFilters( $variableSetInstance ) )
			if ( ! $this->validateFilters( $taxonomy, XBRL_Constants::$arcRoleVariableSetFilter, $variableSetInstance, $variableSetInstance ) )
			{
				return false;
			}

			// Check variable scopes for visible variables
			if ( ! $this->validateVariableScopes( $taxonomy, $variableSetInstance ) )
			{
				return false;
			}

			// Now the variables are known validate the formula
			if ( ! $variableSetInstance->validate( null, $this->nsMgr ) )
			{
				return false;
			}

			$variableSetInstance->validateMessages( $taxonomy->getDefaultLanguage() );

			// The variable refs MUST either refer to variables or parameters
			$variableSetRefs = $variableSetInstance->getVariableRefs();

			foreach ( $variableSetRefs as $qname => $variableSetRefQName )
			{
				// Look for a variable with this name
				if ( ! is_null( $variableSetRefQName ) && isset( $variableSetInstance->variablesByQName[ $variableSetRefQName->clarkNotation() ] ) )
				{
					continue;
				}

				if ( ! is_null( $variableSetRefQName ) && isset( $variableSetInstance->parameters[ $variableSetRefQName->clarkNotation() ] ) )
				{
					// Parameters do not contribute the definition of the dependency tree
					unset( $variableSetRefs[ $qname ] );
					continue;
				}

				// BMS 2018-02-27 See note above with the same date about a line commented out
				// Could be a reference to a global parameter.  If so, add the global parameter.
				if ( isset( $this->parameterQnames[ $variableSetRefQName->clarkNotation() ] ) )
				{
					// Parameters do not contribute the definition of the dependency tree
					$variableSetInstance->parameters[ $variableSetRefQName->clarkNotation() ] = $this->parameterQnames[ $variableSetRefQName->clarkNotation() ];
					unset( $variableSetRefs[ $qname ] );
					continue;
				}

				// Could be a reference to scope dependency
				if ( isset( $variableSetInstance->scopeDependencies[ $variableSetRefQName->clarkNotation() ] ) )
				{
					unset( $variableSetRefs[ $qname ] );
					continue;
				}
				// Are variable references that are not not exist as variables or parameters in the variable set
				unset( $variableSetRefs[ $qname ] );
				$this->log->formula_validation( "Variable set", "Variable reference does not not exist as a variable or parameter in the variable set",
					array(
						'reference' => (string)$variableSetRefQName,
						'error' => 'xbrlve:unresolvedDependency'
					)
				);
			}

			// Create a variables dependency tree
			$variableRefs = array();
			foreach ( $variableSetInstance->variablesByQName as $qname => $variable )
			{
				$variableRefs[ $qname ] = array();
				foreach ( $variable->getVariableRefs() as $variableRefQName )
				{
					// If this is not a reference to a variable ignore because non-variable,
					// such as parameters, do not affect the dependency
					if ( isset( $variableSetInstance->variablesByQName[ $variableRefQName->clarkNotation() ] ) )
					{
						$variableRefs[ $qname ][] = $variableRefQName;
					}
				}
			}

			// Create a hierarchy from the remainder.
			$hierarchy = array();
			$roots = array();

			foreach ( $variableRefs as $dependent => $users )
			{
				if ( ! count( $users ) )
				{
					if ( ! isset( $roots[ $dependent ] ) )
					{
						$roots[ $dependent ] = array();
					}

					if ( ! isset( $hierarchy[ $dependent ] ) )
					{
						$hierarchy[ $dependent ] = array();
					}

					continue;
				}

				foreach ( $users as /** @var QName $userQName */ $userQName )
				{
					$user = $userQName->clarkNotation();

					if ( ! isset( $hierarchy[ $user ] ) )
					{
						$roots[ $user ] = array();
					}

					if ( isset( $roots[ $dependent ] ) )
					{
						unset( $roots[ $dependent ] );
					}

					if ( ! isset( $hierarchy[ $dependent ] ) )
					{
						$hierarchy[ $dependent ] = array();
					}

					$hierarchy[ $user ][ $dependent ] =& $hierarchy[ $dependent ];
				}
			}

			// If there are nodes but no roots then there is a cyclic problem
			if ( $hierarchy && ! $roots )
			{
				$links = array();
				foreach ( $variableRefs as $dependent => $users )
				{
					foreach ( $users as /** @var QName $userQName */ $userQName )
					{
						$user = $userQName->clarkNotation();
						$links[] = "{$userQName->clarkNotation()}->$dependent";
					}
				}

				$this->log->formula_validation( "Variables", "There is a cyclic dependency between the variable references",
					array(
						'variables' => implode( ", ", $links ),
						'error' => 'xbrlve:cyclicDependencies',
					)
				);

				return false;
			}

			$variableSetInstance->variableOrderHierarchy = array_intersect_key( $hierarchy, $roots );

			// check existence assertion @test variable dependencies (not including precondition references)

	        // check messages variable dependencies

	        // check preconditions
			if ( ! $this->validatePreconditions( $taxonomy, $variableSetInstance ) )
			{
				// return false;
			}

			unset( $variableSetInstance );
		}

		// Make sure formulas are executed in the correct order if there are scope dependencies
		$scopeDependencies = array();
		foreach ( $this->variableSets as $variableSetInstances )
		{
			foreach ( $variableSetInstances as $variableSetInstance )
			{
				$toVariableSetKey = "{$variableSetInstance->extendedLinkRoleUri}#{$variableSetInstance->label}";

				if ( $variableSetInstance->scopeDependencies )
				{
					foreach ( $variableSetInstance->scopeDependencies as $qname => $scopeDependency )
					{
						$fromVariableSetKey = "{$scopeDependency['fromRoleUri']}#{$scopeDependency['from']}";

						if ( ! isset( $this->variableSets[ $fromVariableSetKey ] ) )
						{
							$this->log->formula_validation( "variable sets", "The scope dependency formula does not exist",
								array(
									'from formula' => $scopeDependency['from'],
									'to formula' => '$variableSetInstance->label',
									'error' => 'xbrlve:dependentFormulaNotFound'
								)
							);
						}

						$scopeDependencyVariableSet = $this->variableSets[ $fromVariableSetKey ];

						if ( ! ( ( $variableSetInstance instanceof Formula && $scopeDependencyVariableSet[0] instanceof Formula ) ||
								 ( get_class( $variableSetInstance ) == get_class( $scopeDependencyVariableSet[0] ) )
							   )
						)
						{
							$this->log->formula_validation( "variable sets", "The variable set type of the source and target must be the same (formula or one of the assertion type)",
								array(
									'from formula' => $scopeDependency['from'],
									'to formula' => '$variableSetInstance->label',
									'error' => 'xbrlvarscopee:conflictingVariableSet types'
								)
							);
						}

						if ( $variableSetInstance->aspectModel != $scopeDependencyVariableSet[0]->aspectModel )
						{
							$this->log->formula_validation( "variable sets", "The aspect models of the formulas linked by a scope dependency are different",
								array(
									'from formula' => $scopeDependency['from'],
									'to formula' => $variableSetInstance->label,
									'error' => 'xbrlvarscopee:conflictingAspectModels'
								)
							);
						}

						if ( $variableSetInstance->instanceName != $scopeDependencyVariableSet[0]->instanceName )
						{
							$this->log->formula_validation( "variable sets", "The instancs of the scope source and target must be the same",
								array(
									'from formula' => $scopeDependency['from'],
									'to formula' => $variableSetInstance->label,
									'error' => 'xbrlvarscopee:differentInstances'
								)
							);
						}

						$scopeDependencies[ $toVariableSetKey ][] = $fromVariableSetKey;
					}
				}
				else
				{
					// Just add the variable set
					$scopeDependencies[ $toVariableSetKey ] = array();
				}
			}
		}

		// Create a hierarchy of the formulas.
		$hierarchy = array();
		$roots = array();

		foreach ( $scopeDependencies as $dependent => $users )
		{
			if ( ! count( $users ) )
			{
				if ( ! isset( $roots[ $dependent ] ) )
				{
					$roots[ $dependent ] = array();
				}

				if ( ! isset( $hierarchy[ $dependent ] ) )
				{
					$hierarchy[ $dependent ] = array();
				}

				continue;
			}

			foreach ( $users as $user )
			{
				// $user = $userQName->clarkNotation();

				if ( ! isset( $hierarchy[ $user ] ) )
				{
					$roots[ $user ] = array();
				}

				if ( isset( $roots[ $dependent ] ) )
				{
					unset( $roots[ $dependent ] );
				}

				if ( ! isset( $hierarchy[ $dependent ] ) )
				{
					$hierarchy[ $dependent ] = array();
				}

				$hierarchy[ $user ][ $dependent ] =& $hierarchy[ $dependent ];
			}
		}

		// If there are nodes but no roots then there is a cyclic problem
		if ( $hierarchy && ! $roots )
		{
			$links = array();
			foreach ( $variableRefs as $dependent => $users )
			{
				foreach ( $users as /** @var QName $userQName */ $userQName )
				{
					$user = $userQName->clarkNotation();
					$links[] = "{$userQName->clarkNotation()}->$dependent";
				}
			}

			$this->log->formula_validation( "Variables", "There is a cyclic dependency between the formula scope variable references",
				array(
					'variables' => implode( ", ", $links ),
					'error' => 'xbrlve:cyclicDependencies',
				)
			);

			return false;
		}

		$orderedVariables = array();
		$flatten = function( $hierarchy, $parent = null ) use ( &$flatten, &$orderedVariables )
		{
			// $results = array();
			$parents[] = $parent;
			foreach ( $hierarchy as $variable => $dependents )
			{
				// If the variable already exists, remove and re-add so it is appended (moved down the list)
				if ( isset( $orderedVariables[ $variable ] ) )
				{
					$parents = array_merge( $parents, $orderedVariables[ $variable ] );
					unset( $orderedVariables[ $variable ] );
				}
				$orderedVariables[ $variable ] = $parents;
				if ( ! count( $dependents ) ) continue;
				$flatten( $dependents, $variable );
			}
			// return $results;
		};

		$flatten( array_intersect_key( $hierarchy, $roots ) );

		unset( $variableSetInstance );

		// foreach ( $this->variableSets as $variableSetInstances )
		foreach ( $orderedVariables as $formulaQName => $x )
		{
			if ( ! isset( $this->variableSets[ $formulaQName ] ) )
			{
				$this->log->formula_validation( "variables sets", "The dependent formula does not exist",
					array(
						'formula' => $variableSetInstance->label,
						'error' => 'xbrlve:missingFormula'
					)
				);
			}

			$variableSetInstances = $this->variableSets[ $formulaQName ];

			foreach ( $variableSetInstances as $variableSetInstance )
			{
		        // check for variable sets referencing fact or general variables
		        if ( $variableSetInstance->scopeDependencies )
		        {
		        	// Access the variable set containing the scope variable
		        	foreach ( $variableSetInstance->scopeDependencies as $scopeVariableQName => $scopeDependency )
		        	{
						$fromVariableSetKey = "{$scopeDependency['fromRoleUri']}#{$scopeDependency['from']}";
						$scopeVariableSetInstances = $this->variableSets[ $fromVariableSetKey ];

						// Allow for many
						foreach ( $scopeVariableSetInstances as $scopeVariableSetInstance )
						{
							// Copy the evaluation result(s) and the variable binding(s)to this instance
							if ( $variableSetInstance instanceof Formula )
							{
								// Begin by removing any ScopeVariable variables.  These were added to get by the variable set validation.
								foreach ( $variableSetInstance->variablesByQName as $variableQName => $variable )
								{
									if ( ! $variable instanceof ScopeVariable || ! is_null( $variable->binding ) ) continue;
									unset( $variableSetInstance->variablesByQName[ $variableQName ] );
									// Also remove from the variable order list
									unset( $variableSetInstance->variableOrderHierarchy[ $variableQName ] );
								}

								// Now create a variable for the scope variable fact
								$variable = new ScopeVariable();
								$variable->label = $scopeVariableQName;
								$variable->extendedLinkRoleUri = $scopeVariableSetInstance->extendedLinkRoleUri;
								$variable->name = $scopeDependency['name'];
								// Add the variable
								$variableSetInstance->variablesByQName[ $scopeVariableQName ] = $variable;
								// add a reference to the variable to the beginning of the list of
								// order variables so it is processed before any others
								$variableSetInstance->variableOrderHierarchy = array_reverse( $variableSetInstance->variableOrderHierarchy, true );
								$variableSetInstance->variableOrderHierarchy[ $scopeVariableQName ] = array();
								$variableSetInstance->variableOrderHierarchy = array_reverse( $variableSetInstance->variableOrderHierarchy, true );

								// Create a binding for each result
								if ( $variableSetInstance->evaluationResults )
								{
									$variableSetInstance->evaluationResults = array_merge( $variableSetInstance->evaluationResults, $scopeVariableSetInstance->evaluationResults );
								}
								else
								{
									$variableSetInstance->evaluationResults = $scopeVariableSetInstance->evaluationResults;
								}

								$binding = new ScopeVariableBinding( $scopeVariableSetInstance->xbrlInstance, $variable );

								// Create an iterator from the facts
								if ( $scopeVariableSetInstance->evaluationResults )
								{
									// Only take evaluation results generated by the source variable set
									$items = array_filter( $variableSetInstance->evaluationResults,
										function( $evaluationResult ) use( $scopeDependency )
										{
											return $evaluationResult['variableSet'] == $scopeDependency['from'];
										}
									);
									$facts = DocumentOrderNodeIterator::fromItemset( $items );
									$binding->facts = $facts;
								}

								$variable->binding = $binding;
				        		// $variableSetInstance->factVariableBindings[ $variableQName ] = $binding;
				        	}
				        	else
				        	{
				        		// TODO
				        	}
						}
		        	}
		        }

				// Evaluate the variable set
				if ( ! $this->evaluate( $variableSetInstance ) )
				{
					return false;
				}
			}
		}

		if ( $this->formulaFactsContainer )
		{
			$document = $this->formulaFactsContainer->generateInstanceDocument( $this->instances[ $this->instanceQName->clarkNotation() ], $this->nsMgr );
		}

		return true;
	}

	/**
	 * Look for and validate any variable scope arcs
	 * @param \XBRL $taxonomy
	 * @param VariableSet $variableSetInstance
	 */
	private function validateVariableScopes( $taxonomy, $variableSetInstance )
	{
		$scopeArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleVariablesScope, $variableSetInstance->extendedLinkRoleUri, null, $variableSetInstance->path, null, $variableSetInstance->linkbase );

		if ( ! $scopeArcs ) return true;

		// Include any scope variables referenced directly from this variable set or indirectly because
		// because the variable referenced by this variable set references another variable set and so on
		$label = $variableSetInstance->label;
		$labels = array();
		while (true)
		{
			$found = false;
			$labels[] = $label;
			foreach ( $scopeArcs as $scopeArc )
			{
				if ( ! isset( $scopeArc['to'] ) || $scopeArc['to'] != $label ) continue;
				// This is a different kind of variable
				$scopeQName = new QName( $scopeArc['name']['originalPrefix'], $scopeArc['name']['namespace'], $scopeArc['name']['name'] );
				$found = true;
				break;
			}

			if ( ! $found ) break;
			if ( isset( $variableSetInstance->scopeDependencies[ $scopeQName->clarkNotation() ] ) )
			{
				// Cyclic relationship detected
				$this->log->formula_validation( "Scope variables", "The scope variables include a cyclic dependency",
					array(
						'error' => 'xbrl21:directedCycleError',
						'variable sets' => implode( ", ", $labels ) . ", ..."
					)
				);
			}

			$variableSetInstance->scopeDependencies[ $scopeQName->clarkNotation() ] = $scopeArc;
			if ( ! isset( $scopeArc['from'] ) ) break;
			$label = $scopeArc['from'];
		}

		// Now add any variables from the source variable set so they can be included in this variable set's validation
		foreach ( $variableSetInstance->scopeDependencies as $qname => $scopeDependency )
		{
			$fromVariableSetKey = "{$scopeDependency['fromRoleUri']}#{$scopeDependency['from']}";

			$scopeVariableInstances = $this->variableSets[ $fromVariableSetKey ];
			// look for any variables
			foreach ( $scopeVariableInstances as $scopeVariableInstanceKey => $scopeVariableInstance )
			{
				foreach ( $scopeVariableInstance->variablesByQName as $variableQName => $variable )
				{
					// Create a dummy variable. It will be overridden later on.
					if ( isset( $variableSetInstance->variablesByQName[ $variableQName ] ) ) continue;
					$variableSetInstance->variablesByQName[ $variableQName ]  = new ScopeVariable();
				}
			}
		}

		return true;
	}

	/**
	 * Add the label to a resource
	 * @param XBRL $taxonomy
	 * @param Variable|AssertionSet $target
	 * @param string $lang
	 * @return boolean
	 */
	private function validateLabels( $taxonomy, $target, $lang = 'en' )
	{
		$labelArcs = $taxonomy->getGenericArc( XBRL_Constants::$genericElementLabel, $target->extendedLinkRoleUri, $target->label, $target->path, null, $target->linkbase );
		if ( ! $labelArcs ) return true;

		// Find the filter resources
		foreach ( $labelArcs as $labelArc )
		{
			if ( $labelArc['path'] != $target->path ) continue;

			// TODO Handle preferred label by taking and using the preferred label role from the arc
			$result = $taxonomy->getGenericLabel( XBRL_Constants::$genericRoleLabel, $labelArc['to'], substr( $lang, 0, 2 ), $target->path );

			if ( ! $result ) continue;

			// The should be only one but make sure by taking the first.
			$textLabel = reset( $result );
			$target->description = $textLabel['text'];
		}

		return true;
	}

	/**
	 * Process all the variable filters that are the target of an arc.
	 * @param XBRL $taxonomy
	 * @param string $arcRole
	 * @param Variable $variable (by reference)
	 * @param VariableSet $variableSet
	 * @return bool
	 */
	private function validateFilters( $taxonomy, $arcRole, &$variable, $variableSet )
	{
		// Find all filter arcs related to this variable
		$variableFilterArcs = $taxonomy->getGenericArc( $arcRole, $variable->extendedLinkRoleUri, $variable->label, $variable->path, null, $variable->linkbase );

		if ( ! $variableFilterArcs ) return true;

		// Find the filter resources
		$filterResources = array();

		// BMS 2018-06-29 Changed this function so the arc for loop is on the outside so it can take advantage of indexing of the resource collections
		// $result = $taxonomy->getGenericResource( 'filter', null, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$filterResources, $variableFilterArcs )
		// {
		// 	foreach ( $variableFilterArcs as $arcIndex => $variableFilterArc )
		// 	{
		// 		if ( $roleUri == $variableFilterArc['toRoleUri'] )
		// 		if ( $variableSetName == $variableFilterArc['label'] )
		// 		{
		// 			$resource['linkbase'] = $linkbase;
		// 			if ( $variableFilterArc['attributes'] )
		// 			{
		// 				foreach ( $variableFilterArc['attributes'] as $name => $attribute )
		// 				{
		// 					// BMS 2018-04-09 Test candidates changed.
		// 					$value = $attribute['type'] == "xs:boolean"
		// 						? (bool)$attribute['value']
		// 						: $attribute['value'];
		// 					$resource[ $name ] = $value;
		// 				}
		// 			}
		// 			$filterResources[ $variableSetName ][] = $resource;
		// 		}
		// 	}
		// 	return true;
		// } );

		foreach ( $variableFilterArcs as $arcIndex => $variableFilterArc )
		{
			if ( $variable->path == $variableFilterArc['frompath'] )
			$result = $taxonomy->getGenericResource( 'filter', null, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$filterResources, $variableFilterArc )
			{
				// Don't believe this is necessary as $variableSetName is $variableFilterArc['label']
				// if ( $variableSetName = $variableFilterArc['label'] )
				if ( $resource['path'] != $variableFilterArc['topath'] /* || $linkbase != $variableFilterArc['linkbase'] */ ) return true;

				$resource['linkbase'] = $linkbase;
				if ( $variableFilterArc['attributes'] )
				{
					foreach ( $variableFilterArc['attributes'] as $name => $attribute )
					{
						// BMS 2018-04-09 Test candidates changed.
						$value = $attribute['type'] == "xs:boolean"
							? (bool)$attribute['value']
							: $attribute['value'];
						$resource[ $name ] = $value;
					}
				}
				$filterResources[ $variableSetName ][] = $resource;

				return true;
			}, $variableFilterArc['toRoleUri'], $variableFilterArc['to'], $variableFilterArc['tolinkbase'] );
		}

		foreach ( $filterResources as $variableFilterLabel => $filterResourceSet )
		{
			foreach ( $filterResourceSet as $filterResource )
			{
				// if ( $variable->path != $filterResource['path'] ) continue;

				// If this is a group filter make sure the arc is not covering
				if ( $arcRole == XBRL_Constants::$arcRoleVariableSetFilter )
				{
					if ( isset( $filterResource['cover'] ) && $filterResource['cover'] )
					{
						$this->log->formula_validation( "Filter", "Group filters MUST not cover",
							array(
								'filter type' => $filterResource['filterType'],
								'label' => $filterResource['label'],
								'error' => 'lyquidity:variableSetFilterCovered',
							)
						);
					}
				}

				// Create the filter instance
				$filterClassName = '\\XBRL\\Formulas\\Resources\\' .
					Formulas::$formulaElements[ $filterResource['filterType'] ]['part'] .
					( isset( Formulas::$formulaElements[ $filterResource['filterType'] ]['className'] )
						? Formulas::$formulaElements[ $filterResource['filterType'] ]['className']
						: ucfirst( $filterResource['filterType'] )
					);

				if ( is_null( $filterClassName ) )
				{
					$this->log->formula_validation( "Filter", "Invalid filter type",
						array(
							'Filter type' => $resource['filterType'],
						)
					);
					return false;
				}

				if ( $variableSet->aspectModel == "non-dimensional" &&
					 in_array( $filterResource['filterType'], array( 'explicitDimension', 'typedDimension' ) )
				)
				{
					$this->log->formula_validation( "Filter", "Dimension filters not allowed when using the non-dimensional aspect model",
						array(
							'aspect model' => $variableSet->aspectModel,
							'filter type' => $filterResource['filterType'],
							'label' => $filterResource['label'],
							'error' => 'xbrlve:filterAspectModelMismatch',
						)
					);
				}
				else if ( $variableSet->aspectModel == "dimensional" &&
					      in_array( $filterResource['filterType'], array( 'scenario', 'segment' ) )
				)
				{
					$this->log->formula_validation( "Filter", "Non-dimensional filters not allowed when using the dimensional aspect model",
						array(
							'aspect model' => $variableSet->aspectModel,
							'filter type' => $filterResource['filterType'],
							'label' => $filterResource['label'],
							'error' => 'xbrlve:filterAspectModelMismatch',
						)
					);
				}

				/** @var \XBRL\Formulas\Resources\Filters\Filter $filter */
				$filter = $filterClassName::fromArray( $filterResource );

				if ( ! $filter->validate( $variableSet, $this->nsMgr ) )
				{
					return false;
				}

				// If this is a group filter then make sure the filter does not reference any
				if ( $arcRole == XBRL_Constants::$arcRoleVariableSetFilter )
				{
					// There should be no variable refs
					$refs = $filter->getVariableRefs();
					if ( $refs )
					{
						foreach ( $refs as $refQName )
						{
							if ( isset( $variableSet->variablesByQName[ $refQName->clarkNotation() ] ) )
							{
								$this->log->formula_validation( "Group Filter", "Variable references are not allowed from group filters",
									array(
										'reference' => $refQName->clarkNotation(),
										'filter' => $filter->label,
										'variable set' => $variableSet->label,
										'error' => 'xbrlve:factVariableReferenceNotAllowed',
									)
								);
							}
						}
					}
				}

				$variable->addFilter( $filter );
			}
		}

		return true;

		// // Return applying any filters
		// return $arcRole == XBRL_Constants::$arcRoleVariableSetFilter
		// 	? true
		// 	: $this->validateAspectCovers( $taxonomy, $variable );
	}

	/**
	 * Validate consistency assertion
	 * @param XBRL $taxonomy
	 * @param VariableSet $variableSet
	 * @return boolean
	 */
	private function validatePreconditions( $taxonomy, $variableSet )
	{
		$preconditionArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleVariableSetPrecondition, $variableSet->extendedLinkRoleUri, $variableSet->label, $variableSet->path, null, $variableSet->linkbase );
		if ( $preconditionArcs )
		{
			$element = "precondition";
			$preconditionClassName = '\\XBRL\\Formulas\\Resources\\' .
				Formulas::$formulaElements[ $element ]['part'] .
				( isset( Formulas::$formulaElements[ $element ]['className'] )
					? Formulas::$formulaElements[ $element ]['className']
					: ucfirst( $element )
				);

			$preconditionResources = array();
			// BMS 2018-06-29 Modified this loop to put the arcs on the outside so it can take advantage of the indexes on the resources collection
			// $taxonomy->getGenericResource( 'variable', $element, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( $preconditionArcs, &$preconditionResources )
			// {
			// 	foreach ( $preconditionArcs as $preconditionArc )
			// 	{
			// 		if ( $roleUri == $preconditionArc['toRoleUri'] )
			// 		if ( $variableSetName == $preconditionArc['label'] )
			// 		{
			// 			$resource['linkbase'] = $linkbase;
			// 			$resource['linkRoleUri'] = $roleUri;
			// 			$preconditionResources[] = $resource;
			// 		}
			// 	}
			// 	return true;
			// } );

			foreach ( $preconditionArcs as $preconditionArc )
			{
				if ( $variableSet->path != $preconditionArc['frompath'] ) continue;
				$taxonomy->getGenericResource( 'variable', $element, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( $preconditionArc, &$preconditionResources )
				{
					// This test is probably redundant because I believe $variableSetName is the same as $variableSetArc['label']
					// if ( $variableSetName == $preconditionArc['label'] )
					if ( $resource['path'] == $preconditionArc['topath'] /* || $linkbase == $preconditionArc['linkbase'] */ )
					{
						$resource['linkbase'] = $linkbase;
						$resource['linkRoleUri'] = $roleUri;
						$preconditionResources[] = $resource;
					}

					return true;
				}, $preconditionArc['toRoleUri'], $preconditionArc['to'], $preconditionArc['tolinkbase'] );
			}

			if ( ! $preconditionResources )
			{
				$this->log->formula_validation( "Precondition", "The source precondition for formula resource cannot be located",
					array(
						'formula' => $variableSet->label,
					)
				);

				return false;
			}

			foreach ( $preconditionResources as $preconditionResource )
			{
				/** @var Precondition $precondition */
				$precondition = $preconditionClassName::fromArray( $preconditionResource );
				$precondition->extendedLinkRoleUri = $preconditionResource['linkRoleUri'];
				if ( ! $precondition->validate( $variableSet, $this->nsMgr ) )
				{
					return false;
				}

				$variableSet->preconditions[ "{$precondition->extendedLinkRoleUri}#{$precondition->label}" ] = $precondition;
			}
		}

		return true;
	}

	/**
	 * Validate consistency assertion
	 * @param XBRL $taxonomy
	 * @return boolean
	 */
	private function validateConsistencyAssertions( $taxonomy )
	{
		$element = "consistencyAssertion";
		$assertionResources = $taxonomy->getGenericResource( "assertionset", $element );
		if ( ! $assertionResources ) return true;

		$caClassName = '\\XBRL\\Formulas\\Resources\\' .
			Formulas::$formulaElements[ $element ]['part'] .
			( isset( Formulas::$formulaElements[ $element ]['className'] )
				? Formulas::$formulaElements[ $element ]['className']
				: ucfirst( $element )
			);

		foreach ( $assertionResources as $assertionResource )
		{
			/** @var ConsistencyAssertion $consistencyAssertion */
			$consistencyAssertion = $caClassName::fromArray( $assertionResource[ 'assertionset' ] );
			if ( ! $consistencyAssertion->validate( null, $this->nsMgr ) )
			{
				return false;
			}
			// $consistencyAssertion->extendedLinkRoleUri = $assertionResource['linkRoleUri'];

			$this->validateLabels( $taxonomy, $consistencyAssertion, $taxonomy->getDefaultLanguage() );

			// linked consistency assertions
			$consistencyAssertionArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleAssertionConsistencyFormula, $assertionResource['roleUri'], $assertionResource['resourceName'], $consistencyAssertion->path );

			$consistencyAssertionArcs = array_filter( $consistencyAssertionArcs, function( $arc ) use( $consistencyAssertion )
			{
				return $arc['frompath'] == $consistencyAssertion->path;
			} );

			if ( ! $consistencyAssertionArcs )
			{
				$this->log->formula_validation( "Consistency assertion", "No consistency assertion arcs ",
					array(
						'assertion name' => $assertionResource['resourceName'],
						'linkbase' => $assertionResource['linkbase'],
					)
				);
				continue;
			}

			foreach ( $consistencyAssertionArcs as $consistencyAssertionArc )
			{
				// BMS 2019-02-11
				if ( $assertionResource['linkbase'] != $consistencyAssertionArc['linkbase'] ||
					 $assertionResource['resourceName'] != $consistencyAssertionArc['from'] ||
					 $assertionResource['assertionset']['path'] != $consistencyAssertionArc['frompath'] ) continue;

				$formulaLabel = "{$consistencyAssertionArc['toRoleUri']}#{$consistencyAssertionArc['to']}";

				// Check the formula exists
				if ( ! isset( $this->variableSets[ $formulaLabel ] ) )
				{
					$this->log->formula_validation( "Consistency assertion", "The target formula cannot be located",
						array(
							'assertion name' => $consistencyAssertionArc['from'],
							'formula' => $consistencyAssertionArc['to'],
							'linkbase' => $consistencyAssertionArc['linkbase'],
						)
					);

					return false;
				}

				// Look for any parameters
				$parameterArcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleAssertionConsistencyParameter, $consistencyAssertionArc['fromRoleUri'], $consistencyAssertionArc['from'] );

				foreach ( $parameterArcs as $parameterArc )
				{
					// BMS 2019-02-11
					if ( $consistencyAssertion->path != $parameterArc['frompath'] ) continue;

					// Get the resources
					$parameterNames = array();
					$taxonomy->getGenericResource( 'variable', 'parameter', function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( $parameterArc, &$parameterNames )
					{
						// BMS 2018-06-29 This closure has been modified to use the indexes in the resources array
						// if ( $roleUri == $parameterArc['toRoleUri'] )
						// This test is probably redundant because I believe $variableSetName is the same as $variableSetArc['label']
						// if ( $variableSetName == $parameterArc['label'] )
						if ( $resource['path'] ==  $parameterArc['topath'] )
						{
							$resource['linkbase'] = $linkbase;
							$parameterNames[] = $resource['name'];
						}
						return true;
					}, $parameterArc['toRoleUri'], $parameterArc['to'], $parameterArc['tolinkbase'] );

					// Look for any parameters and add any found to the parameters list
					foreach ( $parameterNames as $parameterName )
					{
						$parameterQName = "{{$parameterName['namespace']}}{$parameterName['name']}";
						// Use the name on the arc (if there is one) or default to the parameter name
						$arcQName = isset( $parameterArc['name'] )
							? "{{$parameterArc['name']['namespace']}}{$parameterArc['name']['name']}"
							: $parameterQName;

						if ( isset( $this->parameterQnames[ $parameterQName ] ) )
						{
							$consistencyAssertion->parameters[ $arcQName ] = $this->parameterQnames[ $parameterQName ];
						}
					}
				}

				/** @var Formula $formula */
				$formulas =& $this->variableSets[ $formulaLabel ];

				foreach ( $formulas as $formula )
				{
					if ( $formula->label != $consistencyAssertionArc['to'] || ! $formula instanceof Formula ) continue;
					$formula->isConsistencyAssertionTarget = true;
					$consistencyAssertion->formulas[ $formulaLabel ][] =& $formula;
				}

				unset( $formula );
			}

			$this->consistencyAssertions[ $consistencyAssertionArc['from' ] ] = $consistencyAssertion;
		}

		// Process each consistency assertion
		foreach ( $this->consistencyAssertions as $from => $consistencyAssertion )
		{
			foreach ( $consistencyAssertion->formulas as $to => $formulas )
			{
				foreach ( $formulas as $formulaLabel => $formula )
				{
					// $this->log->debug( $formula->factsContainer->generateInstanceDocument( $formula->xbrlInstance, $formula->nsMgr ) );

					// Process the facts in the formula against the consistency definition
					if ( ! $formula->factsContainer->facts )
					{
						continue;
					}

					if ( ! isset( $formula->factsContainer->facts[ $formula->label ] ) ) continue;
					foreach ( $formula->factsContainer->facts[ $formula->label ] as $key => $derivedFact )
					{
						$vars = $formula->factsContainer->vars[ $formula->label ][ $key ];
						$consistencyAssertion->checkFactConsistency( $formula, $derivedFact, $vars, $key, $this->log );
					}
				}
			}
		}

		return true;
	}

	/**
	 * Validate and process custom signatures (if there are any)
	 * @param XBRL $taxonomy
	 * @return bool
	 */
	private function validateCustomFunction( $taxonomy )
	{

		// Get any variable sets.  These will be headed by a formula.
		$signatures = $taxonomy->getGenericResource( 'customfunction',  'signature' );
		$implementationResources = $taxonomy->getGenericResource( 'customfunction',  'implementation' );

		// Make sure any implementations have signatures
		if ( $implementationResources )
		{
			foreach ( $implementationResources as $x => $implementationResource )
			{
				// TODO
			}
		}

		if ( ! $signatures ) return true;

		foreach ( $signatures as $signatureKey => $signatureReference )
		{
			/** @var Signature $signature */
			$signature = Signature::fromArray( $signatureReference['customfunction'] );

			// Make sure the signature label has not been used already
			if ( isset( $this->signatures[ $signatureReference['resourceName'] ] ) )
			{
				$this->log->formula_validation( "custom function", "More than one signature has the same label",
					array(
						'error' => 'xbrlcfie:tooManyCFIRelationships',
						'label' => $signatureReference['resourceName']
					)
				);
			}

			$this->signatures[ $signatureReference['resourceName'] ] = $signature;

			if ( ! $signature->validate( null, $this->nsMgr ) )
			{
				return false;
			}

			// Locate the implementation
			$arcs = $taxonomy->getGenericArc( XBRL_Constants::$arcRoleCustomFunctionImplementation, $signatureReference['roleUri'], $signatureReference['resourceName'] );
			$implementations = array();
			foreach ( $arcs as $arc )
			{
				foreach ( $implementationResources as $implementationResourceKey => $implementationResource )
				{
					if ( $implementationResource['resourceName'] !=  $arc['label'] ) continue;
					if ( $implementationResource['roleUri'] !=  $arc['toRoleUri'] ) continue;

					$resource = $implementationResource['customfunction'];
					$resource['linkbase'] = $implementationResource['linkbase'];
					$implementations[] = Implementation::fromArray( $resource );
					unset( $implementationResources[ $implementationResourceKey ] );
				}

			}

			if ( count( $implementations ) > 1 )
			{
				$this->log->formula_validation( "custom function", "More than one signature has the same label",
					array(
						'error' => 'xbrlcfie:tooManyCFIRelationships',
						'label' => $signatureReference['resourceName'],
						'implementations' => implode( ", ", array_reduce( $implementations, function( $carry, $item ) { $carry[] = $item->label; return $carry; }, [] ) ),
					)
				);
			}

			if ( ! count( $implementations ) )
			{
				// If there is no explicit implementation make sure there is an existing function with this signature
				$functionTable = FunctionTable::getInstance();
				$functionDef = $functionTable->Bind( $signature->name['name'], $signature->name['namespace'], count( $signature->inputs ) );
				if ( ! $functionDef )
				{
					$this->log->formula_validation( 'Custom function', 'Missing implementation for signature', array(
						'signature' => $signatureReference['resourceName'],
						// 'error' => 'xbrlcfie:missingCFIRelationship'
					) );

					// return false;
				}
			}

			foreach ( $implementations as $implementation )
			{
				if ( count( $implementation->inputs ) != count( $signature->inputs ) )
				{
					$this->log->formula_validation( 'Custom function', 'Different parameter counts for implementation and signature', array(
						'signature' => $signatureReference['resourceName'],
						'signature count' => count( $signature->inputs ),
						'$implementation count' => count( $implementation->inputs ),
						'error' => 'xbrlcfie:inputMismatch'
					) );

					return false;
				}

				// Add a defintion to the function table
				$functionTable = FunctionTable::getInstance();
				$parameters = $this->parameterQnames;
				$functionTable->AddWithArity( $signature->name['namespace'], $signature->name['name'],  count( $signature->inputs ), $signature->output,
					function( $context, $provider, $args ) use( $implementation, $signature, $parameters )
					{
						return $implementation->execute( $signature, $context, $provider, $args, $parameters );
					}
				);

			}

			$this->signatures[ $signatureReference['resourceName'] ]->implementations = $implementations;
		}

		if ( count( $implementationResources ) )
		{
			$this->log->formula_validation( "custom function", "One or more implementations do not have a signature",
				array(
					'error' => 'xbrlcfie:missingCFIRelationship',
					'implementations' => implode( ", ", array_reduce( $implementations, function( $carry, $item ) { $carry[] = $item->label; return $carry; }, [] ) ),
				)
			);
		}

		return true;
	}

	/**
	 * Process an equality definition
	 * @param XBRL $taxonomy
	 * @return boolean
	 */
	private function validateEqualityDefinition( $taxonomy )
	{
		\XBRL_Log::getInstance()->info( "Need to implement equality definition validation" );
		$taxonomy->getGenericArc( XBRL_Constants::$arcRoleVariableEqualityDefinition );
		return true;
	}

	/**
	 * Evaluate the formula. At the moment the instanceQNames points to the current instance being processed
	 * @param VariableSet $variableSet
	 * @return bool
	 */
	private function evaluate( $variableSet )
	{
		if ( ! $this->canEvaluate ) return;

		// Process the variables in hierarchy order
		// $variableSet->parameters =& $this->parameterQnames;
		$variableSet->nsMgr = $this->nsMgr;
		$variableSet->xbrlInstance = $this->instances[ $this->instanceQName->clarkNotation() ];
		if ( $variableSet->evaluate() )
		{
			// If the variable set is a formula then add a facts container
			if ( $variableSet instanceof Formula )
			{
				if ( is_null( $this->formulaFactsContainer ) ) $this->formulaFactsContainer = new GeneratedFacts();
				$variableSet->factsContainer = $this->formulaFactsContainer;
			}

			$variableSet->ProcessEvaluationResult( $this->log );

			// If the variable set is a formula recover the facts container
			if ( $variableSet instanceof Formula )
			{
				// This is probably redundant because the container instance will be passed to the variable set by reference
				$this->formulaFactsContainer = $variableSet->factsContainer;
			}
		}

		return true;
	}

}