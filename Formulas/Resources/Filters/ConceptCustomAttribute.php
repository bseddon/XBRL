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

/**
 * Implements the filter class for the ConceptCustomAttribute filter
 * http://www.xbrl.org/Specification/conceptFilters/REC-2009-06-22/conceptFilters-REC-2009-06-22.html#example-concept-custom-attribute-filter
 * CAN BE OPTIMIZED
 * (see ModelFormulaObject.py ModelConceptCustomAttribute class)
 */
class ConceptCustomAttribute extends ConceptFilterWithQnameExpression
{
	/**
	 * 'credit' or 'debit' or 'none'
	 * @var string $value
	 */
	public $value;

	/**
	 * A list of attribute qnames
	 * @var array $attributes
	 */
	public $attributes = array();

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
		if ( property_exists( $attributes, 'value' ) )
		{
			$this->value = (string)$attributes->value;
			$result['value'] = $this->value;
		}

		$attributeElements = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] )->attribute;

		foreach ( $attributeElements as $attributeElement )
		{
			$attributes = $attributeElement->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CF ] );

			if ( property_exists( $attributeElement, "qname" ) )
			{
				$qname = qname( trim( $attributeElement->qname ), $node->getDocNamespaces( true ) );
				if ( is_null( $qname ) ) continue;
				$this->attributes['qnames'][] = $qname->clarkNotation();
			}
			else if ( property_exists( $attributeElement, "qnameExpression" ) )
			{
				$this->attributes['qnameExpressions'][] = trim( $attributeElement->qnameExpression );
			}
			else
			{
				if ( \XBRL::isValidating() )
				{
					$log->formula_validation( "Concept Filters", "No qname or qnameExpression attribute in the 'attribute' element of the conceptCustomAttribute element", array(
						'roleuri' => $roleUri,
						'label' => $label,
						'localname' => $localName,
					) );
				}
			}
		}

		$result['attributes'] = $this->attributes;

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
		if ( ! $this->attributes )
		{
			return null;
		}

		$clauses = array();

		if ( isset( $this->attributes['qnames'] ) && $this->attributes['qnames'] )
		foreach ( $this->attributes['qnames'] as $qname )
		{
			$name = qname( $qname );
			$clauses[] = $this->value
				? "xfi:concept-custom-attribute(fn:node-name(.),fn:QName('{$name->namespaceURI}','{$name->localName}')) eq {$this->value}"
				: "xfi:concept-custom-attribute(fn:node-name(.),fn:QName('{$name->namespaceURI}','{$name->localName}'))";
		}

		if ( isset( $this->attributes['qnameExpressions'] ) && $this->attributes['qnameExpressions'] )
		foreach ( $this->attributes['qnameExpressions'] as $qnameExpression )
		{
			$clauses[] = $this->value
				? "xfi:concept-custom-attribute(fn:node-name(.),{$qnameExpression}) eq {$this->value}"
				: "xfi:concept-custom-attribute(fn:node-name(.),{$qnameExpression})";
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
		if ( $this->attributes )
		{
			if ( isset( $this->attributes['qnameExpressions'] ) && $this->attributes['qnameExpressions'] )
			foreach ( $this->attributes['qnameExpressions'] as $qnameExpression )
			{
				try
				{
					$xpath2Expression = XPath2Expression::Compile( $qnameExpression, $nsMgr );
					if ( parent::checkForCoverXFIFunctionUse( $qnameExpression, $xpath2Expression ) )
					{
						return false;
					}
					$this->attributes['qnameXPath2Expressions'][] = $xpath2Expression;
				}
				catch ( \Exception $ex )
				{
					\XBRL_Log::getInstance()->formula_validation( "Concept custom attribute filter", "Failed to compile qname expression",
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

		if ( $this->attributes )
		{
			if ( isset( $this->attributes['qnameXPath2Expressions'] ) && $this->attributes['qnameXPath2Expressions'] )
			foreach ( $this->attributes['qnameXPath2Expressions'] as $qnameXPath2Expression )
			{
				$variableRefs = array_merge( $variableRefs, $qnameXPath2Expression->getParameterQNames() );
			}
		}

		return $variableRefs;
	}

}