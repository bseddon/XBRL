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
use lyquidity\xml\MS\XmlNamespaceManager;

/**
 * Implements the filter class for the ConceptDataType filter
 * http://www.xbrl.org/Specification/conceptFilters/REC-2009-06-22/conceptFilters-REC-2009-06-22.html#example-concept-data-type-filter
 * CAN BE OPTIMIZED
 * This will be similar to ConceptName filter
 * (see ModelFormulaObject.py ModelConceptDataType class)
 */
class ConceptDataType extends ConceptFilterWithQnameExpression
{
	/**
	 * Boolean value indicating whether strict rules apply or not
	 * @var string $strict
	 */
	public $strict = true;

	/**
	 * A list of data type qnames
	 * @var array $dataTypes
	 */
	public $dataTypes = array();

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
			$this->strict = filter_var( (string)$attributes->strict, FILTER_VALIDATE_BOOLEAN );
			$result['strict'] = $this->strict;
		}

		$types = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] )->type;

		foreach ( $types as $type )
		{
			$attributes = $type->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] );

			if ( property_exists( $type, "qname" ) )
			{
				$qname = qname( trim( $type->qname ), $node->getDocNamespaces( true ) );
				if ( is_null( $qname ) ) continue;
				$this->dataTypes['qnames'][] = $qname->clarkNotation();
			}
			else if ( property_exists( $type, "qnameExpression" ) )
			{
				$this->dataTypes['qnameExpressions'][] = trim( $type->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Concept Filters", "No qname or qnameExpression attribute in the 'type' element of the conceptCustomAttribute element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$result['dataTypes'] = $this->dataTypes;

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
		if ( ! $this->dataTypes )
		{
			return null;
		}

		$clauses = array();

		if ( isset( $this->dataTypes['qnames'] ) && $this->dataTypes['qnames'] )
		foreach ( $this->dataTypes['qnames'] as $qname )
		{
			$name = qname( $qname );
			$clauses[] = $this->strict
				? "xfi:concept-data-type(fn:node-name(.)) eq fn:QName('{$name->namespaceURI}','{$name->localName}')"
				: "xfi:concept-data-type-derived-from(fn:node-name(.),fn:QName('{$name->namespaceURI}','{$name->localName}'))";
		}

		if ( isset( $this->dataTypes['qnameExpressions'] ) && $this->dataTypes['qnameExpressions'] )
		foreach ( $this->dataTypes['qnameExpressions'] as $qnameExpression )
		{
			$clauses[] = $this->strict
				? "xfi:concept-data-type(fn:node-name(.)) eq $qnameExpression"
				: "xfi:concept-data-type-derived-from(fn:node-name(.),$qnameExpression)";
		}

		return implode( " or ", $clauses );
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
		if ( $this->dataTypes )
		{
			if ( isset( $this->dataTypes['qnameExpressions'] ) && $this->dataTypes['qnameExpressions'] )
			foreach ( $this->dataTypes['qnameExpressions'] as $qnameExpression )
			{
				try
				{
					$xpath2Expression = XPath2Expression::Compile( $qnameExpression, $nsMgr );
					if ( parent::checkForCoverXFIFunctionUse( $qnameExpression, $xpath2Expression ) )
					{
						return false;
					}
					$this->dataTypes['qnameXPath2Expressions'][] = $xpath2Expression;
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
	 * Return any parameter references in the qname expression statements (if there are any)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		if ( $this->dataTypes )
		{
			if ( isset( $this->dataTypes['qnameXPath2Expressions'] ) && $this->dataTypes['qnameXPath2Expressions'] )
			foreach ( $this->dataTypes['qnameXPath2Expressions'] as $qnameXPath2Expression )
			{
				$variableRefs = array_merge( $variableRefs, $qnameXPath2Expression->getParameterQNames() );
			}
		}

		return $variableRefs;
	}


}