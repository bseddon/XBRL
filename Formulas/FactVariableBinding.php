<?php

/**
 * XBRL Formulas
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *		 |___/	  |_|					 |___/
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

use XBRL\Formulas\Resources\Formulas\Formula;
// use XBRL\Formulas\Resources\Variables\Variable;
use lyquidity\XPath2\XPath2NodeIterator;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\XPath2Expression;

 /**
  * Implements a binding implementation for fact variables
  */
class FactVariableBinding extends VariableBinding implements \Iterator
{
	/**
	 * Aspects covered for this binding
	 * @var array $aspectsCovered
	 */
	public $aspectsCovered = array();

	/**
	 * A list of aspects defined for this fact
	 * @var array $aspectsDefined
	 */
	public $aspectsDefined = array();

	/**
	 * A list of the aspects and their uncovered facts
	 * @var array $uncoveredAspectFacts
	 */
	public $uncoveredAspectFacts = array();

	/**
	 * Provides a binding the chance to set a fallback value and return true or return false
	 * Set a fallback value for the related variable if it has a fallback value
	 * @param VariableSet $variableSet
	 * @param bool alwaysUseFallbackValue
	 * @return boolean
	 */
	public function setFallbackValue( $variableSet, $alwaysUseFallbackValue = false )
	{
		if ( is_null( $this->var->fallbackValue ) ) return false;
		if ( ! $alwaysUseFallbackValue && ! is_null( $this->facts ) ) return false;

		$this->facts = null;

		$vars = $variableSet->getParametersAsVars();

		// Evaluate the fallback value.  Should return an XPath2Item
		$fallbackResult = $this->var->evaluateXPath( $variableSet, $this->var->fallbackValue, $vars );
		$this->yieldedFact = $fallbackResult;
		$this->isFallback = true;

		return true;
	}

	/** Iterator support */

	/**
	 * Move to the beginning of the operator
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\VariableBinding::rewind()
	 */
	public function rewind()
	{
		$this->uncoveredAspectFacts = array();
		return parent::rewind();
	}

	/**
	 * Returns the current item and sets the uncovered aspect fact array if necessary
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\VariableBinding::current()
	 */
	public function current()
	{
		$fact = parent::current();
		if ( $fact )
		{
			if ( ! $this->uncoveredAspectFacts )
			{
				$coveredFact = $fact;

				if ( $this->var->bindAsSequence )
				{
					if ( $coveredFact instanceof XPath2NodeIterator )
					{
						// Get the first item
						$coveredFact->rewind();
						$coveredFact = $coveredFact->current();  // Should be DOMXPathNavigator
					}
				}

				$this->uncoveredAspectFacts = array_fill_keys( $this->aspectsDefined, $coveredFact );
				$this->uncoveredAspectFacts = array_merge( $this->uncoveredAspectFacts, array_fill_keys( $this->aspectsCovered, null ) );
			}
		}

		return $fact;
	}

	/**
	 * Returns the next $fact value or FALSE if there are no more facts.
	 * {@inheritDoc}
	 * @see \Iterator::next()
	 */
	public function next()
	{
		$this->uncoveredAspectFacts = array();
		return parent::next();
	}

	/**
	 * Check if the current node is valid and that the pointer can be moved forwards
	 * {@inheritDoc}
	 * @see \Iterator::valid()
	 */
	public function valid()
	{
		if ( parent::valid() )
		{
			return true;
		}
		else
		{
			$this->uncoveredAspectFacts = array();
			return false;
		}
	}

