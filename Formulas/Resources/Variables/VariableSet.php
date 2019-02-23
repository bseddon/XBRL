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

 namespace XBRL\Formulas\Resources\Variables;

use XBRL\Formulas\Resources\Resource;
use XBRL\Formulas\Resources\Filters\Filter;
use XBRL\Formulas\Resources\VariableBinding;
use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\Evaluation;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\Properties\Resources;
use XBRL\Formulas\FactVariableBinding;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\xml\QName;
use lyquidity\XPath2\Iterator\BufferedNodeIterator;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\FactValues;

/**
 * A class to hold a variable set
 */
class VariableSet extends Resource
{
	/**
	 * The name of the instance used by this variable set
	 * @var string $instanceName
	 */
	public $instanceName;

	/**
	 * The name of the aspect model: 'dimensional' or 'non-dimensional'
	 * @var string $aspectModel
	 */
	public $aspectModel = "";

	/**
	 * Indicates whether implicit filtering is active.  If implicit
	 * filtering is not active then all aspects must be covered.
	 * @var bool $implicitFiltering
	 */
	public $implicitFiltering = true;

	/**
	 * The id value if present
	 * @var string|null $id
	 */
	public $id;

	/**
	 * The label of the variable set
	 * @var string|null $label
	 */
	public $label;

	/**
	 * The namespace of the original variable set element
	 * @var string $namespace
	 */
	public $namespace;

	/**
	 * A list of variables associated with this variable set instance
	 * @var array[Variable]
	 */
	public $variablesByQName = array();

	/**
	 * A lookup to associate a varable qname to a label
	 * @var array[string]
	 */
	public $variableLabelToQNameMap = array();

	/**
	 * A list of filters applied to the variable set
	 * @var array[Filter] $groupFilters
	 */
	public $groupFilters = array();

	/**
	 * An evaluated expression representing the XPath implied by the $groupFilters
	 * @var string $groupFilterExpression
	 */
	public $groupFilterExpression = null;

	/**
	 * A reference to the global parameters list
	 * @var array[Parameter] $parameters
	 */
	public $parameters = array();

	/**
	 * A hierarchy defining the order in which variables should be processed.
	 * Members at a level can be processed in any order.
	 * @var array
	 */
	public $variableOrderHierarchy = array();

	/**
	 * List of cached non-nil facts
	 * @var XPath2NodeIterator $nonNilsFactsCache
	 */
	public $nonNilsFactsCache = array();

	/**
	 * List of cached facts including nils
	 * @var XPath2NodeIterator $nilsFactsCache
	 */
	public $nilsFactsCache = array();

	/**
	 * A list of the covers
	 * @var array $covers
	 */
	public $covers = array();

	/**
	 * A list of variable bindings
	 * @var array[VariableBinding] $variableBindings
	 */
	public $factVariableBindings = array();

	/**
	 * A list of bindings of general variables
	 * @var array[VariableBinding] $generalVariableBindings
	 */
	public $generalVariableBindings = array();

	/**
	 * Namespace resolver to use
	 * @var XmlNamespaceManager $nsMgr
	 */
	public $nsMgr;

	/**
	 * The XBRL_instance against which to evaluate the formula
	 * @var \XBRL_Instance $xbrlInstance
	 */
	public $xbrlInstance;

	/**
	 * The taxonomy instance being used
	 * @var \XBRL
	 */
	public $xbrlTaxonomy;

	/**
	 * Temporary variable to indicate whether the XPath evaluation function has already set the current working directory
	 * @var bool $changedDirectory
	 */
	public $changedDirectory;

	/**
	 * The role of the source link role uri
	 * @var string $extendedLinkRoleUri
	 */
	public $extendedLinkRoleUri;

	/**
	 * List of typed dimensions in the instance taxonomy
	 * @var array $typedDimensions
	 */
	public $typedDimensions = array();

	/**
	 * List of explicit dimensions in the instance taxonomy
	 * @var array $explicitDimensions
	 */
	public $explicitDimensions = array();

