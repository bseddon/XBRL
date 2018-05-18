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
use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\Value\QNameValue;

 /**
  * Implements the filter class for the period filter
  * http://www.xbrl.org/Specification/aspectCoverFilters/REC-2011-10-24/aspectCoverFilters-REC-2011-10-24.html#sec-aspect-cover-filter
  */
class AspectCover extends Filter
{
	/**
	 * all, concept, entity-identifier, location, period, unit, complete-segment, complete-scenario, non-XDT-segment, non-XDT-scenario, dimensions
	 * An array of aspect covers
	 * @var array $aspects
	 */
	public $aspects = array();

	/**
	 * A list of the dimensions (if any)
	 * @var array $dimensions
	 */
	public $dimensions = array();

	/**
	 * A list of the excluded dimensions (if any)
	 * @var array $excludedDimensions
	 */
	public $excludedDimensions = array();

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

		$aspects = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_ACF ] )->aspect;
		// if ( \XBRL::isValidating() && ! count( $aspects ) )
		// {
		// 	$log->formula_validation( "Aspect cover filters", "There are no aspect elements in the aspect cover filter element", array(
		// 		'roleuri' => $roleUri,
		// 		'label' => $label,
		// 		'localname' => $localName,
		// 	) );
		// }

		foreach ( $aspects as $aspect )
		{
			$aspect = trim( $aspect );
			$aspectId = isset( Formula::$aspectCoversMap[ $aspect ] )
				? Formula::$aspectCoversMap[ $aspect ]
				: $aspect;
			$this->aspects[ $aspectId ] = $aspect;
		}

		$dimensions = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_ACF ] )->dimension;

		foreach ( $dimensions as $dimension )
		{
			if ( property_exists( $dimension, "qname" ) )
			{
				$qname = qname( trim( $dimension->qname ), $node->getDocNamespaces( true ) );
				$this->dimensions['qname'][] = is_null( $qname ) ? null : $qname->clarkNotation();
			}
			else if ( property_exists( $dimension, "qnameExpression" ) )
			{
				$this->dimensions['qnameExpression'][] = trim( $dimension->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Aspect cover filters", "No qname or qnameExpression for the dimension of the aspect cover filter element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$excludeDimensions = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_ACF ] )->excludeDimension;

		foreach ( $excludeDimensions as $dimension )
		{
			if ( property_exists( $dimension, "qname" ) )
			{
				$qname = qname( trim( $dimension->qname ), $node->getDocNamespaces( true ) );
				$this->excludedDimensions['qname'][] = is_null( $qname ) ? null : $qname->clarkNotation();
			}
			else if ( property_exists( $dimension, "qnameExpression" ) )
			{
				$this->excludedDimensions['qnameExpression'][] = trim( $dimension->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Aspect cover filters", "No qname or qnameExpression for the dimension of the aspect cover filter element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$result['aspects'] = $this->aspects;
		$result['dimensions'] = $this->dimensions;
		$result['excludedDimensions'] = $this->excludedDimensions;

		$result = parent::storeFilter( $result, $localName );

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		/**
		 * From the specification ( section 2.1)
		 * These filters do not perform any "filtering", and thus have no implied XPath expression.
		 * (Or for some implementations that and all the filters of a fact variable together, then the
		 * XPath expression for the filtering action for this filter would be true().
		 */
		return null;
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param XPath2NodeIterator $facts
	 * @return XPath2NodeIterator Returns the filtered list
	 */
	public function Filterx( $facts )
	{
	}

	/**
	 * Returns the set of aspects covered by this instance
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		// Unusually, this implementation will updat the binding directly
		$covered = array();

		if ( isset( $this->aspects['all'] ) )
		{
			$covered = is_null( $factVariableBinding )
				? array_values( Formula::$aspectCoversMap )
				: $factVariableBinding->aspectsDefined;

			$pos = array_search( ASPECT_DIMENSIONS, $covered );
			if ( $pos !== false )
			{
				unset( $covered[ $pos ] );
			}
			if ( $factVariableBinding )
			{
				$covered = array_unique( array_merge( $covered, array_diff( $covered, $factVariableBinding->aspectsCovered ) ) );
			}
		}
		else
		{
			$covered = array_keys( $this->aspects );
			if ( in_array( ASPECT_DIMENSIONS, $covered ) )
			{
				$pos = array_search( ASPECT_DIMENSIONS, $covered );
				if ( $pos !== false )
				{
					unset( $covered[ $pos ] );
				}

				if ( $factVariableBinding )
				{
					$dimensions = array_diff( $factVariableBinding->aspectsDefined, array_values( Formula::$aspectCoversMap ) );
					$covered = array_merge( $covered, $dimensions );
				}
			}
			else
			{
				if ( isset( $this->dimensions['qname'] ) )
				foreach( $this->dimensions['qname'] as $includedKey => $dimension )
				{
					if ( in_array( $dimension, $covered ) ) continue;
					$covered[] = $dimension;
				}

				if ( isset( $this->dimensions['qnameExpression'] ) )
				foreach( $this->dimensions['qnameExpression'] as $includedKey => $dimensionExpression )
				{
					try
					{
						$dimension = CoreFuncs::Atomize( $this->evaluateXPath( $variableSet, $dimensionExpression ) );
						if ( $dimension instanceof QNameValue )
						{
							$dimension = "{{$dimension->NamespaceUri}}{$dimension->LocalName}";
						}
						if ( in_array( $dimension, $covered ) ) continue;
						$covered[] = $dimension;
					}
					catch ( \Exception $ex )
					{
						$x = 1;
					}
				}
			}
		}

		// Handle any excluded dimensions
		if ( isset( $this->excludedDimensions['qname'] ) )
		foreach( $this->excludedDimensions['qname'] as $includedKey => $dimension )
		{
			$pos = array_search( $dimension, $covered );
			if ( $pos === false ) continue;
			unset( $covered[ $pos ] );
		}

		if ( isset( $this->excludedDimensions['qnameExpression'] ) )
		foreach( $this->excludedDimensions['qnameExpression'] as $includedKey => $dimensionExpression )
		{
			try
			{
				$dimension = CoreFuncs::Atomize( $this->evaluateXPath( $variableSet, $dimensionExpression ) );
				if ( $dimension instanceof QNameValue )
				{
					$dimension = "{{$dimension->NamespaceUri}}{$dimension->LocalName}";
				}
				$pos = array_search( $dimension, $covered );
				if ( $pos === false ) continue;
				unset( $covered[ $pos ] );
			}
			catch ( \Exception $ex )
			{
				$x = 1;
			}
		}

		// Add the the values in $covered to the binding
		if ( $factVariableBinding )
		{
			$factVariableBinding->aspectsCovered = array_unique(
				$this->cover
					? array_merge( $factVariableBinding->aspectsCovered, $covered )
					: array_diff( $factVariableBinding->aspectsCovered, $covered )
			);
		}

		return $covered;
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