	/**
	 * Partitions the facts in this binding into groups that have the same aspect values
	 * The function will leave settings the $facts variable to the list of groups generated
	 * TODO This needs to become part of the binding iterator so the partitions are yielded
	 * 		as soon as they are available.
	 * @param Formula $variableSet
	 * @return void
	 */
	public function partitionFacts( $variableSet )
	{
		$groups = array();

		$remainingFacts = array();
		if ( $this->facts )
		foreach ( $this->facts as $fact )
		{
			$clonedFact = $fact instanceof XPath2NodeIterator
				? $fact->CloneInstance()
				: $fact;
			$remainingFacts[ spl_object_hash( $clonedFact ) ] = $clonedFact;
		}

		$test = 0;
		// Following line is useful to produce tests of comparisons starting later in the list of remaining facts
		// $remainingFacts = array_slice( $remainingFacts, 6 );

		if ( $this->uncoveredAspectFacts )
		{
			while ( count( $remainingFacts ) )
			{
				$test++;

				// Take the first fact and find all the others like it
				// First remove it from the list of facts

				$firstFact = reset( $remainingFacts );
				$firstKey = key( $remainingFacts );
				unset( $remainingFacts[ $firstKey ] );

				$group = array( $firstFact );

				// The list of $uncoveredAspectFactKeys cannot include aspects covered by this binding
				$uncoveredAspectFactKeys = array_diff( array_keys( array_filter( $this->uncoveredAspectFacts ) ), $this->aspectsCovered );
				$uncoveredAspectFacts = array_fill_keys( $uncoveredAspectFactKeys, $firstFact );
				$matchedFacts = $this->var->aspectsMatch( $uncoveredAspectFactKeys, $uncoveredAspectFacts, $variableSet, $remainingFacts, false, $test == 1 );

				$count = count( $matchedFacts );

				// Add each fact to the group and remove it from the list of remaining facts
				foreach ( $matchedFacts as $matchedFact )
				{
					$group[] = $matchedFact;

					if ( isset( $remainingFacts[ spl_object_hash( $matchedFact ) ] ) )
					{
						unset( $remainingFacts[ spl_object_hash( $matchedFact ) ] );
					}
					else
					{
						// Getting here means the use of spl_object_hash on the $fact instances has not worked
						// Instead need to focus on the underlying object
						echo "This is a problem\n";
					}
				}

				// \XBRL_Log::getInstance()->info( "$test: $count" );

				// If there are any add them to the group
				if ( $count )
				{
					$test += $count;

					// If the 'matches' attribute is not defined for the variable then remove duplicate facts from the result
					// This is the equivalent of matchesSubPartitions() (FormulaEvaluator.py line 1265)
					// The difference between this comparison and the XPath comparison is that this one compares all aspects
					// It follows then that if the XPath is comparing all apsects then this is redundant.

					// If there are no aspects covered then there can be no facts that are more unique.
					if ( count( $this->aspectsCovered ) && ! $this->var->matches )
					{
						// Remove duplicates
						$uniqueFacts = array();
						$duplicates = array();

						foreach ( $group as $key1 => /** @var DOMXPathNavigator $fact1 */ $fact1 )
						{
							if ( $uniqueFacts )
							{
								$parent1 = $fact1->CloneInstance();
								$parent1->MoveToParent();

								$unitRef1 = FactValues::getUnitRef( $fact1 );
								$contextRef1 = FactValues::getContextRef( $fact1 );
								$name1 = $fact1->getLocalName();
								// $found = false;

								// Compare this fact against the facts remaining in $group
								foreach ( $uniqueFacts as $key2 => /** @var DOMXPathNavigator $fact2 */ $fact2 )
								{
									// No need to compare against self
									if ( $key1 == $key2 ) continue;

									$parent2 = $fact2->CloneInstance();
									$parent2->MoveToParent();

									if ( ! $parent1->IsSamePosition( $parent2 ) )
									{
										continue;
									}

									if ( $fact2->getLocalName() != $name1 )
									{
										continue;
									}

									$unitRef2 = FactValues::getUnitRef( $fact2 );
									$contextRef2 = FactValues::getContextRef( $fact2 );

									// Neither has a unit ref there is nothing to check
									if ( $unitRef1 && $unitRef1 )
									{
										// If one has a unit ref but the other does not then they are not duplicates
										if ( ! $unitRef1 && $unitRef2 || $unitRef1 && ! $unitRef2 )
										{
											continue;
										}

										// OK, both have unit refs so compare then
										if ( ! \XBRL_Equality::unit_equal(
												$variableSet->xbrlInstance->getUnit( $unitRef1 ),
												$variableSet->xbrlInstance->getUnit( $unitRef2 ),
												\XBRL_Types::getInstance(),
												$variableSet->xbrlInstance->getInstanceNamespaces()
											)
										)
										{
											continue;
										}
									}

									// Both MUST have context refs so compare them
									if ( ! \XBRL_Equality::context_equal(
											$variableSet->xbrlInstance->getContext( $contextRef1 ),
											$variableSet->xbrlInstance->getContext( $contextRef2 )
										)
									)
									{
										continue;
									}

									$duplicates[] = $fact1;

									continue 2;
									// $found = true;
									// break;
								}

								// if ( $found ) continue;
							}

							$uniqueFacts[ $key1 ] = $fact1;
						}

						// $group = $uniqueFacts;
						$groups = array_merge( $groups, array( DocumentOrderNodeIterator::fromItemset( $uniqueFacts ) ), $duplicates );
						$group = array();

					}
				}

				// Two different tests:
				// 22170 v22 (no matches) Duplicate facts in a sub-sequence
				//			 This uses a sub-sequence because some aspects are covered and there are no duplicates
				//           when all aspects are considered.
				// 22170 v27 (matches) Duplicate facts in a sub-sequence
				// 22170 v28 (no matches) All duplicate facts output in sequence
				//			 There are no covered aspects and there are no duplicates
				// 22170 v29 (matches)
				if ( $this->var->matches )
				{
					$groups[] = DocumentOrderNodeIterator::fromItemset( $group );
				}
				else
				{
					$groups = array_merge( $groups, $group );
					// $groups[] = DocumentOrderNodeIterator::fromItemset( $group );
				}
			}
		}
		else
		{
			$groups = array_values( $remainingFacts );
		}

		$this->facts = DocumentOrderNodeIterator::fromItemset( $groups );
	}

