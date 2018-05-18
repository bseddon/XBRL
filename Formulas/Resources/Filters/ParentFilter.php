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
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the parent filter
  * http://www.xbrl.org/specification/tupleFilters/REC-2009-06-22/tupleFilters-REC-2009-06-22.html#sec-parent-filter
  * CAN BE OPTIMIZED
  * HAS BEEN OPTIMIZED
  * (see ModelFormulaObject.py ModelAncestorFilter class)
  */
class ParentFilter extends Filter
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

		$parents = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_TF ] )->parent;
		if ( \XBRL::isValidating() && ! count( $parents ) )
		{
			$log->formula_validation( "Tuple filters", "There are no parent elements in the parent filter element", array(
				'roleuri' => $roleUri,
				'label' => $label,
				'localname' => $localName,
			) );
		}

		foreach ( $parents as $parent )
		{
			$attributes = $parent->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_TF ] );

			if ( property_exists( $parent, "qname" ) )
			{
				$qname = qname( trim( $parent->qname ), $node->getDocNamespaces( true ) );
				if ( is_null( $qname ) ) continue;
				$this->qnames[] = $qname->clarkNotation();
			}
			else if ( property_exists( $parent, "qnameExpression" ) )
			{
				$this->qnameExpressions[] = trim( $parent->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Tuple filters", "No qname or qnameExpression attribute in the parent filter element", array(
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
		$expressions = array();

		// Include the qnames if being called as part of a boolean filter or there are qname expressions
		if ( $this->inBoolean || $this->qnameExpressions )
		foreach ( $this->qnames as $qname )
		{
			$name = qname( $qname );
			$expressions[] = "(fn:node-name(..) eq fn:QName('{$name->namespaceURI}','{$name->localName}'))";
		}

		foreach ( $this->qnameExpressions as $qnameExpression )
		{
			$expressions[] = "(fn:node-name(..) eq {$qnameExpression})";
		}

		return implode( " or ", $expressions );
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
			if ( $this->testParent( $fact ) )
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
	 * This is just like the AncestorFilter implementation except that it goes up
	 * only one level.
	 * @param XPathNavigator $fact
	 * @return boolean
	 */
	private function testParent( $fact )
	{
		$xpathQNames = null;
		/**
		 *
		 * @var \DOMNode $parentNode
		 */
		$parentNode = $fact->getUnderlyingObject()->parentNode;

		if( ! is_null( $parentNode ) && $parentNode->nodeType != XML_DOCUMENT_NODE  )
		{
			$clark = str_replace( "{}", "", "{{$parentNode->namespaceURI}}{$parentNode->localName}" );

			// Does this node match any of the qnames?
			if ( in_array( $clark, $this->qnames ) )
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
	 * Check the qname expression (if there is one)
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
