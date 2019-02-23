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

use XBRL\Formulas\Resources\Filters\Filter;
use XBRL\Formulas\VariableBinding;
use lyquidity\XPath2\XPath2NodeIterator;
use XBRL\Formulas\Evaluation;
use XBRL\Formulas\FactValues;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\Resources\Formulas\Aspects\Aspect;
use lyquidity\xml\MS\XmlNamespaceManager;
use XBRL\Formulas\FactVariableBinding;
use lyquidity\XPath2\TreeComparer;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\CoreFuncs;
use XBRL\Formulas\Resources\Assertions\ValueAssertion;
use XBRL\Formulas\Resources\Assertions\ExistenceAssertion;
use XBRL\Formulas\Resources\Filters\MatchFilter;
use XBRL\Formulas\Resources\Filters\AspectCover;
use lyquidity\xml\QName;
use lyquidity\XPath2\Iterator\BufferedNodeIterator;

 /**
  * A class to process a fact variable definitions
  */
class FactVariable extends Variable
{
	/**
	 * Flag if the variables can include nil variables
	 * @var bool $nils
	 */
	public $nils = false;

	/**
	 * If the @matches attribute on the fact variable is omitted or is equal to false
	 * then the evaluation result MUST NOT contain any aspect-matched facts.
	 * @var bool $matches
	 */
	public $matches = false;

	/**
	 * The value of any fallback value or null if not provided
	 * @var mixed $fallbackValue
	 */
	public $fallbackValue;

	/**
	 * A flag indicating whether facts should be bound as a sequence
	 * @var bool $bindAsSequence
	 */
	public $bindAsSequence = false;

	/**
	 * An array of name elements (prefix, namespace and name)
	 * @var array $name
	 */
	public $name = array();

	/**
	 * An array of filters that apply to this variable
	 * @var array[Filter] $filters
	 */
	public $filters = array();

	/**
	 * A set of generated filter expressions
	 * @var array $filterExpressions
	 */
	public $filterExpressions = array();

	/**
	 * A list of filters that can be applied to tuples (location and concept)
	 * @var array $tupleFilters
	 */
	public $tupleFilters = array();

	/**
	 * A list of filters that cannot be applied to tuples (not location and not concept)
	 * @var array $tupleFilters
	 */
	public $nonTupleFilters = array();

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

		$attributes = $node->attributes();

		$this->nils = property_exists( $attributes, "nils" ) // Optional
			? filter_var( $attributes->nils, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE )
			: null;

		$this->matches = property_exists( $attributes, "matches" ) // Optional
			? filter_var( $attributes->matches, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE )
			: null;

		$this->fallbackValue = property_exists( $attributes, "fallbackValue" ) // Optional
			? (string) $attributes->fallbackValue
			: null;

		$this->bindAsSequence = property_exists( $attributes, "bindAsSequence" ) // Required
			? filter_var( $attributes->bindAsSequence, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE )
			: null;

		$result['nils'] = $this->nils;
		$result['matches'] = $this->matches;
		$result['fallbackValue'] = $this->fallbackValue;
		$result['bindAsSequence'] = $this->bindAsSequence;

		$result = parent::storeVariable( $result, $localName );

		return $result;
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		$log = \XBRL_Log::getInstance();

		if ( $this->fallbackValue )
		{
			$xpath2Expression = XPath2Expression::Compile( $this->fallbackValue, $nsMgr );
			$qnames = $xpath2Expression->getParameterQNames();
			if ( $qnames )
			{
				foreach ( $qnames as $key => $qname )
				{
					// Only parameters allowed
					if ( isset( $variableSet->variablesByQName[ (string)$qname ] ) )
					{
						$log->formula_validation( "variables", "Variable references are not allowed in fallback expression",
							array(
								'error' => 'xbrlve:fallbackValueVariableReferenceNotAllowed',
							)
						);
					}
				}
			}
		}

		// Check any aspect cover filters for consistency
		$aspectCovers = array();
		foreach ( $this->filters as $filter )
		{
			if ( ! $filter instanceof AspectCover ) continue;
			foreach ( $filter->getAspectsCovered( $variableSet, null ) as $aspectCovered )
			{
				if ( isset( $aspectCovers[ $aspectCovered ] ) )
				{
					// If the cover values are the same then continue otherwise report an error
					if ( $aspectCovers[ $aspectCovered ] == $filter->cover ) continue;

					$log->formula_validation( "aspect cover filter", "cover value across filters is different for the same aspect",
						array(
							'error' => 'xbrlacfe:inconsistentAspectCoverFilters',
							'filter' => $filter->label,
							'aspect' => $aspectCovered,
						)
					);
				}
				else
				{
					$aspectCovers[ $aspectCovered ] = $filter->cover;
				}
			}
		}

