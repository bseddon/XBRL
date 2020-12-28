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

use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\XPath2NodeIterator;

/**
  * Implements a binding implementation for general variables
 */
class GeneralVariableBinding extends VariableBinding
{
	/**
	 * If the bound variable specifies bind as a sequence then put the facts into a container
	 * @param Formula $variableSet
	 * @return void
	 */
	public function partitionFacts( $variableSet )
	{
		if ( ! $this->var->bindAsSequence ) return;

		$groups = array();

		// This is a bit belt-and-braces but just to be sure
		if ( $this->facts instanceof XPath2NodeIterator )
		{
			$groups[] = $this->facts->CloneInstance();
		}
		else if ( is_array( $this->facts ) )
		{
			$groups[] = DocumentOrderNodeIterator::fromItemset( $this->facts, true );
		}
		else
		{
			$groups[] = DocumentOrderNodeIterator::fromItemset( array( $this->facts ) );
		}

		$this->facts = DocumentOrderNodeIterator::fromItemset( $groups );
	}

}