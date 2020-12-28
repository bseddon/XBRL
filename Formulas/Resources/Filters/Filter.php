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
use XBRL\Formulas\Resources\Resource;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\XPath2Expression;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\QName;
use lyquidity\xml\exceptions\InvalidOperationException;

/**
 * Base class for XBRL variable filters
 */
class Filter extends Resource
{
	/**
	 * The label of the filter
	 * @var string|null
	 */
	public $label;

	/**
	 * Flag indicating whether this filter covers an aspect
	 * @var bool $cover
	 */
	public $cover = false;

	/**
	 * Flag indicating whether this is a complement filter
	 * @var bool $complement
	 */
	public $complement = false;

	/**
	 * Used to sort filters when added to fact variables so filtering performance can be optimized
	 * This default priority should be overridden in filters that should appear earler in the list
	 * @var integer $sortPriority
	 */
	public $sortPriority = 99;

	/**
	 * The role uri of the resource (filter in this case)
	 * @var string $roleUri
	 */
	public $roleUri = null;

	/**
	 * The role of the extended link containing this resource
	 * @var string $linkRoleUri
	 */
	public $linkRoleUri = null;

	/**
	 * Flag set to true when the filter is being called as an argument of a boolean filter
	 * @var string
	 */
	public $inBoolean = false;

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
		$this->label = $label;
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );
		return $result;
	}

	/**
	 * Stores a node array with a resource type name of 'filter'
 	 * @param array $node A an array representation of the resource node
 	 * @param string $type
	 */
	public function storeFilter( $node, $type )
	{
		$node['type'] = 'filter';
		$node['filterType'] = $type;

		return $node;
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
		throw new InvalidOperationException( "The toQuery method of the base filter class should not be called" );
	}

	/**
	 * Filter an array of facts using the filters usually when there is no XPath query.
	 * @param XPath2NodeIterator $facts
	 * @param VariableSet $variableSet
	 * @return XPath2NodeIterator Returns the filtered list
	 */
	public function Filter( $facts, $variableSet )
	{
		return $facts;
	}

	/**
	 * Called to check that the three XFF 'cover' functions (xff:uncovered-aspect, xff:uncovered-non-dimensional-aspects
	 * and xff:uncovered-dimensional-aspects) are not called in filter expressions
	 * @param string $expression
	 * @param XPath2Expression $compiledExpression
	 */
	public function checkForCoverXFIFunctionUse( $expression, $compiledExpression )
	{
		$result = $compiledExpression->isFunctionUsed(
			array(
				'xff:uncovered-aspect',
				'xff:uncovered-non-dimensional-aspects',
				'xff:uncovered-dimensional-aspects'
			)
		);

		if ( $result )
		{
			\XBRL_Log::getInstance()->formula_validation( "Filter validation", "The filter expression contains xff 'uncovered' functions",
				array(
					'expression' => $expression,
					'error' => 'xffe:invalidFunctionUse'
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return array An array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array();
	}

	/**
	 * Cache of the qname generated from a 'name' array
	 * @var QName $qname
	 */
	private $qname;

	/**
	 * Return the QName of the 'variable' property if any
	 * @return NULL|QName
	 */
	public function getVariableQName()
	{
		// Use the cached name if there is one.
		if ( ! $this->qname )
		{
			if ( ! property_exists( $this, 'variable' ) ) return null;
			$this->qname = $qname = new QName( $this->variable['originalPrefix'], $this->variable['namespace'], $this->variable['name'] );
		}

		return $this->qname;
	}

	/**
	 *
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$qname = $this->getVariableQName();
		return is_null( $qname )
			? array()
			: array( $qname );
	}

}