	/**
	 * A list of defaults for dimensions
	 * @var array $dimensionDefaults
	 */
	public $dimensionDefaults = array();

	/**
	 * A combined list of the explicit and typed dimensions
	 * @var array $allDimensions
	 */
	public $allDimensions = array();

	/**
	 * A list of pre-conditions affecting the formula
	 * @var array[Precondition] $preconditions
	 */
	public $preconditions = array();

	/**
	 * A base defined by the formula
	 * @var string
	 */
	public $base = "";

	/**
	 * A list of messages indexed by arc role
	 * @var array $messages
	 */
	public $messages = array();

	/**
	 * A list of scope dependencies
	 * @var array
	 */
	public $scopeDependencies = array();

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
		$result = array(
			'type' => 'variableset',
			'variablesetType' => 'variableSet',
			'label' => $label,
			'namespace' => $domNode->namespaceURI,
		);

		// $attributes = $node->attributes();
        //
		// if ( property_exists( $attributes, "id" ) )
		// {
		// 	$result['id'] = trim( $attributes->id );
		// }

		$result = array_merge( $result, parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log ) );

		$attributes = $node->attributes();
		if ( ! property_exists( $attributes, "aspectModel" ) )
		{
			$log->formula_validation( "Variables", "Missing aspect model attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->aspectModel = (string)$attributes->aspectModel;

		if ( $this->aspectModel != "dimensional" && $this->aspectModel != "non-dimensional" )
		{
			$log->formula_validation( "Variables", "The provided aspect model attribute contains an illegal value", array(
				'aspectModel' => $this->aspectModel,
				'error' => 'xbrlve:unknownAspectModel'
			) );
		}

		$xmlAttributes = $node->attributes( 'xml', true );

		if ( property_exists( $xmlAttributes, "base" ) )
		{
			$this->base = (string)$xmlAttributes->base;
		}

		$result['aspectModel'] = $this->aspectModel;
		$result['base'] = $this->base;

		return $result;
	}

	/**
	 * Add a variable, check for duplicates and update the lable -> qname map
	 * @param Variable $variable (reference)
	 * @return false;
	 */
	public function AddVariable( &$variable )
	{
		$qname = $variable->getQName()->clarkNotation();
		if ( isset( $this->variablesByQName[ $qname ] ) )
		{
			$x = 1;
			\XBRL_Log::getInstance()->formula_validation( "Variable-set", "The variable name (defined on an arc) already exists", array(
				'name' => $qname->clarkNotation(),
				'error' => 'xbrlve:duplicateVariableNames'
			) );
			return false;
		}

		$this->variablesByQName[ $qname ] =& $variable;
		$this->variableLabelToQNameMap[ "{$variable->extendedLinkRoleUri}#{$variable->label}" ] = $qname;

		return true;
	}

	/**
	 * Adds a group filter
	 * @param Filter $filter
	 */
	public function AddFilter( $filter )
	{
		$this->groupFilters[] = $filter;
	}

	/**
	 * Provide a list of arcroles that are valid this resource
	 */
	public function getDescendantArcroles()
	{
		return array( \XBRL_Constants::$arcRoleVariableSet, \XBRL_Constants::$arcRoleVariableSetFilter, \XBRL_Constants::$arcRoleVariableSetPrecondition );
	}

	/**
	 * Abstract function to allow descendents to validate their messages
	 * @param string $lang
	 * @return bool
	 */
	public function validateMessages( $lang = null )
	{
		return true;
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param XPath2NodeIterator $facts An array of source facts to filter by applying the local filters
	 * @param array $uncovered
	 * @return array Returns an array of two elements (tuple).  One if the filtered list of facts.  The other is the list of covered aspects.
	 */
	public function Filter( $facts, $uncovered )
	{
		// Apply each filter in $this->filters.
	}

	/**
	 * Main function to perform variable set evaluation
	 */
	public function evaluate()
	{
		$this->covers = $this->aspectModel == "dimensional" ? Formula::$dimensionAspectModelMembers : Formula::$nonDimensionAspectModelMembers;

		// If there are group filters generate a sub-query
		if ( $this->groupFilters && is_null( $this->groupFilterExpression ) )
		{
			$groupFilterExpressions = array();
			foreach ( $this->groupFilters as $filter )
			{
				$query = $filter->toQuery( $this, null );
				if ( $filter->complement )
				{
					$query = "not($query)";
				}
				$groupFilterExpressions[] = $query;
			}

			$this->groupFilterExpression = implode( " and ", array_filter( $groupFilterExpressions ) );
		}

		$hypercubes = $this->xbrlTaxonomy->getDefinitionHypercubes();

		foreach ( $hypercubes as $hypercubeLabel => $hypercube )
		{
			foreach ( $hypercube['dimensions'] as $dimensionLabel => $dimension )
			{
				// Find the taxonomy of the dimension to get the element definition
				$dimTaxonomy = $this->xbrlTaxonomy->getTaxonomyForXSD( $dimensionLabel );
				$dimElement = $dimTaxonomy->getElementById( $dimensionLabel );
				$dimQName = new QName( null, $dimension['dimension_namespace'], $dimElement['name'] );

				if ( isset( $dimElement['typedDomainRef'] ) )
					$this->typedDimensions[] = $dimQName->clarkNotation(); else
					$this->explicitDimensions[] = $dimQName->clarkNotation();

				if ( ! isset( $dimension['default'] ) ) continue;

				$default = $dimension['default'];
				$memTaxonomy = $this->xbrlTaxonomy->getTaxonomyForXSD( $default['label'] );
				$memElement = $memTaxonomy->getElementById( $default['label'] );
				// $default['namespace'] is almost certainly null.  Changed to use the $memTaxonomy namespace if it is
				// $this->dimensionDefaults[ $dimQName->clarkNotation() ] = new QName( null, $default['namespace'], $memElement['name'] );
				$this->dimensionDefaults[ $dimQName->clarkNotation() ] = new QName( null, $default['namespace'] ? $default['namespace'] : $memTaxonomy->getNamespace(), $memElement['name'] );
			}
		}

		foreach ( $this->xbrlTaxonomy->getAllDimensions() as $dimension => $dimensionElement )
		{
			$dimQName = new QName( null, $dimensionElement['namespace'], $dimensionElement['name'] );
			if ( isset( $dimensionElement['typedDomainRef'] ) )
			{
				if ( ! in_array( $dimQName->clarkNotation(), $this->typedDimensions ) )
					$this->typedDimensions[] = $dimQName->clarkNotation();
			}
			else
			{
				if ( ! in_array( $dimQName->clarkNotation(), $this->explicitDimensions ) )
					$this->explicitDimensions[] = $dimQName->clarkNotation();
			}
		}

		$this->allDimensions = array_merge( array_fill_keys( $this->explicitDimensions, 'explicit' ),  array_fill_keys( $this->typedDimensions, 'typed' ) );

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

		$flatten( $this->variableOrderHierarchy );

		if ( ! Evaluation::Evaluate( $this, $orderedVariables, array() ) )
		{
			$type = basename( get_class( $this ) );
			\XBRL_Log::getInstance()->alert( "Evaluation failed for a $type named '{$this->label}'" );
			return false;
		}

		return true;
	}

	/**
	 * Give the variable set instance an opportunity to process the facts
	 */
	public function evaluateResult()
	{
		// Check the variable set is not all fallback values
		$factsFallback = $this->factVariableBindings && ! count( array_filter( $this->factVariableBindings,
			function( /** @var VariableBinding $variableBinding */ $variableBinding )
			{
				return ! $variableBinding->isFallback;
			} )
		);

		// $generalsFallback =  $this->generalVariableBindings && ! count( array_filter( $this->generalVariableBindings,
		// 	function( /** @var VariableBinding $variableBinding */ $variableBinding )
		// 	{
		// 		return ! $variableBinding->isFallback;
		// 	} )
		// );

		if ( $factsFallback /* && $generalsFallback */ )
		{
			return false;

			$msg = "All facts have fallback values";
			\XBRL_Log::getInstance()->formula_validation( "Variable set evaluation", $msg, array(
				'variable set' => get_called_class()
			) );

			throw new \Exception( $msg );
		}

		// Check preconditions
		if ( ! $this->preconditions )
		{
			return true;
		}

		foreach ( $this->preconditions as $precondition )
		{
			try
			{
				$vars = $this->getBindingsAsVars();
				$result = $this->evaluateXPathExpression( $this, $precondition->testXPathExpression, $vars );
				// The pre-condition result should be true or false and only true will allow the evaluation to be counted
				if ( ! $result instanceof CoreFuncs::$True )
				{
					if ( ! $result instanceof CoreFuncs::$False )
					{
						throw XPath2Exception::withErrorCodeAndParam( "FORG0006", "The precondition with label '{0}' did not return a boolean result", $precondition->label );
					}

					return false;
				}
			}
			catch( XPath2Exception $ex )
			{
				throw $ex;
			}
			catch( \Exception $ex )
			{
				throw $ex;
			}
		}

		return true;
	}

	/**
	 * Return an array of parameter values
	 * @param array $vars (optional) An array of existing vars
	 * @return array
	 */
	public function getParametersAsVars( $vars = array() )
	{
		foreach ( $this->parameters as $qname => $parameter )
		{
			$vars[ $qname ] = $parameter->result;
		}

		return $vars;
	}

	/**
	 * Return an array of bound facts and parameters after the evaluation
	 * @param array $vars (optional) An array of existing vars
	 * @return array
	 */
	public function getBindingsAsVars( $vars = array() )
	{
		// $vars = array_merge( $existingVars, $this->parameters );
		$vars = $this->getParametersAsVars( $vars );

		foreach( $this->factVariableBindings as $bindingQName => /** @var \XBRL\Formulas\factVariableBinding $varBinding */ $varBinding )
		{
			if ( $varBinding->isFallback )
			{
				$current = $varBinding->yieldedFact;
			}
			else
			{
				// Using current makes sure the fact is set and the binding variables are correct
				if ( ! $varBinding->valid() ) $varBinding->rewind();
				$current = $varBinding->current();
			}
			if ( $current instanceof XPath2NodeIterator ) $current->Reset();
			$vars[ $bindingQName ] = $current;
			$vars = array_merge( $vars, $varBinding->getAdditionalVars() );
		}

		foreach( $this->generalVariableBindings as $bindingQName => /** @var VariableBinding $varBinding */ $varBinding )
		{
			// Using current makes sure the fact is set and the binding variables are correct
			// $vars[ $bindingQName ] = $this->variablesByQName[ $bindingQName ]->bindAsSequence ? $varBinding->facts : $varBinding->current();
			if ( ! $varBinding->valid() ) $varBinding->rewind();
			$current = $varBinding->current();
			if ( $current instanceof XPath2NodeIterator )
			{
				// BMS 2018-04-04 Test 0023 V-03 fails because the vars are being reset presumably because a var is not a clone
				$current = $current->CloneInstance();
				$current->Reset();
			}
			else if ( $current instanceof XPathNavigator)
			{
				$current = $current->CloneInstance();
			}
			$vars[ $bindingQName ] = $current;
		}

		return $vars;
	}

	/**
	 * This information willl be used by scope variable processing to access the last set of uncovvered aspects
	 * @return NULL|FactVariableBinding
	 */
	private $lastFactVariableBinding = null;

	/**
	 * Return the last binding which will contain the last set of uncovered items.
	 * This information willl be used by scope variable processing to access the last set of uncovvered aspects
	 * @return NULL|FactVariableBinding
	 */
	public function getLastFactBinding()
	{
		return $this->lastFactVariableBinding;
	}

	/**
	 * Record the last fact variable binding created and used
	 * @param FactVariableBinding $binding
	 */
	public function setLastFactBinding( &$binding )
	{
		$this->lastFactVariableBinding = $binding;
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
		return "The wrong compare results function has been called";
	}

	/**
	 * Return a list of the input instance fact with nils
	 * @return \lyquidity\XPath2\XPath2NodeIterator
	 */
	public function getFactsWithNils()
	{
		if ( $this->nilsFactsCache )
		{
			return $this->nilsFactsCache;
		}
		else
		{
			$nilsFactsCacheExpression = "xfi:facts-in-instance(/xbrli:xbrl)";
			if ( ! is_null( $this->groupFilterExpression ) )
			{
				$nilsFactsCacheExpression .= "[{$this->groupFilterExpression}]";
			}

			// Because this is the first time the facts have been accessed there are only parameters to be used as variables
			$this->nilsFactsCache = BufferedNodeIterator::fromSourceWithClone( $this->evaluateXPath( $this, $nilsFactsCacheExpression, $this->parameters ), false );
			// $this->nilsFactsCache = $this->evaluateXPath( $this, $nilsFactsCacheExpression, $this->parameters );
			return $this->nilsFactsCache; // ->CloneInstance();
		}
	}

	/**
	 * A list of dimensions
	 * @var array[\QName]|null
	 */
	private $nilFactsDimensions;

	/**
	 * A list of dimensions
	 * @var array[\QName]|null
	 */
	private $factsDimensions;

	/**
	 * Return a list of the dimensions across all dimensions
	 * @param bool $nils
	 * @return array[\QName]|null
	 */
	public function getFactsDimensions( $nils )
	{
		return $nils ? $this->nilFactsDimensions : $this->factsDimensions;
	}

	/**
	 * Set a list of the dimensions across all dimensions
	 * @param bool $nils
	 * @param array $dimensions
	 * @return array[\QName]|null
	 */
	public function setFactsDimensions( $nils, $dimensions )
	{
		if ( $nils )
			$this->nilFactsDimensions = $dimensions;
		else
			$this->factsDimensions = $dimensions;
	}

	/**
	 * Return a list of the input instance fact withou nils
	 * @return \lyquidity\XPath2\XPath2NodeIterator
	 */
	public function getFactsWithoutNils()
	{
		if ( $this->nonNilsFactsCache )
		{
			return $this->nonNilsFactsCache;
		}
		else
		{
			$nonNilsFactsCacheExpression = "xfi:non-nil-facts-in-instance(/xbrli:xbrl)";
			if ( ! is_null( $this->groupFilterExpression ) )
			{
				$nonNilsFactsCacheExpression .= "[{$this->groupFilterExpression}]";
			}

			// Because this is the first time the facts have been accessed there are only parameters to be used as variables
			$this->nonNilsFactsCache = BufferedNodeIterator::fromSourceWithClone( $this->evaluateXPath( $this, $nonNilsFactsCacheExpression, $this->parameters ), false );
			// $this->nonNilsFactsCache = $this->evaluateXPath( $this, $nonNilsFactsCacheExpression, $this->parameters );
			return $this->nonNilsFactsCache; //->CloneInstance();
		}
	}

	/**
	 * Returns a set of details for a fact
	 * @param DOMXPathNavigator|XPathItem $fact
	 * @param bool $includePrefix (default: true) When true a prefix indicating the type of value will be included
	 * @return array
	 */
	public function getVariableDetails( $variable, $includePrefix = true )
	{
		$result = array( 'value' => $this->valueToString( $variable, $includePrefix ) );
		if ( $variable instanceof DOMXPathNavigator )
		{
			$result['concept'] = $variable->getName();
			$result['context'] = FactValues::getContextRef( $variable );
			$result['unit'] = FactValues::getUnitRef( $variable );

		}
		return $result;
	}

	/**
	 * Generates a default message based on the formula test
	 * @param string $test  The test on which to base the default message
	 * @param array $vars
	 * @return mixed[]
	 */
	public function createDefaultMessage( $test, $vars )
	{
		$substitutions = array();

		// Look for variables in the test
		foreach ( $vars as $name => $var )
		{
			if ( strpos( $test, "\$$name") === false ) continue;

			$substitutions[ $name ]	= Resource::valueToString( $var );
		}

		foreach ( $substitutions as $name => $substitution )
		{
			$test = str_replace( $name, $name . " " . $substitution, $test );
		}

		return $test;
	}
}