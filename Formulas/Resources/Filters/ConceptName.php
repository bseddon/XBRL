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
use XBRL\Formulas\FactVariableBinding;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\xml\QName;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the ConceptName filter
  * http://www.xbrl.org/Specification/conceptFilters/REC-2009-06-22/conceptFilters-REC-2009-06-22.html#sec-concept-name-filter
  * HAS BEEN OPTIMIZED
  */
class ConceptName extends Filter
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
	 * An array of compiles expressions.  What Arelle would call a 'prog'
	 * @var array[XPath2Expression] $qnameXPath2Expressions
	 */
	public $qnameXPath2Expressions = array();

	/**
	 * Concept filter should appear at the beginning of the filter list
	 * @var integer $sortPriority
	 */
	public $sortPriority = 1;

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

		$concepts = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] )->concept;
		if ( \XBRL::isValidating() && ! count( $concepts ) )
		{
			$log->formula_validation( "Concept Filters", "There are no concept elements in the ConceptName filter element", array(
				'roleuri' => $roleUri,
				'label' => $label,
				'localname' => $localName,
			) );
		}

		foreach ( $concepts as $concept )
		{
			$attributes = $concept->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] );

			if ( property_exists( $concept, "qname" ) )
			{
				$qname = qname( trim( $concept->qname ), $node->getDocNamespaces( true ) );
				if ( is_null( $qname ) ) continue;
				$this->qnames[] = $qname->clarkNotation();
			}
			else if ( property_exists( $concept, "qnameExpression" ) )
			{
				$this->qnameExpressions[] = trim( $concept->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Concept Filters", "No qname or qnameExpression attribute in the concept element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$result['qnames'] = $this->qnames;
		$result['qnameExpressions'] = $this->qnameExpressions;
		// $result['inBoolean'] = true;

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
		foreach ( $this->qnames as $clark )
		{
			$qname = qname( $clark );
			$clauses[] = "(fn:node-name(.) eq fn:QName('{$qname->namespaceURI}','{$qname->localName}'))";
		}

		foreach ( $this->qnameExpressions as $qnameExpression )
		{
			$clauses[] = "(fn:node-name(.) eq $qnameExpression)";
		}

		if ( ! $clauses )
		{
			return null;
		}

		$query = implode( " or ", $clauses );
		return count( $clauses ) > 1 ? "($query)" : $query;
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

		if ( ! $this->qnames ) return $facts;

		$matched = array();
		$notMatched = array();
		foreach ( $facts as /** @var XPathNavigator $fact */ $fact )
		{
			$clark = "{{$fact->getNamespaceURI()}}{$fact->getLocalName()}";

			if ( in_array( $clark, $this->qnames ) )
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
	 * Returns the set of aspects covered by this instance
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return array an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_CONCEPT );
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
					if ( parent::checkForCoverXFIFunctionUse( $qnameExpression, $xpath2Expression ) )
					{
						return false;
					}

					$this->qnameXPath2Expressions[] = $xpath2Expression;
				}
				catch ( XPath2Exception $ex )
				{
					\XBRL_Log::getInstance()->formula_validation( "Concept name filter", "Failed to compile qname expression",
						array(
							'qname expression' => $qnameExpression,
							'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "xbrlve:noCustomFunctionSignature" : $ex->ErrorCode ) : get_class( $ex ),
							'reason' => $ex->getMessage()
						)
					);
				}
				catch ( \Exception $ex )
				{
					// BMS 2018-03-27 Test 21201 V-02 requires that this compile failure return xbrlve:noCustomFunctionSignature
					//				  because the custom function my-own:ending-balance does not exist
					\XBRL_Log::getInstance()->formula_validation( "Concept name filter", "Failed to compile qname expression",
						array(
							'qname expression' => $qnameExpression,
							'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "err:XPST0017" : $ex->ErrorCode ) : get_class( $ex ),
							'reason' => $ex->getMessage()
						)
					);
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
