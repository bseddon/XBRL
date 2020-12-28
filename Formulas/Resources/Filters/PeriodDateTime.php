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

use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\XPath2\XPath2NodeIterator;
use XBRL\Formulas\FactVariableBinding;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\MS\XmlNamespaceManager;

 /**
  * Implements the base filter class for the period filters
  */
class PeriodDateTime extends Filter
{
	/**
	 * The date to be used to validate this filter (required)
	 * @var string
	 */
	public $date = null;

	/**
	 * A compiled version of the 'date' property
	 * @var XPath2Expression $dateXPath2expression
	 */
	public $dateXPath2expression = null;

	/**
	 * The date to be used to validate this filter
	 * @var string
	 */
	public $time = null;

	/**
	 * A compiled version of the 'time' property
	 * @var XPath2Expression $timeXPath2expression
	 */
	public $timeXPath2expression = null;

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

		if ( ! property_exists( $attributes, "date" ) )
		{
			$log->formula_validation( "Filters", "Missing period filter 'date' attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->date = (string)$node->attributes()->date;
		$result['date'] = $this->date;

		if ( property_exists( $attributes, "time" ) )
		{
			$this->time = (string)$node->attributes()->time;
			$result['time'] = $this->time;
		}

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
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return array an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_PERIOD );
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
		try
		{
			if ( isset( $this->date ) && ! empty( $this->date ) )
			{
				$xpath2Expression = XPath2Expression::Compile( $this->date, $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $this->date, $xpath2Expression ) )
				{
					return false;
				}
				$this->dateXPath2expression = $xpath2Expression;
			}

		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "Period", "Failed to compile date expression",
				array(
					'date' => $this->select,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);

			return false;
		}

		try
		{
			if ( isset( $this->time ) && ! empty( $this->time ) )
			{
				$xpath2Expression = XPath2Expression::Compile( $this->time, $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $this->time, $xpath2Expression ) )
				{
					return false;
				}
				$this->timeXPath2expression = $xpath2Expression;
			}

		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "Period", "Failed to compile time expression",
				array(
					'time' => $this->time,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$result = $this->dateXPath2expression instanceof XPath2Expression
			? $this->dateXPath2expression->getParameterQNames()
			: array();

		if ( $this->timeXPath2expression && $this->timeXPath2expression instanceof XPath2Expression )
		{
			$result = array_merge( $result, $this->timeXPath2expression->getParameterQNames() );
		}

		return $result;
	}

}
