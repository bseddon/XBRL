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
use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\XPath2Exception;

/**
  * Implements the filter class for the ConceptSubstitutionGroup filter
  * http://www.xbrl.org/Specification/conceptFilters/REC-2009-06-22/conceptFilters-REC-2009-06-22.html#sec-concept-substitution-group-filter
  * CAN BE OPTIMIZED
  * (see ModelFormulaObject.py ModelConceptSubstitutionGroup class)
 */
class ConceptSubstitutionGroup extends ConceptFilterWithQnameExpression
{
	/**
	 * Boolean value indicating whether strict rules apply or not
	 * @var string $strict
	 */
	public $strict = true;

	/**
	 * An array with an element 'qname' or 'qnameExpression'
	 * @var array $substitutionGroup
	 */
	public $substitutionGroups;

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
		if ( ! property_exists( $attributes, 'strict' ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Concept filters", "There is no 'strict' attribute in the conceptDataType filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			$this->strict = filter_var( $attributes->strict,  FILTER_VALIDATE_BOOLEAN );
			$result['strict'] = $this->strict;
		}

		$result['substitutionGroups'] = array();

		$substitutionGroups = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] )->substitutionGroup;

		foreach ( $substitutionGroups as $substitutionGroup )
		{
			$attributes = $substitutionGroup->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] );

			if ( property_exists( $substitutionGroup, "qname" ) )
			{
				$qname = qname( trim( $substitutionGroup->qname ), $node->getDocNamespaces( true ) );
				$this->substitutionGroups['qnames'][] = is_null( $qname ) ? null : $qname->clarkNotation();
			}
			else if ( property_exists( $substitutionGroup, "qnameExpression" ) )
			{
				$this->substitutionGroups['qnameExpressions'][] = trim( $substitutionGroup->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Concept Filters", "No qname or qnameExpression attribute in the 'substitutionGroup' element of the conceptSubstitutionGroup element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$result['substitutionGroups'] = $this->substitutionGroups;

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
		if ( ! $this->substitutionGroups )
		{
			return null;
		}

		$clauses = array();

		if ( isset( $this->substitutionGroups['qnames'] ) && $this->substitutionGroups['qnames'] )
		foreach ( $this->substitutionGroups['qnames'] as $qname )
		{
			$name = qname( $qname );
			$clauses[] = $this->strict
				? "fn:QName('{$name->namespaceURI}','{$name->localName}') eq (xfi:concept-substitutions(fn:node-name(.)))[1]"
				: "fn:QName('{$name->namespaceURI}','{$name->localName}') = xfi:concept-substitutions(fn:node-name(.))";
		}

		if ( isset( $this->substitutionGroups['qnameExpressions'] ) && $this->substitutionGroups['qnameExpressions'] )
		foreach ( $this->substitutionGroups['qnameExpressions'] as $qnameExpression )
		{
			$clauses[] = $this->strict
				? "{$qnameExpression} eq (xfi:concept-substitutions(fn:node-name(.)))[1]"
				: "{$qnameExpression} = xfi:concept-substitutions(fn:node-name(.))";
		}

		return implode( " or ", $clauses );

	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @var VariableSet $variableSet
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_CONCEPT );
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param Formula $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		if ( $this->substitutionGroups )
		{
			if ( isset( $this->substitutionGroups['qnameExpressions'] ) && $this->substitutionGroups['qnameExpressions'] )
			foreach ( $this->substitutionGroups['qnameExpressions'] as $qnameExpression )
			{
				try
				{
					$xpath2Expression = XPath2Expression::Compile( $qnameExpression, $nsMgr );
					if ( parent::checkForCoverXFIFunctionUse( $qnameExpression, $xpath2Expression ) )
					{
						return false;
					}
					$this->substitutionGroups['qnameXPath2Expressions'][] = $xpath2Expression;
				}
				catch ( \Exception $ex )
				{
					\XBRL_Log::getInstance()->formula_validation( "Concept type filter", "Failed to compile qname expression",
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
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		if ( $this->substitutionGroups )
		{
			if ( isset( $this->substitutionGroups['qnameXPath2Expressions'] ) && $this->substitutionGroups['qnameXPath2Expressions'] )
			foreach ( $this->substitutionGroups['qnameXPath2Expressions'] as $qnameXPath2Expression )
			{
				$variableRefs = array_merge( $variableRefs, $qnameXPath2Expression->getParameterQNames() );
			}
		}

		return $variableRefs;
	}

}