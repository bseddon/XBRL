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

 use lyquidity\XPath2\XPath2Expression;
use lyquidity\xml\MS\XmlNamespaceManager;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the parent filter
  * http://www.xbrl.org/specification/tupleFilters/REC-2009-06-22/tupleFilters-REC-2009-06-22.html#sec-ancestor-filter
  * CAN BE OPTIMIZED
  * HAS BEEN OPTIMIZED
  * Instead of using an XPath statement use the Filter
  * method to examine the ancestors of the facts directly
  * to determine if any matches ant of the QNames
  * (see ModelFormulaObject.py ModelAncestorFilter class)
  */
class AncestorFilter extends Filter
{
	/**
	 * A qname instance or null
	 * @var array $qnames
	 */
	public $qnames = array();

	/**
	 * The qname expression or null
	 * @var array $qnameExpressions
	 */
	public $qnameExpressions = array();

	/**
	 * An array of compiled expressions.  What Arelle would call a 'prog'
	 * @var array[XPath2Expression] $qnameXPath2Expressions
	 */
	public $qnameXPath2Expressions = array();

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

		$ancestors = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_TF ] )->ancestor;
		if ( \XBRL::isValidating() && ! count( $ancestors ) )
		{
			$log->formula_validation( "Tuple filters", "There are no parent elements in the ancestor filter element", array(
				'roleuri' => $roleUri,
				'label' => $label,
				'localname' => $localName,
			) );
		}

		foreach ( $ancestors as $ancestor )
		{
			// $attributes = $ancestor->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_TF ] );

			if ( property_exists( $ancestor, "qname" ) )
			{
				$qname = qname( trim( $ancestor->qname ), $node->getDocNamespaces( true ) );
				if ( is_null( $qname ) ) continue;
				$this->qnames[ $qname->clarkNotation() ] = array(
					'name' => $qname->localName,
					'namespace' => $qname->namespaceURI,
					'originalPrefix' => $qname->prefix
				);
			}
			else if ( property_exists( $ancestor, "qnameExpression" ) )
			{
				$this->qnameExpressions[] = trim( $ancestor->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Tuple filters", "No qname or qnameExpression attribute in the ancestor filter element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$result["qnames"] = $this->qnames;
		$result["qnameExpressions"] = $this->qnameExpressions;

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
		$clauses = array();

		// Include the qnames if being called as part of a boolean filter or there are qname expressions
		if ( $this->inBoolean || $this->qnameExpressions )
		foreach ( $this->qnames as $qname )
		{
			$clauses[] = "fn:not(fn:empty(ancestor::*[fn:node-name(.) eq fn:QName('{$qname['namespace']}','{$qname['name']}')]))";
		}

		foreach ( $this->qnameExpressions as $qnameExpression )
		{
			$clauses[] = "fn:not(fn:empty(ancestor::*[fn:node-name(.) eq $qnameExpression]))";
		}

		return implode( " or ", $clauses );
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param XPath2NodeIterator $facts
	 * @param VariableSet $variableSet
	 * @return XPath2NodeIterator Returns the filtered list
	 */
	public function Filter( $facts, $variableSet )
	{
		// If the filter is being used in a boolean filter or there is a qname expresssion then return the input facts
		if ( $this->inBoolean || $this->qnameExpressions ) return $facts;

		$matched = array();
		$notMatched = array();

		foreach ( $facts as /** @var XPathNavigator $fact */ $fact )
		{
			if ( $this->testAncestor( $fact ) )
			{
				$matched[] = $fact->CloneInstance();
			}
			else
			{
				$notMatched[] = $fact->CloneInstance();
			}
		}

		return DocumentOrderNodeIterator::fromItemset( $this->complement ? $notMatched : $matched );
	}

	/**
	 * Tests the ancestors of $fact to see if they have one of the specified QNames
	 * @param XPathNavigator $fact
	 * @return boolean
	 */
	private function testAncestor( $fact )
	{
		$xpathQNames = null;
		/**
		 *
		 * @var \DOMNode $parentNode
		 */
		$parentNode = $fact->getUnderlyingObject()->parentNode;
		while( ! is_null( $parentNode ) && $parentNode->nodeType != XML_DOCUMENT_NODE  )
		{
			$clark = str_replace( "{}", "", "{{$parentNode->namespaceURI}}{$parentNode->localName}" );

			// Does this node match any of the qnames?
			if ( isset( $this->qnames[ $clark ] ) )
			{
				return true;
			}

			// Does this match any of the QName expressions?
			// Begin by evaluating the expressions if there are any
			if ( is_null( $xpathQNames ) )
			{
				$xpathQNames = array();

				foreach ( $this->qnameXPath2Expressions as $xpathExpression )
				{
					/**
					 * @var QNameValue $qname
					 */
					$qname = $this->evaluateXPathExpression( $variableSet, $xpathExpression, array( $fact ) );
					if ( ! $qname instanceof QNameValue ) continue;

					$xpathQNames[] = $qname->ToClarkNotation();
				}
			}

			// Does this node match any of the qnames?
			if ( in_array( $clark, $xpathQNames) )
			{
				return true;
			}

			$parentNode = $parentNode->parentNode;
		}

		return false;
	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @var VariableSet $variableSet
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_LOCATION );
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
		if ( $this->qnameExpressions )
		{
			foreach ( $this->qnameExpressions as $qnameExpression )
			{
				try
				{
					$xpath2Expression = XPath2Expression::Compile( $qnameExpression, $nsMgr );
					// Don't want xff 'uncovered' functions in the expression
					if ( parent::checkForCoverXFIFunctionUse( $qnameExpression, $xpath2Expression ) )
					{
						return false;
					}
					$this->qnameXPath2Expressions[] = $xpath2Expression;
				}
				catch ( \Exception $ex )
				{
					\XBRL_Log::getInstance()->formula_validation( "Concept name filter", "Failed to compile qname expression",
						array(
							'qname expression' => $qnameExpression,
							'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
							'reason' => $ex->getMessage()
						)
					);
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Return any parameter references in the qname expression statements (if there are any)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		if ( $this->qnameXPath2Expressions )
		{
			foreach ( $this->qnameXPath2Expressions as $expression )
			{
				$variableRefs = array_merge( $variableRefs, $expression->getParameterQNames() );
			}
		}

		return $variableRefs;
	}

}