		return parent::validate( $variableSet, $nsMgr );
	}

	/**
	 * Add the filters in a defined order starting with the concept filter to aid performance
	 * @param Filter $filter
	 */
	public function AddFilter( $filter )
	{
		$this->filters[] = $filter;

		if ( count( $this->filters ) <= 1 ) return;

		usort( $this->filters, function( /** @var Filter $a */ $a, /** @var Filter $b */ $b )
		{
			return $a->sortPriority > $b->sortPriority
				? 1
				: ( $a->sortPriority < $b->sortPriority ? -1 : 0 );
		} );
	}

	/**
	 * Provide a list of arcroles that are valid this resource
	 */
	public function getDescendantArcroles()
	{
		return array( \XBRL_Constants::$arcRoleVariableFilter );
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		foreach ( $this->filters as $filter )
		{
			$variableRefs = array_merge( $variableRefs, $filter->getVariableRefs() );
		}

		return $variableRefs;
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param Evaluation $evaluation The variable set containing the variables
	 * @param array $uncoveredAspectFacts The binding of the parent variable (if there is one) and so uncovered facts
	 * @return VariableBinding A new binding for this variable
	 */
	public function Filter( $evaluation, $uncoveredAspectFacts )
	{
		global $debug_statements;

		$variableSet = $evaluation->variableSet;

		/** @var XPath2NodeIterator $facts */
		$facts = null;

		// If there are no facts create a list
		$facts = $this->nils
			? $variableSet->getFactsWithNils()
			: $variableSet->getFactsWithoutNils();

		// $facts = $facts->CloneInstance();

		// Create a list of the aspects covered by the filters used by this variable
		$binding = new FactVariableBinding( $variableSet->xbrlInstance, $this );
		// $variableSet->variableBindings[ $this->getQName() ] =& $binding;
		$binding->aspectsDefined = $variableSet->covers;

		// Initialize the vars array
		$vars = array();

		$includeDimensions = in_array( ASPECT_DIMENSIONS, $binding->aspectsDefined );
		if ( $includeDimensions )
		{
			// Remove the generic ASPECT_DIMENSIONS item as it will be replaced with real dimensions
			unset( $binding->aspectsDefined[ array_search( ASPECT_DIMENSIONS, $binding->aspectsDefined) ] );

			$allDimensions = $variableSet->getFactsDimensions( $this->nils );
			if ( ! is_null( $allDimensions ) )
			{
				$binding->aspectsDefined = array_unique( array_merge( $binding->aspectsDefined, $allDimensions ) );
			}
			else
			{
				// Cache to prevent redundant context lookups
				$dimensionsCache = array();
				// Initialize the variable
				$allDimensions = array();
				// Must use $facts here not $binding.  Not just because there are no facts in the binding yet
				// but also because the $binding iterator will cause the state of the binding to be invalid
				foreach ( $facts as $fact )
				{
					if ( ! $fact instanceof DOMXPathNavigator ) continue;

					$contextRef = FactValues::getContextRef( $fact );
					if ( ! $contextRef ) continue;
					if ( isset( $dimensionsCache[ $contextRef] ) )
					{
						// $dimensions = $dimensionsCache[ $contextRef ];
						continue;
					}
					else
					{
						$dimensions = array_keys( FactValues::getDimensionsInContextRef( $evaluation->variableSet, $contextRef ) );
						if ( is_array( $variableSet->dimensionDefaults ) )
						{
							$dimensions = array_merge( $dimensions, array_keys( $variableSet->dimensionDefaults ) );
						}
						$dimensionsCache[ $contextRef ] = array_unique( $dimensions );
					}

					// Add the dimensions from the fact
					$binding->aspectsDefined = array_unique( array_merge( $binding->aspectsDefined, array_unique( $dimensions ) ) );
					$allDimensions = array_unique( array_merge( $allDimensions, $dimensions ) );
				}

				$variableSet->setFactsDimensions( $this->nils, $allDimensions );
			}
		}

		// This filter needs to be applied after the collection of dimensions (above)
		// so the collection is able to operate over all facts
		foreach ( $this->filters as /** @var Filter $filter */ $filter )
		{
			$facts = $filter->Filter( $facts, $variableSet );
		}

		// Process filters to collect covers.  This could be
		// merged with the for loop above but they are separate
		// to highlight their different purposes
		$aspectCoverFilter = null;
		foreach ( $this->filters as $filter )
		{
			if ( $filter instanceof AspectCover )
			{
				$aspectCoverFilter = $filter;
				continue;
			}

			if ( $filter->cover )
			{
				$binding->aspectsCovered = array_merge( $binding->aspectsCovered, $filter->getAspectsCovered( $variableSet, $binding ) );
			}

			// If it is a match filter then add the variables required by the lyquidity:aspectMatch() custom function
			if ( $filter instanceof MatchFilter )
			{
				$lyquidityNamespace = \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ];
				if ( ! isset( $vars["{{$lyquidityNamespace}}factVariable"] ) )	$vars["{{$lyquidityNamespace}}factVariable"] = $this;
				if ( ! isset( $vars["{{$lyquidityNamespace}}variableSet"] ) )	$vars["{{$lyquidityNamespace}}variableSet"] = $variableSet;
			}
		}

		if ( $aspectCoverFilter )
		{
			$aspectCoverFilter->getAspectsCovered( $evaluation->variableSet, $binding );
		}

		// Apply each filter in $this->filters.
		// BMS 2018-02-13 Modify this to create a pair of filter lists.
		// One that apply to all aspects types and another that is for filters that do not apply to tuples
		if ( ! $this->nonTupleFilters && ! $this->tupleFilters )
		{
			foreach ( $this->filters as $filter )
			{
				$query = $filter->toQuery( $evaluation->variableSet, $binding );
				if ( $query && $filter->complement )
				{
					$query = "not($query)";
				}

				// Allocate the query to one or other of the filter lists. The filter MAY be a boolean
				// in which case the aspect(s) covered may include tuple friendly AND non-tuple friendly
				// queries.  If the aspects list includes ANY that are not location or concept default
				// to adding the query to the non-tuple filter list. Do this by performing a diff on the
				// filter aspects covered and the location and concept aspects.  If the result is empty
				// then there are only tuple filters.  If there are any elements in the result then
				// filter should be added to the non-tuple list (this takes advantage of the way PHP
				// array functions return members of the first array and filter by all others).
				$aspects = $filter->getAspectsCovered( $evaluation->variableSet, $binding );
				if ( array_diff( $aspects, array( ASPECT_LOCATION, ASPECT_CONCEPT ) ) )
				{
					$this->nonTupleFilters[] = $query;
				}
				else
				{
					$this->tupleFilters[] = $query;
				}
			}
		}

		$binding->aspectsCovered = array_unique( $binding->aspectsCovered );
		$this->nonTupleFilters = array_filter( $this->nonTupleFilters );
		$this->tupleFilters = array_filter( $this->tupleFilters );

		// Initialize the list of variables that will be used in XPath query evaluations
		// $vars = array(); // $variableSet->parameters;

		// If implicit filtering and there are existing filters
		// This filter used to be performed after the 'main' filter but is moved here because filtering
		// via the 'aspectsMatch' function is much quicker than by using the XPath 2.0 processor
		if ( $uncoveredAspectFacts )
		{
			if ( $variableSet->implicitFiltering )
			{
				// The list of testable aspects has be:
				//		the defined aspects of the current binding; plus
				//		the aspects of the uncovered aspect facts; minus
				//		the covered aspected

				// BMS 2018-02-11 Another version
				$aspects = array_filter( array_unique( array_merge( $binding->aspectsDefined, array_keys( $uncoveredAspectFacts ) ) ) );
				// The implicit filter spec states: "A matchable aspect is an aspect in the aspect universe that is not covered
				// for the current variable and that is not covered for at least one of the current variable's preceding variables."
				// As a result it's important that any null values in the $uncoveredAspectFacts array are removed.
				$testableAspects = array_filter( array_unique( array_merge( $binding->aspectsDefined, array_keys( $uncoveredAspectFacts ) ) ),
					function( $aspect ) use( &$binding, $uncoveredAspectFacts ) {
						return ! in_array( $aspect, $binding->aspectsCovered ) && isset( $uncoveredAspectFacts[ $aspect ] ) && $uncoveredAspectFacts[ $aspect ];
					}
				);

				if ( $testableAspects )
				{
					$testableAspectFacts = array_fill_keys( $testableAspects, null );
					$testableAspectFacts = array_merge( $testableAspectFacts, array_intersect_key( $uncoveredAspectFacts, array_flip( $testableAspects ) ) );
					$matchedFacts = $this->aspectsMatch( $testableAspects, $testableAspectFacts, $variableSet, $facts, false, false );
					$facts = DocumentOrderNodeIterator::fromItemset( $matchedFacts );

					$count = $facts->getCount();
					if ( $debug_statements )
					{
						\XBRL_Log::getInstance()->info( "After implicit filter: $count" );
					}
				}
				else
				{
					// Handle tuples (see Arelle:FormulaEvaluator.py.ImplicitFilters line 622)
					// Need an example before implementing this
				}
			}
		}

		// $filterExpressions = array_filter( $filterExpressions );
		if ( count( $this->tupleFilters ) || count( $this->nonTupleFilters ) )
		{
			$filterExpression = implode( " and \n", $this->tupleFilters );

			if ( $this->nonTupleFilters )
			{
				$nonTupleExpression = "(if (fn:QName(\"http://www.xbrl.org/2003/instance\",\"tuple\") = xfi:concept-substitutions(fn:node-name(.))) " .
									  "then (false()) " .
									  "else (" . implode( " and ", $this->nonTupleFilters ) . "))";
				// Create a final expression
				$filterExpression = implode( " and \n", array_filter( array( $filterExpression, $nonTupleExpression ) ) );
			}

			// Reset needed because the $fasts list may have been iterated above leaving the position at the end of the list
			$facts->Reset();
			// Add any variables that have been processed already
			$vars = $variableSet->getBindingsAsVars( $vars );
			$vars['facts'] = $facts; // ->CloneInstance();

			if ( $debug_statements )
			{
				\XBRL_Log::getInstance()->info( $filterExpression );
			}
			$facts = $this->evaluateXPath( $variableSet, "\$facts[{$filterExpression}]", $vars );
		}

		$facts = BufferedNodeIterator::Preload( $facts );

		$count = $facts->getCount();
		if ( $debug_statements )
		{
			\XBRL_Log::getInstance()->info( "After main filter:     $count" );
		}

		$factsClone = $facts->CloneInstance();
		$factsClone->Reset();
		if ( ! $factsClone->MoveNext() )
		{
			return $binding;
		}

		// $factsClone = $facts->CloneInstance();
		// $factsClone->Reset();

		// A 'notBinding' will only exist if the prior variable set a fallback value
		// if ( $evaluation->notBindings && $factsClone->MoveNext() )
		if ( $evaluation->notBindings )
		{
			// Got to compare the facts with the facts if the corresponding binding so begin by making a list of these facts
			// This is the equivalent of the Arelle function evaluationIsUnnecessary in FormulaEvaluator.py.  Arelle uses
			// hashes of fact objects indexed by the variable QName to determine if the same facts are being use more than once
			// to create an evaluation.  I'm not sure that hashes of DOMNode instances are stable enough to do something similar
			// in PHP so instead the facts contained in existing evaluations are used.

			// Filtering is based on the var of the current variable set
			$evaluationVars = $variableSet instanceof Formula
				? array_map( function( $evaluationResult ) { return $evaluationResult['vars']; }, $variableSet->evaluationResults )
				: ( $variableSet instanceof ValueAssertion
						? array_merge( $variableSet->satisfied, $variableSet->unsatisfied )
						: ( $variableSet instanceof ExistenceAssertion
								? $variableSet->satisfied
								: array()
						  )
				  );

			/**
			 * @var Formula $formula
			 */
			$formula = $variableSet;
			foreach ( $evaluation->notBindings as $notBindingQName => $notBinding )
			{
				foreach ( $evaluationVars as $evaluationResultKey => $vars )
				{
					if ( ! isset( $vars[ $notBindingQName ] ) ||
						 ! $vars[ $notBindingQName ] instanceof DOMXPathNavigator
					) continue;

					// Each evaluation fact becomes the source of an aspect fact against which to check the original facts
					// Make sure the location is always included to ensure the evaluation facts being compared are alway in the same block
					$testableAspects = array_diff( $notBinding->aspectsDefined, array_diff( $notBinding->aspectsCovered, array( ASPECT_LOCATION) ) );
					$uncoveredAspectFacts = array_fill_keys( $testableAspects, $vars[ $notBindingQName ] );

					$matchedFacts = $this->aspectsMatch( $testableAspects, $uncoveredAspectFacts, $variableSet, $facts, true, false );
					$facts = DocumentOrderNodeIterator::fromItemset( $matchedFacts );
				}
			}

			$count = $facts->getCount();
			global $debug_statements;
			if ( $debug_statements )
			{
				\XBRL_Log::getInstance()->info( "After formulas filter: $count" );
			}
		}

		$binding->facts = $facts; //->CloneInstance();
		// $binding->facts = BufferedNodeIterator::fromSource( $facts );

		return $binding;
	}

	/**
	 * A list of components indexed by fact hash
	 * @var array
	 */
	private $factComponentsCache = array();

	/**
	 * A list of elements indexed by the QName of the element
	 * @var array
	 */
	private $factElementCache = array();

	/**
	 * A list of QNames indexed by fact hash
	 * @var array
	 */
	private $factQNameCache = array();

	/**
	 * A list of parent hashes indexed by fact hash
	 * @var array $factParentCache
	 */
	private $factParentCache = array();

	/**
	 * A list of schema prefixes indexed by namespace
	 * @var array $factNamespaceCache
	 */
	private $factNamespaceCache = array();

	/**
	 * A list of summary information about a fact indexed by hash
	 * @var array $factCache
	 */
	private $factCache = array();

	/**
	 * A list of schema elements for aspect dimensions indexed by dimension qname
	 * @var array $dimensionElementCache
	 */
	private $dimensionElementCache = array();

	/**
	 * A list of the dimensions discovered in contexts. The list is
	 * indexed by context and dimension qname
	 * @var array $contextDimensionCache
	 */
	private $contextDimensionCache = array();

	/**
	 * A list of equality definitions associated with a dimension
	 * @var array $equalityDefinitionsCache
	 */
	private $equalityDefinitionsCache = array();

	/**
	 * Filter the provided facts matching them with any implicit filters from other variables
	 * @param array $testableAspects
	 * @param array[DOMXPathNavigator] $uncoveredAspectFacts
	 * @param Formula $variableSet
	 * @param array[DOMXPathNavigator] $facts (by reference) A list of facts to filter
	 * @param bool $negate
	 * @param bool $resetCaches When true the caches will be reset.  Might be set to false when its
	 * 							helpful for them to persist between calls such as in partitionFacts()
	 * @return NULL
	 */
	public function aspectsMatch( $testableAspects, $uncoveredAspectFacts, $variableSet, &$facts, $negate = false, $resetCaches = true )
	{
		if ( count( $variableSet->factVariableBindings ) == 1 )
		{
			// TODO If there is just one variable binding in the variable set and there is a dimensional
			// 		uncovered aspect and the dimensional aspect fact is a tuple handle it
			// 		(see Arelle:FormulaEvaluator.py.ImplicitFilters line 629)
			// Need an example before implementing this
		}

		// Indexes to prevent redundant lookups
		if ( $resetCaches )
		{
			$this->factComponentsCache = array();
			$this->factElementCache = array();
			$this->factQNameCache = array();
			$this->factParentCache = array();
			$this->factNamespaceCache = array();
			$this->factCache = array();
			$this->dimensionElementCache = array();
			$this->contextDimensionCache = array();
			$this->equalityDefinitionsCache = array();
		}

		$matches = array();
		$misMatches = array();
		$types = \XBRL_Types::getInstance();

		foreach ( $facts as /** @var DOMXPathNavigator $fact */ $fact )
		{
			if ( is_null( $fact ) ) continue;

			foreach ( $testableAspects as $aspect )
			{
				$aspectFact = $uncoveredAspectFacts[ $aspect ];
				// BMS 2018-02-11 Changed from 'continue' when the aspect fact is null.
				// This will tend only to occur where there is a an uncovered dimension
				// such as in test 43210 V-05.  The Arelle equivalent is aspectMatches()
				// line 670 in FormulaEvaluator.py
				// BMS 2018-02-16 This is not correct and no longer necessary
				if ( is_null( $aspectFact ) )
				{
					// $misMatches[] = $fact;
					continue;
				}

				// The fact will always match itself
				if ( $aspectFact->getUnderlyingObject()->isSameNode( $fact->getUnderlyingObject() ) )
				{
					continue;
				}

				switch ( $aspect )
				{
					case ASPECT_LOCATION:

						if ( $fact->getUnderlyingObject()->parentNode->isSameNode( $aspectFact->getUnderlyingObject()->parentNode ) )
						{
							continue 2;
						}

						break;

					case ASPECT_CONCEPT:

						if ( $fact->getNamespaceURI() == $aspectFact->getNamespaceURI() &&
							 $fact->getLocalName() == $aspectFact->getLocalName() )
						{
							continue 2;
						}

						break;

					default:

						if ( FactValues::isTuple( $fact ) ) break;

						$factComponents = $this->getFactDetails( $variableSet, $types, $fact );
						$aspectComponents = $this->getFactDetails( $variableSet, $types, $aspectFact );

						$result = $this->aspectMatch( $variableSet, $types, $aspect, $aspectComponents, $factComponents );
						if ( $result ) continue 2;

						break;
				}

				$misMatches[] = $fact;
				continue 2;
			}

			$matches[] = $fact;
		}

		return $negate ? $misMatches : $matches;
	}

	/**
	 * Function to allow aspect matching from an XPath 2.0 custom function generated by the Match* filters
	 * This function is used by lyquidity:aspectMatch
	 * @param Formula $variableSet
	 * @param DOMXPathNavigator $fact
	 * @param DOMXPathNavigator $aspectFact
	 * @param DOMXPathNavigator $aspect
	 */
	public function lyquidityAspectMatch( $variableSet, $fact, $aspectFact, $aspect )
	{
		// The fact will always match itself
		if ( $aspectFact->getUnderlyingObject()->isSameNode( $fact->getUnderlyingObject() ) )
		{
			return true;
		}

		switch ( $aspect )
		{
			case ASPECT_LOCATION:

				return ( $fact->getUnderlyingObject()->parentNode->isSameNode( $aspectFact->getUnderlyingObject()->parentNode ) );

			case ASPECT_CONCEPT:

				return	$fact->getNamespaceURI() == $aspectFact->getNamespaceURI() &&
						$fact->getLocalName() == $aspectFact->getLocalName();

			default:

				if ( FactValues::isTuple( $fact ) ) return false;

				$types = \XBRL_Types::getInstance();

				$factComponents = $this->getFactDetails( $variableSet, $types, $fact );
				$aspectComponents = $this->getFactDetails( $variableSet, $types, $aspectFact );

				return $this->aspectMatch( $variableSet, $types, $aspect, $aspectComponents, $factComponents );
		}

	}

	/**
	 * Return a stored details array or create one and store it
	 * @param Formula $variableSet
	 * @param \XBRL_Types $types
	 * @param DOMXPathNavigator $fact
	 * @return array
	 */
	private function getFactDetails( $variableSet, $types, $fact )
	{
		$factHash = spl_object_hash( $fact );

		if ( ! isset( $this->factCache[ $factHash ] ) )
		{
			// Record the QName and other cache information if not defined
			if ( ! isset( $this->factQNameCache[ $factHash ] ) )
			{
				// Look up the namespace to find the schema prefix
				$factNamespace = $fact->getNamespaceURI();
				if ( ! isset( $this->factNamespaceCache[ $factNamespace ] ) )
				{
					$factTaxonomy = $variableSet->xbrlTaxonomy->getTaxonomyForNamespace( $fact->getNamespaceURI() );
					// Record the mapping
					$this->factNamespaceCache[ $factNamespace ] = $factTaxonomy->getPrefix();
				}
				// Retrieve the prefix to create a QName
				$factPrefix = $this->factNamespaceCache[ $factNamespace ];
				$factQName = new QName( $factPrefix, $fact->getNamespaceURI(), $fact->getLocalName() );

				// And record it against the fact hash
				$this->factQNameCache[ $factHash ] = $factQName;
				// If there is no element cached for the QName get it can record it
				if ( ! isset( $this->factElementCache[ $factQName->clarkNotation() ] ) )
				{
					$this->factElementCache[ $factQName->clarkNotation() ] = $types->getElement( $factQName->localName, $factQName->prefix );
				}
			}

			$factQName = $this->factQNameCache[ $factHash ];

			if ( ! isset( $this->factComponentsCache[ $factHash ] ) )
			{
				$this->factComponentsCache[ $factHash ] = $this->getFactComponents( $variableSet, $fact );
			}

			$factComponents = $this->factComponentsCache[ $factHash ];
			$factComponents['factHash'] = $factHash;
			$factComponents['qname'] = $factQName;
			$factComponents['fact'] = $fact;

			$this->factCache[ $factHash ] = $factComponents;
		}

		return $this->factCache[ $factHash ];
	}

	/**
	 * Returns the unit and context for the fact as an array indexed by 'context' and 'unit' respectively
	 * @param Formula $variableSet
	 * @param DOMXPathNavigator $fact
	 * return array
	 */
	private function getFactComponents( $variableSet, $fact )
	{
		// Lookup the contextRef and unitRef
		$contextRef = FactValues::getContextRef( $fact );
		$unitRef = FactValues::getUnitRef( $fact );

		return array(
			'contextRef' => $contextRef,
			'unitRef' => $unitRef,
		);
	}

	/**
	 * Match the facts based on the aspect
	 * @param Formula $variableSet
	 * @param \XBRL_Types $types
	 * @param string $aspect
	 * @param array $aspectComponents An array containing the context and the unit for the aspect fact
	 * @param array $factComponents An array containing the context and the unit for the fact
	 * @return bool
	 */
	private function aspectMatch( $variableSet, $types, $aspect, $aspectComponents, $factComponents )
	{
		$factElement = $this->factElementCache[ $factComponents['qname']->clarkNotation() ];
		$aspectFactElement = $this->factElementCache[ $aspectComponents['qname']->clarkNotation() ];
		if ( $factElement['substitutionGroup'] == 'xbrli:tuple' || $aspectFactElement['substitutionGroup'] == 'xbrli:tuple' )
		{
			return $factElement['substitutionGroup'] == 'xbrli:tuple' && $aspectFactElement['substitutionGroup'] == 'xbrli:tuple';
		}

		switch ( $aspect )
		{
			case ASPECT_UNIT:

				$factUnit = $factComponents['unitRef'] ? $variableSet->xbrlInstance->getUnit( $factComponents['unitRef'] ) : false;
				$aspectFactUnit = $aspectComponents['unitRef'] ? $variableSet->xbrlInstance->getUnit( $aspectComponents['unitRef'] ) : false;

				if ( $factUnit )
				{
					return \XBRL_Equality::unit_equal( $factUnit, $aspectFactUnit, $types, $variableSet->xbrlInstance->getInstanceNamespaces() );
				}
				return ! ( (bool)$aspectFactUnit );
		}

		$factContextRef = $factComponents['contextRef'];
		$aspectFactContextRef = $aspectComponents['contextRef'];
		if ( ! $factContextRef || ! $aspectFactContextRef )
		{
			return false; # something wrong, there must be a context
		}

		if ( $factContextRef == $aspectFactContextRef )
		{
			return true; # same context
		}

		// Get the corresponding context
		$factContext = $factComponents['contextRef'] ? $variableSet->xbrlInstance->getContext( $factComponents['contextRef'] ) : false;
		$aspectFactContext = $aspectComponents['contextRef'] ? $variableSet->xbrlInstance->getContext( $aspectComponents['contextRef'] ) : false;

		switch ( $aspect )
		{
			case ASPECT_PERIOD:

				return \XBRL_Equality::period_equal( $factContext['period'], $aspectFactContext['period'] );

			case ASPECT_ENTITY_IDENTIFIER:

				return \XBRL_Equality::identifier_equal( $factContext['entity']['identifier'], $aspectFactContext['entity']['identifier'] );

			case ASPECT_COMPLETE_SCENARIO:
			case ASPECT_COMPLETE_SEGMENT:

				$component = $aspect == ASPECT_COMPLETE_SEGMENT ? "segment" : "scenario";
				$paths = array_filter( Formula::$componentPaths, function( $path ) use( $component ) { return in_array( $component, $path ); } );

				// Could be in context or in the entity
				$factComponent = array();
				foreach ( $paths as $path )
				{
					$factComponent = $this->getComponentForPath( $path, $factContext );
					if ( $factComponent ) break;
				}

				$aspectComponent = array();
				foreach ( $paths as $path )
				{
					$aspectComponent = $this->getComponentForPath( $path, $aspectFactContext );
					if ( $aspectComponent ) break;
				}

				// BMS 2018-02-16 I took these lines out at some point but they are required. See test 47206 V-01.
				// BMS 2018-02-17 They are taken out because when the segment or scenario are being compared while
				//				  the aspect model is 'non-dimensional' they constribute to the equality such that
				//				  differently ordered dimensional elements cause a match failure. See test 47206 V-02.
				// unset( $factComponent['explicitMember'] );
				// unset( $factComponent['typedMember'] );
				// unset( $aspectComponent['explicitMember'] );
				// unset( $aspectComponent['typedMember'] );

				// BMS 2018-01-28 Don't understand why this test was missing. Surely if neither context has
				//                a component then it does not count as a mis-match.  Need to look at the
				//				  segment/scenario tests again (12060-Formula-Processing-OCCRules).
				//				  These tests pass so maybe there are other?
				if ( ! (bool)$factComponent && ! (bool)$aspectComponent )
				{
					return true;
				}

				if ( ! (bool)$factComponent || ! (bool)$aspectComponent )
				{
					return false;
				}

				return \XBRL_Equality::segment_equal( $factComponent, $aspectComponent, true );

			case ASPECT_NON_XDT_SCENARIO:
			case ASPECT_NON_XDT_SEGMENT:

				$component = $aspect == ASPECT_NON_XDT_SEGMENT ? "segment" : "scenario";
				$paths = array_filter( Formula::$componentPaths, function( $path ) use( $component ) { return in_array( $component, $path ); } );

				// Could be in context or in the entity
				$factComponent = array();
				foreach ( $paths as $path )
				{
					$factComponent = $this->getComponentForPath( $path, $factContext );
					if ( $factComponent ) break;
				}

				$aspectComponent = array();
				foreach ( $paths as $path )
				{
					$aspectComponent = $this->getComponentForPath( $path, $aspectFactContext);
					if ( $aspectComponent ) break;
				}

				// Remove any non explicit or typed dimension elements
				foreach ( $factComponent as $key => $item )
				{
					if ( $key != 'explicitMember' && $key != 'typedMember' ) continue;
					unset( $factComponent[$key] );
				}

				foreach ( $aspectComponent as $key => $item )
				{
					if ( $key != 'explicitMember' && $key != 'typedMember' ) continue;
					unset( $aspectComponent[$key] );
				}

				if ( ! (bool)$factComponent && ! (bool)$aspectComponent )
				{
					return true;
				}

				if ( ! (bool)$factComponent || ! (bool)$aspectComponent )
				{
					return false;
				}

				return \XBRL_Equality::segment_equal( $factComponent, $aspectComponent );

		}

		// The only tests remaining are for the dimensions
		// Get the taxonomy for the dimension element
		$dimQName = qname( $aspect );

		if ( ! isset( $this->dimensionElementCache[ $dimQName->clarkNotation() ] ) )
		{
			$dimTaxonomy = $variableSet->xbrlTaxonomy->getTaxonomyForNamespace( $dimQName->namespaceURI );
			if ( ! $dimTaxonomy ) return false; // This should not happen

			$this->dimensionElementCache[ $dimQName->clarkNotation() ] = $dimTaxonomy->getElementByName( $dimQName->localName );
		}

		$dimElement = $this->dimensionElementCache[ $dimQName->clarkNotation() ];

		// return true;

		if ( ! isset( $this->contextDimensionCache[ $factContextRef ][ $dimQName->clarkNotation() ] ) )
		{
			$this->contextDimensionCache[ $factContextRef ][ $dimQName->clarkNotation() ] = $this->getDimensionFromComponent( $variableSet, $dimQName, $dimElement, $factContext );
		}
		$factDimValue = $this->contextDimensionCache[ $factContextRef ][ $dimQName->clarkNotation() ];

		// return true;

		if ( ! isset( $this->contextDimensionCache[ $aspectFactContextRef ][ $dimQName->clarkNotation() ] ) )
		{
			$this->contextDimensionCache[ $aspectFactContextRef ][ $dimQName->clarkNotation() ] = $this->getDimensionFromComponent( $variableSet, $dimQName, $dimElement, $aspectFactContext );
		}
		$aspectDimValue = $this->contextDimensionCache[ $aspectFactContextRef ][ $dimQName->clarkNotation() ];

		if ( ! $factDimValue && ! $aspectDimValue )
		{
			return true;
		}

		if ( ! $factDimValue || ! $aspectDimValue )
		{
			return false;
		}

		if ( isset( $dimElement['typedDomainRef'] ) )
		{
			// Check to see that both fact and aspect have only one member (see Variables 1.0 2.1.2.1 and 2.1.2.2)
			if ( count( $factDimValue['member'] ) != 1 || count( $aspectDimValue['member'] ) != 1 ) return false;

			// Check to see if there is an equality defintion for the aspect
			if ( ! isset( $this->equalityDefinitionsCache[ $dimQName->clarkNotation() ] ) )
			{
				$equalityDefinitions = $this->getEqualityDefinitionsForTypedDimenson( $dimQName, $variableSet->xbrlTaxonomy );
				foreach ( $equalityDefinitions as $equalityDefinition )
				{
					$xpathExpression = XPath2Expression::Compile( $equalityDefinition['test'], $variableSet->nsMgr );
					$this->equalityDefinitionsCache[ $dimQName->clarkNotation() ][] = $xpathExpression;
				}
			}

			if ( isset( $this->equalityDefinitionsCache[ $dimQName->clarkNotation() ] ) )
			{
				$equalityDefinitions = $this->equalityDefinitionsCache[ $dimQName->clarkNotation() ];

				// Need to apply the custom function defined by the equality definition
				foreach ( $equalityDefinitions as $equalityDefinition )
				{
					$aspectNamespace = \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_ASPECT_TEST ];
					$result = $this->evaluateXPathExpression( $variableSet, $equalityDefinition,
						array(
							"{{$aspectNamespace}}a" => $factComponents['fact'],
							"{{$aspectNamespace}}b" => $aspectComponents['fact']
						)
					);

					return CoreFuncs::BooleanValue( $result ) instanceof CoreFuncs::$True;
				}
			}
			else
			{
				if ( ! property_exists( $this, 'comparer' ) )
				{
					$this->comparer = new TreeComparer( null );
					$this->comparer->excludeWhitespace = true;
					$this->comparer->untypedCanBeNumeric = true;
				}

				$namespaces = array();
				foreach ( $variableSet->xbrlInstance->getInstanceNamespaces() as $prefix => $namespace )
				{
					$namespaces[] = empty( $prefix )
						? "xmlns=\"$namespace\""
						: "xmlns:$prefix=\"$namespace\"";
				}
				$namespaces = implode( " ", $namespaces );

				// Perform a simple value check
				foreach ( $factDimValue['member'] as $key => $members )
				{
					if ( ! isset( $aspectDimValue['member'][ $key ] ) )
					{
						return false;
					}

					foreach ( $members as $value )
					{
						$doc1 = new \DOMDocument();
						$result = @$doc1->loadXML( "<?xml version=\"1.0\" encoding=\"UTF-8\"?><root $namespaces>$value</root>" );

						$doc2 = new \DOMDocument();
						$value1 = $aspectDimValue['member'][ $key ][0];
						$result = @$doc2->loadXML( "<?xml version=\"1.0\" encoding=\"UTF-8\"?><root $namespaces>{$aspectDimValue['member'][ $key ][0]}</root>" );

						$res = $this->comparer->DeepEqualByNavigator( new DOMXPathNavigator( $doc1 ), new DOMXPathNavigator( $doc2 ) );
						if ( ! $res )
						{
							return false;
						}
						// return $res;
					}
				}

				return true;
			}
		}
		else
		{
			return $factDimValue['member'] == $aspectDimValue['member'];
		}

		return true;
	}

	/**
	 * Get the components for the path requested
	 * @param array $path
	 * @param array $context
	 * @return array
	 */
	private function getComponentForPath( $path, $context )
	{
		foreach ( $path as $element )
		{
			if ( ! $context ) continue;
			if ( ! isset( $context[ $element ] ) )
			{
				$context = array();
				continue;
			}
			$context = $context[ $element ];
		}

		return $context;
	}

	/**
	 * Retrieve the dimension information from a context
	 * @param Formula $variableSet
	 * @param QName $dimQName The QName of the defining dimension name
	 * @param QName $dimElement The QName of the defining dimension member
	 * @param array $context An array containing contet information
	 * @return array
	 */
	private function getDimensionFromComponent( $variableSet, $dimQName, $dimElement, $context )
	{
		$typed = isset( $dimElement['typedDomainRef'] );

		// Could be in context or in the entity
		foreach ( Formula::$componentPaths as $path )
		{
			$component = $this->getComponentForPath( $path, $context );
			if ( ! $component ) continue;

			// Look for the dimension type but if not found try a different path
			if ( ! isset( $component[ $typed ? 'typedMember' : 'explicitMember' ] ) ) continue;

			foreach ( $component[ $typed ? 'typedMember' : 'explicitMember' ] as $dimension )
			{
				// See if the qname of the dimension is the same at the aspect QName
				$qname = qname( $dimension['dimension'], $variableSet->xbrlInstance->getInstanceNamespaces() );
				if ( ! $qname->equals( $dimQName ) ) continue;

				return $dimension;
			}
		}

		return array();
	}

	/**
	 * Generate a query to test whether pairs of variables match based on their aspect values
	 * TODO This need work to improve it.  One action is to perform the conceptRef test only
	 *      for each unique fact.  At the moment it happens for each aspect most of which will
	 * 		use the same fact.
	 * @param array $testableAspects
	 * @param array[DOMXPathNavigator] $uncoveredAspectFacts
	 * @param Formula $variableSet
	 * @param array $filterExpressions
	 * @param array $vars
	 * @return NULL
	 */
	public function createAspectsTestQuery( $testableAspects, $uncoveredAspectFacts, $variableSet, &$filterExpressions, &$vars )
	{
		if ( is_null( $filterExpressions ) ) $filterExpressions = array();
		if ( is_null( $vars ) ) $vars = array();

		if ( count( $variableSet->factVariableBindings ) == 1 )
		{
			// TODO If there is just one variable binding in the variable set and there is a dimensional
			// 		uncovered aspect and the dimensional aspect fact is a tuple handle it
			// 		(see Arelle:FormulaEvaluator.py.ImplicitFilters line 629)
			// Need an example before implementing this
		}

		foreach ( $testableAspects as $aspect )
		{
			$aspectFact = $uncoveredAspectFacts[ $aspect ];

			if ( is_null( $aspectFact ) ) continue;

			// $conceptRef = FactValues::getContextRef( $aspectFact );

			if ( in_array( $aspect, $variableSet->aspectModel == 'dimensional' ? Formula::$dimensionAspectModelMembers : Formula::$nonDimensionAspectModelMembers ) )
			{
				// When true a test will be added
				// if ( $aspect == ASPECT_NON_XDT_SEGMENT ) continue;
				// if ( $aspect == ASPECT_NON_XDT_SCENARIO ) continue;
				// if ( $aspect == ASPECT_COMPLETE_SCENARIO ) continue;
				// if ( $aspect == ASPECT_COMPLETE_SEGMENT ) continue;
				// if ( $aspect == ASPECT_UNIT ) continue;
				// if ( $aspect == ASPECT_LOCATION ) continue;

				$modifiedAspect = "aspect:" . trim( $aspect, '\\' );
				$modifiedAspectQName = qname( $modifiedAspect, $variableSet->nsMgr->getNamespaces() );
				$aspectFilterExpression = str_replace("\$aspectTest:b", "\$$modifiedAspect", str_replace( "\$aspectTest:a", ".", Aspect::$aspectTests[ $aspect ] ) );
				if ( $aspect == ASPECT_UNIT )
				{
					// "(not(xfi:is-numeric(fn:node-name(\$aspectTest:a))) and not(xfi:is-numeric(fn:node-name(\$aspectTest:b)))) or ";
					$aspectFilterExpression = "(.[not(@unitRef)] and \${$modifiedAspect}[not(@unitRef)]) or (./@unitRef eq \${$modifiedAspect}/@unitRef) or ($aspectFilterExpression)";
				}
				else if ( $aspect != ASPECT_CONCEPT  && $aspect != ASPECT_LOCATION )
				{
					// Add a context test to exempt period, entity, segment and dimension
					// tests if the fact and aspect fact have the same context ref.
					// $aspectFilterExpression = ".[@conceptRef eq '$conceptRef'] or ($aspectFilterExpression)";
					$aspectFilterExpression = "(.[not(@contextRef)] and \${$modifiedAspect}[not(@contextRef)]) or (./@contextRef eq \${$modifiedAspect}/@contextRef) or ($aspectFilterExpression)";
				}

				$filterExpressions[ $modifiedAspect ] = "($aspectFilterExpression)";

				$vars[ $modifiedAspectQName->clarkNotation() ] = $aspectFact;
			}
			else
			{
				// Dimensional
				if ( ! in_array( ASPECT_DIMENSIONS, $variableSet->covers ) )
				{
					\XBRL_Log::getInstance()->formula_validation( "Implicit filters", "Dimensions cannot appear as implicit filters when the aspect model does not support them",
						array(
							'variable' => $this->name,
							'dimension' => $aspect
						)
					);

					return null;
				}

				$aspectQName = qname( $aspect, $variableSet->nsMgr->getNamespaces() );
				$aspectQName->prefix = $variableSet->nsMgr->lookupPrefix( $aspectQName->namespaceURI );

				if ( in_array( $aspect, $variableSet->explicitDimensions ) )
				{
					// Add a filter for this explicit dimension
					$dimension = "fn:QName('{$aspectQName->namespaceURI}','{$aspectQName->prefix}:{$aspectQName->localName}')";
					$dimensionFilterTest = str_replace(
						"#dimension", $dimension, str_replace(
							"\$aspectTest:b",
							"\${$aspectQName->prefix}:{$aspectQName->localName}", str_replace(
								"\$aspectTest:a", ".",
								Aspect::$explicitDimensionTest
							)
						)
					);
					// $filterExpressions[] = "(.[@conceptRef eq '$conceptRef'] or ($dimensionFilterTest))";
					// The following test means match if:
					//	a context ref is not defined on either fact
					//	the same context ref is defined on both facts
					//	the explicit filter matches
					$filterExpressions[ $aspectQName->clarkNotation() ] = "((.[not(@contextRef)] and \${$aspectQName->prefix}:{$aspectQName->localName}[not(@contextRef)]) or (./@contextRef eq \${$aspectQName->prefix}:{$aspectQName->localName}/@contextRef) or ($dimensionFilterTest))";
				}
				else if ( in_array( $aspect, $variableSet->typedDimensions ) )
				{
					$equalityDefinitions = $this->getEqualityDefinitionsForTypedDimenson( $aspectQName, $variableSet->xbrlTaxonomy );
					if ( $equalityDefinitions )
					{
						$customTest = "(" . implode( ") or (", array_map( function( $def ) { return $def['test']; }, Aspect::$customTypedDimensionTest ) ) . ")";
						$test = str_replace( "#custom", $customTest, Aspect::$customTypedDimensionTest );
					}
					else
					{
						$test = Aspect::$defaultTypedDimensionTest;
					}

					// Add a filter for a typed dimension
					$dimension = "fn:QName('{$aspectQName->namespaceURI}','{$aspectQName->prefix}:{$aspectQName->localName}')";
					$dimensionFilterTest = str_replace(
						"#dimension",
						$dimension, str_replace(
							"\$aspectTest:b",
							"\${$aspectQName->prefix}:{$aspectQName->localName}", str_replace(
								"\$aspectTest:a", ".", $test
							)
						)
					);

					// $filterExpressions[] = "(.[@conceptRef eq '$conceptRef'] or ($dimensionFilterTest))";
					// The following test means match if:
					//	a context ref is not defined on either fact
					//	the same context ref is defined on both facts
					//	the explicit filter matches
					$filterExpressions[ $aspectQName->clarkNotation() ] = "((.[not(@contextRef)] and \${$aspectQName->prefix}:{$aspectQName->localName}[not(@contextRef)]) or (./@contextRef eq \${$aspectQName->prefix}:{$aspectQName->localName}/@contextRef) or ($dimensionFilterTest))";
				}
				else
				{
					// Filter on context ref only
					$filterExpressions[ $aspectQName->clarkNotation() ] = "((.[not(@contextRef)] and \${$aspectQName->prefix}:{$aspectQName->localName}[not(@contextRef)]) or (./@contextRef eq \${$aspectQName->prefix}:{$aspectQName->localName}/@contextRef))";
				}

				$vars[ $aspectQName->clarkNotation() ] = $aspectFact;
			}
		}

	}

	/**
	 * Return any equality definitions for the dimension defined by $aspectQName
	 * @param QName $aspectQName
	 * @param XBRL $taxonomy
	 * @return array
	 */
	private function getEqualityDefinitionsForTypedDimenson( $aspectQName, $taxonomy )
	{
		// Get all the equality definitions
		$equalityDefinitions = array();

		$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $aspectQName->namespaceURI );
		if ( ! $dimTaxonomy ) return $dimTaxonomy;

		$element = $dimTaxonomy->getElementByName( $aspectQName->localName );
		if ( ! $element ) return $dimTaxonomy;

		$domainRef = $element['typedDomainRef'];
		$parts = explode( "#", $domainRef );
		if ( empty( $parts[0] ) ) // Might be a local reference
		{
			$parts[0] = $dimTaxonomy->getTaxonomyXSD();
			$domainRef = implode( "#", $parts );
		}

		// BMS 2019-02-11 This might need turning inside-out so the arc are read and the resources filtered.
		//                This is how other look ups work.
		// BMS 2019-02-22 No it doesn't because the equality definition is linked to the dimension member not this variable
		$equalityDefinitionResources = $taxonomy->getGenericResource( 'equality', 'equalityDefinition', null, null, null );
		foreach ( $equalityDefinitionResources as $equalityDefinitionResource )
		{
			// BMS 2019-02-21 TODO Filter resources by linkbase
			$equalityDefinitionArcs = $taxonomy->getGenericArc( \XBRL_Constants::$arcRoleVariableEqualityDefinition, $equalityDefinitionResource['roleUri'], null, $this->path, null, $equalityDefinitionResource['linkbase'] );
			$domainRefArcs = array_filter( $equalityDefinitionArcs, function( $arc ) use( $domainRef )
			{
				return $arc['from'] == $domainRef;
			} );

			if ( ! $domainRefArcs ) continue;
			// Now look for the equality resource with the 'to' name
			$domainRefTos = array_map( function( $arc ) { return $arc['to']; }, $domainRefArcs );
			if ( in_array( $equalityDefinitionResource['resourceName'], $domainRefTos ) )
			{
				$equalityDefinitions[] = $equalityDefinitionResource['equality'];
			}
		}

		if ( count( $equalityDefinitions ) > 1 )
		{
			\XBRL_Log::getInstance()->formula_validation( "Variables 2.1.2.2.1", "Typed-dimension domain definition has more than one equality-definition relationship to an equality definition",
				array(
					'domain ref' => $domainRef,
					'error' => 'xbrlve:multipleTypedDimensionEqualityDefinitions'
				)
			);
		}

		return $equalityDefinitions;
	}

}
