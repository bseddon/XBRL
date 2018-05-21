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

 /**
  * Implements the filter class for the Identity filter
  * http://www.xbrl.org/specification/entityfilters/rec-2009-06-22/entityfilters-rec-2009-06-22.html#sec-entity-identifier-filter
  * CAN OPTIMIZE
  * If a general filter is used by more than one variable and the
  * filter has no dependent variables then cache the results of the
  * first instance computed and use the cached values to intersect
  * with the list of facts for subsequenct evaluations
  * The approach is to compute a list of all the possible results for
  * the filter then generate a list of facts that are also in the
  * possible list.  Note that the facts passed to the filter are
  * likely to have been processed by other filters
  * (see ModelFormulaObject.py ModelGeneral class)
  */
class General extends Filter
{
	/**
	 * The filter test to apply
	 * @var string $test
	 */
	public $test = null;

	/**
	 * A compiled version of the 'test' property
	 * @var XPath2Expression $xpath2expression
	 */
	public $testXPath2expression;

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

		if ( ! property_exists( $attributes, 'test' ) && \XBRL::isValidating() )
		{
			$log->formula_validation( "Entity filter", "No 'test' attribute in the identifier element", array(
				'roleuri' => $roleUri,
				'label' => $label,
				'localname' => $localName,
			) );
		}
		else
		{
			$this->test = (string)$attributes->test;
		}

		$result['test'] = $this->test;

		$result = parent::storeFilter( $result, $localName );

		return $result;
	}

	/**
	 * Counts the number of times toQuery()
	 * @var integer $count
	 */
	public $count = 0;

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		$this->count++;
		return $this->test ? "({$this->test})" : 'true()';
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
			if ( isset( $this->test ) && ! empty( $this->test ) )
			{
				$expression = XPath2Expression::Compile( $this->test, $nsMgr );
				$this->testXPath2expression = $expression;
			}

			return true;
		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "General filter", "Failed to compile test expression",
				array(
					'test' => $this->test,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);
		}
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return $this->testXPath2expression instanceof XPath2Expression
			? $this->testXPath2expression->getParameterQNames()
			: parent::getVariableRefs();
	}

}
