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
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\MS\XmlNamespaceManager;

 /**
  * Implements the filter class for the ConceptName filter
  * http://www.xbrl.org/Specification/dimensionFilters/REC-2009-06-22/dimensionFilters-REC-2009-06-22+corrected-errata-2011-03-10.html#sec-explicit-dimension-filter
  */
class TypedDimension extends Filter
{
	/**
	 * A qname or qname expression representing the dimension to filter
	 * @var string $dimension
	 */
	public $dimension;

	/**
	 * A qname or qname expression representing the dimension to filter
	 * @var string $dimensionExpression
	 */
	public $dimensionExpression;

	/**
	 * A compiles expression of the $dimensionExpression (if there is one)
	 * @var XPath2Expression $dimensionXPath2Expression
	 */
	public $dimensionXPath2Expression;

	/**
	 * An XPath2 expression representing the dimension members to filter
	 * @var string $test
	 */
	public $test;

	/**
	 * A compiled XPath2 expression representing the dimension members to filter
	 * @var string $test
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

		$dimension = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_DF ] )->dimension;
		if ( ! count( $dimension ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Dimension Filters", "There are no dimension elements in the explicit dimension filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			if ( property_exists( $dimension, "qname" ) )
			{
				$qname = qname( trim( $dimension->qname ), $node->getDocNamespaces( true ) );
				// $result["dimension"] = is_null( $this->dimension ) ? null : $this->dimension->clarkNotation();
				$this->dimension = array(
					'name' => $qname->localName,
					'namespace' => $qname->namespaceURI,
					'originalPrefix' => $qname->prefix,
				);
				$result["dimension"] = $this->dimension;
			}
			else if ( property_exists( $dimension, "qnameExpression" ) )
			{
				$this->dimensiondimension = trim( $dimension->qnameExpression );
				$result["dimensionExpression"] = $this->dimensiondimension;
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Dimension Filters", "No qname or qnameExpression element in the dimension filter element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$attributes = $node->attributes();

		if ( property_exists( $attributes, 'test' ) )
		{
			$this->test = (string)$attributes->test;
			$result['test'] = $this->test;
		}

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
		// return "(true())";

		$clauses = array();

		$dimensionClause = "";
		if ( $this->dimension )
		{
			// $qname = qname( $this->dimension );
			$dimensionClause = "fn:QName('{$this->dimension['namespace']}','{$this->dimension['name']}')";
		}
		else if ( $this->dimensionExpression )
		{
			$dimensionClause = $this->dimensionExpression;
		}
		else
		{
			return "false()";
		}

		if ( $this->test )
		{
			return "( xfi:fact-has-typed-dimension(.,$dimensionClause) and xfi:fact-typed-dimension-value(.,$dimensionClause)[{$this->test}] )";
		}
		else
		{
			return "xfi:fact-has-typed-dimension(.,$dimensionClause)";
		}
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
		if ( $this->dimension )
		{
			// $qname = qname( $this->dimension );
			return array( "{{$this->dimension['namespace']}}{$this->dimension['name']}" );
		}
		else if ( $this->dimensionXPath2Expression )
		{
			/**
			 * @var XPath2Item $result
			 */
			$result = $this->evaluateXPathExpression( $variableSet, $this->dimensionXPath2Expression );
			return array( (string)$result->getValue() );
		}
		else if ( $this->dimensionExpression )
		{
			/**
			 * @var XPath2Item $result
			 */
			$result = $this->evaluateXPath( $variableSet, $this->dimensionExpression );
			return array( (string)$result->getValue() );
		}

		return array( ASPECT_DIMENSIONS );
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
		if ( isset( $this->dimensionExpression ) && ! empty( $this->dimensionExpression ) )
		{
			try
			{
				$xpath2Expression = XPath2Expression::Compile( $this->dimensionExpression, $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $this->dimensionExpression, $xpath2Expression ) )
				{
					return false;
				}
				$this->dimensionXPath2Expression = $xpath2Expression;
			}
			catch ( \Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Explicit dimension filter", "Failed to compile qname expression",
					array(
						'qname expression' => $this->dimensionExpression,
						'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);

				return false;
			}

		}

		if ( $this->test )
		{
			try
			{
				$xpath2Expression = XPath2Expression::Compile( $this->test, $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $this->test, $xpath2Expression ) )
				{
					return false;
				}
				$this->testXPath2Expression = $xpath2Expression;
			}
			catch ( \Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Explicit dimension filter", "Failed to compile test expression",
					array(
						'qname expression' => $this->test,
						'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);

				return false;
			}

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
		$variableRefs = isset( $this->dimensionXPath2Expression )
			? $this->dimensionXPath2Expression->getParameterQNames()
			: array();

		if ( isset( $this->testXPath2Expression ) )
		{
			$variableRefs = array_merge( $variableRefs, $this->testXPath2Expression->getParameterQNames() );
		}

		return $variableRefs;
	}

}
