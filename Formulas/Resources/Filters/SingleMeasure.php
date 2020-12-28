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
  * Implements the filter class for the ConceptName filter
  * http://www.xbrl.org/Specification/dimensionFilters/REC-2009-06-22/dimensionFilters-REC-2009-06-22+corrected-errata-2011-03-10.html#sec-explicit-dimension-filter
  */
class SingleMeasure extends Filter
{
	/**
	 * A qname or qname representing the unit to filter
	 * @var array $qname
	 */
	public $qname;

	/**
	 * A qname or qname expression representing the unit to filter
	 * @var array $qnameExpression
	 */
	public $qnameExpression;

	/**
	 * An array of compiled expressions.  What Arelle would call a 'prog'
	 * @var array[XPath2Expression] $qnameXPath2Expressions
	 */
	public $qnameXPath2Expression;

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

		$measures = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_UF ] )->measure;
		if ( ! count( $measures ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Unit Filters", "There are no measure elements in the explicit single measure element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			foreach ( $measures as $key => $measure )
			{
				if ( property_exists( $measure, "qname" ) )
				{
					$qname = qname( trim( $measure->qname ), $node->getDocNamespaces( true ) );
					if ( is_null( $qname ) ) continue;
					// if ( $qname->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
					// {
					// 	$qname->namespaceURI = "";
					// 	$qname->prefix = "";
					// }

					$this->qname = array(
							'name' => $qname->localName,
							'namespace' => $qname->namespaceURI,
							'originalPrefix' => $qname->prefix
						);
				}
				else if ( property_exists( $measure, "qnameExpression" ) )
				{
					$this->qnameExpression = trim( $measure->qnameExpression );
				}
				else
				{
					if ( \XBRL::isValidating() )
					{
						$log->formula_validation( "Unit Filters", "No qname or qnameExpression element in the single measure filter element", array(
							'roleuri' => $roleUri,
							'label' => $label,
							'localname' => $localName,
						) );
					}
				}

				// Should only be one one break
				break;
			}

			$result["qname"] = $this->qname;
			$result["qnameExpression"] = $this->qnameExpression;
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
		$clause = "";
		if ( $this->qname )
		{
			$clause = "fn:QName('{$this->qname['namespace']}','{$this->qname['originalPrefix']}:{$this->qname['name']}')";
		}
		else if ( $this->qnameExpression )
		{
			$clause = $this->qnameExpression;
		}
		else
		{
			return null;
		}

		return "(xfi:is-numeric(fn:node-name(.)) and count(xfi:unit-numerator(xfi:unit(.))) eq 1 and count(xfi:unit-denominator(xfi:unit(.))) eq 0 and xfi:measure-name(xfi:unit-numerator(xfi:unit(.))[1]) eq $clause)";
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
		return array( ASPECT_UNIT );
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
		if ( $this->qnameExpression )
		{
			try
			{
				$xpath2Expression = XPath2Expression::Compile( $this->qnameExpression, $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $this->qnameExpression, $xpath2Expression ) )
				{
					return false;
				}
				$this->qnameXPath2Expression = $xpath2Expression;
			}
			catch ( \Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Concept name filter", "Failed to compile qname expression",
					array(
						'qname expression' => $this->qnameExpression,
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
	 * Return any parameter references in the qname expression statements (if there are any)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		if ( $this->qnameXPath2Expression )
		{
			$variableRefs = array_merge( $variableRefs, $this->qnameXPath2Expression->getParameterQNames() );
		}

		return $variableRefs;
	}

}
