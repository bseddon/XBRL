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

namespace XBRL\Formulas\Resources\Formulas;

 use lyquidity\xml\QName;

 /**
  * Implements the filter class for the period filter
  * http://www.xbrl.org/Specification/valueFilters/REC-2009-06-22/valueFilters-REC-2009-06-22.html#sec-nil-filter
  */
class Tuple extends Formula
{
	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->testValue = false;
	}

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
		$result['variablesetType'] = 'tuple';

		return $result;
	}

	/**
	 * Allow resources to be validated
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		$log = \XBRL_Log::getInstance();

		// If there is no source then concept MUST be explicitly declared
		if ( is_null( $this->source ) )
		{
			if ( is_null( $this->conceptRule ) )
			{
				$log->formula_validation( "Tuple", "If there is no source then a concept aspect rule MUST be explicitly declared but is not provided",
					array(
						'formula' => $this->label,
						'error' => 'xbrlfe:missingConceptRule'
					)
				);
			}

			// Create a qname from either the qname or the expression
			/**
			 * @var QName $qname
			 */
			$qname = null;
			if ( $this->conceptRule['qnameExpression'] )
			{
				foreach ( $this->conceptRule['qnameExpression'] as $qnameExpression )
				{
					// Check any variables in the expression are valid
					$expression = XPath2Expression::Compile( $qnameExpression, $this->nsMgr );
					$variableRefs = $expression->getParameterQNames();
					foreach ( $variableRefs as $variableQName )
					{
						if ( ! isset( $this->variablesByQName[ $variableQName->clarkNotation() ] ) )
						{
							$log->formula_validation( "Concept rule", "The variable reference in the concept rule qname expression is not to a valid variable",
								array(
									'formula' => $this->label,
									'variable reference' => $variableQName->clarkNotation(),
									'error' => 'xbrlfe:conceptExpressionInvalid'
								)
							);
						}
					}
				}
			}
			else
			{
				$qname = new QName( $this->conceptRule['qname'][0]['originalPrefix'], $this->conceptRule['qname'][0]['namespace'], $this->conceptRule['qname'][0]['name'] );

				// BMS 2018-01-05 Now not sure why these tests are needed.
	 			// Next find out if the qname refers to a variable.  If it does look for a filter that defines a concept
	 			// Begin by assuming the qname refers to a taxonomy element
	 			$concept = $qname;
	 			if ( isset( $this->variablesByQName[ $concept->clarkNotation() ] ) )
	 			{
	 				/**
	 				 * @var ConceptName $selectedFilter
	 				 */
	 				$selectedFilter = null;
	 				foreach ( $this->variablesByQName[ $concept->clarkNotation() ]->filters as $filter )
	 				{
	 					if ( ! $filter instanceof ConceptName ) continue;
	 					$selectedFilter = $filter;
	 					break;
	 				}

	 				if ( $selectedFilter )
	 				{
						$concept = null;
						$totalFilters = count( $selectedFilter->qnames ) + count( $selectedFilter->qnameExpressions );
						if ( $totalFilters == 1 )
						{
							// It's one of these
							foreach ( $selectedFilter->qnames as $filterQName )
							{
								$concept = qname( $filterQName );
								$taxonomy = $this->xbrlTaxonomy->getTaxonomyForNamespace( $concept->namespaceURI );
								$concept->prefix = $taxonomy->getPrefix();
							}

							if ( ! $concept )
							{
								foreach ( $selectedFilter->qnameExpressions as $filterQNameExpression )
				 				{
									$result = $this->evaluateXPath( $this, $this->conceptRule->$filterQNameExpression );
									$concept = new QName( $result->Prefix, $result->NamespaceUri, $result->LocalName );
			 					}
							}
						}

						if ( $totalFilters != 1 || ! $concept )
						{
							$log->formula_validation( "Formula", "There is no formula source but the concept aspect rule does not define a reference to a schema element",
								array(
									'formula' => $this->label,
									'error' => 'xbrlfe:missingConceptRule'
								)
							);
						}
	 				}
	 				else
	 				{
	 					// If the concept references a variable and there is no filter then the
	 					// the concept cannot be resolved statically.
	 					$concept = null;
	 				}
				}

				if ( $concept )
				{
					// BMS 2017-12-24 Move the following tests here because surely they are only relevant when there is no formula source
					$types = \XBRL_Types::getInstance();
					$conceptElement = $this->xbrlTaxonomy->getElementByName( $concept->localName );
					// Or could do it this way
					// $conceptElement = $types->getElement( $this->conceptRule['qname'][0]['name'], $this->conceptRule['qname'][0]['originalPrefix'] );
					// $isNumeric = $types->resolvesToBaseType( $conceptElement['types'][0], array( 'xs:decimal', 'xsd:decimal' ) );
					if ( ! $conceptElement )
					{
						$log->formula_validation( "Tuple", "The concept provided by the concept aspect rule is not a valid schema element",
							array(
								'formula' => $label,
								'error' => 'xbrlfe:missingConceptRule'
							)
						);
					}

					$isTuple = $types->resolveToSubstitutionGroup( $conceptElement['substitutionGroup'], array( 'xbrli:tuple' ) );
					if ( ! $isTuple )
					{
						$log->formula_validation( "Tuple", "The concept provided by the concept aspect rule is not a tuple",
							array(
								'formula' => $label,
								'error' => 'xbrlfe:invalidConceptRule'
							)
						);
					}
				}
			}
		}
		else // $source is not null
		{
			$sourceQName = new QName( $this->source['originalPrefix'], $this->source['namespace'], $this->source['name'] );

			// If the if the source is not the uncovered qname make sure the referenced variable exists
			if ( $sourceQName->namespaceURI != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] ||
				 $sourceQName->localName != "uncovered" )
			{
				if ( ! isset( $this->variablesByQName[ $sourceQName->clarkNotation() ] ) ||
					 ! $this->variablesByQName[ $sourceQName->clarkNotation() ] instanceof FactVariable
				)
				{
					$log->formula_validation( "Formula", "",
						array(
							'concept' => $sourceQName->prefix ? "{$sourceQName->prefix}:{$sourceQName->localName}" : $sourceQName->localName,
							'formula' => $this->label,
							'error' => 'xbrlfe:nonexistentSourceVariable',
						)
					);
				}

				/**
				 * Look for the variable
				 * @var FactVariable $variable
				 */
				$variable = $this->variablesByQName[ $sourceQName->clarkNotation() ];
				if ( $variable->bindAsSequence )
				{
					// TODO Should check the XPath implied by the fallback value but, for now,
					//	    copy Arelle and fail if there is any fallback value
					$log->formula_validation( "Formula", "Formula source is a fact variable that binds as a sequence",
						array(
							'formula' => $this->label,
							'source' => $sourceQName->prefix ? "{$sourceQName->prefix}:{$sourceQName->localName}" : $sourceQName->localName,
							'error' => 'xbrlfe:defaultAspectValueConflicts',
						)
					);
				}
			}
		}

		$sources = $this->getAspectSources();
		foreach ( $sources as $ruleName => $source )
		{
			if ( $source['namespace'] == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] && $source['name'] == 'uncovered' ) continue;

			$sourceQName = new QName( $source['originalPrefix'], $source['namespace'], $source['name'] );
			/**
			 * Look for the variable
			 * @var FactVariable $variable
			 */
			$variable = $this->variablesByQName[ $sourceQName->clarkNotation() ];
			if ( ! $variable->fallbackValue ) continue;

			// TODO Should check the XPath implied by the fallback value but, for now,
			//	    copy Arelle and fail if there is any fallback value
			$log->formula_validation( "Formula aspects", "Formula source is a fact variable that has a fallback value",
				array(
					'formula' => $this->label,
					'rule' => $ruleName,
					'error' => 'xbrlfe:bindEmptySourceVariable',
				)
			);
		}

		return true;
	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{

	}
}
