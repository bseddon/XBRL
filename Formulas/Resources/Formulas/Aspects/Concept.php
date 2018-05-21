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

namespace XBRL\Formulas\Resources\Formulas\Aspects;

use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\QName;

/**
 * A class to process a general variable definitions
 */
class Concept extends Aspect
{
	/**
	 * A qname instance or null
	 * @var array $qname
	 */
	public $qname = array();

	/**
	 * The qname expression or null
	 * @var array $qnameExpression
	 */
	public $qnameExpression = array();

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
		// Get the relevant source for this aspect
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$conceptChildren = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA] );
		if ( is_null( $this->source ) && ! count( $conceptChildren ) )
		{
			$log->formula_validation( "Aspect rule", "The concept rule does not have any children but there is no source value",
				array(
					'error' => 'xbrlfe:incompleteConceptRule',
				)
			);

			return $result;
		}

		if ( count( $conceptChildren ) )
		{
			foreach ( $conceptChildren as $key => $qname )
			{
				if ( $key == "qname" )
				{
					$qname = qname( trim( $qname ), $node->getDocNamespaces( true ) );
					$this->qname[] = array(
						'originalPrefix' => $qname->prefix,
						'namespace' => $qname->namespaceURI,
						'name' => $qname->localName,
					);
				}
				else if ( $key == "qnameExpression" )
				{
					$this->qnameExpression[] = trim( $qname );
				}
				else
				{
					if ( \XBRL::isValidating() )
					{
						$log->formula_validation( "Concept Aspect", "No qname or qnameExpression attribute in the concept element", array(
							'roleuri' => $roleUri,
							'label' => $label,
							'localname' => $localName,
						) );
					}
				}
			}
		}
		else
		{
			$this->qname[] = $this->source;
		}

		$result["qname"] = $this->qname;
		$result["qnameExpression"] = $this->qnameExpression;

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}

	/**
	 * Returns the fact associated with the formula source
	 * @param VariableSet $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return DOMXPathNavigator|null
	 */
	private function getSourceFact( $variableSet, $evaluationResult, $log )
	{
		$sourceQName = null;

		if ( count( $this->qname ) )
		{
			$sourceQName = new QName( $this->qname[0]['originalPrefix'], $this->qname[0]['namespace'], $this->qname[0]['name'] );
			if ( ! $sourceQName ) return null;
		}
		elseif ( count( $this->qnameExpression ) )
		{
			$qname = $this->evaluateXPath( $variableSet, $this->qnameExpression[0], $evaluationResult['vars'] );
			if ( $qname instanceof XPath2Item )
			{
				$qname = $qname->getTypedValue();
			}
			if ( ! $qname ) return null;
			$sourceQName = new QName( $qname->Prefix, $qname->NamespaceUri, $qname->LocalName );
		}
		else
		{
			return null;
		}

		// OK, got the source fact but it might be a variable in which case the value of the variable will be required
		if ( isset( $variableSet->variablesByQName[ $sourceQName->clarkNotation() ] ) )
		{
			if ( ! isset( $evaluationResult['vars'][ $sourceQName->clarkNotation() ] ) )
			{
				$log->formula_validation( "Concept aspect", "Unable to find variable",
					array(
						'variable' => $sourceQName->clarkNotation(),
						'error' => 'xbrlfe:incompleteConceptRule'
					)
				);
			}
			// Get the evaluation value for this variable
			$fact = $evaluationResult['vars'][ $sourceQName->clarkNotation() ];
			if ( $fact instanceof XPath2NodeIterator )
			{
				// Get the first fact
				while (true)
				{
					foreach ( $fact as $node )
					{
						if ( ! $node instanceof DOMXPathNavigator ) continue;
						$fact = $node;
						break 2;
					}

					$fact = null;
					break;
				}
			}

			if ( $fact instanceof XPathNavigator )
			{
				$sourceQName = new QName( $fact->getPrefix(), $fact->getNamespaceURI(), $fact->getLocalName() );
			}
			else
			{
				$log->formula_validation( "Concept aspect", "The variable reference does not point to a result node",
					array(
						'variable' => $sourceQName->clarkNotation(),
						'error' => 'xbrlfe:incompleteConceptRule'
					)
				);
			}
		}
		return $sourceQName;
	}

	/**
	 * Get the concept aspect value
	 * @param VariableSet $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return QName|null
	 */
	public function getValue( $variableSet, $evaluationResult, $log )
	{
		$sourceFact = $this->getSourceFact( $variableSet, $evaluationResult, $log );
		if ( ! $sourceFact )
		{
			$log->formula_validation( "Concept aspect rule", "No valid concept can be located",
				array(
					'source' => $this->qname
									? (string)(new QName( $this->qname['originalPrefix'], $this->qname['namespace'], $this->qname['name']) )
									: $this->qnameExpression,
					'error' => 'xbrlfe:missingConceptRule'
				)
			);
			return null;
		}

		return $sourceFact;
	}
}
