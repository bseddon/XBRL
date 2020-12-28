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

 use XBRL\Formulas\FactVariableBinding;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\MS\XmlNamespaceManager;

 /**
  * Implements the filter class for the Identity filter
  * http://www.xbrl.org/specification/entityfilters/rec-2009-06-22/entityfilters-rec-2009-06-22.html#sec-entity-identifier-filter
  */
class Identifier extends Filter
{
	/**
	 * The filter test to apply
	 * @var string $test
	 */
	public $test = null;

	/**
	 * A compiled expression of the $test value (if there is one)
	 * @var XPath2Expression $testXPath2Expression
	 */
	public $testXPath2Expression;

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

		if ( ! property_exists( $attributes, 'test' ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Entity filter", "No 'test' attribute in the identifier element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			$this->test = trim( $attributes->test );
		}

		$result['test'] = $this->test;

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
		$test = $this->test ? $this->test : 'true()';
		$filter = "(xfi:identifier(.)[$test])";
		return $filter;
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
	 * @return array An array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_ENTITY_IDENTIFIER );
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
			$xpath2Expression = XPath2Expression::Compile( $this->test, $nsMgr );
			if ( parent::checkForCoverXFIFunctionUse( $xpath2Expression, $xpath2Expression ) )
			{
				return false;
			}
			$this->testXPath2Expression = $xpath2Expression;
			return true;
		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "Concept relation filter", "Failed to compile test expression",
				array(
					'test expression' => $this->test,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);

			return false;
		}
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		if ( $this->testXPath2Expression )
		{
			return $this->testXPath2Expression->getParameterQNames();
		}
		return parent::getVariableRefs();
	}

}
