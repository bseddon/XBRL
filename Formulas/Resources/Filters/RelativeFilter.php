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
 *
 */

namespace XBRL\Formulas\Resources\Filters;

use XBRL\Formulas\Resources\Formulas\Formula;
use XBRL\Formulas\FactVariableBinding;
use lyquidity\xml\QName;


 /**
  * Implements the filter class for the relative filter
  * http://www.xbrl.org/Specification/relativeFilters/REC-2009-06-22/relativeFilters-REC-2009-06-22.html#sec-relative-filters
  * CAN BE OPTIMIZED
  * (see ModelFormulaObject.py ModelRelativeFilter class)
  */
class RelativeFilter extends MatchFilter
{
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

		$result = parent::storeFilter( $result, $localName );

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param Formula $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		$variable = new QName( $this->variable['originalPrefix'], $this->variable['namespace'], $this->variable['name'] );

		// Look for this variable among the bindings.  If it exists
		// and has a fallback value the the query is just false()
		if ( isset( $variableSet->factVariableBindings[ $variable->clarkNotation() ] ) )
		{
			/**
			 * @var FactVariableBinding $binding
			 */
			$binding = $variableSet->factVariableBindings[ $variable->clarkNotation() ];
			if ( $binding->isFallback )
			{
				return "false()";
			}
		}
		else // BMS 201803-07 What else could it be? if ( isset( $variableSet->generalVariableBindings[ $variable->clarkNotation() ] ) )
		{
			return "false()";
		}

		$this->allowAspectsCovered = true;
		$result = parent::toQuery( $variableSet, $factVariableBinding );
		// Once the filter has been created the class should return the filters applied
		// $this->allowAspectsCovered = false;

		return $result;
	}

	/**
	 * Flag to control when this class will return a list of covered aspects.
	 * A list should be returned only when called by the toQuery() method of
	 * this function which sets the flag
	 * @var string
	 */
	private $allowAspectsCovered = false;

	/**
	 * Returns either no or all aspects
	 *
	 * @param Formula $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return $this->allowAspectsCovered
			? array_diff( $factVariableBinding->aspectsDefined, $factVariableBinding->aspectsCovered )
			: array();
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
		return parent::validate( $variableSet, $nsMgr );
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return parent::getVariableRefs();
	}

}
