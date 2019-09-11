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

namespace XBRL\Formulas;

use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\VariableBinding;
use XBRL\Formulas\Resources\Variables\FactVariable;
use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\Resources\Assertions\ExistenceAssertion;

/**
 * This class will evaluate the variables in a variable set.  A new class will be created recursively for each
 * variable processed within a fact loop
 */
class Evaluation
{
	/**
	 * A reference to the variable set
	 * @var VariableSet
	 */
	public $variableSet;

	/**
	 * A portion of the variable dependency hierachy to be processed
	 * @var array $variableHierarchy
	 */
	public $orderedVariableQNames = array();

	/**
	 * A set of expression used to exclude facts (mainly when the parent variable has a fallback value applied)
	 * @var array[FactVariableBinding]
	 */
	public $notBindings = array();

	/**
	 * Evaluate the variable
	 * @param VariableSet $variableSet
	 * @param array $orderedVariableQNames
	 * @param array $notBindings
	 * @param array $uncoveredAspectFacts
	 */
	public static function Evaluate( $variableSet, $orderedVariableQNames, $notBindings, $uncoveredAspectFacts = null )
	{
		return ( new Evaluation( $variableSet, $orderedVariableQNames, $notBindings ) )->processVariables( $uncoveredAspectFacts );
	}

	/**
	 * Constructor
	 * @param VariableSet $variableSet
	 * @param array $orderedVariableQNames
	 * @param array $notBindings
	 */
	public function __construct( $variableSet, $orderedVariableQNames, $notBindings )
	{
		$this->variableSet = $variableSet;
		$this->orderedVariableQNames = $orderedVariableQNames;
		$this->notBindings = $notBindings;

		reset( $this->orderedVariableQNames );
	}

