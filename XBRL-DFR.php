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
use XBRL\Formulas\Resources\Assertions\ValueAssertion;

define( 'NEGATIVE_AS_BRACKETS', 'brackets' );
define( 'NEGATIVE_AS_MINUS', 'minus' );

class XBRL_DFR extends XBRL
{
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
	 * Holds a list of features
	 * @var array
	 */
	private $features = array();

	/**
	 * How to style negative numbers
	 * @var string NEGATIVE_AS_BRACKETS | NEGATIVE_AS_MINUS
	 */
	private $negativeStyle = NEGATIVE_AS_BRACKETS;

	/**
	 * When true, any columns that contain no values or only closing balance values will be removed
	 * @var string
	 */
	private $stripEmptyColumns = false;

	/**
	 * A fixed list of dimensions to exclude when determining if there should be a grid layout
	 * @var array
	 */
	private $axesToExclude = array();

	// Private variables for the function validateDFR
	/**
	 * A list of primary items within each ELR in the presentation linkbase being evaluated
	 * @var [][] $presentationPIs
	 */
	private $presentationPIs = array();
	/**
	 * A list of primary items within each ELR in the calculation linkbase being evaluated
	 * @var [][] $calculationPIs
	 */
	private $calculationPIs = array();
	/**
	 * A list of primary items within each ELR in the definition linkbase being evaluated
	 * @var [][] $definitionPIs
	 */
	private $definitionPIs = array();

	/**
	 * A list of the calculation networks or roles defined in the taxonomy.
	 * By default the full structure is not realized so this variable holds
	 * the realized network so they do not have to be realized repeatedly.
	 * @var array
	 */
	private $calculationNetworks = array();
	/**
	 * A list of the defintion networks or roles defined in the taxonomy.
	 * By default the full structure is not realized so this variable holds
	 * the realized network so they do not have to be realized repeatedly.
	 * @var array $definitionNetworks
	 */
	private $definitionNetworks = array();
	/**
	 * A list of the presentation networks or roles defined in the taxonomy.
	 * By default the full structure is not realized so this variable holds
	 * the realized network so they do not have to be realized repeatedly.
	 * @var array $presentationNetworks
	 */
	private $presentationNetworks = array();

	/**
	 * Created by the constructor to hold the list of valid presentation relationships
	 * @var array
	 */
	private $allowed = array();

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
			// XBRL_Constants::$dfrReportingScenarioAxis // Variance
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

	/**
	 * Renders an evidence package for a set of networks
	 * @param array $networks
	 * @param XBRL_Instance $instance
	 * @param XBRL_Formulas $formulas
	 * @param Observer $observer
	 * @param bool $echo
	 * @return array
	 */
	public function renderPresentationNetworks( $networks, $instance, $formulas, $observer, $evaluationResults, $echo = true )
	{
		$result = array();

		foreach ( $networks as $elr => $network )
		{
			$result[ $elr ] = $this->renderPresentationNetwork( $network, $elr, $instance, $formulas, $observer, $evaluationResults, $echo );
		}

		return $result;
	}

	/**
	 * Renders an evidence package for a network
	 * @param array $network
	 * @param string $elr
	 * @param XBRL_Instance $instance
	 * @param XBRL_Formulas $formulas
	 * @param Observer $observer
	 * @param bool $echo
	 * @return array
	 */
	public function renderPresentationNetwork( $network, $elr, $instance, $formulas, $observer, $evaluationResults, $echo = true )
	{
		$entities = $instance->getContexts()->AllEntities();

		// Add a depth to each node
		$addDepth = function( &$nodes, $depth = 0 ) use( &$addDepth )
		{
			foreach ( $nodes as $label => &$node )
			{
				$node['depth'] = $depth;
				if ( ! isset( $node['children'] ) ) continue;
				$addDepth( $node['children'], $depth + 1 );
			}
			unset( $node );
		};

		$addDepth( $network['hierarchy'] );

		$result = array();

		foreach ( $entities as $entity )
		{
			$entityQName = qname( $entity );

			// Find periods
			$names = array_map( function( $conceptQName )
			{
				return $conceptQName->localName;
			}, $network['concepts'] );

			$elements = $instance->getElements()->ElementsByName( $names )->getElements();

			$contextRefs = array_reduce( $elements, function( $carry, $element ) use ( $instance )
			{
				$result = array_unique( array_map( function( $fact ) { return $fact['contextRef']; }, array_values( $element ) ) );
				return array_unique( array_merge( $carry, $result ) );
			}, array() );

			$contexts = array_intersect_key( $instance->getContexts()->getContexts(), array_flip( $contextRefs ) );
			$years = array();
			foreach ( $contexts as $contextRef => $context )
			{
				$year = substr( $context['period']['endDate'], 0, 4 );
				if ( ! isset( $years[ $year ] ) ) $years[ $year ] = array(
					'text' => $context['period']['is_instant']
									? $context['period']['endDate']
									: "{$context['period']['startDate']} - {$context['period']['endDate']}",
					'contextRefs' => array(),
					// 'year' => $year
				);
				$years[ $year ]['contextRefs'][] = $contextRef;
			}

			$result[ $entity ] = $this->renderNetworkReport( $network, $elr, $instance, $entityQName, $formulas, $observer, $evaluationResults, $contexts, $years, $echo );
		}

		return $result;
	}

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

			$this->processNodes( $role['hierarchy'], null, false, $this->allowed['cm.xsd#cm_Network'], false, $calculationELRPIs, $elr, $presentationRollupPIs, $tables, $lineItems, $axes, $concepts, $formulasForELR );

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
	 * @param array $formulasForELR (ref) Array of formulas defined for the ELR
	 * @param XBRL_DFR $taxonomy
	 * @param array $element
	 * @return boolean
	 */
	private function findConceptInFormula( &$formulasForELR, $taxonomy, $element )
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
	 * @param array		$formulasForELR (ref)
	 * @return string
	 */
	private function processNodes( &$nodes, $parentLabel, $parentIsAbstract, $validNodeTypes, $underLineItems, &$calculationELRPIs, $elr, &$presentationRollupPIs, &$tables, &$lineItems, &$axes, &$concepts, &$formulasForELR )
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
							$dimension = $this->definitionNetworks[ $elr ]['hypercubes'][ @reset($tables) ]['dimensions'][ $label ];
							$defaultMember = isset( $dimension['default'] ) ? $dimension['default']['label'] : false;

