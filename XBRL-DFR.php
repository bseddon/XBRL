<?php

/**
 * Digital Financial Reporting taxonomy implementation
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2019 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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

/**
 * Load the XBRL implementation
 */
require_once('XBRL.php');

use XBRL\Formulas\Resources\Variables\FactVariable;
use XBRL\Formulas\Resources\Filters\ConceptName;
use lyquidity\xml\QName;

class XBRL_DFR extends XBRL
{
	/**
	 * Holds a list of features
	 * @var array
	 */
	private $features = array();

	/**
	 * A fixed list of dimensions to exclude when determining if there should be a grid layout
	 * @var array
	 */
	private $axesToExclude = array();

	/**
	 *
	 * @var string
	 */
	public static $originallyStatedLabel = "";

	/**
	 * An array of conceptual model arcroles and relationships
	 * @var array|null
	 */
	private static $conceptualModelRoles;

	public static function getConceptualModelRoles( $cacheLocation = null )
	{
		if ( is_null( self::$conceptualModelRoles ) )
		{
			$context = XBRL_Global::getInstance();
			if ( ! $context->useCache && $cacheLocation )
			{
				$context->cacheLocation = $cacheLocation;
				$context->useCache = true;
				$context->initializeCache();
			}

			$taxonomy = XBRL::withTaxonomy("http://xbrlsite.azurewebsites.net/2016/conceptual-model/cm-roles.xsd", "conceptual-model-roles", true);
			$taxonomy->context = $context;
			$taxonomy->addLinkbaseRef( "http://xbrlsite.azurewebsites.net/2016/conceptual-model/reporting-scheme/ipsas/model-structure/ModelStructure-rules-ipsas-def.xml", "conceptual-model");
			$roleTypes = $taxonomy->getRoleTypes();
			$cm = $taxonomy->getTaxonomyForXSD("cm.xsd");
			$nonDimensionalRoleRef = $cm->getNonDimensionalRoleRefs( XBRL_Constants::$defaultLinkRole );
			$cmArcRoles = $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ];

			$originallyStated = array_filter( $roleTypes['link:label'], function( $role ) { return $role['id']; } );
			self::$originallyStatedLabel = reset( $originallyStated )['roleURI'];

			unset( $taxonomy );
			XBRL::reset();

			self::$conceptualModelRoles = $cmArcRoles;
		}
		return self::$conceptualModelRoles;

	}

	/**
	 * Default constructor
	 */
	function __construct()
	{
		$this->features = array( "conceptual-model" => array(
			'PeriodAxis' => 'PeriodAxis',
			'ReportDateAxis' => XBRL_Constants::$dfrReportDateAxis,
			'ReportingEntityAxis' => XBRL_Constants::$dfrReportingEntityAxis,
			'LegalEntityAxis' => XBRL_Constants::$dfrLegalEntityAxis,
			'ConceptAxis' => XBRL_Constants::$dfrConceptAxis,
			'BusinessSegmentAxis' => XBRL_Constants::$dfrBusinessSegmentAxis,
			'GeographicAreaAxis' => XBRL_Constants::$dfrGeographicAreaAxis,
			'OperatingActivitiesAxis' => XBRL_Constants::$dfrOperatingActivitiesAxis,
			'InstrumentAxis' => XBRL_Constants::$dfrInstrumentAxis,
			'RangeAxis' => XBRL_Constants::$dfrRangeAxis,
			'ReportingScenarioAxis' => XBRL_Constants::$dfrReportingScenarioAxis,
			'CalendarPeriodAxis' => XBRL_Constants::$dfrCalendarPeriodAxis,
			'ReportDateAxis' => XBRL_Constants::$dfrReportDateAxis,
			'FiscalPeriodAxis' => XBRL_Constants::$dfrFiscalPeriodAxis,
			'origionallyStatedLabel' => 'origionallyStated',
			'restatedLabel' => XBRL_Constants::$labelRoleRestatedLabel,
			'periodStartLabel' => XBRL_Constants::$labelRolePeriodStartLabel,
			'periodEndLabel' => XBRL_Constants::$labelRolePeriodEndLabel
		) );

		$this->axesToExclude = array(
			'PeriodAxis', // Exists or implied
			XBRL_Constants::$dfrLegalEntityAxis, // Exists or implied
			XBRL_Constants::$dfrReportDateAxis, // Adjustment
			XBRL_Constants::$dfrReportingScenarioAxis // Variance
		);

		$cmArcRoles = XBRL_DFR::getConceptualModelRoles();

		$this->allowed = $cmArcRoles[ XBRL_Constants::$arcRoleConceptualModelAllowed ]['arcs'];
		if ( ! isset( $allowed['cm.xsd#cm_Concept'] ) )
		{
			$this->allowed['cm.xsd#cm_Concept'] = array();
		}

	}

	/**
	 * This function allows a descendent to do something with the information before it is deleted if helpful
	 * This function can be overridden by a descendent class
	 *
	 * @param array $dimensionalNode A node which has element 'nodeclass' === 'dimensional'
	 * @param array $parentNode
	 * @return bool True if the dimensional information should be deleted
	 */
	protected function beforeDimensionalPruned( $dimensionalNode, &$parentNode )
	{
		return false;
	}

	/**
	 * Gets an array containing a list of extra features supported usually by descendent implementation
	 * @param string $feature (optional) If supplied just the array for the feature is returned or all
	 * 									 features.  If supplied and not found an empty array is returned
	 * @return array By default there are no additional features so the array is empty
	 */
	public function supportedFeatures( $feature = null )
	{
		return $feature
			? ( isset( $this->features[ $feature ] ) ? $this->features[ $feature ] : array() )
			: $this->featrues;
	}

	/**
	 * Returns an array of preferred label pairs.  In the base XBRL instance is only the PeriodStart/PeriodEnd pair.
	 * @return string[][]
	 */
	public function getBeginEndPreferredLabelPairs()
	{
		$result = parent::getBeginEndPreferredLabelPairs();
		$result[] = array(
			self::$originallyStatedLabel,
			XBRL_Constants::$labelRoleRestatedLabel,
		);

		return $result;
	}

	private $presentationPIs = array();
	private $calculationPIs = array();
	private $definitionPIs = array();

	private $calculationNetworks = array();
	private $definitionNetworks = array();
	private $presentationNetworks = array();

	private $allowed = array();

	/**
	 * Validate the the taxonomy against the model structure rules
	 * @param XBRL_Formulas $formula An evaluated formulas instance
	 * @return array|null
	 */
	public function validateDFR( $formulas )
	{
		$log = XBRL_Log::getInstance();

		// Makes sure they are reset in case the same taxonomy is validated twice.
		$this->calculationNetworks = array();
		$this->presentationNetworks = array();

		$this->definitionNetworks = $this->getAllDefinitionRoles();
		foreach ( $this->definitionNetworks as $elr => &$roleRef )
		{
			$roleRef = $this->getDefinitionRoleRef( $elr );

			// Capture primary items
			$this->definitionPIs[ $elr ] = array_filter( array_keys( $roleRef['primaryitems'] ), function( $label )
			{
				$taxonomy = $this->getTaxonomyForXSD( $label );
				$element = $taxonomy->getElementById( $label );
				return ! $element['abstract' ];
			} );

			sort( $this->definitionPIs[ $elr ] );

			// Check members
			foreach ( $roleRef['members'] as $memberLabel => $member )
			{
				$memberTaxonomy = $this->getTaxonomyForXSD( $memberLabel );
				$memberElement = $memberTaxonomy->getElementById( $memberLabel );

				if ( ! $memberElement['abstract' ] )
				{
					$log->business_rules_validation('Model Structure Rules', 'All dimension member elements MUST be abstract',
						array(
							'member' => $memberLabel,
							'role' => $elr,
							'error' => 'error:MemberRequiredToBeAbstract'
						)
					);
				}

				// BMS 2019-03-23 TODO typed members MUST NOT use complex types

				unset( $memberTaxonomy );
				unset( $memberElement );
			}

			// Check hypercube
			foreach ( $roleRef['hypercubes'] as $hypercubeLabel => $hypercube )
			{
				foreach ( $hypercube['parents'] as $primaryItemLabel => $primaryItem )
				{
					if ( ! $primaryItem['closed'] )
					{
						if ( ! isset( $this->definitionNetworks[ $elr ]['primaryitems'][ $primaryItemLabel ]['parents']  ) ) // Only report the error on the line items node
						{
							$log->business_rules_validation('Model Structure Rules', 'All line items to hypercubes MUST be closed',
								array(
									'hypercube' => $hypercubeLabel,
									'primary item' => $primaryItemLabel,
									'role' => $elr,
									'error' => 'error:HypercubesRequiredToBeClosed'
								)
							);
						}
					}

					if ( $primaryItem['arcrole'] == XBRL_Constants::$arcRoleNotAll )
					{
						$log->business_rules_validation('Model Structure Rules', 'All line items to hypercubes MUST be \'all\'',
							array(
								'hypercube' => $hypercubeLabel,
								'primary item' => $primaryItemLabel,
								'role' => $elr,
								'error' => 'error:HypercubeMustUseAllArcrole'
							)
						);
					}

					if ( $primaryItem['contextElement'] != XBRL_Constants::$xbrliSegment )
					{
						$log->business_rules_validation('Model Structure Rules', 'Dimensions in contexts MUST use the segment container',
							array(
								'hypercube' => $hypercubeLabel,
								'primary item' => $primaryItemLabel,
								'role' => $elr,
								'error' => 'error:DimensionsMustUseSegmentContainer'
							)
						);
					}
				}
			}
		}

		unset( $roleRef );

		$this->calculationNetworks = $this->getCalculationRoleRefs();
		$this->calculationNetworks = array_filter( $this->calculationNetworks, function( $roleRef ) { return isset( $roleRef['calculations'] ); } );
		foreach ( $this->calculationNetworks as $elr => $role )
		{
			if ( ! isset( $role['calculations'] ) ) continue;

			foreach ( $role['calculations'] as $totalLabel => $components )
			{
				$calculationELRPIs = array_keys( $components );
				$calculationELRPIs[] = $totalLabel;

				$this->calculationPIs[ $elr ] = isset( $this->calculationPIs[ $elr ] )
					? array_merge( $this->calculationPIs[ $elr ], $calculationELRPIs )
					: $calculationELRPIs;
			}

			unset( $calculationELRPIs );
		}

		$this->presentationNetworks = &$this->getPresentationRoleRefs();

		// Check the definition and presentation roles are consistent then make sure the calculation roles are a sub-set
		if ( $this->definitionNetworks && array_diff_key( $this->presentationNetworks, $this->definitionNetworks ) || array_diff_key( $this->definitionNetworks, $this->presentationNetworks ) )
		{
			$log->business_rules_validation('Model Structure Rules', 'Networks in defintion and presentation linkbases MUST be the same',
				array(
					'presentation' => implode( ', ', array_keys( array_diff_key( $this->presentationNetworks, $this->definitionNetworks ) ) ),
					'definition' => implode( ', ', array_keys( array_diff_key( $this->definitionNetworks, $this->presentationNetworks ) ) ),
					'error' => 'error:HypercubeMustUseAllArcrole'
				)
			);
		}
		else
		{
			if ( array_diff_key( $this->calculationNetworks, $this->presentationNetworks ) )
			{
				$log->business_rules_validation('Model Structure Rules', 'Networks in calculation linkbases MUST be a sub-set of those definition and presentation linkbases',
					array(
						'calculation' => implode( ', ', array_keys( array_diff_key( $this->calculationNetworks, $this->presentationNetworks ) ) ),
						'error' => 'error:HypercubeMustUseAllArcrole'
					)
				);
			}
		}

		$presentationRollupPIs = array();

		foreach ( $this->presentationNetworks as $elr => &$role )
		{
			$this->presentationPIs[$elr] = array();

			foreach ( $role['locators'] as $id => $label )
			{
				$taxonomy = $this->getTaxonomyForXSD( $label );
				$element = $taxonomy->getElementById( $label );

				if ( $element['abstract'] || $element['type'] == 'nonnum:domainItemType' ) continue;

				// One or more of the labels may include the preferred label role so convert all PIs back to their id
				$this->presentationPIs[$elr][] = $taxonomy->getTaxonomyXSD() . "#{$element['id']}";

				// BMS 2019-03-23 TODO Check the concept is not a tuple
			}

			// If there were preferred label roles in any of the PIs then there will be duplicates.  This also sorts the list.
			$this->presentationPIs[ $elr ] = array_unique( $this->presentationPIs[ $elr ] );

			// This set of closures will become methods in a class
			$formulasForELR = array();
			if ( $formulas )
			{
				$variableSets = $formulas->getVariableSets();
				foreach ( $variableSets as $variableSetQName => $variableSetForQName )
				{
					foreach ( $variableSetForQName as /** @var ValueAssertion $variableSet */ $variableSet )
					{
						if ( $variableSet->extendedLinkRoleUri != $elr ) continue;
						if ( ! $variableSet instanceof ValueAssertion ) continue;
						$formulasForELR[] = $variableSet;
					}
				}
			}

			$calculationELRPIs = isset( $this->calculationPIs[ $elr ] ) ? $this->calculationPIs[ $elr ] : array();

			$axes = array();
			$lineItems = array();
			$tables = array();
			$concepts = array();

			$this->processNodes( $role['hierarchy'], null, false, $this->allowed['cm.xsd#cm_Network'], false, $calculationELRPIs, $elr, $presentationRollupPIs, $tables, $lineItems, $axes, $concepts );

			if ( $this->definitionNetworks && count( $tables ) != 1 )
			{
				XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'There MUST be one and only one table per network',
					array(
						'tables' => implode( ', ', $tables ),
						'role' => $elr,
						'error' => 'error:MustBeOnlyOneTablePerNetwork'
					)
				);
			}

			if ( $this->definitionNetworks && count( $lineItems ) != 1 )
			{
				XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'There MUST be one and only one line items node per table',
					array(
						'lineitems' => implode( ', ', $lineItems ),
						'role' => $elr,
						'error' => 'error:OneAndOnlyOneLineItems'
					)
				);
			}

			$role['axes'] = $axes;
			$role['tables'] = $tables;
			$role['lineitems'] = $lineItems;
			$role['concepts'] = $concepts;
		}

		unset( $role );


		// The set of line items used in calculation, definition and presentation linkbases should be the same
		// First check there are consistent networks
		$commonRoles = array_intersect_key( $this->definitionPIs, $this->presentationPIs );

		foreach ( $commonRoles as $elr => $role )
		{
			if ( isset( $presentationRollupPIs[ $elr ] ) )
			{
				$diff = array_unique( array_merge( array_diff( $presentationRollupPIs[ $elr ], $this->calculationPIs[ $elr ] ), array_diff( $this->calculationPIs[ $elr ], $presentationRollupPIs[ $elr ] ) ) );
				if ( $diff )
				{
					$log->business_rules_validation('Model Structure Rules', 'Calculation primary items MUST be the same as presentation items that are used in rollup blocks',
						array(
							'primary item' => implode( ',', $diff ),
							'role' => $elr,
							'error' => 'error:CalculationRelationsMissingConcept'
						)
					);
				}
			}

			$diff = array_unique( array_diff( $this->definitionPIs[ $elr ], $this->presentationPIs[ $elr ] ) );
			if ( $diff )
			{
				$log->business_rules_validation('Model Structure Rules', 'Presentation primary items MUST be the same as definition primary items',
					array(
						'primary item' => implode( ',', $diff ),
						'role' => $elr,
						'error' => 'error:PresentationRelationsMissingConcept'
					)
				);
			}

			$diff = array_unique( array_diff( $this->presentationPIs[ $elr ], $this->definitionPIs[ $elr ] ) );
			if ( $diff )
			{
				$log->business_rules_validation('Model Structure Rules', 'Definition primary items MUST be the same as presentation primary items',
					array(
						'primary item' => implode( ',', $diff ),
						'role' => $elr,
						'error' => 'error:DefinitionRelationsMissingConcept'
					)
				);
			}
		}

		return $this->presentationNetworks;
	}

	/**
	 * Look for a concept in each formula's filter
	 * @param array $formulasForELR
	 * @param XBRL_DFR $taxonomy
	 * @param array $element
	 * @return boolean
	 */
	private function findConceptInFormula( $formulasForELR, $taxonomy, $element )
	{
		if ( ! $formulasForELR ) return false;

		$conceptClark = "{" . $taxonomy->getNamespace() . "}" . $element['name'];

		foreach ( $formulasForELR as $variableSet )
		{
			foreach ( $variableSet->variablesByQName as $qname => $variable )
			{
				if ( ! $variable instanceof FactVariable ) continue;
				foreach ( $variable->filters as $x => $filter )
				{
					if ( ! $filter instanceof ConceptName ) continue;
					foreach ( $filter->qnames as $clark )
					{
						if ( $clark == $conceptClark ) return true;;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Return the label of an axis if it exists in $axes or false
	 * @param string $axisName
	 * @param array $axes
	 * @return string|boolean
	 */
	private function hasAxis( $axisName, $axes )
	{
		$dfrConceptualModel = $this->supportedFeatures('conceptual-model');

		$axisName = $dfrConceptualModel[ $axisName ];
		$axis = array_filter( $axes, function( $axis ) use( $axisName )
		{
			return isset( $axis['dimension'] ) && $axis['dimension']->localName == $axisName;
		} );

		return $axis ? key( $axis ) : false;
	}

	/**
	 *Test whether the $elr contains a $label
	 * @param string $label The label to find
	 * @param string $elr The extended link role to look in
	 * @param string $parentLabel
	 * @param string $source What hypercube aspect to use (primaryitems, members, dimensions)
	 * @param string $recurse If true the hierarchy will be tested recursively
	 * @return unknown|boolean|unknown|boolean
	 */
	private function hasHypercubeItem( $label, $elr, $parentLabel, $source = 'primaryitems', $recurse = true )
	{
		// if ( ! isset( $this->definitionNetworks[ $elr ] ) ) $this->definitionNetworks[ $elr ] = $this->getDefinitionRoleRef( $elr );
		if ( isset( $this->definitionNetworks[ $elr ][ $source ][ $label ] ) ) return $this->definitionNetworks[ $elr ][ $source ][ $label ];

		if ( $recurse )
		{
			// If not check for the label in a different ELR
			foreach ( $this->definitionNetworks as $elr2 => &$role )
			{
				// Ignore the same ELR
				if ( $elr == $elr2 ) continue;

				//
				$node = $this->hasHypercubeItem( $label, $elr2, $parentLabel, $source, false );
				if ( $node )
				{
					XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', ' Network relations for presentation, calculation, and definition relations MUST be defined in the same network.',
						array(
							'parent' => $parentLabel ? $parentLabel : 'Network',
							'concept' => $label,
							'expected role' => $elr,
							'actual role' => $elr,
							'error' => 'error:NetworkIdentifiersInconsistent'
						)
					);
					return $node;
				}
			}
		}

		return false;
	}

	/**
	 * Process the nodes for an ELR.  Returns the pattern type name for the block
	 * @param array		$noes A standard node hierarchy
	 * @param string	$parentLabel The label of the node that owns $nodes
	 * @param boolean	$parentIsAbstract True is the parent node is an abstract node
	 * @param array		$validNodeTypes A list of node types allowed for these nodes
	 * @param boolean	$underLineItems True if the set of nodes a descendent of a line items node
	 * @param array		$calculationELRPIs (ref) An array containing labels of calculation primary items
	 * @param string	$elr The current extended link role being processed
	 * @param array		$presentationRollupPIs (ref) A variable used to capture the priamry items used in rollup blocks
	 * @param array		$tables (ref)
	 * @param array		$lineItems (ref)
	 * @param array		$axes (ref)
	 * @param array		$concepts (ref)
	 * @return string
	 */
	private function processNodes( &$nodes, $parentLabel, $parentIsAbstract, $validNodeTypes, $underLineItems, &$calculationELRPIs, $elr, &$presentationRollupPIs, &$tables, &$lineItems, &$axes, &$concepts )
	{
		$possiblePatternTypes = array();
		$patternType = ''; // Default pattern

		// Make sure the nodes are sorted by order
		uasort( $nodes, function( $nodea, $nodeb ) { return $nodea['order'] - $nodeb['order']; } );

		// Create a list of labels that are not abstract
		$getNonAbstract = function( $nodes )
		{
			return array_filter( array_keys( $nodes ), function( $label )
			{
				$taxonomy = $this->getTaxonomyForXSD( $label );
				$element = $taxonomy->getElementById( $label );
				return ! $element['abstract'];
			} );
		};

		$nonAbstract = $getNonAbstract( $nodes );

		$firstNonAbstractLabel = reset( $nonAbstract );
		$lastNonAbstractLabel = end( $nonAbstract );

		foreach ( $nodes as $label => &$node )
		{
			$first = $label == $firstNonAbstractLabel;
			$last = $label == $lastNonAbstractLabel;

			$taxonomy = $this->getTaxonomyForXSD( $label );
			$element = $taxonomy->getElementById( $label );

			// Recreate the label because if the arc has a preferred label the label will include the preferred label to make the index unique
			$label = $taxonomy->getTaxonomyXSD() . "#{$element['id']}";
			if ( $first ) $firstNonAbstractLabel = $label;
			if ( $last ) $lastNonAbstractLabel = $label;

			$ok = false;
			$type = '';

			foreach( $this->allowed as $child => $detail )
			{
				if ( $ok ) continue;

				switch( $child )
				{
					case 'cm.xsd#cm_Table':
						$ok |= $taxonomy->context->types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtHypercubeItem ) );
						if ( $ok )
						{
							$tables[] = $label;
						}
						break;

					case 'cm.xsd#cm_Axis':
						$ok |= $taxonomy->context->types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) );
						if ( $ok )
						{
							$axes[ $label ] = array( 'dimension' => new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] ) );
						}
						break;

					case 'cm.xsd#cm_Member':

						// Q Which test need the condition: $element['type'] == 'nonnum:domainItemType'
						// A 3000 01-MemberAbstractAttribute
						$ok |= /* $element['abstract'] && */ $element['type'] == 'nonnum:domainItemType' && isset( $this->definitionNetworks[ $elr ]['members'][ $label ] );
						if ( $ok )
						{
							$axes[ $parentLabel ]['members'][ $label ] = new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] );
						}
						break;

					case 'cm.xsd#cm_LineItems':
						if ( $element['abstract'] )
						{
							$item = $this->hasHypercubeItem( $label, $elr, $parentLabel, 'primaryitems', true );
							if ( $item && ! isset( $item['parents'] ) ) // a line item is a root primary item node
							{
								$ok = true;
								$lineItems[ $parentLabel ] = $label;
							}
							unset( $item);
						}
						break;

					case 'cm.xsd#cm_Concept':
						// if ( $patternType == 'rollup' )
						// {
						//	$ok = true;
						//	break;
						// }

						if ( ! $element['abstract'] && $element['type'] != 'nonnum:domainItemType' && $this->isPrimaryItem( $element ) )
						{
							$ok = true;
							$concepts[ $label ] = new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] );

							if ( ! $possiblePatternTypes && in_array( $label, $calculationELRPIs ) )
							{
								// $ok = true;
								$patternType = 'rollup';
								$possiblePatternTypes = array();
								break;
							}

							// Add a list of the possible concept arrangemebt patterns
							//
							// This information comes from http://xbrlsite.azurewebsites.net/2017/IntelligentDigitalFinancialReporting/Part02_Chapter05.7_UnderstandingConceptArrangementPatternsMemberArrangementPatterns.pdf
							// starting with section 1.3.2
							//
							// Rollup: If the concept is in the calculation linkbase then the only pattern us rollup
							//
							// Roll forward: can be detected because
							// (a) it always has in instant as the first and last concept in the presentation relations,
							// (b) the first instant has a periodStart label role,
							// (c) the second instant concept is the same as the first and has the periodEnd label, and
							// (d) XBRL Formulas exist the represent the roll forward mathematical relation.
							//
							// Roll forward info: looks like a roll forward, but is not really a roll forward.
							// While a roll forward reconciles the balance of a concept between two points in time;
							// the roll forward info is really just a hierarchy which shows a beginning and ending
							// balance. A roll forward info concept arrangement pattern is generally shown with a
							// roll forward.  Roll forward info can be detected because:
							// (a) the first concept has a periodStart label,
							// (b) the last concept in the presentation relations has a periodEnd label.
							//
							// Adjustment: always has a 'Report Date [Axis]' and
							// (a) the first concept is an instant and uses the originallyStated label
							// (b) the last concept is an instant and uses the restated label role
							// Alias Concepts for 'Report Creation Date [Axis]' are 'us-gaap:CreationDateAxis' and 'ifrs-full:CreationDateAxis, frm:ReportDateAxis'
							//
							// Variance: can be a specialization of other concept arrangement patterns such as a
							// 			 [Hierarchy] as shown above, a [Roll Up] if the [Line Items] rolled up, or
							//			 even a [RollForward]. Uses the 'Reporting Scenario [Axis]'
							//
							// Aliases concepts are: 'usgaap:StatementScenarioAxis' (Seems missing from IFRS).
							//
							// Complex computation: can be identified because
							// (a) there are numeric relations and those relations do not follow any of the other
							//	   mathematical patterns
							// (b) there is an XBRL formula that represents a mathematical relation other than one
							//     of the other mathematical computation patterns.
							//
							// Text block can always be identified by the data type used to represent the text block
							// which will be: nonnum:textBlockItemType
							//

							if ( $possiblePatternTypes )
							{
								// Filter the list of possible pattern types
								if ( $last )
								{
									// Look for an ending label
									if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodEndLabel )
									{
										if ( $element['periodType'] == 'instant' )
										{
											if ( in_array( 'rollforward', $possiblePatternTypes ) && $this->findConceptInFormula( null, $taxonomy, $element ) )
											{
												$patternType = "rollforward";
												$possiblePatternTypes = array();
												break;
											}
										}

										if ( in_array( 'rollforwardinfo', $possiblePatternTypes ) )
										{
											$patternType = "rollforwardinfo";
											$possiblePatternTypes = array();
											break;
										}
									}

									if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRoleRestatedLabel )
									{
										if ( $element['periodType'] == 'instant' )
										{
											if ( in_array( 'adjustment', $possiblePatternTypes ) )
											{
												$patternType = "adjustment";
												$possiblePatternTypes = array();
												break;
											}
										}
									}

									if ( in_array( 'complex', $possiblePatternTypes ) || $this->findConceptInFormula( null, $taxonomy, $element ) )
									{
										$patternType = "complex";
										$possiblePatternTypes = array();
										break;
									}
								}

								if ( ! in_array( 'complex', $possiblePatternTypes ) && $this->findConceptInFormula( null, $taxonomy, $element ) )
								{
									$possiblePatternTypes[] = 'complex';
								}

							}
							else
							{
								if ( $first )
								{
									// Roll forward
									// Roll forward info
									if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodStartLabel )
									{
										$possiblePatternTypes[] = 'rollforwardinfo';
										if ( $element['periodType'] == 'instant' && $this->findConceptInFormula( null, $taxonomy, $element ) )
										{
											$possiblePatternTypes[] = 'rollforward';
										}
									}

									// Adjustment
									if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_DFR::$originallyStatedLabel )
									{
										// MUST be an instant period type and have a report date axis
										if ( $element['periodType'] == 'instant' && $this->hasAxis( 'ReportDateAxis', $axes ) )
										{
											$possiblePatternTypes[] = 'adjustment';
										}
									}

								}

								// Complex
								if ( $this->findConceptInFormula( null, $taxonomy, $element ) )
								{
									if ( $last )
									{
										$patternType = "complex";
									}
									else
									{
										$possiblePatternTypes[] = 'complex';
									}
								}

								// Text
								if ( $first && $last && $element['type'] == 'nonnum:textBlockItemType' )
								{
									$patternType = 'text';
								}
							}
						}
						break;

					case 'cm.xsd#cm_Abstract':
						// Abstract is low priority - do it later if necessary
						break;

					default:
						// Do nothing
						break;

				}

				if ( $ok )
				{
					$node['modelType'] = $child;
					break;
				}
			}

			if ( ! $ok /* && isset( $validNodeTypes['cm.xsd#cm_Abstract'] ) */ )
			{
				if ( $element['abstract'] && $taxonomy->context->types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrliItem ) ) )
				{
					$ok = true;
					$node['modelType'] = $child = 'cm.xsd#cm_Abstract';
				}
			}

			if ( ! isset( $node['modelType'] ) )
			{
				// Something has gone wrong
				XBRL_Log::getInstance()->warning( "Node without a model type: " . $label );
				continue;
			}

			if ( ! $ok || ! isset( $validNodeTypes[ $node['modelType'] ] ) )
			{
				global $reportModelStructureRuleViolations;
				if ( $reportModelStructureRuleViolations )
				XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'Invalid model structure',
					array(
						'parent' => $parentLabel ? $parentLabel : 'Network',
						'concept' => $label,
						'expected' => implode(', ', array_keys( $validNodeTypes ) ),
						'role' => $elr,
						'error' => 'error:InvalidModelStructure'
					)
				);
			}

			// Set the pattern type here
			if ( ! isset( $node['children'] ) ) continue;
			if ( ! isset( $this->allowed[ $node['modelType'] ] ) )
			{
				global $reportModelStructureRuleViolations;
				if ( $reportModelStructureRuleViolations )
				XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'Invalid model structure.  The computed model type is not allowed at this point',
					array(
						'parent' => $parentLabel ? $parentLabel : 'Network',
						'concept' => $label,
						'expected' => implode(', ', array_keys( $validNodeTypes ) ),
						'model type' => $child,
						'role' => $elr,
						'error' => 'error:InvalidModelStructure'
					)
				);
				continue;
			}

			$isLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
			$isAbstract = $node['modelType'] == 'cm.xsd#cm_Abstract';
			$underLineItems |= $isLineItems;
			$result = $this->processNodes( $node['children'], $label, $isAbstract, $this->allowed[ $child ], $underLineItems, $calculationELRPIs, $elr, $presentationRollupPIs, $tables, $lineItems, $axes, $concepts );
			$node['patterntype'] = $result;

			if ( $underLineItems && ( $isAbstract || $isLineItems ) && ! $result )
			{
				$result = 'set'; // Add a default if one not provided
			}

			if ( $underLineItems && $result )
			{
				$node['variance'] = false;
				$node['grid'] = false;

				// May be a variance
				// See if there is a report scenario axis
				$varianceAxis = $this->hasAxis( 'ReportingScenarioAxis', $axes );

				if ( $varianceAxis )
				{
					// Note: these tests could be combined into one composite
					// test but broken out its easier to see what's going on

					// BMS 2019-03-23 Need to check that there is one parent with two members otherwise its a grid

					// There must be more than one member
					$members = $axes[ $varianceAxis ]['members'];
					if ( count( $members ) > 1 )
					{
						$node['variance'] = $varianceAxis;
					}
					else if ( $members )
					{
						// Check to see if there are nested members.  Only one additional member is required
						if ( isset( $axes[ key( $members ) ] ) && count( $axes[ key( $members ) ]['members'] ) )
						{
							$node['variance'] = $varianceAxis;
						}
					}

				}
				// If not a variance then maybe a grid?
				if ( ! $node['variance'] )
				{
					$otherAxes = array_filter( $axes, function( $axis )
					{
						return isset( $axis['dimension'] ) && ( ! in_array( $axis['dimension']->localName, $this->axesToExclude ) );
					} );

					if ( $otherAxes )
					{
						$node['grid'] = $otherAxes;
					}
				}
			}

			if ( $result == "rollup" )
			{
				// Check that the calculation components are not mixed up
				// Check that the node children can be described by the members of just one calculation relationship
				// Begin by checking to see if the children have a total member
				$error = false;
				$totals = array_intersect_key( $this->calculationNetworks[ $elr ]['calculations'], $node['children'] );
				$error = count( $totals ) > 1;
				if ( ! $error )
				{
					$nonAbstractNodes = $getNonAbstract( $node['children'] );
					if ( $totals )
					{
						// Its an error if all the node members are not described by this relation
						$total = key( $totals );
						// diff should have one member and it should be the total
						$diff = array_diff( $nonAbstractNodes, array_keys( $this->calculationNetworks[ $elr ]['calculations'][ $total ] ) );
						if ( $diff )
						{
							$totalKey = array_search( $total, $diff );
							if ( $totalKey )
							{
								unset( $diff[ $totalKey ] );
							}

							// Check that any remaining elements are not in another rollup
							if ( $diff )
							{
								foreach ( $this->calculationNetworks[ $elr ]['calculations'] as $totalLabel => $components )
								{
									// No need to look in the current rollup
									if ( $totalLabel == $total ) continue;
									// If any diff elements are the same as any component members, that's bad
									if ( ! array_intersect( $diff, array_keys( $components ) ) ) continue;
									$error = true;
									break;
								}
							}
						}
						// $error = count( $diff ) != 1 || reset( $diff ) != $total;
					}
					else
					{
						// If there are no totals loop through each calculation to find a relationship that encompasses all children
						// Assume the worst
						$error = true;
						foreach ( $this->calculationNetworks[ $elr ]['calculations'] as $totalLabel => $components )
						{
							$diff = array_diff( $nonAbstractNodes, array_keys( $components ) );
							if ( ! $diff )
							{
								// Found a matching set
								$error = false;
								break;
							}
						}
					}
				}

				if ( $error )
				{
					XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'A rollup MUST contain components from only one calculation relationship set',
						array(
							'rollup' => $label,
							'role' => $elr,
							'error' => 'error:BlocksRunTogether'
						)
					);
				}

				// Filter any non-PI nodes.  This occurs in the pathalogical test case when a dimension member is a rollup.
				$pis = array_filter( array_keys( $node['children'] ), function( $label )
				{
					$taxonomy = $this->getTaxonomyForXSD( $label );
					$element = $taxonomy->getElementById( $label );
					return ! $element['abstract'] && $this->isPrimaryItem( $element );
				} );

				// Capture the elements in node['children']
				$presentationRollupPIs[ $elr ] = isset( $presentationRollupPIs[ $elr ] )
					? array_merge( $presentationRollupPIs[ $elr ], $pis )
					: $pis;
			}
		}

		unset( $node );

		if ( empty( $patternType ) && $underLineItems )
		{
			$patternType = "set";
		}
		return $patternType;
	}
}
