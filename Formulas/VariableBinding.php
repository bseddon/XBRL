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
 */

namespace XBRL\Formulas;

use XBRL\Formulas\Resources\Variables\FactVariable;
use XBRL\Formulas\Resources\Variables\GeneralVariable;
use XBRL\Formulas\Resources\Variables\Parameter;
use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\Resources\Formulas\Tuple;
use XBRL\Formulas\Resources\Assertions\ValueAssertion;
use XBRL\Formulas\Resources\Assertions\ExistenceAssertion;
use lyquidity\XPath2\XPath2NodeIterator;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\QName;

 /**
  * Implements an abstract class for variable binding that supports
  * iterating over facts and partitioning those facts.
  */
class VariableBinding implements \Iterator
{
	/**
	 * The variable being bound
	 * @var FactVariable $var
	 */
	public $var;

	/**
	 * The qname of $var
	 * @var QName $qname
	 */
	public $qname = null;

	/**
	 * The current $fact
	 * @var mixed $yieldedFact
	 */
	public $yieldedFact;

	/**
	 * An iterator yielding $facts
	 * @var XPath2NodeIterator
	 */
	public $facts;

	/**
	 * A list of instances
	 * @var array
	 */
	public $instances;

	/**
	 * Backing variable for the isFallback() function
	 * @var bool
	 */
	public $isFallback = false;

	/**
	 * Constructor
	 * @param \XBRL_Instance $xbrlInstance
	 * @param Variable $var
	 */
	public function __construct( $xbrlInstance, $var )
	{
		if ( is_null( $var ) ) return;
		$this->var = $var;
		$this->qname = $var->getQName();
		$this->xbrlInstance = $xbrlInstance;
	}

	/**
	 * Default destructor
	 */
	public function __destruct()
	{
		unset( $this->var );
		unset( $this->yieldedFact );
		unset( $this->xbrlInstance );
	}

	/**
	 * Bound variable is a $fact variable
	 * @return bool
	 */
	public function isFactVar()
	{
		return $this->var instanceof FactVariable;
	}

	/**
	 * Bound variable is a general variable
	 * @return bool
	 */
	public function isGeneralVar()
	{
		return $this->var instanceof GeneralVariable;
	}

	/**
	 * Bound variable is a Parameter
	 * @return bool
	 */
	public function isParameter()
	{
		return $this->var instanceof Parameter;
	}

	/**
	 * Bound variable is a Formula
	 * @return bool
	 */
	public function isFormulaResult()
	{
		return $this->var instanceof Formula;
	}

	/**
	 * Returns true if the variable is bind as sequence
	 * @return bool
	 */
	public function isBindAsSequence()
	{
		return is_null( $this->var )
			? false
			: $this->var->bindAsSequence;
	}

	/**
	 * Returns true if the variable has a fallback
	 * @return bool
	 */
	public function isFallback()
	{
		return $this->isFallback;
	}

	/**
	 * Return the name of the variable
	 * @return string|boolean
	 */
	public function resourceElementName()
	{
		if ( $this->isFactVar ) return "Fact Variable";
		else if ( $this->isGeneralVar ) return "General Variable";
		else if ( $this->isParameter ) return "Parameter";
		else if ( $this->var instanceof Tuple ) return "Tuple";
		else if ( $this->var instanceof Formula ) return "Formula";
		else if ( $this->var instanceof ValueAssertion ) return "ValueAssertion";
		else if ( $this->var instanceof ExistenceAssertion ) return "ExistenceAssertion";
		return false;
	}

	/**
	 * Returns the count of $facts (fact variables)
	 * @return int
	 */
	public function getFactsCount()
	{
		if ( is_null( $this->facts ) || ! $this->facts instanceof XPath2NodeIterator )
		{
			return 0;
		}

		return $this->facts->getCount();
	}

	/**
	 * Provides a binding the chance to set a fallback value and return true or return false
	 * By default this does not do anything
	 * @param VariableSet $variableSet
	 * @return boolean
	 */
	public function setFallbackValue( $variableSet )
	{
		return false;
	}

	/** Iterator support */

	/**
	 * Implements the iterator rewind method
	 */
	public function rewind()
	{
		if ( is_null( $this->facts ) )
		{
			return false;
		}

		$result = $this->facts->rewind();

		$this->yieldedFact = null;

		return $result;
	}

	/**
	 * Implements the iterator current method
	 */
	public function current()
	{
		if ( is_null( $this->facts ) )
		{
			return null;
		}

		$fact = $this->facts->current();
		$this->yieldedFact = $fact;

		return $fact;
	}

	/**
	 * Keys the key for the current $fact
	 * {@inheritDoc}
	 * @see Iterator::key()
	 */
	public function key()
	{
		if ( is_null( $this->facts ) )
		{
			return false;
		}

		return $this->facts->key();
	}

	/**
	 * Returns the next $fact value or FALSE if there are no more facts.
	 * {@inheritDoc}
	 * @see Iterator::next()
	 */
	public function next()
	{
		if ( is_null( $this->facts ) )
		{
			return false;
		}

		$this->yieldedFact = null;

		$success = $this->facts->next();

		return $success;
	}

	/**
	 * Check if the current node is valid and that the pointer can be moved forwards
	 * {@inheritDoc}
	 * @see Iterator::valid()
	 */
	public function valid()
	{
		if ( is_null( $this->facts ) )
		{
			return false;
		}

		$result = $this->facts->valid();
		if ( ! $result )
		{
			$this->yieldedFact = null;
		}
		return $result;
	}

	/**
	 * Partitions the facts in this binding into groups that have the same aspect values
	 * The functio will leave settings the $facts variable to the list of groups generated
	 * @param Formula $variableSet
	 * @return void
	 */
	public function partitionFacts( $variableSet )
	{}

	/**
	 * Allows a descendant binding to return additional variables to be used in a query
	 * @return array An empty array
	 */
	public function getAdditionalVars()
	{
		return array();
	}
}