							if ( ! $defaultMember && $element['name'] == XBRL_Constants::$dfrReportDateAxis )
							{
								XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'Report Date [Axis] Missing Dimension Default',
									array(
										'axis' => $label,
										'role' => $elr,
										'error' => 'error:ReportDateDimensionMissingDimensionDefault'
									)
								);

							}

							$axes[ $label ] = array(
								'dimension' => new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] ),
								'dimension-label' => $label,
								'default-member' => $defaultMember
							);
						}
						break;

					case 'cm.xsd#cm_Member':

						// Q Which test need the condition: $element['type'] == 'nonnum:domainItemType'
						// A 3000 01-MemberAbstractAttribute
						$ok |= /* $element['abstract'] && */ $element['type'] == 'nonnum:domainItemType' && isset( $this->definitionNetworks[ $elr ]['members'][ $label ] );
						if ( $ok )
						{
							if ( ! isset( $axes[ $parentLabel ]['domain-member'] ) ) $axes[ $parentLabel ]['domain-member'] = false;
							if ( ! isset( $axes[ $parentLabel ]['root-member'] ) ) $axes[ $parentLabel ]['root-member'] = false;
							if ( isset( $this->definitionNetworks[ $elr ]['members'][ $label ]['parents'][ $parentLabel ]['arcrole'] ) )
							{
								$arcrole = $this->definitionNetworks[ $elr ]['members'][ $label ]['parents'][ $parentLabel ]['arcrole'];
								if ( $arcrole == XBRL_Constants::$arcRoleDimensionDomain ) $axes[ $parentLabel ]['domain-member'] = $label;
							}
							$tableLable = reset( $tables );
							if ( isset( $axes[ $parentLabel ] ) && isset( $axes[ $parentLabel ]['dimension'] ) ) $axes[ $parentLabel ]['root-member'] = $label;
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
								if ( isset( $this->calculationNetworks[ $elr ]['calculations'][ $label ] ) )
								{
									$node['total'] = true;
								}
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
							// (a) it always has an instant as the first and last concept in the presentation relations,
							// (b) the first instant has a periodStart label role,
							// (c) the second instant concept is the same as the first and has the periodEnd label, and
							// (d) XBRL Formulas exist that represent the roll forward mathematical relation.
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
											if ( in_array( 'rollforward', $possiblePatternTypes ) && ( isset( $calculationELRPIs[ $label ] ) || $this->findConceptInFormula( $formulasForELR, $taxonomy, $element ) ) )
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

									if ( in_array( 'complex', $possiblePatternTypes ) || $this->findConceptInFormula( $formulasForELR, $taxonomy, $element ) )
									{
										$patternType = "complex";
										$possiblePatternTypes = array();
										break;
									}
								}

								if ( ! in_array( 'complex', $possiblePatternTypes ) && $this->findConceptInFormula( $formulasForELR, $taxonomy, $element ) )
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
										if ( $element['periodType'] == 'instant' && ( isset( $calculationELRPIs[ $label ] ) || $this->findConceptInFormula( $formulasForELR, $taxonomy, $element ) ) )
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
								if ( $this->findConceptInFormula( $formulasForELR, $taxonomy, $element ) )
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
			$result = $this->processNodes( $node['children'], $label, $isAbstract, $this->allowed[ $child ], $underLineItems, $calculationELRPIs, $elr, $presentationRollupPIs, $tables, $lineItems, $axes, $concepts, $formulasForELR );
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

	/**
	 * Renders the component table
	 * @param array $network
	 * @param string $elr
	 * @return string
	 */
	private function renderComponentTable( $network, $elr )
	{
		$table = $this->getTaxonomyDescriptionForIdWithDefaults( reset( $network['tables'] ) );

		$componentTable =
			"	<div class='component-table'>" .
			"		<div class='ct-header'>Component: Network plus Table</div>" .
			"		<div class='ct-body'>" .
			"			<div class='ct-body-header network'>Network</div>" .
			"			<div class='ct-body-content network'>" .
			"				<div>{$network['text']}</div>" .
			"				<div>$elr</div>" .
			"			</div>" .
			"			<div class='ct-body-header hypercube'>Table</div>" .
			"			<div class='ct-body-content hypercube'>$table</div>" .
			"		</div>" .
			"	</div>";

		return $componentTable;
	}

	/**
	 * Renders the slicers table
	 * @param array $network
	 * @param QName $entityQName
	 * @return string
	 */
	private function renderSlicers( $network, $entityQName )
	{
		$slicers =
			"	<div>" .
			"		<div>Slicers</div>" .
			"		<div class='slicers'>" .
			"			<div class='slicer-header'>Reporting Entity [Axis]</div>" .
			"			<div class='slicer-content'>{$entityQName->localName} ({$entityQName->namespaceURI})</div>";

		foreach ( $network['axes'] as $label => $axis)
		{
			if ( ! isset( $axis['dimension'] ) ) continue;
			if ( count( $axis['members'] ) > 1 ) continue;
			$memberLabel = key($axis['members'] );
			if ( isset( $network['axes'][ $memberLabel ] ) ) continue;

			$dimension = $this->getTaxonomyDescriptionForIdWithDefaults( $label );
			$slicers .= "			<div class='slicer-header'>$dimension</div>";

			/** @var QName $memberQName */
			$memberQName = reset( $axis['members'] );
			$memberTaxonomy = $this->getTaxonomyForNamespace( $memberQName->namespaceURI );
			$memberElement = $memberTaxonomy->getElementByName( $memberQName->localName );

			$member = $this->getTaxonomyDescriptionForIdWithDefaults( $memberTaxonomy->getTaxonomyXSD() . "#" . $memberElement['id'] );
			$slicers .= "			<div class='slicer-content'>$member</div>";
		}

		$slicers .=
			"		</div>" .
			"	</div>";

		return $slicers;
	}

	/**
	 * Renders the model structure table
	 * @param array $network
	 * @return string
	 */
	private function renderModelStructure( $network )
	{
		$structureTable =
			"	<div class='structure-table'>" .
			"		<div>Label</div>" .
			"		<div>Fact set type</div>" .
			"		<div>Report Element Class</div>" .
			"		<div>Period Type</div>" .
			"		<div>Balance</div>" .
			"		<div>Name</div>";

			$renderStructure = function( $nodes ) use( &$renderStructure )
			{
				$result = array();

				foreach( $nodes as $label => $node )
				{
					if ( ! isset( $node['modelType'] ) ) continue;

					/**
					 *
					 * @var XBRL $nodeTaxonomy
					 */
					$nodeTaxonomy = $this->getTaxonomyForXSD( $label );
					$nodeElement = $nodeTaxonomy->getElementById( $label );

					$preferredLabels = isset( $node['preferredLabel'] ) ? array( $node['preferredLabel'] ) : null;
					// Do this because $label includes the preferred label roles and the label passed cannot include it
					$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $nodeTaxonomy->getTaxonomyXSD() . '#' . $nodeElement['id'], $preferredLabels );

					$name = $nodeTaxonomy->getPrefix() . ":" . $nodeElement['name'];
					$class = "";
					$reportElement = "";
					$periodType = "";
					$balance = "";
					$factSetType = "";

					switch ( $node['modelType'] )
					{
						case 'cm.xsd#cm_Table':
							$class = "hypercube";
							$reportElement = "[Table]";
							break;

						case 'cm.xsd#cm_Axis':
							$class = "axis";
							$reportElement = "[Axis]";
							break;

						case 'cm.xsd#cm_Member':
							$class = "member";
							$reportElement = "[Member]";
							break;

						case 'cm.xsd#cm_LineItems':
							$class = "lineitem";
							$reportElement = "[Line item]";
							$factSetType = isset( $node['patterntype'] ) ? $node['patterntype'] : '';
							break;

						case 'cm.xsd#cm_Concept':
							$class = "concept";
							$reportElement = "[Concept]";
							if ( $nodeElement['type'] == 'xbrli:stringItemType' )
							{
								$reportElement .= " string";
							}
							else if ( $this->context->types->resolvesToBaseType( $nodeElement['type'], array( "xbrli:monetaryItemType" ) ) )
							{
								$reportElement .= " monetary";
							}
							else if ( $nodeElement['type'] == 'xbrli:sharesItemType' )
							{
								$reportElement .= " shares";
							}
							else
							{
								$reportElement .= " " . $nodeElement['type'];
							}
							$periodType = $nodeElement['periodType'];
							$balance = isset( $nodeElement['balance'] ) ? $nodeElement['balance'] : 'n/a';
							$factSetType = isset( $node['patterntype'] ) ? $node['patterntype'] : '';
							break;

						case 'cm.xsd#cm_Abstract':
							$class = "abstract";
							$reportElement = "[Abstract]";
							$factSetType = isset( $node['patterntype'] ) ? $node['patterntype'] : '';
							break;
					}

					$result[] = "<div><span class='depth{$node['depth']} $class'>$text</span></div>";
					$result[] = "<div>$factSetType</div>";
					$result[] = "<div>$reportElement</div>"; // This text should be based on some lookup
					$result[] = "<div>$periodType</div>";
					$result[] = "<div>$balance</div>";
					$result[] = "<div>$name</div>";

					if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;
					$result = array_merge( $result, $renderStructure( $node['children'] ) );
				}

				return $result;
			};

			$result = $renderStructure( $network['hierarchy'] );

		$structureTable .= implode( '', $result ) . "	</div>";

		return $structureTable;
	}

	/**
	 * Render a report with columns for any years and dimensions
	 * @param array $network
	 * @param string $elr
	 * @param XBRL_Instance $instance
	 * @param XBRL_Formulas $formulas	The evaluated formulas
	 * @param Observer $observer		An obsever with any validation errors
	 * @param $evaluationResults		The results of validating the formulas
	 * @param $contexts					An array of the context valid for the network being reported
	 * @param $years					An array of years indexed by year number and with members 'label' and 'contextRefs'
	 * @return string
	 */
	private function renderReportTable( $network, $elr, $instance, $formulas, $observer, $evaluationResults, $contexts, $years )
	{
		$axes = &$network['axes'];

		$totalAxesCount = array_reduce( $axes, function( $carry, $axis ) { return $carry + ( isset( $axis['dimension'] ) ? 1 : 0 ) ; } );

		$hasReportDateAxis = false;
		// Get a list of dimensions with more than one member
		$multiMemberAxes = array_reduce( array_keys( $axes ), function( $carry, $axisLabel ) use( $axes, $instance, &$hasReportDateAxis )
		{
			/** @var XBRL $taxonomy */
			$taxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
			$element = $taxonomy->getElementById( $axisLabel );

			$axis = $axes[ $axisLabel ];

			if ( in_array( $element['name'], $this->axesToExclude ) )
			{
				if ( $element['name'] == XBRL_Constants::$dfrReportDateAxis )
				{
					// Must have more than one member
					if ( count( $axis['members'] ) > 1 )
					{
						// Must be more than one context
						if ( $instance->getContexts()->SegmentContexts( strstr( $axisLabel, '#' ), $taxonomy->getNamespace() )->count() > ( $axis['default-member'] ? 0 : 1 ) )
						{
							$hasReportDateAxis = $axisLabel;
						}
					}
				}
				return $carry;
			}

			if ( ! isset( $axis['dimension'] ) || // Ignore member only items
				 (
				   count( $axis['members'] ) <= 1 && // Ignore axes with more than one member
				   ! isset( $axes[ key( $axis['members'] ) ] ) // Or that has sub-members
				 )
			) return $carry;
			return $carry + array( $axisLabel );
		}, array() );

		// Get count of dimensions with more than one member
		$multiMemberAxesCount = count( $multiMemberAxes );

		// The number of columns is the number of $years * the number of members for each dimension
		$headerColumnCount = count( $years );

		$getAxisMembers = function( $members ) use( &$getAxisMembers, &$axes )
		{
			$result = array();
			foreach ( $members as $memberLabel => $memberQName )
			{
				$result[] = $memberLabel;
				if ( ! isset( $axes[ $memberLabel ] ) ) continue;
				$result = array_merge( $result, $getAxisMembers( $axes[ $memberLabel ]['members'] ) );
			}

			return $result;
		};

		foreach ( $multiMemberAxes as $axisLabel )
		{
			$headerColumnCount *= count( $getAxisMembers( $axes[ $axisLabel ]['members'] ) );
		}

		$columnCount = $headerColumnCount + 1 + ( $hasReportDateAxis ? 1 : 0 ); // Add the description column

		$getRowCount = function( $nodes, $lineItems = false ) use( &$getRowCount, $instance )
		{
			$count = 0;
			foreach ( $nodes as $label => $node )
			{
				$lineItems |= $node['modelType'] == 'cm.xsd#cm_LineItems';
				if ( $lineItems )
				{
					$count++;
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$count += $getRowCount( $node['children'], $lineItems );
			}

			return $count;
		};

		$rowCount = $getRowCount( $network['hierarchy'] );

		// The final row count has to include the number of multi-member axes * 2 + 1.
		// The 2 because each axis contributes two header rows: for the dimension label and for the member
		// The 1 is from the period axis but the row count already has a line from the header: the line items row
		$rowCount += $multiMemberAxesCount * 2 + 1;

		// How many are header rows?
		// The one is for the implicit period axis.
		$headerRowCount = ($multiMemberAxesCount + 1) * 2;

		// Workout what the columns will contain. $columnHierarchy will contain a hierarchical list of nodes
		// where the leaf nodes represent the actual columns and there should be $headerColumnCount of them.
		// Each node will contain a list of the axis/members and a list of contexts which apply at that node.
		// Until there is a more complete example
		$columnHierarchy['Period [Axis]'] = $years;
		$columnHierarchy['Period [Axis]']['total-children'] = count( $years );

		// Extend $columnHierarchy to add columns for $multiMemberAxes and their members
		if ( $multiMemberAxes )
		{
			$addToColumnHierarchy = function( &$columnHierarchy, $multiMemberAxes ) use ( &$addToColumnHierarchy, $instance, &$axes, &$getAxisMembers )
			{
				$totalChildren = 0;

				foreach ( $columnHierarchy as $axisLabel => &$members )
				{
					if ( ! $multiMemberAxes )
					{
						$totalChildren += $members['total-children'];
						continue;
					}

					foreach ( $members as $index => &$member )
					{
						if ( $index == 'total-children' ) continue;

						if ( isset( $member['children'] ) )
						{
							$totalChildren += $addToColumnHierarchy( $member['children'], $multiMemberAxes );
						}
						else
						{
							// Get the axis text
							$nextAxisLabel = reset( $multiMemberAxes );
							/** @var XBRL_DFR $axisTaxonomy */
							$axisTaxonomy = $this->getTaxonomyForXSD( $nextAxisLabel );
							$axisText = $axisTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $nextAxisLabel );

							// Get the members
							$axisMembers = $getAxisMembers( $axes[ $nextAxisLabel ]['members'] );
							$axis = $axes[ $nextAxisLabel ];

							// Workout which contexts apply
							$cf = new ContextsFilter( $instance, array_reduce( $member['contextRefs'], function( $carry, $contextRef ) use( $instance) { $carry[ $contextRef ] = $instance->getContext( $contextRef ); return $carry; }, array() ) );

							$nextMembers = array();

							foreach ( $axisMembers as $memberLabel )
							{
								/** @var XBRL_DFR $memberTaxonomy */
								$memberTaxonomy = $this->getTaxonomyForXSD( $memberLabel );
								$memberText = $memberTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberLabel );
								$filteredContexts = $cf->SegmentContexts( strstr( $nextAxisLabel, '#' ), $axisTaxonomy->getNamespace(), strstr( $memberLabel, '#' ), $memberTaxonomy->getNamespace() );
								$guid = XBRL::GUID();
								$nextMembers[ $guid ] = array(
									'text' => $memberText,
									'contextRefs' => array_keys( $filteredContexts->getContexts() ),
									'default-member' => $axis['default-member'] == $memberLabel,
									'domain-member' => $axis['domain-member'] == $memberLabel,
									'root-member' => $axis['root-member'] == $memberLabel
								);
							}

							$nextMembers['total-children'] = count( $axisMembers );
							$member['children'][ $axisText ] = $nextMembers;

							// if ( count( $multiMemberAxes ) == 1 ) continue;

							$totalChildren += $addToColumnHierarchy( $member['children'], array_splice( $multiMemberAxes, 1 ) );
							// $nextMembers['total-children'] = $totalChildren;
							// unset( $nextMembers );
						}
					}
					unset( $member );
				}

				$members['total-children'] = $totalChildren;

				unset( $members );

				return $totalChildren;
			};

			$c = $addToColumnHierarchy( $columnHierarchy, $multiMemberAxes );
		}

		// Create an index of contextRef to column.  Should be only one column for each context.
		// At the same time create a column layout array that can be used to generate the column headers
		$columnLayout = array();
		$createContextRefColumns = function( $columnNodes, $depth = 0 ) use( &$createContextRefColumns, &$columnLayout )
		{
			$result = array();
			foreach ( $columnNodes as $axisLabel => $columnMembers )
			{
				$details = array( 'text' => $axisLabel, 'span' => $columnMembers['total-children'] );
				$columnLayout[ $depth ][] = $details;

				foreach ( $columnMembers as $index => $columnNode )
				{
					if ( $index == 'total-children' ) continue;

					$span = isset( $columnNode['children'] )
						? array_reduce( $columnNode['children'], function( $carry, $axis ) { return $carry + $axis['total-children']; }, 0 )
						: 1;
					$columnLayout[ $depth + 1 ][] = array(
						'text' => $columnNode['text'],
						'span' => $span,
						'default-member' => isset( $columnNode['default-member'] ) && $columnNode['default-member'],
						'domain-member' => isset( $columnNode['domain-member'] ) && $columnNode['domain-member'],
						'root-member' => isset( $columnNode['root-member'] ) && $columnNode['root-member']
					);

					if ( isset( $columnNode['children'] ) && $columnNode['children'] )
					{
						$result += $createContextRefColumns( $columnNode['children'], $depth + 2 );
					}
					else
					$result += array_fill_keys( $columnNode['contextRefs'], $index );
				}
			}
			return $result;
		};
		$contextRefColumns = $createContextRefColumns( $columnHierarchy );
		$columnRefs = array_flip( array_values( array_unique( $contextRefColumns ) ) );

		if ( count( $columnLayout ) != $headerRowCount )
		{
			$generatedHeaderRows = count( $columnLayout );
			XBRL_Log::getInstance()->warning( "The number of header rows generated ($generatedHeaderRows) does not equal the number of row expected ($headerRowCount)" );
		}

		$getFactSetTypes = function( $nodes, $lineItems = false ) use( &$getFactSetTypes, $instance )
		{
			$factSetTypes = array();

			foreach ( $nodes as $label => $node )
			{
				$thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				$lineItems |= $thisLineItems;
				if ( $lineItems && ( $thisLineItems || $node['modelType'] == 'cm.xsd#cm_Abstract' ) )
				{
					$factSetTypes[ $label ] = isset( $node['patterntype'] ) ? $node['patterntype'] : 'set';
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$factSetTypes = array_merge( $factSetTypes, $getFactSetTypes( $node['children'], $lineItems ) );
			}

			return $factSetTypes;
		};

		$factSetTypes = $getFactSetTypes( $network['hierarchy'] );

		// For roll forward and roll forward info
		// unset( $columnLayout[1][2] );
		// $columnLayout[0][0]['span']--;
		// $headerColumnCount--;
		// $columnCount--;

		// For grid
		// unset( $columnLayout[3][0] );
		// unset( $columnLayout[3][1] );
		// unset( $columnLayout[3][2] );
		// unset( $columnLayout[3][3] );
		// unset( $columnLayout[2][0] );
		// unset( $columnLayout[1][0] );
		// $columnLayout[0][0]['span'] -= 4;
		// $headerColumnCount -= 4;
		// $columnCount -= 4;

		$removeColumn = function( &$axis, $columnId ) use( &$removeColumn )
		{
			foreach ( $axis as $axisId => &$columns )
			{
				if ( $axisId == 'total-children ') continue;

				if ( isset( $columns[ $columnId ] ) )
				{
					unset( $columns[ $columnId ] );
					$columns['total-children']--;
					if ( ! $columns['total-children'] )
					{
						unset( $axis[ $axisId ] );
					}
					return 1;
				}

				foreach ( $columns as $id => &$column )
				{

					if ( isset( $column['children'] ) )
					{
						$result = $removeColumn( $column['children'], $columnId );
						if ( $result )
						{
							$columns['total-children']--;
							if ( ! count( $column['children'] ) )
							{
								unset( $columns[ $id ] );
							}
							return 1;
						}
					}
				}

			}

			return 0;
		};

		// for( $i = 0; $i < 5; $i++ )
		// {
		// 	$removeColumn( $columnHierarchy, array_flip( $columnRefs )[0] );
		// 	$headerColumnCount--;
		// 	$columnCount--;
		// 	$columnLayout = array();
		// 	$contextRefColumns = $createContextRefColumns( $columnHierarchy );
		// 	$columnRefs = array_flip( array_values( array_unique( $contextRefColumns ) ) );
		// }

		/**
		 * Return the fact corresponding to the originally stated or restated condition
		 * @var callable $getStatedFacts
		 * @param XBRL_DFR $nodeTaxonomy
		 * @param array $facts (ref)
		 * @param array $axis an entry for an axis in $axes
		 * @param ContextsFilter $cf A filter of instant contexts
		 * @param bool $originally True gets the facts for the orginally stated case; false restated
		 */
		$getStatedFacts = function( $nodeTaxonomy, &$facts, &$axis, /** @var ContextsFilter $cf */ $cf, $orginally = false ) use ( &$getStatedFacts, $hasReportDateAxis )
		{
			// The opening balance value is the one that has a context with the non-default/non-domain member
			$members = array_reduce( $axis['members'], function( $carry, $memberQName ) use( &$axis, $nodeTaxonomy ) {
				$memberTaxonomy = $nodeTaxonomy->getTaxonomyForPrefix( $memberQName->prefix );
				$memberElement = $memberTaxonomy->getElementByName( $memberQName->localName );
				$memberLabel = $memberTaxonomy->getTaxonomyXSD() . "#" . $memberElement['id'];
				if ( $memberLabel == $axis['default-member'] || $memberLabel == $axis['domain-member'] || $memberLabel == $axis['root-member'] )
				{
					$carry[] = $memberLabel;
				}
				return $carry;
			}, array() );
			// For now assume there is only one
			$memberLabel = reset( $members );
			// Find the context(s)
			$reportDateAxisTaxonomy = $nodeTaxonomy->getTaxonomyForXSD( $hasReportDateAxis );
			$memberTaxonomy = $nodeTaxonomy->getTaxonomyForXSD( $memberLabel );
			$filteredContexts = $axis['default-member']
				? $cf->NoSegmentContexts()
				: $cf->SegmentContexts( strstr( $hasReportDateAxis, '#' ), $reportDateAxisTaxonomy->getNamespace(), strstr( $memberLabel, '#' ), $memberTaxonomy->getNamespace() );
			// There should be only one
			$contextRef = key( $filteredContexts->getContexts() );

			// Find the fact WITHOUT this context
			$cbFacts = $facts;
			$facts = array();
			foreach ( $cbFacts as $factIndex => $fact )
			{
				if ( $orginally ? $fact['contextRef'] == $contextRef : $fact['contextRef'] != $contextRef ) continue;
				if ( ! $hasReportDateAxis )
				{
					$fact['contextRef'] = $cbFact['contextRef'];
				}
				$facts[ $factIndex ] = $fact;
				break;
			}
		};

		// Now workout the facts layout.
		// Note to me.  This is probably the way to go as it separates the generation of the facts from the rendering layout
		$getFactsLayout = function( $nodes, $lineItems = false ) use( &$getFactsLayout, &$getStatedFacts, $instance, &$axes, $columnLayout, $columnRefs, $contextRefColumns, $contexts, $hasReportDateAxis )
		{
			$rows = array();
			$priorRowContextRefsForByColumns = array();

			foreach ( $nodes as $label => $node )
			{
				$abstractLineItems = $node['modelType'] == 'cm.xsd#cm_Abstract';
				$thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				$lineItems |= $thisLineItems | $abstractLineItems;
				if ( $lineItems )
				{
					/** @var XBRL_DFR $nodeTaxonomy */
					$nodeTaxonomy = $this->getTaxonomyForXSD( $label );
					$nodeElement = $nodeTaxonomy->getElementById( $label );

					if ( $thisLineItems )
					{
					}
					else if ( $node['modelType'] == 'cm.xsd#cm_Abstract' )
					{
					}
					else
					{
						// Add the data.  There is likely to be only a partial facts set
						$facts = $instance->getElement( $nodeElement['name'] );

						if ( isset( $node['preferredLabel'] ) )
						{
							$openingBalance = $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodStartLabel;
							$cf = new ContextsFilter( $instance, $contexts );
							/** @var ContextsFilter $instantContextsFilter */
							$instantContextsFilter = $cf->InstantContexts();

							if ( $hasReportDateAxis && $node['preferredLabel'] == self::$originallyStatedLabel )
							{
								// If there is domain or default member of ReportDateAxis then one approach
								// is taken to find an opening balance.  If not the another approach is required.
								$axis = $axes[ $hasReportDateAxis ];
								if ( $axis['default-member'] || $axis['domain-member'] || $axis['root-member'] )
								{
									$getStatedFacts( $nodeTaxonomy, $facts, $axis, $instantContextsFilter, true );
								}
								else
								{
									$openingBalance = true;
								}
							}

							if ( $openingBalance )
							{
								$instantContexts = $instantContextsFilter-> getContexts();
								uksort( $instantContexts, function( $a, $b ) use ( &$contexts )
								{
									return -1 * strcmp( $contexts[ $a] ['period']['endDate'], $contexts[ $b]['period']['endDate'] );
								} );

								$instantContextsKeys = array_flip( array_keys( $instantContexts ) );
								$cbFacts = $facts;
								$facts = array();
								foreach ( $cbFacts as $cbFactIndex => $cbFact )
								{
									$segmentContextFilter = $instantContextsFilter->SameContextSegment( $contexts[ $cbFact['contextRef'] ] );
									$segmentContexts = $segmentContextFilter-> getContexts();
									uksort( $segmentContexts, function( $a, $b ) use ( &$contexts )
									{
										return -1 * strcmp( $contexts[ $a ] ['period']['endDate'], $contexts[ $b ]['period']['endDate'] );
									} );

									// Find the fact's prior context
									reset( $segmentContexts );
									do
									{
										if ( key( $segmentContexts ) != $cbFact['contextRef'] ) continue;
										next( $segmentContexts );
										break;
									}
									while ( next( $segmentContexts ) );

									if ( is_null( $contextRef = key( $segmentContexts ) ) ) continue;

									// Find the fact with this context
									foreach ( $cbFacts as $factIndex => $fact )
									{
										if ( $fact['contextRef'] != $contextRef ) continue;
										if ( ! $hasReportDateAxis )
										{
											$fact['contextRef'] = $cbFact['contextRef'];
										}
										$facts[ $factIndex ] = $fact;
										break;
									}
								}
								unset( $cbFacts );
							}
						}

						$columns = array();
						// Look for the fact with $contextRef
						if ( $hasReportDateAxis )
						{
							// Find the segment with $hasReportDateAxis
							if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRoleRestatedLabel )
							{
								$axis = $axes[ $hasReportDateAxis ];
								if ( $axis['default-member'] || $axis['domain-member'] || $axis['root-member'] )
								{
									$getStatedFacts( $nodeTaxonomy, $facts, $axis, $instantContextsFilter, false );
									$fact = reset( $facts );
								}
								else
								{
									$fact = reset( $facts ); // By default use the first fact
									if ( count( $facts ) > 1 && $priorRowContextRefsForByColumns )
									{
										$contextRef = reset( $priorRowContextRefsForByColumns );
										// Look for a fact with this context ref
										$f = @reset( array_filter( $facts, function( $fact ) use ( $contextRef ) { return $fact['contextRef'] == $contextRef ; } ) );
										if ( $f ) $fact = $f;
									}
								}
							}
							else
							{
								$fact = reset( $facts );
							}

						}

						$priorRowContextRefsForByColumns = array();

						$lastRowLayout = end( $columnLayout );

						foreach ( $facts as $factIndex => $fact )
						{
							if ( ! $fact || ! isset( $contextRefColumns[ $fact['contextRef'] ] ) ) continue;
							$columnIndex = $columnRefs[ $contextRefColumns[ $fact['contextRef'] ] ];
							// Check that the column is still reportable.  It might have been removed as empty
							if ( ! isset( $lastRowLayout[ $columnIndex ] ) ) continue;
							$currentColumn = $lastRowLayout[ $columnIndex ];

							$columns[ $columnIndex ] = $fact;
							$priorRowContextRefsForByColumns[ $columnIndex ] = $fact['contextRef'];
						}
						unset( $fact ); // Gets confusing having old values hanging around
						unset( $facts );

						ksort( $columns );
						$rows[ $label ] = array( 'columns' => $columns, 'taxonomy' => $nodeTaxonomy, 'element' => $nodeElement, 'node' => $node );
						unset( $columns );
					}
				}
				else
				{
					// No layout until the line items node is found
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$rows = array_merge( $rows, $getFactsLayout( $node['children'], $lineItems ) );
			}

			return $rows;
		};

		$factsLayout = $getFactsLayout( $network['hierarchy'] );

		$dropEmptyColumns = function( $rows, &$columnHierarchy, &$columnCount, &$headerColumnCount, &$columnRefs, &$columnLayout ) use( &$createContextRefColumns, &$removeColumn )
		{
			$columnsToDrop = array();
			$flipped = array_flip( $columnRefs );
			for ( $columnIndex = 0; $columnIndex<$headerColumnCount; $columnIndex++ )
			{
				$empty = true;

				foreach ( $rows as $label => $row )
				{
					$closingBalance = isset( $row['node']['preferredLabel'] ) && $row['node']['preferredLabel'] == XBRL_Constants::$labelRolePeriodEndLabel;
					if ( $closingBalance ) continue;  // Ignore closing balance
					if ( ! isset( $row['columns'][ $columnIndex ] ) ) continue;

					$empty = false;
					break;
				}
				if ( $empty ) $columnsToDrop[] = $flipped[ $columnIndex ];
			}

			foreach ( $columnsToDrop as $columnIndex )
			{
				$removeColumn( $columnHierarchy, $columnIndex );
				$headerColumnCount--;
				$columnCount--;
				$columnLayout = array();
				$contextRefColumns = $createContextRefColumns( $columnHierarchy );
				$columnRefs = array_flip( array_values( array_unique( $contextRefColumns ) ) );
			}

		};

		$dropEmptyColumns( $factsLayout, $columnHierarchy, $columnCount, $headerColumnCount, $columnRefs, $columnLayout );

		// Now workout the layout.
		$createLayout = function( $nodes, $lineItems = false, $patternType = 'set', $main = false, &$row = 0 ) use( &$createLayout, &$getStatedFacts, $instance, &$axes, $columnCount, $columnLayout, $columnRefs, $contextRefColumns, $contexts, $headerColumnCount, $headerRowCount, $rowCount, $factSetTypes, $hasReportDateAxis )
		{
			$divs = array();
			$priorRowContextRefsForByColumns = array();

			foreach ( $nodes as $label => $node )
			{
				$abstractLineItems = $node['modelType'] == 'cm.xsd#cm_Abstract';
				$thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				$lineItems |= $thisLineItems | $abstractLineItems;
				if ( $lineItems )
				{
					/** @var XBRL_DFR $nodeTaxonomy */
					$nodeTaxonomy = $this->getTaxonomyForXSD( $label );
					$nodeElement = $nodeTaxonomy->getElementById( $label );
					$preferredLabels = isset( $node['preferredLabel'] ) ? array( $node['preferredLabel'] ) : null;
					// Do this because $label includes the preferred label roles and the label passed cannot include it
					$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $nodeTaxonomy->getTaxonomyXSD() . '#' . $nodeElement['id'], $preferredLabels );

					if ( $thisLineItems )
					{
						// This is where the headers are laid out
						// This is the line-item header
						$divs[] =	"			<div class='report-header line-item' style='grid-area: 1 / 1 / span $headerRowCount / span 1;'>$text</div>";
						if ( $hasReportDateAxis )
						{
							$reportDateAxisTaxonomy = $nodeTaxonomy->getTaxonomyForXSD( $hasReportDateAxis );
							$reportDateAxisElement = $reportDateAxisTaxonomy->getElementById( $hasReportDateAxis );
							$text = $reportDateAxisTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $reportDateAxisTaxonomy->getTaxonomyXSD() . '#' . $reportDateAxisElement['id'] );
							$divs[] =	"			<div class='report-header axis-label line-item' style='grid-area: 1 / 2 / span $headerRowCount / span 1;'>$text</div>";
						}

						foreach ( $columnLayout as $row => $columns )
						{
							$column = 2 + ( $hasReportDateAxis ? 1 : 0 );
							$headerRow = $row + 1;
							$rowClass = $row % 2 ? "member-label" : "axis-label";
							if ( $headerRow == count( $columnLayout ) )
							{
								$rowClass .= " last";
							}
							foreach ( $columns as $columnSpan )
							{
								$columnClass = isset( $columnSpan['default-member'] ) && $columnSpan['default-member'] ? ' default-member' : '';
								$columnClass .= isset( $columnSpan['domain-member'] ) && $columnSpan['domain-member'] ? ' domain-member' : '';
								$columnClass .= isset( $columnSpan['root-member'] ) && $columnSpan['root-member'] ? ' root-member' : '';

								$span = $columnSpan['span'];
								$divs[] = "			<div class='report-header $rowClass$columnClass' style='grid-area: $headerRow / $column / $headerRow / span $span;'>{$columnSpan['text']}</div>";

								$column += $span;
								if ( $column > $columnCount + 1)
								{
									XBRL_Log::getInstance()->warning( "The number of generated header columns ($column) is greater than the number of expected columns ($columnCount)" );
								}
							}
						}
					}
					else if ( $node['modelType'] == 'cm.xsd#cm_Abstract' )
					{
						$row++;
						$main = false;
						if ( isset( $node['patterntype'] ) )
						{
							if ( $node['patterntype'] == 'rollup' )
							{
								$main = $patternType != $node['patterntype'];
							}
							$patternType = $node['patterntype'];
						}
						// Abstract rows laid out here
						$startDateAxisStyle = $hasReportDateAxis ? 'style="grid-column-start: span 2;"' : '';
						$divs[] = "		<div class='report-line line-item abstract' data-row='$row' $startDateAxisStyle>$text</div>";
						for ( $i = 0; $i < $headerColumnCount; $i++ ) $divs[] = "<div class='report-line abstract-value' data-row='$row'></div>";
					}
					else
					{
						// All other (concept) rows laid out here
						$row++;
						$totalClass = isset( $node['total'] ) && $node['total'] ? 'total' : '';
						$totalClass .= $main && $totalClass ? ' main' : '';
						// Add the data.  There is likely to be only a partial facts set
						$facts = $instance->getElement( $nodeElement['name'] );

						if ( isset( $node['preferredLabel'] ) )
						{
							$openingBalance = $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodStartLabel;
							// $openingBalance = false;
							$cf = new ContextsFilter( $instance, $contexts );
							/** @var ContextsFilter $instantContextsFilter */
							$instantContextsFilter = $cf->InstantContexts();

							if ( $hasReportDateAxis && $node['preferredLabel'] == self::$originallyStatedLabel )
							{
								// If there is domain or default member of ReportDateAxis then one approach
								// is taken to find an opening balance.  If not the another approach is required.
								$axis = $axes[ $hasReportDateAxis ];
								if ( $axis['default-member'] || $axis['domain-member'] || $axis['root-member'] )
								{
									$getStatedFacts( $nodeTaxonomy, $facts, $axis, $instantContextsFilter, true );
								}
								else
								{
									$openingBalance = true;
								}
							}

							if ( $openingBalance )
							{
								$instantContexts = $instantContextsFilter-> getContexts();
								uksort( $instantContexts, function( $a, $b ) use ( &$contexts )
								{
									return -1 * strcmp( $contexts[ $a] ['period']['endDate'], $contexts[ $b]['period']['endDate'] );
								} );

								$instantContextsKeys = array_flip( array_keys( $instantContexts ) );
								$cbFacts = $facts;
								$facts = array();
								foreach ( $cbFacts as $cbFactIndex => $cbFact )
								{
									$segmentContextFilter = $instantContextsFilter->SameContextSegment( $contexts[ $cbFact['contextRef'] ] );
									$segmentContexts = $segmentContextFilter-> getContexts();
									uksort( $segmentContexts, function( $a, $b ) use ( &$contexts )
									{
										return -1 * strcmp( $contexts[ $a ] ['period']['endDate'], $contexts[ $b ]['period']['endDate'] );
									} );

									// Find the fact's prior context
									reset( $segmentContexts );
									do
									{
										if ( key( $segmentContexts ) != $cbFact['contextRef'] ) continue;
										next( $segmentContexts );
										break;
									}
									while ( next( $segmentContexts ) );

									if ( is_null( $contextRef = key( $segmentContexts ) ) ) continue;

									// Find the fact with this context
									foreach ( $cbFacts as $factIndex => $fact )
									{
										if ( $fact['contextRef'] != $contextRef ) continue;
										if ( ! $hasReportDateAxis )
										{
											$fact['contextRef'] = $cbFact['contextRef'];
										}
										$facts[ $factIndex ] = $fact;
										break;
									}
								}
								unset( $cbFacts );
							}
							else if ( $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodEndLabel && $patternType == 'rollforward' )
							{
								$totalClass = 'total';
							}
						}

						// This line MUST appear after preferred labels have been processed
						$divs[] = "		<div class='report-line line-item $totalClass' data-row='$row'>$text</div>";

						$columns = array();
						// Look for the fact with $contextRef
						if ( $hasReportDateAxis )
						{
							// Find the segment with $hasReportDateAxis
							if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRoleRestatedLabel )
							{
								$axis = $axes[ $hasReportDateAxis ];
								if ( $axis['default-member'] || $axis['domain-member'] || $axis['root-member'] )
								{
									$getStatedFacts( $nodeTaxonomy, $facts, $axis, $instantContextsFilter, false );
									$fact = reset( $facts );
								}
								else
								{
									$fact = reset( $facts ); // By default use the first fact
									if ( count( $facts ) > 1 && $priorRowContextRefsForByColumns )
									{
										$contextRef = reset( $priorRowContextRefsForByColumns );
										// Look for a fact with this context ref
										$f = @reset( array_filter( $facts, function( $fact ) use ( $contextRef ) { return $fact['contextRef'] == $contextRef ; } ) );
										if ( $f ) $fact = $f;
									}
								}

								$totalClass = 'total';
							}
							else
							{
								$fact = reset( $facts );
							}

							$reportAxisMemberClass = '';

							$text = ''; // By default there is no text for the column
							if ( isset( $contexts[ $fact['contextRef'] ]['entity']['segment']['explicitMember'] ) )
							{
								$reportDateAxisTaxonomy = $nodeTaxonomy->getTaxonomyForXSD( $hasReportDateAxis );
								$reportDateAxisElement = $reportDateAxisTaxonomy->getElementById( $hasReportDateAxis );
								$qname = $reportDateAxisTaxonomy->getPrefix() . ":" . $reportDateAxisElement['name'];

								$explicitMembers = $contexts[ $fact['contextRef'] ]['entity']['segment']['explicitMember'];
								$em = @reset( array_filter( $explicitMembers, function( $em ) use( $qname ) {
									return $em['dimension'] == $qname;
								} ) );

								if ( $em && isset( $em['member'] ) )
								{
									$member = $em['member'];
									$qname = qname( $member, $instance->getInstanceNamespaces() );
									$memberTaxonomy = $nodeTaxonomy->getTaxonomyForNamespace( $qname->namespaceURI );
									if ( $memberTaxonomy )
									{
										$memberElement = $memberTaxonomy->getElementByName( $qname->localName );
										{
											if ( $memberElement )
											{
												$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberTaxonomy->getTaxonomyXSD() . '#' . $memberElement['id'] );
											}
										}
									}
								}

							}

							else if ( $axis['default-member'] )
							{
								$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $axis['default-member'] );
								$reportAxisMemberClass = 'default-member';
							}

							$divs[] = "<div class='report-line member-label value $reportAxisMemberClass'>$text</div>";
						}

						$priorRowContextRefsForByColumns = array();

						$lastRowLayout = end( $columnLayout );

						foreach ( $facts as $factIndex => $fact )
						{
							if ( ! $fact || ! isset( $contextRefColumns[ $fact['contextRef'] ] ) ) continue;
							$columnIndex = $columnRefs[ $contextRefColumns[ $fact['contextRef'] ] ];
							// Check that the column is still reportable.  It might have been removed as empty
							if ( ! isset( $lastRowLayout[ $columnIndex ] ) ) continue;
							$currentColumn = $lastRowLayout[ $columnIndex ];
							$columnTotalClass = $currentColumn['default-member'] || $currentColumn['domain-member'] || $currentColumn['root-member']
								? 'total'
								: '';
							$type = (string) XBRL_Instance::getElementType( $fact );
							$valueClass = empty( $type ) ? '' : $nodeTaxonomy->valueAlignment( $type, $instance );
							$value = $nodeTaxonomy->sanitizeText( $nodeTaxonomy->formattedValue( $fact, $instance, false ) );
							if ( $fact['value'] && is_numeric( $fact['value'] ) )
							{
								if ( $this->negativeStyle == NEGATIVE_AS_BRACKETS )
								{
									if ( $fact['value'] < 0 )
									{
										$valueClass .= ' neg';
										$value = "(" . abs( $fact['value'] ) . ")";
									}
									else $valueClass .= ' pos';
								}
							}

							$columns[ $columnIndex ] = "<div class='report-line value $totalClass $columnTotalClass $valueClass' data-row='$row'>$value</div>";
							$priorRowContextRefsForByColumns[ $columnIndex ] = $fact['contextRef'];
						}
						unset( $fact ); // Gets confusing having old values hanging around
						unset( $facts );

						// Fill in
						foreach ( $lastRowLayout as $columnIndex => $column )
						{
							if ( isset( $columns[ $columnIndex ] ) ) continue;
							$columns[ $columnIndex ] = "<div class='report-line value' data-row='$row'></div>";
						}

						ksort( $columns );
						$divs = array_merge( $divs, $columns );
						unset( $columns );
					}
				}
				else
				{
					// No layout until the line items node is found
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$divs = array_merge( $divs, $createLayout( $node['children'], $lineItems, $patternType, $main, $row ) );
			}

			return $divs;
		};

		$columnWidth = array_search( 'text', $factSetTypes ) ? 'auto' : '90px';

		$reportDateColumn = $hasReportDateAxis ? ' auto ' : '';
		$reportTable =
			"	<div style='display: grid; grid-template-columns: auto 1fr;'>" .
			"		<div class='report-table' style='display: grid; grid-template-columns: 400px $reportDateColumn repeat( $headerColumnCount, $columnWidth ); grid-template-rows: repeat(10, auto);' >";

		$reportTable .= implode( '', $createLayout( $network['hierarchy'] ) );

		$reportTable .=
			"			<div class='report-line line-item abstract final'></div>";

		if ( $hasReportDateAxis )
		{
			$reportTable .=
				"			<div class='report-line line-item abstract final'></div>";
		}

		for ( $i = 0; $i < $headerColumnCount; $i++ ) $divs[] =
			$reportTable .= "<div class='report-line abstract-value final' ></div>";

		$reportTable .=
		"		</div>" .
			"		<div></div>" .
			"	</div>";

		return $reportTable;
	}

	/**
	 * Render a report with columns for any years and dimensions
	 * @param array $network			An array generated by the validsateDLR process
	 * @param string $elr				The extended link role URI
	 * @param XBRL_Instance $instance	The instance being reported
	 * @param QName $entityQName
	 * @param XBRL_Formulas $formulas	The evaluated formulas
	 * @param Observer $observer		An obsever with any validation errors
	 * @param $evaluationResults		The results of validating the formulas
	 * @param $contexts					An array of the context valid for the network being reported
	 * @param $years					An array of years indexed by year number and with members 'label' and 'contextRefs'
	 * @param $echo						If true the HTML will be echoed
	 * @return string
	 */
	private function renderNetworkReport( $network, $elr, $instance, $entityQName, $formulas, $observer, $evaluationResults, $contexts, $years, $echo = true )
	{
		$componentTable = $this->renderComponentTable( $network, $elr );

		$structureTable = $this->renderModelStructure( $network );

		$slicers = $this->renderSlicers( $network, $entityQName );

		$reportTable = $this->renderReportTable( $network, $elr, $instance, $formulas, $observer, $evaluationResults, $contexts, $years );

		$report =
			"<div class='model-structure'>" .
			$componentTable . $structureTable . $slicers . $reportTable .
			"</div>";

		if ( $echo ) echo $report;

		return $report;
	}

}
