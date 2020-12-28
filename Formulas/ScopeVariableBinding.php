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

use Iterator;

/**
 * A binding for scope variable results
 */
class ScopeVariableBinding extends FactVariableBinding
{
	/**
	 * Bound variable is a $fact variable
	 * @return bool
	 */
	public function isFactVar()
	{
		return true;
	}

	/**
	 * Provides a GUID for the current fact
	 * @var string $scopeFactGUID
	 */
	public $scopeFactGUID;

	/**
	 * Returns the current fbiact by stripping out the fact from the current item (an array)
	 * The array has two items: 'fact' and 'binding'
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\FactVariableBinding::current()
	 */
	public function current()
	{
		// Calling the grandparent method
		$scopeFact = VariableBinding::current();
		if ( ! $scopeFact || ! is_array( $scopeFact ) )
		{
			$this->scopeFactGUID = null;
			return null;
		}

		$fact = $scopeFact['result'];

		/**
		 * @var FactVariableBinding $binding
		 */
		$binding = $scopeFact['lastFactBinding'];

		$this->aspectsCovered = $binding->aspectsCovered;
		$this->aspectsDefined = $binding->aspectsDefined;
		$this->uncoveredAspectFacts = $scopeFact['uncoveredAspectFacts'];

		$this->scopeFactGUID = isset( $scopeFact['GUID'] )
			? $scopeFact['GUID']
			: null;

		return $fact;
	}

	/**
	 * Move to the beginning of the operator
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\FactVariableBinding::rewind()
	 */
	public function rewind()
	{
		$this->scopeFactGUID = null;
		return parent::rewind();
	}

	/**
	 * Returns the next $fact value or FALSE if there are no more facts.
	 * {@inheritDoc}
	 * @see Iterator::next()
	 */
	public function next()
	{
		$this->scopeFactGUID = null;
		return parent::next();
	}

	/**
	 * Check if the current node is valid and that the pointer can be moved forwards
	 * {@inheritDoc}
	 * @see Iterator::valid()
	 */
	public function valid()
	{
		if ( parent::valid() )
		{
			return true;
		}
		else
		{
			$this->scopeFactGUID = null;
			return false;
		}
	}


	/**
	 * Allows a binding to return additional variables to be used in a query
	 * In this case the additional vars are those of in effect when the evalution
	 * result of the source formula was generated.
	 */
	public function getAdditionalVars()
	{
		$scopeFact = VariableBinding::current();
		$scopeFact = VariableBinding::current();
		if ( ! $scopeFact || ! is_array( $scopeFact ) )
		{
			return array();
		}

		return $scopeFact['vars'];
	}
}