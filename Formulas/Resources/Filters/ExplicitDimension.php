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
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\QName;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the ConceptName filter
  * http://www.xbrl.org/Specification/dimensionFilters/REC-2009-06-22/dimensionFilters-REC-2009-06-22+corrected-errata-2011-03-10.html#sec-explicit-dimension-filter
  */
class ExplicitDimension extends Filter
{

	/**
	 * A qname or qname representing the dimension to filter
	 * @var string $dimension
	 */
	public $dimension;

	/**
	 * A qname or qname expression representing the dimension to filter
	 * @var string $dimensionExpression
	 */
	public $dimensionExpression;

	/**
	 * A compiled expression of the $dimensionExpression (if there is one)
	 * @var XPath2Expression $dimensionXPath2Expression
	 */
	public $dimensionXPath2Expression;

	/**
	 * A list of qname or qname expression representing the dimension members to filter
	 * @var array $members
	 */
	public $members = array();

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
			$namespaces = $node->getDocNamespaces(true);

			if ( property_exists( $dimension, "qname" ) )
			{
				$qname = qname( trim( $dimension->qname ), $node->getDocNamespaces( true ) );
				$this->dimension = array(
					'name' => $qname->localName,
					'namespace' => $qname->namespaceURI,
					'originalPrefix' => $qname->prefix,
				);
				// $this->dimension = $qname->clarkNotation();
				$result["dimension"] = $this->dimension;
			}
			else if ( property_exists( $dimension, "qnameExpression" ) )
			{
				$qname = trim( $dimension->qnameExpression );
				if ( is_null( $qname ) ) return false;
				$this->dimensionExpression = $qname->clarkNotation();
				$result["dimensionExpression"] = $this->dimensionExpression;
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

		$members = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_DF ] )->member;

		foreach ( $members as $member )
		{
			$mem = array();

			if ( property_exists( $member, "variable" ) )
			{
				$namespaces = $member->variable->getDocNamespaces( true );

				/**
				 * @var QName $qname
				 */
				// If there is no prefix it should not be resolved to a default namespace
				// BMS 2018-02-19 For some reason this has been changed to set a namespace if there is
				// no prefix but there is a local default namespace.  I am going to assume this should
				// have been to do so only when there is a prefix.  Anyway, reverting.
				$variable = trim( $member->variable );
				$qname = strpos( $variable, ":" )
					? qname( $variable, $namespaces )
					// : new QName( "", isset( $namespaces[''] ) ? $namespaces[''] : null, $variable );
					: new QName( "", null, $variable );

				if ( ! $qname->prefix )
				{
					foreach ( $namespaces as $prefix => $namespace )
					{
						// Ignore default namespaces
						if ( ! $prefix ) continue;
						if ( $qname->namespaceURI != $namespace ) continue;
						$qname->prefix = $prefix;
						break;
					}
				}

				if ( is_null( $qname ) )
				{
					$log->formula_validation( "Dimension Filters", "Invalid qname in the dimension filter member variable element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
						'variable' => trim( $member->variable )
					) );
				}
				else
				{
					$mem['variable'] = array(
						'name' => is_null( $qname ) ? $source : $qname->localName,
						'originalPrefix' => is_null( $qname ) ? null : $qname->prefix,
						'namespace' => is_null( $qname ) ? null : $qname->namespaceURI,
					);
				}

			}
			else if ( property_exists( $member, "qname" ) )
			{
				$qname = qname( trim( $member->qname ), $node->getDocNamespaces( true ) );
				// $mem["qname"] = is_null( $qname ) ? null : $qname->clarkNotation();
				$mem['qname'] = array(
					'name' => is_null( $qname ) ? $source : $qname->localName,
					'originalPrefix' => is_null( $qname ) ? null : $qname->prefix,
					'namespace' => is_null( $qname ) ? null : $qname->namespaceURI,
				);
			}
			else if ( property_exists( $member, "qnameExpression" ) )
			{
				$mem["qnameExpression"] = trim( $member->qnameExpression );
			}

			// There can be a sequence of linkrole, arcrole and axis
			if ( property_exists( $member, 'linkrole' ) )
			{
				$mem['linkrole'] = trim( $member->linkrole );
			}

			if ( property_exists( $member, 'arcrole' ) )
			{
				$mem['arcrole'] = trim( $member->arcrole );
			}

			if ( property_exists( $member, 'axis' ) )
			{
				$validAxes = array(
					'child-or-self',
					'child',
					'descendant',
					'descendant-or-self',
					'DRS-child', // DF 1.1
					'DRS-descendant', // DF 1.1
				);

				$axis = trim( $member->axis );
				if ( ! in_array( $axis, $validAxes ) )
				{
					if ( \XBRL::isValidating() )
					{
						$log->formula_validation( "Dimension Filters", "If specified the axis MUST be one of '" . implode( "', '", $validAxes ) . "'" , array(
							'roleuri' => $roleUri,
							'label' => $label,
							'localname' => $localName,
							'axis' => $axis,
						) );
					}
				}
				else
				{
					// If DRS-* then an arcrole MUST NOT be specified
					if ( \XBRL::startsWith( $axis, "DRS-" ) && isset( $mem['arcrole'] ) )
					{
						$log->formula_validation( "Dimension Filters", "If the axis is DRS then the arcrole MUST NOT be specified", array(
							'roleuri' => $roleUri,
							'label' => $label,
							'localname' => $localName,
							'axis' => $axis,
							'error' => 'xbrldfe:DRSaxisFilterSpecifiesArcrole'
						) );
					}
					$mem['axis'] = $axis;
				}
			}

			$this->members[] = $mem;
		}

		$result['members'] = $this->members;
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

		if ( ! $this->members )
		{
			return "xfi:fact-has-explicit-dimension(.,$dimensionClause)";
		}

		foreach ( $this->members as $member )
		{
			$memberClause = "";
			if ( isset( $member['qname'] ) )
			{
				// $qname = qname( $member['qname'] );
				$memberClause = "fn:QName('{$member['qname']['namespace']}','{$member['qname']['name']}')";
			}
			else if ( isset( $member['qnameExpression'] ) )
			{
				$memberClause = $member['qnameExpression'];
			}
			elseif ( isset( $member['variable'] ) )
			{
				// $memberClause = "fn:QName('{$member['variable']['namespace']}','{$member['variable']['name']}')";
				// TODO Can the QName resulting from this query be determined once for this set of aspect facts?
				//      If the implied variable can depend on other variables that are linked to the current fact
				//      then the QName cannot be determined upfront.  However it formula rule prohibit such a
				//      relationship then it can.
				//      For now assume the worst case and have the clause evaluated for every fact
				$variable = $member['variable']['originalPrefix']
					? "\${$member['variable']['originalPrefix']}:{$member['variable']['name']}"
					: "\${$member['variable']['name']}";

				// $variable is 'the current fact'
				$memberClause = "xfi:fact-explicit-dimension-value( $variable, $dimensionClause )";
			}
			else
			{
				continue;
			}

			// Dimensions filter spec 2.1 states:
			// If a filter member does not contain a filter-member linkrole, arcrole and axis, then the only
			// domain member in its filter-member set is the domain member identified by the filter-member value.
			if ( ! isset( $member['arcrole'] ) || ! isset( $member['axis'] ) || ! isset( $member['linkrole'] ) )
			{
				if ( isset( $member['axis'] ) && in_array( $member['axis'], array( 'DRS-child', 'DRS-descendant' ) ) )
				{
					$axis = isset( $member['axis'] ) ? $member['axis'] : "";
					$linkrole = isset( $member['linkrole'] ) ? $member['linkrole'] : "";
					$xpath = "( if (xfi:fact-has-explicit-dimension(.,$dimensionClause)) then " .
							 "( some \$member in xfi:filter-member-DRS-selection($dimensionClause,fn:node-name(.),$memberClause,'$linkrole','$axis') satisfies (xfi:fact-explicit-dimension-value(.,$dimensionClause) eq \$member) )" .
							 " else fn:false() )";
					$clauses[] = $xpath;
				}
				else
				{
					$clauses[] = "xfi:fact-has-explicit-dimension-value(.,$dimensionClause,$memberClause)";
				}
			}
			else
			{
				$arcrole = isset( $member['arcrole'] ) ? $member['arcrole'] : "";
				$axis = isset( $member['axis'] ) ? $member['axis'] : "";
				$linkrole = isset( $member['linkrole'] ) ? $member['linkrole'] : "";

				// TODO This needs optimizing otherwise the whole expression is evaluated for every fact element.
				$xpath = "( if (xfi:fact-has-explicit-dimension(.,$dimensionClause)) then " .
						 "( some \$member in xfi:filter-member-network-selection($dimensionClause,$memberClause,'$linkrole','$arcrole','$axis') satisfies (xfi:fact-explicit-dimension-value(.,$dimensionClause) eq \$member) )" .
						 " else fn:false() )";
				$clauses[] = $xpath;
			}
		}

		return implode( " or ", $clauses );

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
	 * @return an array of aspect identifiers
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
			catch ( Exception $ex )
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

		foreach ( $this->members as &$member )
		{
			if ( ! isset( $member['qnameExpression'] ) ) continue;

			try
			{
				$xpath2Expression = XPath2Expression::Compile( $member['qnameExpression'], $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $member['qnameExpression'], $xpath2Expression ) )
				{
					return false;
				}
				$member['qnameXPath2Expression'] = $xpath2Expression;
			}
			catch ( Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Explicit dimension filter", "Failed to compile test expression",
					array(
						'qname expression' => $member['qnameExpression'],
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

		foreach ( $this->members as $member )
		{
			if ( isset( $member['qnameXPath2Expression']) )
			{
				$variableRefs = array_merge( $variableRefs, $member['qnameXPath2Expression']->getParameterQNames() );
			}
			else if ( isset( $member['variable'] ) )
			{
				$variableRefs[] = new QName(
					$member['variable']['originalPrefix'],
					$member['variable']['namespace'],
					$member['variable']['name']
				);
			}
		}

		return $variableRefs;
	}

}
