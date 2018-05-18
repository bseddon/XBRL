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

namespace XBRL\Formulas\Resources\Variables;


use XBRL\Formulas\VariableBinding;
use XBRL\Formulas\Evaluation;

class ScopeVariable extends FactVariable
{
	public $binding;

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param Evaluation $evaluation The variable set containing the variables
	 * @param array $uncoveredAspectFacts The binding of the parent variable (if there is one) and so uncovered facts
	 * @return VariableBinding A new binding for this variable
	 */
	public function Filter( $evaluation, $uncoveredAspectFacts )
	{
		return $this->binding;
	}

	/**
	 * Returns true if the uncovered aspect facts of the binding are compatible with $uncoveredAspectFacts
	 * @param VariableSet $variableSet
	 * @param Array $uncoveredAspectFacts
	 * @param VariableBinding $binding
	 * @return boolean
	 */
	public function matchUncoveredAspects( $variableSet, $uncoveredAspectFacts, $binding )
	{
		if ( ! $binding->valid() ) return true;
		if ( ! $variableSet->implicitFiltering || ! $uncoveredAspectFacts ) return true;

		// Make sure the facts in $uncoveredAspectFacts are consistent with the facts in $binding->uncoveredAspectFacts
		foreach ( $uncoveredAspectFacts as $aspect => $aspectFact )
		{
			if ( is_null( $aspectFact ) ) continue;
			if ( ! isset( $binding->uncoveredAspectFacts[ $aspect ] ) || is_null( $binding->uncoveredAspectFacts[ $aspect ] ) ) continue;

			if ( ! $this->lyquidityAspectMatch( $variableSet, $binding->uncoveredAspectFacts[ $aspect ], $aspectFact, $aspect) )
			{
				return false;
			}
		}

		return true;
	}

}