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

namespace XBRL\Formulas\Resources\Filters;

 use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\MS\XmlNamespaceManager;
use XBRL\Formulas\Resources\Resource;

 /**
  * Implements the filter class for the ConceptName filter
  * http://www.xbrl.org/Specification/conceptFilters/REC-2009-06-22/conceptFilters-REC-2009-06-22.html#sec-concept-name-filter
  *
  */
class Boolean extends Filter
{
	/**
	 * A list of related filters
	 * @var array[Filter] $filters
	 */
	protected $filters = array();

	/**
	 * Converts the filters to a collection of XPath queries
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		$result = array();

		if ( count( $this->filters ) == 0 )
		{
			return array( "false()" );
		}

		foreach ( $this->filters as $filter )
		{
			$filter->inBoolean = true;

			$query = $filter->toQuery( $variableSet, $factVariableBinding );
			if ( $filter->complement )
			{
				$query = "not($query)";
			}
			$result[] = $query;
		}

		return $result;
	}

	/**
	 * Validate that there are filters for this boolean
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 * @return bool
	 */
	public function validate( $variableSet, $nsMgr )
	{
		$arcs = $variableSet->xbrlTaxonomy->getGenericArc( \XBRL_Constants::$arcRoleBooleanFilter, $this->linkRoleUri, $this->label, $this->path );

		/**
		 * @var array[Filter] $filters
		 */
		$filters = array();

		foreach ( $arcs as $arc )
		{
			// BMS 2019-02-11
			if ( $this->path != $arc['frompath'] ) continue;

			$variableSet->xbrlTaxonomy->getGenericResource( 'filter', null, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$filters, $arc )
			{
				// if ( $resource['label'] != $arc['to'] ) return true;
				if ( $resource['path'] != $arc['topath'] /* || $linkbase != $arc['linkbase'] */ ) return true;

				if ( $arc['attributes'] )
				{
					foreach ( $arc['attributes'] as $name => $attribute )
					{
						$value = $attribute['type'] == "xs:boolean"
							? (bool)$attribute['value']
							: $attribute['value'];
						$resource[ $name ] = $value;
					}
				}

				$filterResources[] = $resource;
				$className = __NAMESPACE__ . '\\' . ucfirst( $resource['filterType'] );
				$filters[] = $className::fromArray( $resource );

				return true;
			}, $arc['toRoleUri'], $arc['to'], $arc['tolinkbase'] );
		}

		foreach ( $filters as $filter )
		{
			if ( ! $filter->validate( $variableSet, $nsMgr ) )
			{
				return false;
			}
		}

		$this->filters = $filters;

		return true;
	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		$result = array();

		foreach ( $this->filters as $filter )
		{
			if ( ! $filter->cover ) continue;
			$result = array_merge( $result, $filter->getAspectsCovered( $variableSet, $factVariableBinding ) );
		}

		return $result;
	}
}