	/**
	 * Partitions the facts in this binding into groups that have the same aspect values
	 * The function will leave settings the $facts variable to the list of groups generated
	 * TODO This needs to become part of the binding iterator so the partitions are yielded
	 * 		as soon as they are available.
	 * @param Formula $variableSet
	 * @return void
	 */
	public function partitionFactsOld( $variableSet )
	{
		$groups = array();

		$remainingFacts = array();
		foreach ( $this->facts as $fact )
		{
			$clonedFact = $fact instanceof XPath2NodeIterator
				? $fact->CloneInstance()
				: $fact;
			$remainingFacts[ spl_object_hash( $clonedFact ) ] = $clonedFact;
		}

		$test = 0;

		$filterExpressions = array();
		$vars = $variableSet->getBindingsAsVars();
		$uncoveredAspectFactKeys = array_keys( array_filter( $this->uncoveredAspectFacts ) );

		if ( $this->uncoveredAspectFacts )
		{
			while ( count( $remainingFacts ) )
			{
				$test++;

				// Take the first fact and find all the others like it
				// First remove it from the list of facts
				$firstFact = reset( $remainingFacts );
				$firstKey = key( $remainingFacts );
				unset( $remainingFacts[ $firstKey ] );

				$group = array( $firstFact );

				// Whether the 'matches' attribute is defined for the variable or not,
				// the comparisons for the uncovered aspects should be applied
				if ( $filterExpressions )
				{
					// If the filters have been created just need to replace the vars
					foreach ( $uncoveredAspectFactKeys as $aspect )
					{
						if ( $aspect[0] == '\\' )
						{
							$aspect = "aspect:" . trim( $aspect, '\\' );
						}
						$aspectQName = qname( $aspect, $variableSet->nsMgr->getNamespaces() );
						$vars[ $aspectQName->clarkNotation() ] = $firstFact;
					}
				}
				else
				{
					$uncoveredAspectFacts = array_fill_keys( $uncoveredAspectFactKeys, $firstFact );

					$this->var->createAspectsTestQuery( $uncoveredAspectFactKeys, $uncoveredAspectFacts, $variableSet, $filterExpressions, $vars );

					$filterExpression = implode( " and ", array_filter( $filterExpressions ) );
					// \XBRL_Log::getInstance()->info( "$test: $filterExpression" );
					$compiledExpression = XPath2Expression::Compile( "\$facts[{$filterExpression}]", $variableSet->nsMgr );

				}

				// Look for items in $remainingFacts that have the same aspects
				// if ( $filterExpressions )
				{
					// $filterExpression = implode( " and ", array_filter( $filterExpressions ) );
					// \XBRL_Log::getInstance()->info( "$test: $filterExpression" );

					$vars['facts'] = DocumentOrderNodeIterator::fromItemset( array_values( $remainingFacts ) );
					// $matchedFacts = $variableSet->evaluateXPath( $variableSet, "\$facts[{$filterExpression}]", $vars );
					$matchedFacts = $variableSet->evaluateXPathExpression( $variableSet, $compiledExpression, $vars );

					// $count = $matchedFacts->getCount();
					// \XBRL_Log::getInstance()->info( $count );

					$count = 0;

					// Add each fact to the group and remove it from the list of remaining facts
					foreach ( $matchedFacts as $matchedFact )
					{
						$count++;
						$group[] = $matchedFact;

						if ( isset( $remainingFacts[ spl_object_hash( $matchedFact ) ] ) )
						{
							unset( $remainingFacts[ spl_object_hash( $matchedFact ) ] );
						}
						else
						{
							// Getting here means the use of spl_object_hash on the $fact instances has not worked
							// Instead need to focus on the underlying object
							echo "This is a problem\n";
						}
					}

					\XBRL_Log::getInstance()->info( "$test: $count" );

					// If there are any add them to the group
					if ( $count )
					{
						$test += $count;

						// If the 'matches' attribute is not defined for the variable then remove duplicate facts from the result
						// This is the equivalent of matchesSubPartitions() (FormulaEvaluator.py line 1265)
						// The difference between this comparison and the XPath comparison is that this one compares all aspects
						// It follows then that if the XPath is comparing all apsects then this is redundant.

						// If there are no aspects covered then there can be no facts that are more unique.
						if ( count( $this->aspectsCovered ) && ! $this->var->matches )
						{
							// Remove duplicates
							$uniqueFacts = array();
							$duplicates = array();

							foreach ( $group as $key1 => /** @var DOMXPathNavigator $fact1 */ $fact1 )
							{
								if ( $uniqueFacts )
								{
									$parent1 = $fact1->CloneInstance();
									$parent1->MoveToParent();

									$unitRef1 = FactValues::getUnitRef( $fact1 );
									$contextRef1 = FactValues::getContextRef( $fact1 );
									$name1 = $fact1->getLocalName();
									// $found = false;

									// Compare this fact against the facts remaining in $group
									foreach ( $uniqueFacts as $key2 => /** @var DOMXPathNavigator $fact2 */ $fact2 )
									{
										// No need to compare against self
										if ( $key1 == $key2 ) continue;

										$parent2 = $fact2->CloneInstance();
										$parent2->MoveToParent();

										if ( ! $parent1->IsSamePosition( $parent2 ) )
										{
											continue;
										}

										if ( $fact2->getLocalName() != $name1 )
										{
											continue;
										}

										$unitRef2 = FactValues::getUnitRef( $fact2 );
										$contextRef2 = FactValues::getContextRef( $fact2 );

										// Neither has a unit ref there is nothing to check
										if ( $unitRef1 && $unitRef1 )
										{
											// If one has a unit ref but the other does not then they are not duplicates
											if ( ! $unitRef1 && $unitRef2 || $unitRef1 && ! $unitRef2 )
											{
												continue;
											}

											// OK, both have unit refs so compare then
											if ( ! \XBRL_Equality::unit_equal(
													$variableSet->xbrlInstance->getUnit( $unitRef1 ),
													$variableSet->xbrlInstance->getUnit( $unitRef2 ),
													\XBRL_Types::getInstance(),
													$variableSet->xbrlInstance->getInstanceNamespaces()
												)
											)
											{
												continue;
											}
										}

										// Both MUST have context refs so compare them
										if ( ! \XBRL_Equality::context_equal(
												$variableSet->xbrlInstance->getContext( $contextRef1 ),
												$variableSet->xbrlInstance->getContext( $contextRef2 )
											)
										)
										{
											continue;
										}

										$duplicates[] = $fact1;

										continue 2;
										// $found = true;
										// break;
									}

									// if ( $found ) continue;
								}

								$uniqueFacts[ $key1 ] = $fact1;
							}

							// $group = $uniqueFacts;
							$groups = array_merge( $groups, array( DocumentOrderNodeIterator::fromItemset( $uniqueFacts ) ), $duplicates );
							$group = array();

						}
					}
				}

				// Two different tests:
				// 22170 v22 (no matches) Duplicate facts in a sub-sequence
				//			 This uses a sub-sequence because some aspects are covered and there are no duplicates
				//           when all aspects are considered.
				// 22170 v27 (matches) Duplicate facts in a sub-sequence
				// 22170 v28 (no matches) All duplicate facts output in sequence
				//			 There are no covered aspects and there are no duplicates
				// 22170 v29 (matches)
				if ( $this->var->matches )
				{
					$groups[] = DocumentOrderNodeIterator::fromItemset( $group );
				}
				else
				{
					$groups = array_merge( $groups, $group );
					// $groups[] = DocumentOrderNodeIterator::fromItemset( $group );
				}
			}
		}
		else
		{
			$groups = array_values( $remainingFacts );
		}

		$this->facts = DocumentOrderNodeIterator::fromItemset( $groups );
	}

}