	/**
	 * Called to process variables in the hierarchy order
	 * @param array $uncoveredAspectFacts A list of the uncovered aspect facts
	 * @return boolean
	 */
	public function processVariables( $uncoveredAspectFacts )
	{
		$originalUncoveredAspectFacts = $uncoveredAspectFacts;

		// Any variables to process?
		if ( current( $this->orderedVariableQNames ) === false )
		{
			$this->variableSet->evaluateResult();
			return true;
		}

		$qname = current( array_keys( $this->orderedVariableQNames ) );

		// Find the corresponding variable
		if ( ! isset( $this->variableSet->variablesByQName[ $qname ] ) )
		{
			\XBRL_Log::getInstance()->formula_validation( "Formula evaluation", "Unable to find a variable with the qname",
				array(
					'qname' => $qname,
					'variable set' => $this->variableSet->label,
				)
			);

			return false;
		}

		/**
		 * @var FactVariable $variable
		 */
		$variable = $this->variableSet->variablesByQName[ $qname ];

		/**
		 * @var VariableBinding $binding
		 */
		$binding = $variable->Filter( $this, $uncoveredAspectFacts );
		if ( is_null( $binding ) )
		{
			return false;
		}
		if ( $binding->isGeneralVar() )
		{
			$this->variableSet->generalVariableBindings[ $qname ] =& $binding;
		}
		else
		{
			$this->variableSet->setLastFactBinding( $binding );
			$this->variableSet->factVariableBindings[ $qname ] =& $binding;
		}

		$remainder = array_slice( $this->orderedVariableQNames, 1 );

		// If there are no remainders or there are
		if ( count( $remainder ) )
		{
			$evaluated = false;

			if ( $variable->bindAsSequence )
			{
				if ( ! $binding->rewind() )
				{
					if ( $binding->setFallbackValue( $this->variableSet, ! $originalUncoveredAspectFacts ) )
					{
						$bindings = $this->notBindings;
						$bindings[ $qname ] = $binding;
						$result = Evaluation::Evaluate( $this->variableSet, $remainder, $bindings, $originalUncoveredAspectFacts );
					}

					unset( $binding );
					return true;
				}

				$binding->current();

				if ( $variable instanceof FactVariable )
				{
					$this->updateUncoveredAspectFacts( $uncoveredAspectFacts, $binding );
					$uncoveredAspectFacts = $binding->uncoveredAspectFacts;
				}

				// Variables bound as a sequence need to be partitioned into groups with matching aspect values
				// each group processed as a fact.
				$binding->partitionFacts( $this->variableSet );

				// Could just use a foreach( $binding as $fact ) loop here but the fact
				// is not required here just advance the current fact
				// $more = true;
				while( true )
				{
					// BMS 2018-03-10
					// This call is used to ensure that nested variables imported into scope by a scope-variable
					// arc are only used where their uncovered aspect facts overlap.  Conformance test 60600 V-02
					// provides an example.  Formula_A imports Formula_C and Formula_E.  Both imports return two facts
					// but each with a different context.  So when Formula_E.e (context c1) is matched with Formula_C.c
					// (context c1) the values are used.  But when Formula_E.e (context c1) is matched with Formula_C.c
					// (context c2) they do not and the values are not considered.
					if ( $variable->matchUncoveredAspects( $this->variableSet, $originalUncoveredAspectFacts, $binding ) )
					{
						$result = Evaluation::Evaluate( $this->variableSet, $remainder, $this->notBindings, $uncoveredAspectFacts );
						if ( ! $result )
						{
							return false;
						}
						$evaluated = true;
					}

					if ( ! $binding->next() )
					{
						break;
					}

					if ( $variable instanceof FactVariable )
					{
						$this->updateUncoveredAspectFacts( $uncoveredAspectFacts, $binding );
						$uncoveredAspectFacts = $binding->uncoveredAspectFacts;
					}
				}

			}
			else
			{
				// $fact is not used directly.  The foreach loop updates the current fact and this is used.
				// This could be replaced with a while( next() ) pattern
				foreach ( $binding as $fact )
				{
					// BMS 2018-03-10 See the note above with the same date for an explanation of the reason for this call
					if ( ! $variable->matchUncoveredAspects( $this->variableSet, $originalUncoveredAspectFacts, $binding ) )
					{
						continue;
					}

					if ( $binding->isFactVar() )
					{
						$this->updateUncoveredAspectFacts( $uncoveredAspectFacts, $binding );
						$uncoveredAspectFacts = $binding->uncoveredAspectFacts;
					}

					// Need to provide the appropriate uncovered aspects list (maybe this property should be part of the binding?)
					$result = Evaluation::Evaluate( $this->variableSet, $remainder, $this->notBindings, $uncoveredAspectFacts );
					if ( ! $result )
					{
						return false;
					}
					$evaluated = ! $this->variableSet instanceof Formula; // Is an assertion
				}
			}

			// BMS 2018-03-27 The $evaluated variable was added to catch an issue with one of the assertion tests
			//				  which failed because results appear to be evaluated more than once (22180 V-41)).
			//				  But it causes a problem with 22180 V-10 as it omits half the results!
			//				  It is necessary to call this function after sucessfully processing any discovered fact
			//				  if the variable at a high level has a fallback value as is the case in 22180 V-10.
			if ( ! $evaluated && $binding->setFallbackValue( $this->variableSet, ! $originalUncoveredAspectFacts ) )
			{
				$bindings = $this->notBindings;
				$bindings[ $qname ] = $binding;
				$result = Evaluation::Evaluate( $this->variableSet, $remainder, $bindings, $originalUncoveredAspectFacts );
			}
		}
		else
		{
			// There's no remainder so no more variables to process.  Time to use the facts.
			if ( $variable->bindAsSequence )
			{
				if ( ! $binding->isFallback )
				{
					if ( $variable instanceof FactVariable )
					{
						// For good or bad, the semantic of the FactVariableBinding class requires there
						// to be a retrieved current for the uncoveredAspectFacts array to be valid.
						$binding->rewind();
						$binding->current();

						$this->updateUncoveredAspectFacts( $uncoveredAspectFacts, $binding );
						$uncoveredAspectFacts = $binding->uncoveredAspectFacts;
					}

					// Variables bound as a sequence need to be partitioned.  For fact variables this is into groups with
					// matching aspect values.  For general variables it is to process the values as one sequence.
					// Each group (fact binding = n, general binding = 1) is then processed as a fact.
					$binding->partitionFacts( $this->variableSet );
				}
			}

			if ( $binding->rewind() )
			{
				// Could just use a foreach( $binding as $fact ) loop here but the fact
				// is not required here just advance the current fact
				$more = true;
				while( $more )
				{
					$current = $binding->current();
					// BMS 2018-03-10 See the note above with the same date for an explanation of the reason for this call
					if ( $variable->matchUncoveredAspects( $this->variableSet, $originalUncoveredAspectFacts, $binding ) )
					{
						$this->variableSet->evaluateResult();
					}

					$more = $binding->next();
				}
			}
			else if ( $binding->setFallbackValue( $this->variableSet, true ) )
			{
				$this->variableSet->evaluateResult();
			}
			else if ( $this->variableSet instanceof ExistenceAssertion )
			{
				$this->variableSet->evaluationNotSatisfied();
			}
		}

		unset( $binding );
		if ( isset( $this->variableSet->factVariableBindings[ $qname ] ) )
		{
			isset( $this->variableSet->factVariableBindings[ $qname ] );
		}

		if ( isset( $this->variableSet->generalVariableBindings[ $qname ] ) )
		{
			isset( $this->variableSet->generalVariableBindings[ $qname ] );
		}

		return true;
	}

	/**
	 * Update the uncover aspect facts list in the bindings
	 * @param array $uncoveredAspectFacts
	 * @param VariableBinding $binding
	 */
	private function updateUncoveredAspectFacts( $uncoveredAspectFacts, $binding )
	{
		if ( ! $uncoveredAspectFacts || $binding instanceof ScopeVariableBinding ) return;

		// Get the current fact so the uncovered aspects array
		$fact = $binding->current();

		// Fill any null values in the parent binding with facts from $binding
		// NOTE: Arelle does it this way (see implicitFilter() on line 618 of FormulaEvaluator.py) but it is
		//       necessary that the facts in the parent binding take precedence.  The formula spec say that
		//       any of the facts can be used.  Still, for testing purposes, making this process as close to
		//       the way Arelle operates means it is easier to identify implementation problems.
		foreach ( $binding->uncoveredAspectFacts as $aspect => $fact )
		{
			// Don't change non-null aspects
			if ( ! is_null( $fact ) ) continue;

			// Otherwise, if the corresponding aspect in the existing uncovered aspect facts list is not null apply it.
			if ( ! isset( $uncoveredAspectFacts[ $aspect ] ) || is_null( $uncoveredAspectFacts[ $aspect ] ) ) continue;

			$binding->uncoveredAspectFacts[ $aspect ] = $uncoveredAspectFacts[ $aspect ];
		}
	}
}