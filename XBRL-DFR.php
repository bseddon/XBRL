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

use XBRL\Formulas\Resources\Filters\ConceptName;
use XBRL\Formulas\Resources\Formulas\Formula;
use lyquidity\xml\QName;
use XBRL\Formulas\Resources\Assertions\ExistenceAssertion;
use XBRL\Formulas\Resources\Assertions\ValueAssertion;
use XBRL\Formulas\Resources\Assertions\VariableSetAssertion;
use XBRL\Formulas\Resources\Assertions\ConsistencyAssertion;
use XBRL\Formulas\Resources\Variables\Parameter;

define( 'NEGATIVE_AS_BRACKETS', 'brackets' );
define( 'NEGATIVE_AS_MINUS', 'minus' );

class XBRL_DFR
{
	/**
	 * This is set in getConceptualModelRoles
	 * @var string
	 */
	public static $originallyStatedLabel = "";

	/**
	 * An array of conceptual model arcroles and relationships
	 * @var array|null
	 */
	private static $conceptualModelRoles;
	private static $defaultConceptualModelRoles;

	/**
	 * Returns the current set of conceptual model roles.  If not defined, creates a default set from?:
	 * http://xbrlsite.azurewebsites.net/2016/conceptual-model/reporting-scheme/ipsas/model-structure/ModelStructure-rules-ipsas-def.xml
	 * @param string $cacheLocation (optional)
	 * @return array
	 */
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
			// $cm = $taxonomy->getTaxonomyForXSD("cm.xsd");
			// $nonDimensionalRoleRef = $cm->getNonDimensionalRoleRefs( XBRL_Constants::$defaultLinkRole );
			// $cmArcRoles = $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ];

			$originallyStated = array_filter( $roleTypes['link:label'], function( $role ) { return $role['id']; } );
			self::$originallyStatedLabel = reset( $originallyStated )['roleURI'];

			self::setConceptualModelRoles( $taxonomy );
			self::$defaultConceptualModelRoles = self::$conceptualModelRoles;

			unset( $taxonomy );
			XBRL::reset();

			// self::$conceptualModelRoles = $cmArcRoles;
		}
		return self::$conceptualModelRoles;

	}

	/**
	 * Sets some model roles if there are any in the taxonomy or sets the default model roles
	 * @param XBRL $taxonomy A taxonomy to use or null
	 */
	public static function setConceptualModelRoles( $taxonomy = null )
	{
		$cmTaxonomy = $taxonomy ? $taxonomy->getTaxonomyForXSD("cm.xsd") : null;
		if ( $cmTaxonomy )
		{
			$nonDimensionalRoleRef = $cmTaxonomy->getNonDimensionalRoleRefs( XBRL_Constants::$defaultLinkRole );
			if ( isset( $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ] ) )
			{
				$cmArcRoles = $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ];
				self::$conceptualModelRoles = $cmArcRoles;

				return;
			}
		}

		// Fallback
		self::$conceptualModelRoles = self::$defaultConceptualModelRoles;
	}

	/**
	 * Include a rendering of the components grid
	 * @var string
	 */
	public $includeComponent = true;

	/**
	 * Include a rendering of the structure grid
	 * @var string
	 */
	public $includeStructure = true;

	/**
	 * Include a rendering of the slicers grid
	 * @var string
	 */
	public $includeSlicers = true;

	/**
	 * Include a rendering of the report grid
	 * @var string
	 */
	public $includeReport = true;

	/**
	 * Include a rendering of the facts table grid
	 * @var string
	 */
	public $includeFactsTable = true;

	/**
	 * Include the checkbox controls
	 * @var string
	 */
	public $includeCheckboxControls = true;

	/**
	 * Include wider/narrower controls with the table grid
	 * @var string
	 */
	public $includeWidthcontrols = true;

	/**
	 * Include a rendering of the business rules grid
	 * @var string
	 */
	public $includeBusinessRules = true;

	/**
	 * When true all grids will be shown (visible).  When false only the rendered table will be visible by default.
	 * @var string
	 */
	public $showAllGrids = false;

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

	/**
	 * A list of aliases for the DFR ReportDateAxis
	 * @var array
	 */
	private $reportDateAxisAliases = array();

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
	 * A list of the definition networks or roles defined in the taxonomy.
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
	 * A list of translations of the reporting constants like 'axis' and 'period'
	 * @var array $constantLabels
	 */
	private static $constantTextTranslations = array(
		'da' => array(
			'Axis' => 'Akse',
			'Component' => 'Komponent',
			'Component: Network plus Table' => 'Komponent: Netv&aelig;rk plus tabel',
			'Network' => 'Net&aelig;rk',
			'Network plus Table' => 'Netv&aelig;rk plus tabel',
			'Period' => 'Periode',
			'Period [Axis]' => 'Periode [Akse]',
			'Reporting Entity' => 'Rapporteringsenhed',
			'Reporting Entity [Axis]' => 'Rapporteringsenhed [Akse]',
			'Table' => 'Tabel',
			'Context' => 'Kontekst',
			'Concept' => 'koncept',
			'Value' => 'V&aelig;rdi',
			'Unit' => 'Enhed',
			'Rounding' => 'Afrunding',
			'Fact table for' => 'Fakta tabel for',
			'Label' => 'Etiket',
			'Fact set type' => 'Fact set type',
			'Report Element Class' => 'Rapport Element Klasse',
			'Period Type' => 'Periodetype',
			'Balance' => 'Balance',
			'Name' => 'Navn',
			'Report sections' => 'Rapport sektioner',
			'Structure' => 'Struktur',
			'Slicers' => 'Slicers',
			'Report' => 'Rapport',
			'Facts' => 'Fakta',
			'Rules' => 'Regler',
			'There are no business rules' => 'Der er ingen forretningsregler',
			'Business rules' => 'Forretningsregel',
			'Line item' => 'Regnskabskoncept',
			'Calculated' => 'Beregnet',
			'Decimals' => 'Decimaler',
			'There is no data to report' => 'Der er ingen data at rapportere',
			'Columns' => 'Kolonner',
			'Wider' => 'Bredere',
			'Narrower' => 'Smallere',
			'Order' => 'Order',
			'Parent concept' => 'Parent concept'
		),
		'de' => array(
			'Axis' => 'Achse',
			'Component' => 'Komponente',
			'Component: Network plus Table' => 'Komponente: Netzwerk plus tabelle',
			'Network' => 'Netzwerk',
			'Network plus Table' => 'Netzwerk plus tabelle',
			'Period' => 'Zeitspanne',
			'Period [Axis]' => 'Zeitspanne [Achse]',
			'Reporting Entity' => 'Berichtseinheit',
			'Reporting Entity [Axis]' => 'Berichtseinheit [Achse]',
			'Table' => 'Tabelle',
			'Context' => 'Zusammenhang',
			'Concept' => 'Konzept',
			'Value' => 'Wert',
			'Unit' => 'Einheit',
			'Rounding' => 'Runden',
			'Fact table for' => 'Faktentabelle für',
			'Label' => 'Etikette',
			'Fact set type' => 'Typ des Faktensatzes',
			'Report Element Class' => 'Berichtselementklasse',
			'Period Type' => 'Periodentyp',
			'Balance' => 'Balance',
			'Name' => 'Name',
			'Report sections' => 'Berichtsabschnitte',
			'Structure' => 'Struktur',
			'Slicers' => 'Slicers',
			'Report' => 'Bericht',
			'Facts' => 'Fakten',
			'Rules' => 'Regeln',
			'There are no business rules' => 'Es gibt keine Geschäftsregeln',
			'Business rules' => 'Geschäftsregel',
			'Line item' => 'Buchhaltungbegriff',
			'Calculated' => 'Berechnet',
			'Decimals' => 'Dezimalstellen',
			'There is no data to report' => 'Es sind keine Daten zu melden',
			'Columns' => 'Säulen',
			'Wider' => 'Breiter',
			'Narrower' => 'Schmaler',
			'Order' => 'Order',
			'Parent concept' => 'Parent concept'
		),
		'es' => array(
			'Axis' => 'Eje',
			'Component' => 'Componente',
			'Component: Network plus Table' => 'Componente: Red más tabla',
			'Network' => 'Red',
			'Network plus Table' => 'Red más tabla',
			'Period' => 'Período',
			'Period [Axis]' => 'Período [Eje]',
			'Reporting Entity' => 'Entidad que informa',
			'Reporting Entity [Axis]' => 'Entidad que informa [Eje]',
			'Table' => 'Tabla',
			'Context' => 'Contexto',
			'Concept' => 'Concepto',
			'Value' => 'Valor',
			'Unit' => 'Unidad',
			'Rounding' => 'Redondeo',
			'Fact table for' => 'Tabla de hechos para',
			'Label' => 'Etiqueta',
			'Fact set type' => 'Tipo de conjunto de hechos',
			'Report Element Class' => 'Clase de elemento de informe',
			'Period Type' => 'Tipo de periodo',
			'Balance' => 'Equilibrar',
			'Name' => 'Nombre',
			'Report sections' => 'Secciones de informes',
			'Structure' => 'Estructura',
			'Slicers' => 'Slicers',
			'Report' => 'Informe',
			'Facts' => 'Hechos',
			'Rules' => 'Reglas',
			'There are no business rules' => 'No hay reglas de negocio',
			'Business rules' => 'Reglas de negocio',
			'Line item' => 'Término contable',
			'Calculated' => 'Calculado',
			'Decimals' => 'Decimales',
			'There is no data to report' => 'No hay datos para reportar',
			'Columns' => 'Columnas',
			'Wider' => 'Más amplio',
			'Narrower' => 'Más estrecho',
			'Order' => 'Order',
			'Parent concept' => 'Parent concept'
		),
		'fr' => array(
			'Axis' => 'Axe',
			'Component' => 'Composant',
			'Component: Network plus Table' => 'Composant: Réseau de tableau',
			'Network' => 'Réseau',
			'Network plus Table' => 'Réseau de tableau',
			'Period' => 'Période',
			'Period [Axis]' => 'Période [Axe]',
			'Reporting Entity' => 'Entité comptable',
			'Reporting Entity [Axis]' => 'Entité comptable [Axe]',
			'Table' => 'Tableau',
			'Context' => 'Contexte',
			'Concept' => 'Concept',
			'Value' => 'Valeur',
			'Unit' => 'Unité',
			'Rounding' => 'Arrondi',
			'Fact table for' => 'Table de faits pour',
			'Label' => 'Étiquette',
			'Fact set type' => 'Type d\'ensemble factuel',
			'Report Element Class' => 'Classe d\'élément de rapport',
			'Period Type' => 'Type de période',
			'Balance' => 'Équilibre',
			'Name' => 'Nom',
			'Report sections' => 'Sections du rapport',
			'Structure' => 'Structure',
			'Slicers' => 'Slicers',
			'Report' => 'Rapport',
			'Facts' => 'Faits',
			'Rules' => 'Règles',
			'There are no business rules' => 'Il n\'y a pas de règles d\'affaires',
			'Business rules' => 'Règle d\'affaires',
			'Line item' => 'Terme comptable',
			'Calculated' => 'Calculé',
			'Decimals' => 'Décimales',
			'There is no data to report' => 'Il n\'y a pas de données à signaler',
			'Columns' => 'Les colonnes',
			'Wider' => 'Plus large',
			'Narrower' => 'Plus étroit',
			'Order' => 'Order',
			'Parent concept' => 'Parent concept'
		),
		'it' => array(
			'Axis' => 'Asse',
			'Component' => 'Componente',
			'Component: Network plus Table' => 'Componente: Rete più Tabella',
			'Network' => 'Rete',
			'Network plus Table' => 'Rete più Tabella',
			'Period' => 'Periodo',
			'Period' => 'Periodo [Asse]',
			'Reporting Entity' => 'Entità segnalante',
			'Reporting Entity [Axis]' => 'Entità segnalante [Asse]',
			'Table' => 'Tabella',
			'Context' => 'Contesto',
			'Concept' => 'Concetto',
			'Value' => 'Valore',
			'Unit' => 'Unità',
			'Rounding' => 'Arrotondamento',
			'Fact table for' => 'Tabella dei fatti per',
			'Label' => 'Etichetta',
			'Fact set type' => 'Fatto impostato tipo',
			'Report Element Class' => 'Segnala la classe degli elementi',
			'Period Type' => 'Tipo di periodo',
			'Balance' => 'Equilibrio',
			'Name' => 'Nome',
			'Report sections' => 'Segnala sezioni',
			'Structure' => 'Struttura',
			'Slicers' => 'Slicers',
			'Report' => 'Rapporto',
			'Facts' => 'Fatti',
			'Rules' => 'Regole',
			'There are no business rules' => 'Non ci sono regole attività commerciale',
			'Business rules' => 'Regola attività commerciale',
			'Line item' => 'Termine di contabilità',
			'Calculated' => 'Calcolato',
			'Decimals' => 'Decimali',
			'There is no data to report' => 'Non ci sono dati da segnalare',
			'Columns' => 'Colonne',
			'Wider' => 'Più ampia',
			'Narrower' => 'Più stretto',
			'Order' => 'Order',
			'Parent concept' => 'Parent concept'
		)
	);

	/**
	 * Created by the constructor to hold the list of valid presentation relationships
	 * @var array
	 */
	private $allowed = array();

	/**
	 * Constructor
	 * @var XBRL $taxonomy
	 */
	private $taxonomy = null;

	/**
	 *
	 * @var array
	 */
	private static $beginEndPreferredLabelPairs = array();

	/**
	 * A static constructor
	 * @param string $cacheLocation
	 */
	public static function Initialize( $cacheLocation )
	{
		$cmArcRoles = XBRL_DFR::getConceptualModelRoles( $cacheLocation );

		self::$beginEndPreferredLabelPairs = array(
			array(
				XBRL_DFR::$originallyStatedLabel,
				XBRL_Constants::$labelRoleRestatedLabel
			),
			array(
				// self::$originallyStatedLabel,
				XBRL_Constants::$labelRoleVerboseLabel
			)
		);

		array_push( XBRL_DFR::$beginEndPreferredLabelPairs, reset( XBRL::$beginEndPreferredLabelPairs ) );

		XBRL::$beforeDimensionalPrunedDelegate = function( XBRL $taxonomy, array $dimensionalNode, array &$parentNode )
		{
			return false;
		};

		XBRL::$beginEndPreferredLabelPairsDelegate = function()
		{
			return XBRL_DFR::$beginEndPreferredLabelPairs;
		};

	}

	/**
	 * Constructor
	 * @param XBRL $taxonomy
	 */
	function __construct( XBRL $taxonomy )
	{
		$this->taxonomy = $taxonomy;

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
			'CreationDateAxis', // ifrs and us-gaap ReportDateAxis
			// XBRL_Constants::$dfrReportingScenarioAxis // Variance
		);

		$this->reportDateAxisAliases = array(
			'CreationDateAxis', // ifrs and us-gaap
			XBRL_Constants::$dfrReportDateAxis
		);


		$cmArcRoles = XBRL_DFR::getConceptualModelRoles();

		$this->allowed = $cmArcRoles[ XBRL_Constants::$arcRoleConceptualModelAllowed ]['arcs'];
		if ( ! isset( $allowed['cm.xsd#cm_Concept'] ) )
		{
			$this->allowed['cm.xsd#cm_Concept'] = array();
		}

	}

	/**
	 * Translate constant text used in the slicers, components and elsewhere
	 * Will return the input $text if $lang is null or begins with 'en'
	 * @param string $lang
	 * @param string $text
	 * @return string
	 */
	public function getConstantTextTranslation( $lang, $text )
	{
		if ( is_null( $lang ) || strpos( $lang, 'en' ) === 0 || ! isset( self::$constantTextTranslations[ $lang ][ $text ] ) )
		{
			return $text;
		}

		return self::$constantTextTranslations[ $lang ][ $text ];
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
	 * Renders an evidence package for a set of networks
	 * @param array $networks
	 * @param XBRL_Instance $instance
	 * @param array $report
	 * @param \Log_observer $observer
	 * @param string|null $lang			(optional: default = null) The language to use or null for the default
	 * @param bool $echo
	 * @param array $factsData			@reference If not null an array
	 * @return array
	 */
	public function renderPresentationNetworks( $networks, $instance, &$report, $observer, $lang = null, $echo = true, &$factsData = null )
	{
		// If the checkboxes are not displayed then make sure all selected tables are shown
		if ( ! $this->includeCheckboxControls ) $this->showAllGrids = true;

		$result = array();

		foreach ( $networks as $elr => $network )
		{
			// if ( ! \XBRL::endsWith( $elr, "http://www.microsoft.com/20180630/taxonomy/role/DisclosureDeferredIncomeTaxAssetsAndLiabilitiesDetail" ) ) continue;

			$entityHasReport = false;
			$data = is_array( $factsData ) ? array() : null;
			$entities = $this->renderPresentationNetwork( $network, $elr, $instance, $report, $observer, $entityHasReport, $lang, $echo, $data );
			if ( is_array( $factsData ) ) $factsData[ $elr ] = $data;

			// error_log( $elr );
			$result[ $elr ] = array(
				'entities' => $entities,
				'text' => $networks[ $elr ]['text'],
				'hasReport' => $entityHasReport
			);
		}

		return $result;
	}

	/**
	 * Renders an evidence package for a network
	 * @param array $network
	 * @param string $elr
	 * @param XBRL_Instance $instance
	 * @param array $report
	 * @param \Log_observer $observer
	 * @param bool $entityHasReport
	 * @param string|null $lang			(optional: default = null) The language to use or null for the default
	 * @param bool $echo
	 * @param array $factsData			@reference If not null an array
	 * @return array
	 */
	public function renderPresentationNetwork( $network, $elr, $instance, &$report, $observer, &$entityHasReport = false, $lang = null, $echo = true, &$factsData = null )
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

			$hasReport = false;
			$data = is_array( $factsData ) ? array() : null;
			$result[ $entity ] = $this->renderNetworkReport( $network, $elr, $instance, $entityQName, $report, $observer, $hasReport, $lang, $echo, $data );
			if ( is_array( $factsData ) ) $factsData[ $entity ] = $data;
			$entityHasReport |= $hasReport;
		}

		return $result;
	}

	/**
	 * Validate the the taxonomy against the model structure rules
	 * @param array $formulaSummaries An evaluated formulas instance
	 * @param string $lang a locale to use when returning the text. Defaults to null to use the default.
	 * @return array|null
	 */
	public function validateDFR( &$formulaSummaries, $rebuildDefinitionsCache = false, $lang = null )
	{
		global $reportModelStructureRuleViolations;

		$log = XBRL_Log::getInstance();

		// Makes sure they are reset in case the same taxonomy is validated twice.
		$this->calculationNetworks = array();
		$this->presentationNetworks = array();
		$this->definitionNetworks = $this->taxonomy->getAllDefinitionRoles( $rebuildDefinitionsCache );

		$this->taxonomy->generateAllDRSs();

		foreach ( $this->definitionNetworks as $elr => &$roleRef )
		{
			$roleRef = $this->taxonomy->getDefinitionRoleRef( $elr );

			if ( property_exists( $this, 'definitionRoles' ) && ! in_array( $elr, $this->definitionRoles ) )
			{
				unset( $this->definitionNetworks[ $elr ] );
				continue;
			}

			// Capture primary items
			$this->definitionPIs[ $elr ] = array_filter( array_keys( $roleRef['primaryitems'] ), function( $label )
			{
				$taxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
				$element = $taxonomy->getElementById( $label );
				return ! $element['abstract' ];
			} );

			sort( $this->definitionPIs[ $elr ] );

			// Check members
			foreach ( $roleRef['members'] as $memberLabel => $member )
			{
				$memberTaxonomy = $this->taxonomy->getTaxonomyForXSD( $memberLabel );
				$memberElement = $memberTaxonomy->getElementById( $memberLabel );

				if ( ! $memberElement['abstract' ] )
				{
					global $reportModelStructureRuleViolations;
					if ( $reportModelStructureRuleViolations )
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
			if ( $reportModelStructureRuleViolations )
			foreach ( $roleRef['hypercubes'] as $hypercubeLabel => $hypercube )
			{
				if ( ! isset( $hypercube['parents'] ) ) continue;

				foreach ( $hypercube['parents'] as $primaryItemLabel => $primaryItem )
				{
					if ( ! isset( $primaryItem['closed'] ) || ! $primaryItem['closed'] )
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

		$this->calculationNetworks = $this->taxonomy->getCalculationRoleRefs();
		$this->calculationNetworks = array_filter( $this->calculationNetworks, function( $roleRef ) { return isset( $roleRef['calculations'] ); } );
		foreach ( $this->calculationNetworks as $elr => $role )
		{
			if ( property_exists( $this, 'calculationRoles' ) && ! in_array( $elr, $this->calculationRoles ) )
			{
				unset( $this->calculationNetworks[ $elr ] );
				continue;
			}

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

		$this->presentationNetworks = &$this->taxonomy->getPresentationRoleRefs( null, null, $lang );

		// If there are no defined networks create one and add all the elements as nodes
		if ( $this->presentationNetworks )
		{
			if ( property_exists( $this, 'presentationRoles' ) )
			foreach ( $this->presentationNetworks as $elr => $role )
			{
				if ( in_array( $elr, $this->presentationRoles ) ) continue;
				unset( $this->presentationNetworks[ $elr ] );
			}
		}
		else
		{
			$xsd = $this->taxonomy->getTaxonomyXSD();
			$hierarchy = array();
			$locators = array();
			$paths = array();
			$roleUri = 'http://allConceptsAbstract';
			$order = 0;

			// Add presentation and defintion networks and also a line item abstract element
			$elements =& $this->taxonomy->getElements();
			$name = 'AllConceptsAbstract';
			$id = $this->taxonomy->getPrefix() . "_$name";
			$root = "{$xsd}#{$id}";
			$hierarchy[ $root ] = array(
				'label' => $root,
				'order' => 0,
				'use' => 'optional',
				'priority' => "0",
				'nodeclass' => 'primaryItem',
				'dt' => 'p',
				'hypercubes' => array(),
				'hypercubespruned' => true,
			);
			$locators[ $id ] = $root;
			foreach ( $elements as $label => $element )
			{
				$order++;
				$hierarchy[ $root ]['children']["{$xsd}#{$label}"] = array(
					'label' => "{$xsd}#{$label}",
					'order' => $order,
					'use' => 'optional',
					'priority' => "0",
					'nodeclass' => 'primaryItem',
					'dt' => 'p',
					'hypercubes' => array(),
					'hypercubespruned' => true,
				);
				$locators[ $label ] = "{$xsd}#{$label}";
				$paths[ $label ] = array( "$root/{$xsd}#{$label}" );
			}
			$elements[ $id ] = array(
				'id' => $id,
				'name' => $name,
				'type' => 'xbrli:stringItemType',
				'substitutionGroup' => 'xbrli:item',
				'abstract' => 1,
				'nilable' => 1,
				'periodType' => 'duration',
				'prefix' => $this->taxonomy->getPrefix(),
			);
			$paths[ $id ] = array( $root );

			$this->presentationNetworks[ $roleUri ] = array(
				'type' => 'simple',
				'href' => "{$this->taxonomy->getSchemaLocation()}#all",
				'roleUri' => $roleUri,
				'used' => true,
				'hierarchy' => $hierarchy,
				'locators' => $locators,
				'paths' => $paths,
				'hypercubes' => array(),
				'text' => 'All members network',
			);

			$this->definitionNetworks[ $roleUri ] = array(
				'members' => array(),
				'primaryitems' => array_reduce( $elements, function( $carry, $element ) use( $xsd )
				{
					$carry[ "$xsd#{$element['id']}" ] = array(
						'hypercubes' => array(),
						'localhypercubes' => array(),
					);
					return $carry;
				}, array() ),
				'dimensions' => array(),
				'hypercubes' => array(),
				'type' => 'simple',
				'href' => $this->taxonomy->getSchemaLocation(),
				'roleUri' => $roleUri,

			);
		}

		// Check the definition and presentation roles are consistent then make sure the calculation roles are a sub-set
		global $reportModelStructureRuleViolations;
		if ( $reportModelStructureRuleViolations )
		if ( $this->definitionNetworks && array_diff_key( $this->presentationNetworks, $this->definitionNetworks ) || array_diff_key( $this->definitionNetworks, $this->presentationNetworks ) )
		{
			$log->business_rules_validation('Model Structure Rules', 'Networks in definition and presentation linkbases MUST be the same',
				array(
					'presentation' => implode( ', ', array_keys( array_diff_key( $this->presentationNetworks, $this->definitionNetworks ) ) ),
					'definition' => implode( ', ', array_keys( array_diff_key( $this->definitionNetworks, $this->presentationNetworks ) ) ),
					'error' => 'error:NetworksMustBeTheSame'
				)
			);
		}
		else
		{
			if ( array_diff_key( $this->calculationNetworks, $this->presentationNetworks ) )
			{
				$log->business_rules_validation('Model Structure Rules', 'Networks in calculation linkbases MUST be a sub-set of those in definition and presentation linkbases',
					array(
						'calculation' => implode( ', ', array_keys( array_diff_key( $this->calculationNetworks, $this->presentationNetworks ) ) ),
						'error' => 'error:NetworksMustBeTheSame'
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
				$taxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
				$element = $taxonomy->getElementById( $label );

				if ( $element['abstract'] || $element['type'] == 'nonnum:domainItemType' ) continue;

				// BMS 2019-03-23 TODO Check the concept is not a tuple
				if ( $element['substitutionGroup'] == "xbrli:tuple" )
				{
					continue;
				}

				// One or more of the labels may include the preferred label role so convert all PIs back to their id
				$this->presentationPIs[$elr][] = $taxonomy->getTaxonomyXSD() . "#{$element['id']}";

			}

			// If there were preferred label roles in any of the PIs then there will be duplicates.  This also sorts the list.
			$this->presentationPIs[ $elr ] = array_unique( $this->presentationPIs[ $elr ] );

			$calculationELRPIs = isset( $this->calculationPIs[ $elr ] ) ? $this->calculationPIs[ $elr ] : array();

			$axes = array();
			$lineItems = array();
			$tables = array();
			$concepts = array();

			// Access the list of primary items
			// $primaryItems = $this->getDefinitionPrimaryItems();
			$primaryItems = $this->taxonomy->getDefinitionRolePrimaryItems( $elr );
			$currentPrimaryItem = array();

			$this->processNodes( $role['hierarchy'], null, false, $this->allowed['cm.xsd#cm_Network'], false, $calculationELRPIs, $elr, $presentationRollupPIs, $tables, $lineItems, $axes, $concepts, $formulaSummaries, $primaryItems, $currentPrimaryItem, null, null );

			if ( isset( $this->definitionNetworks[ $elr ] ) )
			{
				if ( $reportModelStructureRuleViolations && count( $tables ) != 1 )
				{
					XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'There MUST be one and only one table per network',
						array(
							'tables' => $tables ? implode( ', ', $tables ) : 'There is no table',
							'role' => $elr,
							'error' => 'error:MustBeOnlyOneTablePerNetwork'
						)
					);
				}

				if ( $reportModelStructureRuleViolations && count( $lineItems ) != 1 )
				{
					XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'There MUST be one and only one line items node per table',
						array(
							'lineitems' => $lineItems ? implode( ', ', $lineItems ) : 'There is no line item node',
							'role' => $elr,
							'error' => 'error:OneAndOnlyOneLineItems'
						)
					);
				}

				if ( $tables && ! $axes )
				{
					// BMS 2020-09-01 This is a partially complete presentation hierarchy. Probably a
					//				  regular taxonomy not a DFR so try to complete the table.
					$definitionPrimaryItems = $this->taxonomy->getDefinitionPrimaryItems();

					// There is a valid hypercube but no dimensions so create on from the definition
					foreach( $tables as $primaryLabel => $tableLabel )
					{
						if ( ! isset( $definitionPrimaryItems[ $primaryLabel ] ) ) continue;
						$drs = $this->taxonomy->getPrimaryItemDRS( $definitionPrimaryItems[ $primaryLabel ] );
						if ( ! isset( $drs[ $tableLabel ] ) )
						{
							unset( $tables[ $primaryLabel ] );
							continue;
						}

						// If necessary change the table label because the report generation
						// requires it to create an accumulated tables list see line 4711.
						if ( key( $role['hierarchy'] ) != $primaryLabel )
						{
							$tables[ key( $role['hierarchy'] ) ] = $tableLabel;
							unset( $tables[ $primaryLabel ] );
						}
						$hypercubes = $drs[ $tableLabel ];
						foreach( $hypercubes as $hypercubeRole => $hypercube )
						{
							foreach( $hypercube['dimensions'] as $dimensionLabel => $dimension )
							{
								// Don't fill the same axis twice
								if ( isset( $axes[ $tableLabel ][ $dimensionLabel ] ) ) continue;

								// Create an axis
								$dimTaxonomy = $this->taxonomy->getTaxonomyForXSD( $dimensionLabel );
								$element = $dimTaxonomy->getElementById( $dimensionLabel );

								$defaultMember = isset( $this->taxonomy->context->dimensionDefaults[ $dimensionLabel ]['label'] )
									? $this->taxonomy->context->dimensionDefaults[ $dimensionLabel ]['label']
									: false;
								$typedDomainRef = isset( $element['typedDomainRef'] )
									? $element['typedDomainRef']
									: false;

								$axis = array(
									'dimension' => new QName( $dimTaxonomy->getPrefix(), $dimTaxonomy->getNamespace(), $element['name'] ),
									'dimension-label' => $dimensionLabel,
									'default-member' => $defaultMember,
									'typedDomainRef' => $typedDomainRef,
									'root-member' => false,
									'domain-member' => false,
									'members' => array()
								);

								// Add all dimension members to the axis
								$addMembers = function( $dimensionMembers, $root ) use( &$addMembers, &$axis, &$dimensionLabel )
								{
									foreach( $dimensionMembers as $memberLabel => $member )
									{
										$memberTaxonomy = $this->taxonomy->getTaxonomyForXSD( $memberLabel );
										if ( ! $memberTaxonomy ) continue;

										if ( $root )
										{
											$axis['root-member'] = $memberLabel;
											$root = false;
										}

										if ( isset( $member['parents'][ $dimensionLabel ] ) )
										{
											$arcrole = $member['parents'][ $dimensionLabel ]['arcrole'];
											if ( $arcrole == XBRL_Constants::$arcRoleDimensionDomain )
											{
												$axis['domain-member'] = $memberLabel;
											}
											unset( $arcrole );
										}

										$element = $memberTaxonomy->getElementById( $memberLabel );

										$axis['members'][ $memberLabel ] = new QName( $memberTaxonomy->getPrefix(), $memberTaxonomy->getNamespace(), $element['name'] );

										if ( ! isset( $member['children'] ) ) continue;

										$addMembers( $member['children'], false );
									}
								};

								$addMembers( $dimension['members'], true );

								$axes[ $tableLabel ][ $dimensionLabel ] = $axis;

								unset( $axis );
								unset( $element );
								unset( $members );
								unset( $dimTaxonomy );
							}

							unset( $dimensionLabel );
							unset( $dimension );
						}

						unset( $drs );
						unset( $hypercube );
						unset( $hypercubeRole );
						unset( $hypercubes );
					}
					unset( $definitionPrimaryItems );
					unset( $primaryLabel );
					unset( $tableLabel );
				}
			}
			else if ( $tables )
			{
				// If there are tables defined in the presentation but no tables in the definition then drop the presentation
				unset( $this->presentationNetworks['$elr'] );
			}

			$role['axes'] = $axes;
			$role['tables'] = $tables;
			$role['lineitems'] = $lineItems;
			$role['concepts'] = $concepts;
		}

		unset( $role );

		// The set of line items used in calculation, definition and presentation linkbases should be the same
		// First check there are consistent networks
		if ( $reportModelStructureRuleViolations )
		{
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
		}

		return $this->presentationNetworks;
	}

	/**
	 * Look for a concept in each formula's filter
	 * @param array $formulaSummariesForELR (ref) Array of formulas defined for the ELR
	 * @param XBRL $taxonomy
	 * @param array $element
	 * @return boolean
	 */
	private function findConceptInFormula( &$formulaSummariesForELR, $taxonomy, $element )
	{
		if ( ! $formulaSummariesForELR ) return false;

		$conceptClark = "{" . $taxonomy->getNamespace() . "}" . $element['name'];
		return isset( $formulaSummariesForELR[ $conceptClark ] );
	}

	/**
	 * If there are class-equivalent arcs check all formula filters to see if they need to be updated
	 */
	public function fixupFormulas()
	{
		// Find the class-equivalentClass arc role(s)
		$taxonomies = $this->taxonomy->context->getTaxonomiesWithArcRoleTypeId('class-equivalentClass');
		$arcRoles = array_map( function( /** @var XBRL $taxonomy */ $taxonomy )
		{
			$arcRole = $taxonomy->getArcRoleTypeForId( 'class-equivalentClass' );
			if ( ! $arcRole ) return '';
			return str_replace( 'link:definitionArc/', '', $arcRole );
		}, $taxonomies  );

		if ( ! $arcRoles ) return;

		$nonDimensionalRoleRef = $this->taxonomy->getNonDimensionalRoleRefs();
		$classEquivalents = null;
		foreach ( $arcRoles as $arcRole )
		{
			if ( ! isset( $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ][ $arcRole ] ) ) continue;
			$classEquivalents = $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ][ $arcRole ];
			break;
		}

		if ( ! $classEquivalents ) return;

		// $count = array_reduce( $classEquivalents['arcs'], function( $acc, $arcs ) { return $acc + count( $arcs ); }, 0 );

		foreach ( $this->taxonomy->getImportedSchemas() as $label => $taxonomy )
		{
			if ( ! $taxonomy->getHasFormulas() ) continue;

			$resources = $taxonomy->getGenericResource('filter', 'conceptName' );
			if ( ! $resources ) continue;

			$baseTaxonomy = null; // $this->getBaseTaxonomy() ? $this->getTaxonomyForXSD( $this->getBaseTaxonomy() ) : null;

			foreach ( $classEquivalents['arcs'] as $fac => $gaaps )
			{
				$facTaxonomy = $this->taxonomy->getTaxonomyForXSD( $fac );
				if ( ! $facTaxonomy ) 
					continue;

				$facElement = $facTaxonomy->getElementById( $fac );
				if ( ! $facElement ) 
					continue;

				$facClark = "{{$facTaxonomy->getNamespace()}}{$facElement['name']}";

				foreach ( $resources as $resource )
				{
					$changed = false;

					foreach ( $resource['filter']['qnames'] as $qnIndex => $qname )
					{
						if ( $qname != $facClark )
							continue;

						foreach ( $gaaps as $gaapLabel => $gaap )
						{
							$gaapTaxonomy = $this->taxonomy->getTaxonomyForXSD( $gaapLabel );
							if ( ! $gaapTaxonomy )
								continue;

							$gaapElement = $gaapTaxonomy->getElementById( $gaapLabel );
							if ( ! $gaapElement )
								continue;

							// $gaapClark = "{{$gaapTaxonomy->getNamespace()}}{$gaapElement['name']}";
							$gaapClark = $baseTaxonomy
								? "{{$baseTaxonomy->getNamespace()}}{$gaapElement['name']}"
								: "{{$gaapTaxonomy->getNamespace()}}{$gaapElement['name']}";

							// echo "{$resource['linkbase']} - {$resource['resourceName']}: from $facClark to $gaapClark\n";

							//$taxonomy->genericRoles['roles']
							//		[ $resource['roleUri'] ]
							//		['resources']
							//		[ $resource['linkbase'] ]
							//		[ $resource['resourceName'] ]
							//		[ $resource['index'] ]['qnames'][ $qnIndex ] = $gaapClark;
							if ( $resource['filter']['qnames'][ $qnIndex ] == $facClark )
							{
								$resource['filter']['qnames'][ $qnIndex ] = $gaapClark;
							}
							else
							{
								$resource['filter']['qnames'][] = $gaapClark;
							}

							$classEquivalents['arcs'][ $fac ][ $gaapLabel ]['used'] = true;
							$changed = true;
						}
					}

					if ( $changed )
					{
						$taxonomy->updateGenericResource( $resource['roleUri'], $resource['linkbase'], $resource['resourceName'], $resource['index'], $resource['filter'] );
					}
				}
			}
		}

		// For debugging to check the numbers of arcs
		// $countUsed = array_reduce( $classEquivalents['arcs'], function( $acc, $arcs ) { return $acc + count( array_filter( $arcs, function( $arc ) { return $arc['used'] ?? false; } ) ); }, 0 );
		// $unused = \XBRL::array_reduce_key( $classEquivalents['arcs'], function( $acc, $arcs, $key )
		// {
		//	foreach( $arcs as $arc )
		//	{
		//		if ( $arc['used'] ?? false ) continue;
		//		$acc[ $key ][] = $arc['label'];
		//	}
		//	return $acc;
		// }, [] );

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

		$axis = array();
		foreach ( $axisName == XBRL_Constants::$dfrReportDateAxis ? $this->reportDateAxisAliases : array( $axisName ) as $axisName )
		{
			$axis = array_filter( $axes, function( $axis ) use( $axisName )
			{
				return isset( $axis['dimension'] ) && $axis['dimension']->localName == $axisName;
			} );

			if ( $axis ) break;
		}

		return $axis ? key( $axis ) : false;
	}

	/**
	 *Test whether the $elr contains a $label
	 * @param string $label The label to find
	 * @param string $elr The extended link role to look in
	 * @param string $parentLabel
	 * @param string $source What hypercube aspect to use (primaryitems, members, dimensions)
	 * @param string $recurse If true the hierarchy will be tested recursively
	 * @return mixed|boolean
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
					global $reportModelStructureRuleViolations;
					if ( $reportModelStructureRuleViolations )
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
	 * @param array		$formulaSummaries (ref)
	 * @return string
	 */
	private function processNodes( &$nodes, $parentLabel, $parentIsAbstract, $validNodeTypes, $underLineItems, &$calculationELRPIs, $elr, &$presentationRollupPIs, &$tables, &$lineItems, &$axes, &$concepts, &$formulaSummaries, &$primaryItems, &$currentPrimaryItem, $currentHypercubeLabel, $currentDimensionLabel )
	{
		$possiblePatternTypes = array();
		$patternType = ''; // Default pattern

		// Make sure the nodes are sorted by order
		uasort( $nodes, function( $nodea, $nodeb ) { return ( isset( $nodea['order'] ) ? $nodea['order'] : 0 ) - ( isset( $nodeb['order'] ) ? $nodeb['order'] : 0 ); } );

		// Create a list of labels that are not abstract
		$getNonAbstract = function( $nodes )
		{
			return array_filter( array_keys( $nodes ), function( $label )
			{
				$taxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
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

			$taxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
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
							$tables[ $parentLabel ] = $label;
							$currentHypercubeLabel = $label;
						}
						break;

					case 'cm.xsd#cm_Axis':
						$ok |= $taxonomy->context->types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) );
						if ( $ok )
						{
							$currentDimensionLabel = $label;
							$defaultMember = isset( $this->taxonomy->context->dimensionDefaults[ $label ]['label'] ) ? $this->taxonomy->context->dimensionDefaults[ $label ]['label'] : false;

							if ( ! $defaultMember && in_array( $element['name'], $this->reportDateAxisAliases ) )
							{
								XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'Report Date [Axis] Missing Dimension Default',
									array(
										'axis' => $label,
										'role' => $elr,
										'error' => 'error:ReportDateDimensionMissingDimensionDefault'
									)
								);

							}

							$typedDomainRef = isset( $element['typedDomainRef'] ) ? $element['typedDomainRef'] : false;

							$axes[ $parentLabel ][ $label ] = array(
								'dimension' => new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] ),
								'dimension-label' => $label,
								'default-member' => $defaultMember,
								'typedDomainRef' => $typedDomainRef,
								'members' => array()
							);

							$node['typedDomainRef'] = $typedDomainRef;
							$node['default-member'] = $defaultMember;
						}
						break;

					case 'cm.xsd#cm_Member':

						// Q Which test needs the condition: $element['type'] == 'nonnum:domainItemType'
						// A Hoffman test suite 3000 01-MemberAbstractAttribute
						if ( $currentHypercubeLabel && $currentDimensionLabel && $element['type'] == 'nonnum:domainItemType' )
						{
							if ( $currentPrimaryItem )
							{
								$drs = $this->taxonomy->getPrimaryItemDRSForRole( array( $elr => $currentPrimaryItem ), $elr );
								$ok = isset( $drs[ $currentHypercubeLabel ][ $elr ]['dimensions'][ $currentDimensionLabel ]['members'][ $label ] );
							}
							else
							{
								$ok = isset( $this->definitionNetworks[ $elr ]['members'][ $label ] );
							}

							if ( $ok )
							{
								$node['is-domain'] = false;
								$node['is-default'] = false;

								if ( ! isset( $axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['domain-member'] ) ) $axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['domain-member'] = false;
								if ( ! isset( $axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['root-member'] ) ) $axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['root-member'] = false;
								if ( isset( $axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['default-member'] ) && $axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['default-member'] == $label)
								{
									$node['is-default'] = true;
								}

								if ( $currentPrimaryItem )
								{
									$member = $drs[ $currentHypercubeLabel ][ $elr ]['dimensions'][ $currentDimensionLabel ]['members'][ $label ];

									if ( isset( $member['parents'][ $currentDimensionLabel ]['arcrole'] ) )
									{
										$arcrole = $member['parents'][ $currentDimensionLabel ]['arcrole'];
										if ( $arcrole == XBRL_Constants::$arcRoleDimensionDomain )
										{
											$axes[ $currentHypercubeLabel ][ $currentDimensionLabel ]['domain-member'] = $label;
											$node['is-domain'] = true;
										}
										unset( $arcrole );
									}
								}
								else
								{
									if ( isset( $this->definitionNetworks[ $elr ]['members'][ $label ]['parents'][ $parentLabel ]['arcrole'] ) )
									{
										$arcrole = $this->definitionNetworks[ $elr ]['members'][ $label ]['parents'][ $parentLabel ]['arcrole'];
										if ( $arcrole == XBRL_Constants::$arcRoleDimensionDomain )
										{
											$axes[ $currentHypercubeLabel ][ $parentLabel ]['domain-member'] = $label;
											$node['is-domain'] = true;
										}
										unset( $arcrole );
									}
								}

								// Note that $currentDimensionLabel cannot be used because the member parent might be another member not a dimension
								if ( isset( $axes[ $currentHypercubeLabel ][ $parentLabel ] ) && isset( $axes[ $currentHypercubeLabel ][ $parentLabel ]['dimension'] ) ) $axes[ $currentHypercubeLabel ][ $parentLabel ]['root-member'] = $label;
								$axes[ $currentHypercubeLabel ][ $parentLabel ]['members'][ $label ] = new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] );
							}
						}
						break;

					case 'cm.xsd#cm_LineItems':
						if ( $element['abstract'] )
						{
							// BMS 2019-05-14 This probably needs to change to use the $primaryItems collection
							$item = $this->hasHypercubeItem( $label, $elr, $parentLabel, 'primaryitems', true );
							if ( $item && ! isset( $item['parents'] ) ) // a line item is a root primary item node
							{
								$ok = true;
								$lineItems[ $parentLabel ] = $label;
								if ( isset( $primaryItems[ $label ] ) ) $currentPrimaryItem = $primaryItems[ $label ];
							}
							unset( $item);
						}
						break;

					case 'cm.xsd#cm_Concept':

						if ( ! $element['abstract'] && $element['type'] != 'nonnum:domainItemType' && $this->taxonomy->isPrimaryItem( $element ) )
						{
							$ok = true;
							$concepts[ $label ] = new QName( $taxonomy->getPrefix(), $taxonomy->getNamespace(), $element['name'] );
							if ( isset( $primaryItems[ $label ] ) ) $currentPrimaryItem = $primaryItems[ $label ];

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
							// (a) the first concept is an instant and uses non-default preferred label
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
											if ( in_array( 'rollforward', $possiblePatternTypes ) && ( isset( $calculationELRPIs[ $label ] ) || $this->findConceptInFormula( $formulaSummaries[ $elr ], $taxonomy, $element ) || $this->findConceptInFormula( $formulaSummaries[ \XBRL_Constants::$defaultLinkRole ], $taxonomy, $element ) ) )
											{
												$patternType = "rollforward";
												$possiblePatternTypes = array();
												break;
											}
										}

										if ( in_array( 'rollforwardinfo', $possiblePatternTypes ) )
										{
											$patternType = "rollforwardinfo";
											// $patternType = "rollforward";
											$possiblePatternTypes = array();
											break;
										}
									}

									if ( isset( $node['preferredLabel'] ) ) // && $node['preferredLabel'] == XBRL_Constants::$labelRoleRestatedLabel )
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

									if ( in_array( 'complex', $possiblePatternTypes ) || $this->findConceptInFormula( $formulaSummaries[ $elr ], $taxonomy, $element ) || $this->findConceptInFormula( $formulaSummaries[ \XBRL_Constants::$defaultLinkRole ], $taxonomy, $element ) )
									{
										$patternType = "complex";
										$possiblePatternTypes = array();
										break;
									}

									if ( $element['type'] == 'nonnum:textBlockItemType' && in_array( 'text', $possiblePatternTypes ) )
									{
										$patternType = 'text';
										$possiblePatternTypes = array();
										break;
									}
								}

								if ( ! in_array( 'complex', $possiblePatternTypes ) && ( $this->findConceptInFormula( $formulaSummaries[ $elr ], $taxonomy, $element ) || $this->findConceptInFormula( $formulaSummaries[ \XBRL_Constants::$defaultLinkRole ], $taxonomy, $element ) ) )
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
										if ( $element['periodType'] == 'instant' && ( isset( $calculationELRPIs[ $label ] ) || $this->findConceptInFormula( $formulaSummaries[ $elr ], $taxonomy, $element ) || $this->findConceptInFormula( $formulaSummaries[ \XBRL_Constants::$defaultLinkRole ], $taxonomy, $element ) ) )
										{
											$possiblePatternTypes[] = 'rollforward';
										}
									}

									// Adjustment
									if ( isset( $node['preferredLabel'] ) ) // && ( $node['preferredLabel'] == XBRL_DFR::$originallyStatedLabel || $node['preferredLabel'] == XBRL_Constants::$labelRoleVerboseLabel ) )
									{
										// MUST be an instant period type and have a report date axis
										if ( $element['periodType'] == 'instant' && isset( $axes[ $currentHypercubeLabel ] ) && $this->hasAxis( 'ReportDateAxis', $axes[ $currentHypercubeLabel ] ) )
										{
											$possiblePatternTypes[] = 'adjustment';
										}
									}

									// Text
									if ( $element['type'] == 'nonnum:textBlockItemType' )
									{
										if ( $last ) // Could be the first and last (that is, the only) element
										{
											$patternType = 'text';
										}
										else
										{
											$possiblePatternTypes[] = 'text';
										}
									}
								}

								// Complex
								if ( $this->findConceptInFormula( $formulaSummaries[ $elr ], $taxonomy, $element ) || $this->findConceptInFormula( $formulaSummaries[ \XBRL_Constants::$defaultLinkRole ], $taxonomy, $element ) )
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
						'expected' => $validNodeTypes ? implode(', ', array_keys( $validNodeTypes ) ) : 'There are no allowed node types for the parent node',
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
			$result = $this->processNodes( $node['children'], $label, $isAbstract, $this->allowed[ $child ], $underLineItems, $calculationELRPIs, $elr, $presentationRollupPIs, $tables, $lineItems, $axes, $concepts, $formulaSummaries, $primaryItems, $currentPrimaryItem, $currentHypercubeLabel, $currentDimensionLabel );
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
				$hypercubeLabel = $tables ? reset( $tables ) : false;

				// See if there is a report scenario axis
				$varianceAxis = $hypercubeLabel && isset( $axes[ $hypercubeLabel ] )
					? $this->hasAxis( 'ReportingScenarioAxis', $axes[ $hypercubeLabel ] )
					: false;

				if ( $varianceAxis )
				{
					// Note: these tests could be combined into one composite
					// test but broken out its easier to see what's going on

					// BMS 2019-03-23 Need to check that there is one parent with two members otherwise its a grid

					// There must be more than one member
					$members = $axes[ $hypercubeLabel ][ $varianceAxis ]['members'];
					if ( count( $members ) > 1 )
					{
						$node['variance'] = $varianceAxis;
					}
					else if ( $members )
					{
						// Check to see if there are nested members.  Only one additional member is required
						if ( isset( $axes[ $hypercubeLabel ][ key( $members ) ] ) && count( $axes[ $hypercubeLabel ][ key( $members ) ]['members'] ) )
						{
							$node['variance'] = $varianceAxis;
						}
					}

				}
				// If not a variance then maybe a grid?
				if ( $hypercubeLabel && ! $node['variance'] )
				{
					$otherAxes = isset( $axes[ $hypercubeLabel ] ) ? array_filter( $axes[ $hypercubeLabel ], function( $axis )
					{
						return isset( $axis['dimension'] ) && ( ! in_array( $axis['dimension']->localName, $this->axesToExclude ) );
					} ) : array();

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
				$isDescendent = function( $label, $child ) use( &$isDescendent, $elr )
				{
					if ( ! isset( $this->calculationNetworks[ $elr ]['calculations'][ $label ] ) ) return false;

					$children = $this->calculationNetworks[ $elr ]['calculations'][ $label ];
					if ( isset( $children[ $child ] ) ) return true;

					foreach ( $children as $subChild )
					{
						if ( $isDescendent( $subChild['label'], $child ) ) return true;
					}

					return false;
				};

				$error = false;
				$totals = array_intersect_key( $this->calculationNetworks[ $elr ]['calculations'], $node['children'] );
				$error = count( $totals ) > 1;
				if ( $error )
				{
					// Check to see if one is the parents of all the others
					foreach ( $totals as $calcLabel => $calcChildren )
					{
						// Get a list of the other calculations totals so they can be tested
						$rest = array_filter( array_keys( $totals ), function( $label ) use( $calcLabel ) { return $label != $calcLabel; } );
						$found = true; // Assume success
						// Check each of the other totals to see if the primary is the parent of all the others
						foreach ( $rest as $other )
						{
							// If the other is a descendent of the primary check the other
							if ( $isDescendent( $calcLabel, $other ) ) continue;
							$found = false;
							break;
						}

						// When $found is true it means all the other are a child of the primary
						if ( $found )
						{
							$error = false;
							$totals = array( $calcLabel => $totals[ $calcLabel ] );
							break;
						}
					}
				}

				if ( ! $error )
				{
					$nonAbstractNodes = $getNonAbstract( $node['children'] );

					if ( $totals )
					{
						// Its an error if all the node members are not described by this relation
						$total = key( $totals );
						foreach ( $nonAbstractNodes as $nonAbstractNode )
						{
							// BMS 2020-10-08 Make a label because $nonAbstractNode may include the preferred label suffix
							$taxonomy = $this->taxonomy->getTaxonomyForXSD( $nonAbstractNode );
							$element = $taxonomy->getElementById( $nonAbstractNode );
							$nonAbstractNode = "{$taxonomy->getTaxonomyXSD()}#{$element['id']}";
							if ( $nonAbstractNode == $total ) continue;
							if ( $isDescendent( $total, $nonAbstractNode ) ) continue;
							$error = true;
							break;
						}
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
					global $reportModelStructureRuleViolations;
					if ( $reportModelStructureRuleViolations )
					XBRL_Log::getInstance()->business_rules_validation('Model Structure Rules', 'A rollup MUST contain components from only one calculation relationship set',
						array(
							'rollup' => $label,
							'role' => $elr,
							'error' => 'error:BlocksRunTogether'
						)
					);
				}

				// Filter any non-PI nodes.  This occurs in the pathalogical test case when a dimension member is a rollup.
				// BMS 2020-10-08 Changed to a reduce so the label can be converted back to one that does not include a preferred label suffix
				$pis = array_reduce( array_keys( $node['children'] ), function( $carry, $label ) use( &$node )
				{
					$taxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
					$element = $taxonomy->getElementById( $label );
					if ( ! $element['abstract'] && $this->taxonomy->isPrimaryItem( $element ) )
					{
						$carry[] = "{$taxonomy->getTaxonomyXSD()}#{$element['id']}";
					}
					return $carry;
				}, array() );

				// Capture the elements in node['children']
				$presentationRollupPIs[ $elr ] = isset( $presentationRollupPIs[ $elr ] )
					? array_merge( $presentationRollupPIs[ $elr ], $pis )
					: $pis;
			} // $result == rollup

		} // $nodes

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
	 * @param string $lang (optional)
	 * @return string
	 */
	private function renderComponentTable( $network, $elr, $lang = null )
	{
		if ( ! $this->includeComponent ) return '';

		$table = $this->taxonomy->getTaxonomyDescriptionForIdWithDefaults( reset( $network['tables'] ), null, $lang, $elr );
		if ( ! $table )
		{
			$table = "(Implied table)";
		}

		$hideSection = $this->showAllGrids ? '' : 'hide-section';

		$componentTable =
			"	<div class='component-table '>" .
			"		<div class='ct-header'>" . $this->getConstantTextTranslation( $lang, 'Component: Network plus Table' ) . "</div>" .
			"		<div class='ct-body'>" .
			"			<div class='ct-body-header network'>" . $this->getConstantTextTranslation( $lang, 'Network' ) . "</div>" .
			"			<div class='ct-body-content network'>" .
			"				<div>{$network['text']}</div>" .
			"				<div>$elr</div>" .
			"			</div>" .
			"			<div class='ct-body-header hypercube'>" . $this->getConstantTextTranslation( $lang, 'Table' ) . "</div>" .
			"			<div class='ct-body-content hypercube'>$table</div>" .
			"		</div>" .
			"	</div>";

		return $componentTable;
	}

	/**
	 * Create an array of the fact data with columns for any years and dimensions
	 * @param array $network			An array generated by the validsateDLR process
	 * @param string $elr				The extended link role URI
	 * @param XBRL_Instance $instance	The instance being reported
	 * @param QName $entityQName
	 * @param array $factsLayout		A table produced by the report table method
	 * @param string|null $lang			(optional: default = null) The language to use or null for the default
	 * @return string
	 */
	private function createFactsData( $network, $elr, $instance, $entityQName, &$reportsFactsLayout, $lang = null )
	{
		$result = array();

		if ( ! $reportsFactsLayout ) return $result;

		$axes = array_reduce( $network['axes'], function( $carry, $axes )
		{
			$carry = array_merge( $carry, $axes );
			return $carry;
		}, array() );

		$axes = array_filter( $axes, function( $axis ) { return isset( $axis['dimension'] ); } );

		$taxonomy = $instance->getInstanceTaxonomy();
		$conceptLevels = array();
		$conceptParents = array();
		$conceptOrders = array();

		foreach( $network['lineitems'] as $lineItemLabel )
		{
			$conceptLevels[ $lineItemLabel ] = 0;
			$lineItemId = ltrim( strstr( $lineItemLabel, "#" ), "#" );

			foreach( $network['paths'][ $lineItemId ] as $lineItemPath )
			{
				$lineItemParent = preg_replace( "!" . $lineItemLabel . "$!", "", $lineItemPath );

				foreach( $network['concepts'] as $conceptLabel => $conceptQName )
				{
					$conceptId = ltrim( strstr( $conceptLabel, "#" ), "#" );
					foreach( $network['paths'][ $conceptId ] as $conceptPath )
					{
						// BMS 2019-12-16  Not sure if its the right thing to do to ignore an empty $lineItemParent
						// 				   This happens in preg_replace above when $lineItemLabel and $lineItemPath are the same
						if ( ! $lineItemParent || strpos( $conceptPath, $lineItemParent ) === false ) continue;

						$label = $conceptLabel;
						$node = $taxonomy->processNode( $network['hierarchy'], $conceptPath, function( $node ) use( &$conceptOrders, &$label )
						{
							if ( ! $node || ! isset( $node['order'] ) ) return;
							$conceptOrders[ $node['label'] ] = $node['order'];
							if ( ! isset( $node['preferredLabel'] ) ) return;
							$basename = basename($node['preferredLabel']);
							// If the concept label already ends with the basename there is nothing to do
							if ( \XBRL::endsWith( $label, $basename ) ) return;
							$label = $label . $basename;
						} );

						$conceptPath = trim( preg_replace( "!" . $label . "$!", "", str_replace( $lineItemParent, "", $conceptPath ) ), "/" );

						$parentLabels = explode( '/', $conceptPath );
						$conceptParents[ $label ] = $parentLabels;
						if ( $label != $conceptLabel )
						{
							$conceptParents[ $conceptLabel ] = $parentLabels;
						}

						$path = rtrim( $lineItemParent, "/" );
						foreach( $parentLabels as $parentIndex => $parentLabel )
						{
							$path .= "/$parentLabel";
							if ( ! isset( $conceptOrders[ $parentLabel ] ) )
							{
								$node = $taxonomy->processNode( $network['hierarchy'], $path, function( $node ) use( &$conceptOrders )
								{
									if ( ! $node || ! isset( $node['order'] ) ) return;
									$conceptOrders[ $node['label'] ] = $node['order'];
								} );
							}

							if ( ! isset( $conceptLevels[ $parentLabel ] ) )
							{
								$conceptLevels[ $parentLabel ] = $parentIndex;
							}
						}

						$conceptLevels[ $label ] = 100;
						if ( $label != $conceptLabel )
						{
							$conceptLevels[ $conceptLabel ] = 100;
						}

						unset( $label );
					}
				}
			}
		}

		foreach( $conceptParents as $conceptLabel => $parentLabels )
		{
			$x = array();

			foreach ( $parentLabels as $parentLevel => $parentLabel )
			{
				if ( ! isset( $conceptParents[ $parentLabel ] ) )
				{
					$conceptParents[ $parentLabel ] = $x;
				}
				$x[] = $parentLabel;
			}
		}

		unset( $conceptId );
		unset( $conceptLabel );
		unset( $parentIndex );
		unset( $conceptPath );
		unset( $conceptQName );
		unset( $lineItemLabel );
		unset( $lineItemParent );
		unset( $lineItemPath );
		unset( $lineItemId );
		unset( $parentLabel );
		unset( $parentLabels );
		unset( $parentLevel );

		foreach ( $reportsFactsLayout as $reportLabel => $factsLayout )
		{
			$result[ $reportLabel ] = array();
			$result[ $reportLabel ]['title'] = $network['text'];

			$columns['context'] = $this->getConstantTextTranslation( $lang, 'Context' );

			foreach ( $factsLayout['axes'] as $axisLabel )
			{
				$axis = $axes[ $axisLabel ];

				$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
				$dimElement = $dimTaxonomy->getElementById( $axisLabel );
				$dimPrefix = $instance->getPrefixForNamespace( $dimTaxonomy->getNamespace() );
				$dimQName = "$dimPrefix:{$dimElement['name']}";

				$text = $dimTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $axisLabel, null, $lang, $elr );
				$columns[ $dimQName ] = $text;
			}

			$columns['period'] = $this->getConstantTextTranslation( $lang, 'Period [Axis]' );
			$columns['concept'] = $this->getConstantTextTranslation( $lang, 'Concept' );
			$columns['unit'] = $this->getConstantTextTranslation( $lang, 'Unit' );
			$columns['rounding'] = $this->getConstantTextTranslation( $lang, 'Rounding' );
			$columns['value'] = $this->getConstantTextTranslation( $lang, 'Value' );
			$columns['parent'] = $this->getConstantTextTranslation( $lang, 'Parent concept' );
			$columns['pattern'] = $this->getConstantTextTranslation( $lang, 'Fact Set Type' );
			$columns['order'] = $this->getConstantTextTranslation( $lang, 'Order' );

			$columnIndices = array_flip( array_keys( $columns ) );

			$data = array();
			$concepts = array();
			// $conceptTexts = array();
			$contexts = array();
			$periods = array();
			$units = array();
			$patterns = array();
			$parents = array();
			$members = array();
			$memberTexts = array();
			$conceptsInfo = array();

			foreach ( $factsLayout['data'] as $conceptLabel => $row )
			{
				/** @var XBRL $conceptTaxonomy */
				$conceptTaxonomy = $row['taxonomy'];
				$preferredLabel = isset( $row['node']['preferredLabel'] ) ? $row['node']['preferredLabel'] : null;
				$conceptText = $conceptTaxonomy->getTaxonomyDescriptionForIdWithDefaults( '#' . $row['element']['id'], $preferredLabel, $lang, $elr );
				$conceptQName = "{$conceptTaxonomy->getPrefix()}:{$row['element']['name']}";
				// if ( ! isset( $concepts[ $conceptQName ] ) )
				if ( ! isset( $concepts[ $conceptLabel ] ) )
				{
					// $conceptTexts[] = $conceptText;
					// $concepts[ $conceptQName ] = count( $conceptTexts ) - 1;
					$conceptsInfo[ $conceptLabel ] = array( 'qn' => $conceptQName, 't' => $conceptText );
					$concepts[ $conceptLabel ] = count( $conceptsInfo ) - 1;
					if ( $preferredLabel ) $conceptsInfo[ $conceptLabel ]['pl'] = $preferredLabel;
				}

				foreach ( $row['columns'] as $columnIndex => $fact )
				{
					$data[ $columnIndices['concept'] ] = $concepts[ $conceptLabel ];
					$data[ $columnIndices['order'] ] = $row['order'];

					if ( ! in_array( $row['parentQName'], $parents ) )
					{
						$parents[] = $row['parentQName'];
					}
					$data[ $columnIndices['parent'] ] = array_search( $row['parentQName'], $parents );

					if ( ! in_array( $row['pattern'], $patterns ) )
					{
						$patterns[] = $row['pattern'];
					}
					$data[ $columnIndices['pattern'] ] = array_search( $row['pattern'], $patterns );

					$context = $instance->getContext( $fact['contextRef'] );
					if ( ! in_array( $fact['contextRef'], $contexts ) )
					{
						$contexts[] = $fact['contextRef'];
					}
					$data[ $columnIndices['context'] ] = array_search( $fact['contextRef'], $contexts );

					$period = $context['period']['is_instant']
						? $context['period']['endDate']
						: "{$context['period']['startDate']} - {$context['period']['endDate']}";
					if ( ! in_array( $period, $periods ) )
					{
						$periods[] = $period;
					}
					$data[ $columnIndices['period'] ] = array_search( $period, $periods );

					$dimensions = $instance->getContextDimensions( $context );

					foreach ( $factsLayout['axes'] as $axisLabel )
					{
						$axis = $axes[ $axisLabel ];

						$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
						$dimElement = $dimTaxonomy->getElementById( $axisLabel );
						$dimPrefix = $instance->getPrefixForNamespace( $dimTaxonomy->getNamespace() );
						$dimQName = "$dimPrefix:{$dimElement['name']}";

						if ( isset( $axis['typedDomainRef'] ) && $axis['typedDomainRef'] && isset( $dimensions[ $dimQName ] ) )
						{
							$label = $axis['typedDomainRef'];
							$memTaxonomy = $dimTaxonomy;
							if ( $label[0] != '#' )
							{
								$memTaxonomy = $dimTaxonomy->getTaxonomyForXSD( $label );
							}

							$memElement = $memTaxonomy->getElementById( $label );
							$memPrefix = $instance->getPrefixForNamespace( $memTaxonomy->getNamespace() );
							$memQName = "$memPrefix:{$memElement['name']}";
							$memberText = preg_replace( array( "!<$memQName>!", "!</$memQName>!" ), "", reset( $dimensions[ $dimQName ][ $memQName ] ) );
						}
						else
						{
							if ( isset( $dimensions[ $dimQName ] ) )
							{
								$memberQName = qname( $dimensions[ $dimQName ], $instance->getInstanceNamespaces() );
								$memTaxonomy = $dimTaxonomy->getTaxonomyForQName( $dimensions[ $dimQName ] );
								$memElement = $memTaxonomy->getElementByName( $memberQName->localName );
								$memberLabel = $memTaxonomy->getTaxonomyXSD() . "#" . $memElement['id'];
								$memQName = "{$memTaxonomy->getPrefix()}:{$memElement['name']}";
							}
							else
							{
								$memberLabel = $axis['default-member'];
								$memTaxonomy = $dimTaxonomy->getTaxonomyForXSD( $memberLabel );
								$memElement = $memTaxonomy->getElementById( $memberLabel );
								$memPrefix = $instance->getPrefixForNamespace( $memTaxonomy->getNamespace() );
								$memQName = "$memPrefix:{$memElement['name']}";
							}

							$memberText = $memTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberLabel, null, $lang, $elr );
						}

						if ( ! isset( $members[ $memQName ] ) )
						{
							$memberTexts[] = $memberText;
							$members[ $memQName ] = count( $memberTexts ) - 1;
						}

						$data[ $columnIndices[ $dimQName ] ] = $members[ $memQName ];
					}

					if ( ! in_array( $fact['unitRef'], $units ) )
					{
						$units[] = $fact['unitRef'];
					}

					$data[ $columnIndices['unit'] ] = array_search( $fact['unitRef'], $units );
					$data[ $columnIndices['value'] ] = $fact['value'];
					$data[ $columnIndices['rounding'] ] = isset( $fact['decimals'] ) ? $fact['decimals'] : "";

					$result[ $reportLabel ]['data'][] = $data;
				}
			}

			$result[ $reportLabel ]['columns'] = $columns;
			$result[ $reportLabel ]['concepts'] = $concepts;
			$result[ $reportLabel ]['conceptLevels'] = $conceptLevels;
			$result[ $reportLabel ]['conceptParents'] = $conceptParents;
			$result[ $reportLabel ]['conceptsInfo'] = $conceptsInfo;
			$result[ $reportLabel ]['contexts'] = $contexts;
			$result[ $reportLabel ]['members'] = $members;
			$result[ $reportLabel ]['memberTexts'] = $memberTexts;
			$result[ $reportLabel ]['periods'] = $periods;
			$result[ $reportLabel ]['units'] = $units;
			$result[ $reportLabel ]['patterns'] = $patterns;
			$result[ $reportLabel ]['parents'] = $parents;
			$result[ $reportLabel ]['order'] = $conceptOrders;
		}

		return $result;
	}

	/**
	 * Render a report with columns for any years and dimensions
	 * @param array $network			An array generated by the validsateDLR process
	 * @param string $elr				The extended link role URI
	 * @param XBRL_Instance $instance	The instance being reported
	 * @param QName $entityQName
	 * @param array $factsLayout		A table produced by the report table method
	 * @param string|null $lang			(optional: default = null) The language to use or null for the default
	 * @return string
	 */
	private function renderFactsTable( $network, $elr, $instance, $entityQName, &$reportsFactsLayout, $lang = null )
	{
		if ( ! $this->includeFactsTable ) return '';

		$axes = array_reduce( $network['axes'], function( $carry, $axes )
		{
			$carry = array_merge( $carry, $axes );
			return $carry;
		}, array() );

		$axes = array_filter( $axes, function( $axis ) { return isset( $axis['dimension'] ); } );

		$hideSection = $this->showAllGrids ? '' : 'hide-section';

		$factsTable =
			"	<div class='facts-section $hideSection'>";

		foreach ( $reportsFactsLayout as $reportLabel => $factsLayout )
		{
			$columnCount = 6 + count( $factsLayout['axes'] );

			if ( is_numeric( $reportLabel ) )
			{
				$reportTitle = 'main report facts';
			}
			else
			{
				$reportTaxonomy = $this->taxonomy->getTaxonomyForXSD( $reportLabel );
				$reportTitle = "sub-report '" . $reportTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $reportLabel, null, $lang, $elr ) . "'";
			}

			$repeatCount = $columnCount - 1;
			$factsTable .=
				"		<div class='fact-section-title'>" . $this->getConstantTextTranslation( $lang, 'Fact table for' ) . "$reportTitle</div>" .
				"		<div style='display: grid; grid-template-columns: auto 1fr;'>" .
				"			<div class='fact-table' style='display: grid; grid-template-columns: minmax(auto,auto) repeat($repeatCount, auto);'>" .
				"				<div class='fact-table-header'>" . $this->getConstantTextTranslation( $lang, 'Context' ) . "</div>" .
				"				<div class='fact-table-header'>" . $this->getConstantTextTranslation( $lang, 'Period [Axis]' ) . "</div>";

			foreach ( $factsLayout['axes'] as $axisLabel )
			{
				$axis = $axes[ $axisLabel ];

				$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
				$text = $dimTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $axisLabel, null, $lang, $elr );
				$factsTable .=
					"			<div class='fact-table-header'>$text</div>";
			}

			$factsTable .=
				"				<div class='fact-table-header'>" . $this->getConstantTextTranslation( $lang, 'Concept' ) . "</div>" .
				"				<div class='fact-table-header'>" . $this->getConstantTextTranslation( $lang, 'Value' ) . "</div>" .
				"				<div class='fact-table-header'>" . $this->getConstantTextTranslation( $lang, 'Unit' ) . "</div>" .
				"				<div class='fact-table-header last'>" . $this->getConstantTextTranslation( $lang, 'Rounding' ) . "</div>";

			foreach ( $factsLayout['data'] as $conceptLabel => $row )
			{
				/** @var XBRL $conceptTaxonomy */
				$conceptTaxonomy = $row['taxonomy'];
				$conceptText = $conceptTaxonomy->getTaxonomyDescriptionForIdWithDefaults( '#' . $row['element']['id'], null, $lang, $elr );

				foreach ( $row['columns'] as $columnIndex => $fact )
				{
					$context = $instance->getContext( $fact['contextRef'] );
					$period = $context['period']['is_instant']
						? $context['period']['endDate']
						: "{$context['period']['startDate']} - {$context['period']['endDate']}";

					$dimensions = $instance->getContextDimensions( $context );

					$type = (string) XBRL_Instance::getElementType( $fact );
					$valueClass = empty( $type ) ? '' : $conceptTaxonomy->valueAlignment( $type, $instance );

					$factsTable .=
						"				<div class='fact-table-line'><span class='contextRef'>{$fact['contextRef']}</span></div>" .
						"				<div class='fact-table-line'>$period</div>";

					foreach ( $factsLayout['axes'] as $axisLabel )
					{
						$axis = $axes[ $axisLabel ];

						$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
						$dimElement = $dimTaxonomy->getElementById( $axisLabel );
						$dimPrefix = $instance->getPrefixForNamespace( $dimTaxonomy->getNamespace() );
						$dimQName = "$dimPrefix:{$dimElement['name']}";

						if ( isset( $axis['typedDomainRef'] ) && $axis['typedDomainRef'] && isset( $dimensions[ $dimQName ] ) )
						{
							$label = $axis['typedDomainRef'];
							$memTaxonomy = $dimTaxonomy;
							if ( $label[0] != '#' )
							{
								$memTaxonomy = $dimTaxonomy->getTaxonomyForXSD( $label );
							}

							$memElement = $memTaxonomy->getElementById( $label );
							$memPrefix = $instance->getPrefixForNamespace( $memTaxonomy->getNamespace() );
							$memQName = "$memPrefix:{$memElement['name']}";
							$memberText = preg_replace( array( "!<$memQName>!", "!</$memQName>!" ), "", reset( $dimensions[ $dimQName ][ $memQName ] ) );
						}
						else
						{
							if ( isset( $dimensions[ $dimQName ] ) )
							{
								{
									$memQName = qname( $dimensions[ $dimQName ], $instance->getInstanceNamespaces() );
									$memTaxonomy = $dimTaxonomy->getTaxonomyForQName( $dimensions[ $dimQName ] );
									$memElement = $memTaxonomy->getElementByName( $memQName->localName );
									$memberLabel = $memTaxonomy->getTaxonomyXSD() . "#" . $memElement['id'];
								}
							}
							else
							{
								$memberLabel = $axis['default-member'];
								$memTaxonomy = $dimTaxonomy->getTaxonomyForXSD( $memberLabel );
							}

							$memberText = $memTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberLabel, null, $lang, $elr );
						}
						$factsTable .=
							"				<div class='fact-table-line'>$memberText</div>";
					}

					$factsTable .=
						"				<div class='fact-table-line'>$conceptText</div>" .
						"				<div class='fact-table-line $valueClass'>{$fact['value']}</div>" .
						"				<div class='fact-table-line'>{$fact['unitRef']}</div>" ;

					$factsTable .= isset( $fact['decimals'] )
						? "				<div class='fact-table-line last'>{$fact['decimals']}</div>"
						: "				<div class='fact-table-line last'></div>";

				}
			}

			$factsTable .=
				"			</div>" .
				"			<div></div>" .
				"		</div>";

		}

		$factsTable .=
			"	</div>";

		return $factsTable;

	}

	/**
	 * Renders the slicers table
	 * @param array $network
	 * @param XBRL_Instance $instance
	 * @param QName $entityQName
	 * @param array $axes (def: null) A list of axes
	 * @param string $class (def: 'slicers-table') The class name to apply to the table
	 * @param ContextsFilter $contextsFilter (def: null) A collection of contexts used to determine the axis members to select
	 * @return string
	 */
	private function renderSlicers( $network, $instance, $entityQName, $elr, $axes = null, $class = null, $contextsFilter = null, $lang = null )
	{
		if ( ! $this->includeSlicers ) return '';

		if ( is_null( $class ) ) $class = 'slicers-table';

		$slicers =
			"	<div class='$class'>" .
			"		<div class='slicers'>" .
			"			<div class='slicer-header'>" . $this->getConstantTextTranslation( $lang, 'Reporting Entity [Axis]' ) . "</div>" .
			"			<div class='slicer-content'>{$entityQName->localName} ({$entityQName->namespaceURI})</div>";

		$hasMultipleMembers = function( &$axes, &$axis)
		{
			if ( count( $axis['members'] ) > 1 ) return true;
			$memberLabel = key($axis['members'] );
			return isset( $axes[ $memberLabel ] );
		};

		if ( is_null( $axes ) )
		{
			$hypercubeLabel = key( $network['axes'] );
			$axes = $hypercubeLabel ? $network['axes'][ $hypercubeLabel ] : array();
		}

		foreach ( $axes as $axisLabel => $axis)
		{
			if ( ! isset( $axis['dimension'] ) ) continue;

			if ( in_array( $axis['dimension']->localName, $this->reportDateAxisAliases ) ) continue;

			// More than one member?
			if ( $hasMultipleMembers( $axes, $axis ) )
			{
				// If there is a contexts filter, use it to determine the correct member to use
				if ( ! $contextsFilter )
				{
					$contextsFilter = $instance->getContexts();
				}

				if ( $contextsFilter->NoSegmentContexts()->count() )
				{
					// Use the default member
					$memberLabel = $axis['default-member'];
				}
				else
				{
					// Use the member associated with one of the contexts
					$axisContexts = $contextsFilter->SegmentContexts( strstr( $axisLabel, '#' ) )->getContexts();
					if ( count( $axisContexts ) )
					{
						$axisContext = reset( $axisContexts );
						$segment = isset( $axisContext['entity']['segment'] )
							? $axisContext['entity']['segment']
							: ( isset( $axisContext['entity']['scenario'] )
								? $axisContext['entity']['scenario']
								: (isset( $axisContext['segment'] )
									? $axisContext['segment']
									: ( isset( $axisContext['scenario'] )
										? isset( $axisContext['scenario'] )
										: null
									)
								)
							);

						if ( ! $segment ) continue;

						$member = ( isset( $segment['explicitMember'] ) ? $segment['explicitMember'] : $segment['typedMember'] )[0];
						$memberQName = qname( $member['member'], $instance->getInstanceNamespaces() );

						if ( ! $memberQName )
						{
							error_log( "Unable to craete QName for member '$member'" );
							continue;
						}

						$memberTaxonomy = $this->taxonomy->getTaxonomyForNamespace( $memberQName instanceof QName ? $memberQName->namespaceURI : $memberQName['namespaceURI'] );
						$memberElement = $memberTaxonomy->getElementByName( $memberQName instanceof QName ? $memberQName->localName : $memberQName['localName'] );

						$memberLabel = $memberTaxonomy->getTaxonomyXSD() . "#" . $memberElement['id'];
					}
					else
					{
						$memberLabel = $axis['default-member'];
					}
				}
			}
			else if ( ! ( isset( $axis['typedDomainRef'] ) && $axis['typedDomainRef'] ) )
			{
				/** @var QName $memberQName */
				$memberQName = reset( $axis['members'] );

				if ( ! $memberQName )
				{
					if ( ! $axis['default-member'] ) continue;
					$memberLabel = $axis['default-member'];
				}
				else
				{
					$memberTaxonomy = $this->taxonomy->getTaxonomyForNamespace( $memberQName instanceof QName ? $memberQName->namespaceURI : $memberQName['namespaceURI'] );
					$memberElement = $memberTaxonomy->getElementByName( $memberQName instanceof QName ? $memberQName->localName : $memberQName['localName'] );

					$memberLabel = $memberTaxonomy->getTaxonomyXSD() . "#" . $memberElement['id'];
				}
			}

			$dimensionText = $this->taxonomy->getTaxonomyDescriptionForIdWithDefaults( $axisLabel, null, $lang, $elr );
			$slicers .= "			<div class='slicer-header'>$dimensionText</div>";

			if ( isset( $axis['typedDomainRef'] ) && $axis['typedDomainRef'] )
			{
				$contexts = $contextsFilter->SegmentContexts( strstr( $axisLabel, '#' ) )->getContexts();
				$memberText = $axis['typedDomainRef'];
			}
			else
			{
				$memberText = $this->taxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberLabel, null, $lang, $elr );
			}

			$slicers .= "			<div class='slicer-content'>$memberText</div>";
		}

		if ( $class != 'slicers-table' ) // Never include periods in the slicer for a report because it will be in the single member axes list if relevant
		{
			// Add the period dimension
			$slicers .=
			"			<div class='slicer-header'>" . $this->getConstantTextTranslation( $lang, 'Period [Axis]' ) . "</div>" .
			"			<div class='slicer-content'>{$contextsFilter->getPeriodLabel()}</div>";
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
	private function renderModelStructure( $network, $elr, $lang = null )
	{
		if ( ! $this->includeStructure ) return '';

		$hideSection = $this->showAllGrids ? '' : 'hide-section';

		$structureTable =
			"	<div class='structure-table $hideSection'>" .
			"		<div>" . $this->getConstantTextTranslation( $lang, 'Label' ) . "</div>" .
			"		<div>" . $this->getConstantTextTranslation( $lang, 'Fact set type' ) . "</div>" .
			"		<div>" . $this->getConstantTextTranslation( $lang, 'Report Element Class' ) . "</div>" .
			"		<div>" . $this->getConstantTextTranslation( $lang, 'Period Type' ) . "</div>" .
			"		<div>" . $this->getConstantTextTranslation( $lang, 'Balance' ) . "</div>" .
			"		<div>" . $this->getConstantTextTranslation( $lang, 'Name' ) . "</div>";

			$renderStructure = function( $nodes ) use( &$renderStructure, $elr, $lang )
			{
				$result = array();

				foreach( $nodes as $label => $node )
				{
					if ( ! isset( $node['modelType'] ) ) continue;

					/** @var XBRL $nodeTaxonomy */
					$nodeTaxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
					$nodeElement = $nodeTaxonomy->getElementById( $label );

					$preferredLabels = isset( $node['preferredLabel'] ) ? array( $node['preferredLabel'] ) : null;
					// Do this because $label includes the preferred label roles and the label passed cannot include it
					$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $nodeTaxonomy->getTaxonomyXSD() . '#' . $nodeElement['id'], $preferredLabels, $lang, $elr );

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
							if ( $node['typedDomainRef'] ) $reportElement .= " ({$node['typedDomainRef']})";
							if ( $node['default-member'] )
							{
								/** @var XBRL $memberTaxonomy */
								$memberTaxonomy = $this->taxonomy->getTaxonomyForXSD( $node['default-member'] );
								$memberElement = $memberTaxonomy->getElementById( $node['default-member'] );
								$reportElement .= " ({$memberTaxonomy->getPrefix()}:{$memberElement['name']})";
							}
							break;

						case 'cm.xsd#cm_Member':
							$class = "member";
							$reportElement = "[Member]";
							if ( isset( $node['is-domain'] ) && $node['is-domain'] ) $reportElement .= " (domain)";
							if ( isset( $node['is-default'] ) && $node['is-default'] ) $reportElement .= " (default)";
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
							else if ( $this->taxonomy->context->types->resolvesToBaseType( $nodeElement['type'], array( XBRL_Constants::$xbrliMonetaryItemType ) ) )
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
							$periodType = isset( $nodeElement['periodType'] ) ?  $nodeElement['periodType'] : 'n/a';
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
	 * Create a set of links to the fact sets at the top of the page
	 * @param array $network
	 * @param string $elr
	 */
	private function renderFactSetLinks( $network, $elr, $lang = null )
	{
		if ( ! $this->includeFactsTable ) return '';

		// class='factset-links'
		$nodes = $network['hierarchy'];

		$getAbstracts = function( $nodes ) use( &$getAbstracts, $lang )
		{
			$results = array();

			foreach( $nodes as $label => $node )
			{
				if ( isset( $node['patterntype'] ) && $node['patterntype'] && isset( $node['modelType'] ) && $node['modelType'] == 'cm.xsd#cm_Abstract' )
				{
					$taxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
					$element = $taxonomy->getElementById( $label );

					$text = $this->taxonomy->getTaxonomyDescriptionForIdWithDefaults( $label, null, $lang );

					$results[] = "<div class='factset-link' data-name='{$element['name']}'>$text</div>";
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$results = array_merge( $results, $getAbstracts( $node['children'] ) );
			}

			return $results;
		};

		$abstracts = $getAbstracts( $nodes );

		return	$abstracts
			? "<div class='report-table-links'>" .
				implode('', $abstracts ) .
			  "</div>"
			: '';
	}

	/**
	 * Render a report with columns for any years and dimensions
	 * @param array $network
	 * @param array $nodes The node hierarchy to report.  At the top level this will be $network['hierarchy']
	 * @param string $elr
	 * @param XBRL_Instance $instance
	 * @param QName $entityQName
	 * @param array $report	The evaluated formulas
	 * @param \Log_observer $observer		An obsever with any validation errors
	 * @param array $resultFactsLayout
	 * @param array $accumulatedTables
	 * @param array $nodesToProcess
	 * @param array|boolean $lineItems
	 * @param boolean $excludeEmptyHeadrers
	 * @param integer &$row
	 * @param array $lasts
	 * @param string $allowConstrained  Set to false if the view is only one column of text
	 * @param string|null $lang			(optional: default = null) The language to use or null for the default
	 * @param string $parentLabel
	 * @return string
	 */
	private function renderReportTable( $network, $nodes, $elr, $instance, $entityQName, &$report, $observer, &$resultFactsLayout, $accumulatedTables, $nodesToProcess, $lineItems, $excludeEmptyHeadrers, &$row, $lasts, &$allowConstrained, $lang = null, $parentLabel = null )
	{
		/**
		 * How does this work?
		 *
		 * $createLayout is the function that eventually generates the HTML for the presentation nodes in $nodes
		 * It uses $factsLayout which is a collection of rows, one each for every row in $nodes that contains some data.
		 * $factsLayout is generated by $getFactsLayout
		 *
		 * Prior to this, $columnsHierachy is generated to represent the layout of columns based on the set of multi-member
		 * dimensions that need to be reported.  Single member dimensions only appear as members in the slicer display
		 * The basic structure of $columnsHierachy is very simple because, by default, only the period axis exists.  If there
		 * are multi-member dimension to include (which can be explicit or typed) then $addToColumnHierarchy is used to add
		 * the collection of members for a dimension to each of the existing leaf nodes.  For example, if there are two periods
		 * 2018 and 2019 and a dimension D with members M1 and M2 are to be added then M1 and M2 will be added as children of
		 * both 2018 and 2019.
		 *
		 * To make processing efficient $columnsHierarchy is rarely used directly.  Instead $createContextRefColumns is used
		 * to create two index arrays: $contextRefColumns and $columnLayout.  A third index $columnRefs is created from
		 * $contextRefColumns and these three arrays are used to build the report efficiently.
		 *
		 * Just adding columns can quickly get out of control if there are many dimensions so this process is moderated.  When
		 * a column is added, starting with the periods, they are assigned contexts appropriate for the column.  When the column
		 * for 2018 is added it is assigned the contexts for that year.  When M1 is added to 2018 it is assigned those contexts
		 * that apply to M1 in 2018.  If there are no contexts for M1 in 2018 the column is not added.  In this way, only
		 * relevant columns are added.
		 *
		 * This means that one of the early tasks is to retrieve the set of contexts for the facts associated with the nodes
		 * in $nodes.  This set of facts is stored in $elements.  The set of contexts is determined by the contextRef values
		 * of the facts retreived for the nodes.
		 *
		 * Even so, sometime there will be columns that are empty - usually when typed domain dimensions are being used. To
		 * eliminate empty columns $dropEmptyColumns is used.
		 */
//		if ( ! $this->includeReport ) return '';

		$axes = XBRL::array_reduce_key( $network['axes'], function( $carry, $axes, $hypercube ) use ( &$accumulatedTables )
		{
			if ( ! in_array( $hypercube, $accumulatedTables ) ) return $carry;
			return array_merge( $carry, $axes );
		}, [] );

		// Get a list of the nodes representing concepts that have the same dimensionality
		// That is, ignore sub-nodes and their descendants that are associated with aother hypercube
		$getDimensionalNodes = function( $nodes ) use( &$getDimensionalNodes, &$network, &$accumulatedTables )
		{
			$result = array();

			foreach ( $nodes as $label => $node )
			{
				if ( $node['modelType'] == 'cm.xsd#cm_Axis' ) continue;

				if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] )
				{
					/** @var XBRL $nodeTaxonomy */
					$nodeTaxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
					$nodeElement = $nodeTaxonomy->getElementById( $label );

					$label = $nodeTaxonomy->getTaxonomyXSD() . '#' . $nodeElement['id'];
				}

				if ( isset( $network['tables'][ $label ] ) && ! isset( $accumulatedTables[ $label ] ) )
				{
					// BMS 2020-08-31 In the presentation hierarchy node might be linked to a table
					if ( ! isset( $node['children'] ) ) continue;
				}

				if ( isset( $network['concepts'][ $label ] ) )
				{
					// If the network has been loaded from an array the qname values will be an array
					$result[ $label ] = $network['concepts'][ $label ] instanceof QName
						? $network['concepts'][ $label ]
						: new QName( $network['concepts'][ $label ]['prefix'], $network['concepts'][ $label ]['namespaceURI'], $network['concepts'][ $label ]['localName'] );
				}
				if ( ! isset( $node['children'] ) ) continue;
				$result = array_merge( $result, $getDimensionalNodes( $node['children'] ) );
			}

			return $result;
		};

		// $dimensionalNodes is an array of PIs that have dimensions
		$dimensionalNodes = $getDimensionalNodes( $nodes );

		// Get the names of the concepts used by this view (excluding ones in sub-reports)
		$names = array_map( function( $conceptQName )
		{
			return $conceptQName->localName;
		}, $dimensionalNodes );

		// Use the names to return a list of the facts
		$elements = $instance->getElements()->ElementsByName( $names )->getElements();

		// Next, create a list of the context refs used
		$contextRefs = array_reduce( $elements, function( $carry, $element ) use ( $instance )
		{
			$result = array_unique( array_filter( array_map( function( $fact )
			{
				return $fact['taxonomy_element']['substitutionGroup'] == XBRL_Constants::$xbrliTuple ? null : $fact['contextRef'];
			}, array_values( $element ) ) ) );
			return array_unique( array_merge( $carry, $result ) );
		}, array() );

		// And, so, a list of contexts
		$rawContexts = array_intersect_key( $instance->getContexts()->getContexts(), array_flip( $contextRefs ) );

		// Filter contexts to just those used by an axis or default members
		$cf = new ContextsFilter( $instance, $rawContexts );
		// Context without a segment are always allowed because they will be used by default members
		$contexts = $cf->NoSegmentContexts()->getContexts();
		// Select dimension specific contexts
		foreach ( $axes as $axisLabel => $axis )
		{
			if ( ! isset( $axis['dimension'] ) ) continue;
			$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
			$axisContexts = $cf->SegmentContexts( strstr( $axisLabel, '#' ), $dimTaxonomy->getNamespace() );
			$contexts = array_merge( $contexts, $axisContexts->getContexts() );
		}

		// Use the remaining contexts to return a list the applicable years
		$years = array();
			$cf = new ContextsFilter( $instance, $contexts );
			$years = $cf->getDiscreteDateRanges();

		// Present years in a consistent order - most recent first
		uksort( $years, function( $a, $b ) use( &$years )
		{
			return strcmp( $years[ $a ]['text'], $years[ $b ]['text'] ) * -1;
		} );

		$totalAxesCount = array_reduce( $axes, function( $carry, $axis ) { return $carry + ( isset( $axis['dimension'] ) ? 1 : 0 ) ; } );

		$hasReportDateAxis = false;
		// Get a list of dimensions with more than one member
		$multiMemberAxes = array_reduce( array_keys( $axes ), function( $carry, $axisLabel ) use( $axes, $instance, &$hasReportDateAxis )
		{
			/** @var XBRL $taxonomy */
			$taxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
			$element = $taxonomy->getElementById( $axisLabel );

			$axis = $axes[ $axisLabel ];

			if ( ! isset( $axis['dimension'] ) || // Ignore member only items
				 (
				   count( $axis['members'] ) <= 1 && // Ignore axes with more than one member
				   ! isset( $axes[ key( $axis['members'] ) ] ) // Or that has sub-members
				 )
			) return $carry;

			if ( in_array( $element['name'], $this->axesToExclude ) )
			{
				if ( in_array( $element['name'], $this->reportDateAxisAliases ) )
				{
					// Must be more than one context
					if ( $instance->getContexts()->SegmentContexts( strstr( $axisLabel, '#' ), $taxonomy->getNamespace() )->count() > ( $axis['default-member'] ? 0 : 1 ) )
					{
						$hasReportDateAxis = $axisLabel;
					}
				}
				return $carry; // ReportDateAxis is not reported as a column
			}

			// Exclude axes without contexts with any of their members
			if ( $instance->getContexts()->SegmentContexts( strstr( $axisLabel, '#' ), $taxonomy->getNamespace() )->count() == 0 )
			{
				return $carry;
			}

			$carry[] = $axisLabel;
			return $carry;
		}, array() );

		// Add any multi-member typed domains
		foreach ( $axes as $axisLabel => $axis )
		{
			if ( ! isset( $axis['dimension'] ) ) continue;

			// Find any of the dimensions that are typed domains
			if ( ! $axis['typedDomainRef'] ) continue;
			$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
			$axisContexts = $cf->SegmentContexts( strstr( $axisLabel, '#' ), $dimTaxonomy->getNamespace() );
			if ( $axisContexts->count() <= 1 ) continue;

			$label = $axis['typedDomainRef'];
			$memTaxonomy = $dimTaxonomy;
			if ( $label[0] != '#' )
			{
				$memTaxonomy = $dimTaxonomy->getTaxonomyForXSD( $label );
			}

			$memElement = $memTaxonomy->getElementById( $label );
			$memPrefix = $instance->getPrefixForNamespace( $memTaxonomy->getNamespace() );
			$memQName = "$memPrefix:{$memElement['name']}";

			$members = array();
			foreach ( $axisContexts->getContexts() as $contextRef => $context )
			{
				$segment = $instance->getContextSegment( $context );
				$typedDomain = reset( $segment['typedMember'] ); // Probably should search for the one with the dimension label
				$memberText = preg_replace( array( "!<$memQName>!", "!</$memQName>!" ), "", reset( $typedDomain['member'][ $memQName ] ) );
				$members[ $memberText ] = isset( $members[ $memberText ] ) ? $members[ $memberText ] + 1 : 1;
				// BMS 2020-10-09 Changed to store context refs in an array (see other notes with the same date)
				$axes[ $axisLabel ]['typedDomainMembers'][ $memberText ][] = $contextRef;
				unset( $memberText );
				unset( $segment );
				unset( $typedDomain );
			}

			if ( count( $members ) > 1 )
			{
				$multiMemberAxes[] = $axisLabel;
			}

			unset( $axisContexts );
			unset( $dimTaxonomy );
			unset( $label );
			unset( $members );
			unset( $memElement );
			unset( $memPrefix );
			unset( $memQName );
			unset( $memTaxonomy );
		}

		unset( $axisLabel );
		unset( $axis );

		// Get count of dimensions with more than one member
		$multiMemberAxesCount = count( $multiMemberAxes );

		// If there are single member axes then remove contexts that are not related to the single member
		$singleMemberAxes = array_diff_key( $axes, array_flip( $multiMemberAxes ) );
		$cf = new ContextsFilter( $instance, $contexts );
		foreach ( $singleMemberAxes as $axisLabel => $axis )
		{
			if ( $axisLabel == $hasReportDateAxis ) continue;
			if ( ! isset( $axis['dimension'] ) ) continue;
			// Ignore typed domain members as they have only non-default members
			if ( isset( $axis['typedDomainRef'] ) && $axis['typedDomainRef'] ) continue;

			// As it's a single member axis it may be because there is only one member in which case
			// use the key function  to retrieve the only member label.  Alternatively it may be that
			// there are multiple members but the data only uses the default.
			$memberLabel = $axis['default-member'] ? $axis['default-member'] : key( $axis['members'] );

			$dimTaxonomy = $instance->getInstanceTaxonomy()->getTaxonomyForXSD( $axisLabel );
			// If the member is default then the context will be added
			// but there cant be any other members
			if ( ( is_null( $memberLabel ) && $axis['default-member'] ) || $memberLabel == $axis['default-member'] )
			{
				$segmentContexts = $cf->SegmentContexts( strstr( $axisLabel, '#' ), $dimTaxonomy->getNamespace() );
				if ( ! $segmentContexts->count() ) continue;
				$cf->remove( $segmentContexts );
			}
			else
			{
				// If not then select only contexts with the member
				$memTaxonomy = $dimTaxonomy->getTaxonomyForXSD( $memberLabel );
				$cf = $cf->SegmentContexts( strstr( $axisLabel, '#' ), $dimTaxonomy->getNamespace(), strstr( $memberLabel, '#' ), $memTaxonomy->getNamespace() );
			}
		}

		$contexts = $cf->getContexts();
		// Should report here that there are no contexts
		if ( ! $contexts )
		{
			return '';
		}

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

			// Move the first element to the end
			return count( $result ) == 1
				? $result
				: array_merge( array_slice( $result, 1 ), array_slice( $result, 0, 1 ) );
			// return array_reverse( $result );
		};

		foreach ( $multiMemberAxes as $axisLabel )
		{
			$headerColumnCount *= isset( $axes[ $axisLabel ]['typedDomainMembers'] ) && $axes[ $axisLabel ]['typedDomainMembers']
				? count( $axes[ $axisLabel ]['typedDomainMembers'] )
				: count( $getAxisMembers( $axes[ $axisLabel ]['members'] ) );
		}

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

		$rowCount = $getRowCount( $nodes );

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
		$periodAxis = $this->getConstantTextTranslation( $lang, 'Period [Axis]' );
		$columnHierarchy[ $periodAxis ] = $years;
		$columnHierarchy[ $periodAxis ]['total-children'] = count( $years );

		// Extend $columnHierarchy to add columns for $multiMemberAxes and their members
		if ( $multiMemberAxes )
		{
			$addToColumnHierarchy = function( &$columnHierarchy, $multiMemberAxes )
				use ( &$addToColumnHierarchy, $instance, &$axes, &$getAxisMembers, $lang, $elr )
			{
				$totalChildren = 0;

				foreach ( $columnHierarchy as $axisLabel => &$columnAxismembers )
				{
					if ( ! $multiMemberAxes )
					{
						$totalChildren += $columnAxismembers['total-children'];
						continue;
					}

					foreach ( $columnAxismembers as $index => &$columnAxismember )
					{
						if ( $index == 'total-children' ) continue;

						if ( isset( $columnAxismember['children'] ) )
						{
							$totalChildren += $addToColumnHierarchy( $columnAxismember['children'], $multiMemberAxes );
						}
						else
						{
							// Get the axis text
							$nextAxisLabel = reset( $multiMemberAxes );
							/** @var XBRL $axisTaxonomy */
							$axisTaxonomy = $this->taxonomy->getTaxonomyForXSD( $nextAxisLabel );
							$axisText = $axisTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $nextAxisLabel, null, $lang, $elr );

							// Get the members
							$axis = $axes[ $nextAxisLabel ];
							$nextMembers = array();

							if ( isset( $axis['typedDomainMembers'] ) )
							{
								// BMS 2020-10-09 This section has changed.  See other notes with the same date for more information.
								/**
								 * @var ContextsFilter $dimensionContexts
								 */
								$dimensionContexts = $instance->getContexts()->getContextsByRef( $columnAxismember['contextRefs'] )->SegmentContexts( strstr( $nextAxisLabel, '#' ) );
								foreach( $axis['typedDomainMembers'] as $typedLabel => $contextRefs )
								{
									$dimensionContexts = $dimensionContexts->remove( $contextRefs );
								}
								unset( $contextRefs );
								unset( $typedLabel );

								if ( $dimensionContexts->count() )
								{
									$extra = join(',', array_keys( $dimensionContexts->getContexts() ) );
									XBRL_Log::getInstance()->warning( "addToColumnHierarchy: The contexts in typed dimension with label '$nextAxisLabel' do not match all the dimensional contexts for '$index'. The unmatched contexts are $extra." );
								}
								unset( $dimensionContexts );

								foreach ( $axis['typedDomainMembers'] as $memberText => $memberContextRefs )
								{
									// BMS 2020-10-09 Changed because $axis['typedDomainMembers']['contextRefs'] has changed (see other notes with the same date)
									$contextRefs = array_intersect( $columnAxismember['contextRefs'], $memberContextRefs );
									// if ( ! in_array( $contextRef, $columnAxismember['contextRefs'] ) ) continue;
									if ( ! count( $contextRefs ) ) continue;
									$guid = XBRL::GUID();
									$nextMembers[ $guid ] = array(
										'text' => $memberText,
										'dimension-label' => $nextAxisLabel,
										'member-label' => null,
										// BMS 2020-10-09 Changed because $axis['typedDomainMembers']['contextRefs'] has changed (see other notes with the same date)
										'contextRefs' => $contextRefs,
										'default-member' => false,
										'domain-member' => false,
										'root-member' => false
									);

									unset( $contextRefs );
								}

								unset( $memberContextRefs );
								unset( $memberText );
							}
							else
							{
								$axisMembers = $getAxisMembers( $axes[ $nextAxisLabel ]['members'] );

								// Workout which contexts apply
								$cf = new ContextsFilter( $instance, array_reduce( $columnAxismember['contextRefs'], function( $carry, $contextRef ) use( $instance) { $carry[ $contextRef ] = $instance->getContext( $contextRef ); return $carry; }, array() ) );

								foreach ( $axisMembers as $memberLabel )
								{
									/** @var XBRL $memberTaxonomy */
									$memberTaxonomy = $this->taxonomy->getTaxonomyForXSD( $memberLabel );
									$memberText = $memberTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberLabel, null, $lang, $elr );

									// BMS 2019-06-17
									// When considering multi-member axes other than the last a
									// when the member being reviewed is a default member, what's needed are
									// all the contexts that do not have the member's dimension as an explicit
									// dimension.  So instead of selecting those contexts with no segments
									// at all, select any contexts that DO have explicit dimensions that do
									// have the default member's dimension and remove them from the list of
									// context because these context cannot belong to the default member.
									if ( $axis['default-member'] == $memberLabel )
									{
										$filteredContexts = $cf->SegmentContexts( strstr( $nextAxisLabel, '#' ), $axisTaxonomy->getNamespace() );
										$filteredContexts = $cf->clone()->remove( $filteredContexts );
									}
									else
									{
										$filteredContexts = $cf->SegmentContexts( strstr( $nextAxisLabel, '#' ), $axisTaxonomy->getNamespace(), strstr( $memberLabel, '#' ), $memberTaxonomy->getNamespace() );
									}

									if ( $filteredContexts->count() )
									{
										$guid = XBRL::GUID();
										$nextMembers[ $guid ] = array(
											'text' => $memberText,
											'dimension-label' => $nextAxisLabel,
											'member-label' => $memberLabel,
											'contextRefs' => array_keys( $filteredContexts->getContexts() ),
											'default-member' => $axis['default-member'] == $memberLabel,
											'domain-member' => $axis['domain-member'] == $memberLabel,
											'root-member' => $axis['root-member'] == $memberLabel
										);
									}
								}
							}

							$nextMembers['total-children'] = count( $nextMembers );
							$columnAxismember['children'][ $axisText ] = $nextMembers;

							// if ( count( $multiMemberAxes ) == 1 ) continue;

							$immediateChildren = $addToColumnHierarchy( $columnAxismember['children'], array_slice( $multiMemberAxes, 1 ) );
							// Remove any immediate children that have no descendents
							if ( ! $immediateChildren )
							{
								unset( $columnAxismembers[ $index ] );
							}
							$totalChildren += $immediateChildren;
						}
					}

					unset( $index );
					unset( $columnAxismember );
				}

				unset( $axisLabel );

				$columnAxismembers['total-children'] = $totalChildren;

				unset( $columnAxismembers );

				return $totalChildren;
			};

			$headerColumnCount = $addToColumnHierarchy( $columnHierarchy, $multiMemberAxes );
		}

		$columnCount = $headerColumnCount + 1 + ( $hasReportDateAxis ? 1 : 0 ); // Add the description column

		// Create an index of contextRef to column.  Should be only one column for each context.
		// At the same time create a column layout array that can be used to generate the column headers
		$columnLayout = array();
		$createContextRefColumns = function( $columnNodes, $depth = 0, $parentLabels = array(), $inDomain = false ) use( &$createContextRefColumns, &$columnLayout )
		{
			$result = array();
			foreach ( $columnNodes as $axisLabel => $columnMembers )
			{
				if ( ! $columnMembers['total-children'] ) continue;

				$details = array( 'text' => $axisLabel, 'span' => $columnMembers['total-children'] );
				$columnLayout[ $depth ][] = $details;

				foreach ( $columnMembers as $index => $columnNode )
				{
					if ( $index == 'total-children' ) continue;

					$span = isset( $columnNode['children'] )
						? array_reduce( $columnNode['children'], function( $carry, $axis ) { return $carry + $axis['total-children']; }, 0 )
						: 1;

					$dimensionLabel = isset( $columnNode['dimension-label'] ) ? $columnNode['dimension-label'] : null;
					$memberLabel = isset( $columnNode['member-label'] ) ? $columnNode['member-label'] : null;
					$inDomain |= isset( $columnNode['domain-member'] ) && $columnNode['domain-member'];

					$columnLayout[ $depth + 1 ][] = array(
						'text' => $columnNode['text'],
						'span' => $span,
						'default-member' => isset( $columnNode['default-member'] ) && $columnNode['default-member'],
						'domain-member' => isset( $columnNode['domain-member'] ) && $columnNode['domain-member'],
						'root-member' => isset( $columnNode['root-member'] ) && $columnNode['root-member'],
						'dimension-label' => isset( $columnNode['dimension-label'] ) ? $columnNode['dimension-label'] : null,
						'member-label' => isset( $columnNode['dimension-label'] ) ? $columnNode['member-label'] : null,
						'in-domain' => $inDomain,
						'parent-labels' => $parentLabels
					);

					if ( isset( $columnNode['children'] ) && $columnNode['children'] )
					{
						$result += $createContextRefColumns( $columnNode['children'], $depth + 2, array_merge( $parentLabels, $dimensionLabel ? array( $dimensionLabel => $memberLabel ) : array() ), $inDomain );
					}
					else if ( $columnNode['contextRefs'] )
					{
						$result = array_merge( $result, array_fill_keys( $columnNode['contextRefs'], $index ) );
					}
					else
					{
						// This is necessary so the $columnsRef array will be created with the correct column offsets
						$result = array_merge( $result, array( 'place_holder_' . XBRL::GUID() => $index ) );
					}
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
				$abstractLineItems = $node['modelType'] == 'cm.xsd#cm_Abstract';
				$thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				$lineItems |= $thisLineItems | $abstractLineItems;

				// $thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				// $lineItems |= $thisLineItems;
				if ( $lineItems && ( $thisLineItems || $node['modelType'] == 'cm.xsd#cm_Abstract' ) )
				{
					$factSetTypes[ $label ] = isset( $node['patterntype'] ) ? $node['patterntype'] : 'set';
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$factSetTypes = array_merge( $factSetTypes, $getFactSetTypes( $node['children'], $lineItems ) );
			}

			return $factSetTypes;
		};

		$factSetTypes = $getFactSetTypes( $nodes, $lineItems );

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
							if ( ! $axis[ $axisId ]['total-children'] )
							{
								unset( $axis[ $axisId ] );
							}
							else if ( ! count( $column['children'] ) )
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

		/**
		 * Return the fact corresponding to the originally stated or restated condition
		 * @param XBRL $nodeTaxonomy
		 * @param array $facts (ref)
		 * @param array $axis an entry for an axis in $axes
		 * @param ContextsFilter $cf A filter of instant contexts
		 * @param bool $originally True gets the facts for the orginally stated case; false restated
		 * @var callable $getStatedFacts
		 * @var bool $hasReportDateAxis
		 */
		$getStatedFacts = function( $nodeTaxonomy, &$facts, &$axis, /** @var ContextsFilter $cf */ $cf, $originally = false )
			use ( &$getStatedFacts, $hasReportDateAxis )
		{
			// !! This is a spepcial case and there will be only one prior value not prior values for several previous years

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

			$contextRefs = array_keys( $filteredContexts->getContexts() );

			// Find the fact WITHOUT this context
			$cbFacts = $facts;
			$result = array();
			foreach ( $cbFacts as $factIndex => $fact )
			{
				if ( $originally ? in_array( $fact['contextRef'], $contextRefs ) : ! in_array( $fact['contextRef'], $contextRefs ) ) continue;
				$result[ $factIndex ] = $fact;
			}

			return $result;
		};

		// Now workout the facts layout.
		// Note to me.  This is probably the way to go as it separates the generation of the facts from the rendering layout
		// Right now it is used to drop columns
		$getFactsLayout = function( $nodes, $contexts, $parentLabel = null, $parentPattern = 'set', $lineItems = false, $parentModelType = null, $parentConceptQName = null )
			use( &$getFactsLayout, &$getStatedFacts, $instance, &$axes, &$network,
				 $columnLayout, $columnRefs, $contextRefColumns,
				 $elr, $hasReportDateAxis, $nodesToProcess, &$accumulatedTables )
		{
			global $allowAggregations;
			$rows = array();
			$priorRowContextRefsForByColumns = array();

			$firstRow = reset( $nodes );
			$lastRow = end( $nodes );
			$hasOpenBalance = false;
			$order = 0; // Order within parent

			foreach ( $nodes as $label => $node )
			{
				if ( $nodesToProcess && ! in_array( $label, $nodesToProcess ) ) continue;

				$first = $node == $firstRow;
				$last = $node == $lastRow;

				/** @var XBRL $nodeTaxonomy */
				$nodeTaxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
				$nodeElement = $nodeTaxonomy->getElementById( $label );

				$abstractLineItems = $node['modelType'] == 'cm.xsd#cm_Abstract';
				$thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				$lineItems |= $thisLineItems | $abstractLineItems;
				if ( $lineItems )
				{
					// Skip nodes in sub-reports
					if ( isset( $network['tables'][ $label ] ) && ! isset( $accumulatedTables[ $label ] ) )
					{
						continue;
					}

					if ( $thisLineItems )
					{
					}
					else if ( $node['modelType'] == 'cm.xsd#cm_Abstract' )
					{
					}
					else if ( isset( $nodeElement['abstract'] ) && $nodeElement['abstract'] )
					{
					}
					else
					{
						// Can only total numeric values
						$isNumeric = $this->taxonomy->context->types->resolvesToBaseType( $nodeElement['type'], array( 'xs:decimal' ) );

						// Add the data.  There is likely to be only a partial facts set
						$facts = $instance->getElement( $nodeElement['name'] );
						// Filter facts by contexts (ignore tuples - the ones without a contextRef )
						$facts = array_filter( $facts, function( $fact ) use ( $contexts ) { return isset( $fact['contextRef'] ) && isset( $contexts[ $fact['contextRef'] ] ); } );

						// echo count( $facts ) . " $label\n";

						if ( /* $first */ ! $hasOpenBalance && isset( $node['preferredLabel'] ) )
						{
							$openingBalance = $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodStartLabel;
							$cf = new ContextsFilter( $instance, $contexts );
							/** @var ContextsFilter $instantContextsFilter */
							$instantContextsFilter = $cf->InstantContexts();

							if ( $hasReportDateAxis ) // && $node['preferredLabel'] == self::$originallyStatedLabel )
							{
								$hasOpenBalance = true;

								// If there is domain or default member of ReportDateAxis then one approach
								// is taken to find an opening balance.  If not the another approach is required.
								$axis = $axes[ $hasReportDateAxis ];
								if ( $axis['default-member'] || $axis['domain-member'] || $axis['root-member'] )
								{
									$facts = $getStatedFacts( $nodeTaxonomy, $facts, $axis, $instantContextsFilter, true );
									$contextRef = reset($facts)['contextRef'];
									$period = $instantContextsFilter->getContext( $contextRef )['period']['startDate'];
									// This should be one of the columns so do a check
									if ( ! isset( $columnRefs[ $period ] ) )
									{
										error_log( "The adjustment originally stated fact with context '$contextRef' does not have a period that is a column of the report" );
									}
									$instantContextsFilter = $instantContextsFilter->remove( $contextRef )->ContextsContainDate( $period );
									$context = $instantContextsFilter->getContexts();
									// There will be one column here so insert the appropriate context
									// $columnId = key( $columnRefs );
									// $candidates = array_filter( $contextRefColumns, function( $id ) use( $columnId ) { return $id == $columnId; } );
									// $context = array_intersect_key( $instantContextsFilter->remove($contexts)->getContexts(), $candidates );
									if ( $context )
									{
										// Need to retain the the original context so the correct text for the adjustment reported date columns can be retrieved
										// This will be used to replace the contextRef when the text has been retrieved.
										$facts[ key( $facts ) ]['contextRefRestated'] = key( $context );
									}
								}
								else
								{
									$openingBalance = true;
								}
							}

							if ( $openingBalance )
							{
								$hasOpenBalance = true;

								$cbFacts = $facts;
								$facts = array();
								foreach ( $cbFacts as $cbFactIndex => $cbFact )
								{
									/** @var ContextsFilter $segmentContextFilter */
									$segmentContextFilter = $instantContextsFilter->SameContextSegment( $contexts[ $cbFact['contextRef'] ] );
									$segmentContexts = $segmentContextFilter->sortByEndDate()->getContexts();

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
											$fact['priorContextRef'] = $fact['contextRef'];
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
						if ( $hasReportDateAxis && $last )
						{
							$axis = $axes[ $hasReportDateAxis ];
							// Find the segment with $hasReportDateAxis
							if ( isset( $node['preferredLabel'] ) ) // && $node['preferredLabel'] == XBRL_Constants::$labelRoleRestatedLabel )
							{
								if ( $axis['default-member'] || $axis['domain-member'] || $axis['root-member'] )
								{
									// $cf = new ContextsFilter( $instance, $contexts );
									/** @var ContextsFilter $instantContextsFilter */
									// $instantContextsFilter = $cf->InstantContexts();
									$facts = $getStatedFacts( $nodeTaxonomy, $facts, $axis, $instantContextsFilter, false );
									// $fact = reset( $facts );
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
						}

						$priorRowContextRefsForByColumns = array();

						$lastRowLayout = end( $columnLayout );

						// This section computes rollup totals if node presents a rollup pattern
						$rollupTotal = false;
						if ( ( $parentPattern == 'rollup' || $parentModelType == 'cm.xsd#cm_LineItems' ) && isset( $this->calculationNetworks[ $elr ]['calculations'][ $node['label'] ] ) )
						{
							$rollupTotal = true;

							$calculation =& $this->calculationNetworks[ $elr ]['calculations'][ $node['label'] ];
							$calculationTotals = array();
							$calculationDetails = array();
							foreach ( $calculation as $calcItemLabel => $calcItem )
							{
								$weight = isset( $calcItem['weight'] ) && is_numeric( $calcItem['weight'] ) ? $calcItem['weight'] : 1;
								$calcTaxonomy = $this->taxonomy->getTaxonomyForXSD( $calcItemLabel );
								$calcElement = $calcTaxonomy->getElementById( $calcItemLabel );
								// $sign = ( ! isset( $nodeElement['balance'] ) ||
								// 		  ! isset( $calcElement['balance'] ) ||
								// 		  $nodeElement['balance'] == $calcElement['balance'] ? 1 : -1
								// 		);
								// $sign = 1;
								$calcFacts = $instance->getElement( $calcElement['name'] );
								foreach ( $calcFacts as $calcFact )
								{
									$rollupItemValue = $instance->getNumericPresentation( $calcFact );
									$calculationDetails[ $calcFact['contextRef'] ][ "{$calcTaxonomy->getPrefix()}:{$calcElement['name']}" ] = $rollupItemValue . ( $weight != 1 ? " * $weight " : "" );

									// // KLUDGE: It seems users sometimes enter a negative value when the balances are opposite
									// $negated = $nodes[$calcItemLabel]['preferredLabel'] == XBRL_Constants::$labelRoleNegatedLabel;
									// if ( ! $negated && $sign == -1 && $rollupItemValue < 0 ) $weight = 1;
									$rollupItemValue *= $weight; // * $sign;
									$calculationTotals[ $calcFact['contextRef'] ] = (
										isset( $calculationTotals[ $calcFact['contextRef'] ] )
											? $calculationTotals[ $calcFact['contextRef'] ]
											: 0
										) + $rollupItemValue;

									unset( $rollupItemValue );
								}
								unset( $calcTaxonomy );
								unset( $calcElement );
								unset( $calcFacts );
								unset( $calcFact );
							}
							unset( $calculation );
							unset( $calcItem );
							unset( $calcItemLabel );
						}

						$domainTotals = array();
						foreach ( $facts as $factIndex => $fact )
						{
							if ( ! $fact || ! isset( $contextRefColumns[ $fact['contextRef'] ] ) ) continue;
							$columnIndex = $columnRefs[ $contextRefColumns[ $fact['contextRef'] ] ];
							// Check that the column is still reportable.  It might have been removed as empty
							if ( ! isset( $lastRowLayout[ $columnIndex ] ) ) continue;
							$currentColumn = $lastRowLayout[ $columnIndex ];

							$fact['aggregates'] = array();

							$columns[ $columnIndex ] = $fact;
							$priorRowContextRefsForByColumns[ $columnIndex ] = $fact['contextRef'];

							if ( $rollupTotal )
							{
								if ( isset( $calculationTotals[ $fact['contextRef'] ] ) )
								{
									$calculationTotals[ $columnIndex ] = $calculationTotals[ $fact['contextRef'] ];
									unset( $calculationTotals[ $fact['contextRef'] ] );
								}
								if ( isset( $calculationDetails[ $fact['contextRef'] ] ) )
								{
									$calculationDetails[ $columnIndex ] = $calculationDetails[ $fact['contextRef'] ];
									unset( $calculationDetails[ $fact['contextRef'] ] );
								}
							}

							// If not 'in-domain' its not an aggregate total
							// Aggregations tend not to work except in simple examples because it hard to determine where the
							// boundaries of an aggregation occur.  Also the structure of axis members is often 'wrong' and
							// should contain multiple levels.
							if ( ! $isNumeric || ! $currentColumn['in-domain'] || ! $allowAggregations ) continue;

							// OK, look for any details across the facts for this fact
							// Begin by getting the contexts for these facts but exclude the ntext of the fact of the current column
							$cf = ( new ContextsFilter( $instance, $contexts ) )->remove( $fact['contextRef'] );

							$year = $instance->getYearForElement( $fact );
							$cf = $cf->ContextsForYear( $year );

							// Get the segments
							$segment = $instance->getContextSegment( $contexts[ $fact['contextRef'] ] );

							// Merge any default members for dimensions with the explicit members for the current fact's context
							$explicitMembers = array();
							if ( $segment )
							foreach ( $segment['explicitMember'] as $explicitMember )
							{
								$dimQName = qname( $explicitMember['dimension'], $instance->getInstanceNamespaces() );
								$dimTaxonomy = $this->taxonomy->getTaxonomyForNamespace( $dimQName->namespaceURI );
								$dimElement = $dimTaxonomy->getElementByName( $dimQName->localName );
								$dimensionLabel = $dimTaxonomy->getTaxonomyXSD() . '#' . $dimElement['id'];

								$columnMemberQName = qname( $explicitMember['member'], $instance->getInstanceNamespaces() );
								$columnMemberTaxonomy = $this->taxonomy->getTaxonomyForNamespace( $columnMemberQName->namespaceURI );
								$columnMemberElement = $columnMemberTaxonomy->getElementByName( $columnMemberQName->localName );
								$columnMemberLabel = $columnMemberTaxonomy->getTaxonomyXSD() . '#' . $columnMemberElement['id'];

								$explicitMembers[ $dimensionLabel ] = array(
									'member-label' => $columnMemberLabel,
									'member-qname' => $columnMemberQName
								);

								unset( $dimQName );
								unset( $dimTaxonomy );
								unset( $dimElement );
								unset( $dimensionLabel );
								unset( $columnMemberQName );
								unset( $columnMemberTaxonomy );
								unset( $columnMemberElement );
								unset( $columnMemberLabel );
							}

							unset( $explicitMember );

							foreach ( $axes as $axisLabel => $axis )
							{
								// Ignore the members only nodes
								if ( ! isset( $axis['dimension'] ) ) continue;

								$memberIsDomain = false;
								$memberIsDefault = false;

								// Filter the $cf contexts by each of the explicit dimensions
								if ( ! isset( $explicitMembers[ $axisLabel ] ) )
								{
									// If the axis is not one of the explicit dimensions then
									// then there must be a default member that is the root of a hierarchy
									if ( ! $axis['default-member'] )
									{
										// There can be no valid components so continue on to the next fact
										continue 2;
									}

									$explicitMember = array(
										'member-label' => $axis['default-member'],
										'member-qname' => $axis['members'][ $axis['default-member'] ]
									);

									$memberIsDefault = true;
								}
								else
								{
									$explicitMember = $explicitMembers[ $axisLabel ];
								}

								// Get the members
								if ( isset( $axes[ $explicitMember['member-label'] ]['members'] ) )
								{
								 	$members = $axes[ $explicitMember['member-label'] ]['members'];
								}
								else
								{
								 	$members = $axis['members'];
								}

								$memberIsDomain = $axis['domain-member'] == $explicitMember['member-label'];

								// BMS 2019-06-23 Only add 'self' back when the member is NOT the default member
								if ( ! $memberIsDefault )
								{
									$members[ $explicitMember['member-label'] ] = $explicitMember['member-qname'];
								}

								// Now get any contexts with any one of these members
								$memberContexts = array();
								foreach ( $members as $memberLabel => $memberQName )
								{
									$memTaxonomy = $this->taxonomy->getTaxonomyForNamespace( $memberQName->namespaceURI );
									$memElement = $memTaxonomy->getElementByName( $memberQName->localName );

									if ( $axis['default-member'] == $memberLabel )
									{
										$memberFiltered = $cf->SegmentContexts( strstr( $axisLabel, '#' ), $axis['dimension']->namespaceURI );
										$memberFiltered = $cf->clone()->remove( $memberFiltered );
									}
									else
									{
										$memberFiltered = $cf->SegmentContexts( strstr( $axisLabel, '#' ), $axis['dimension']->namespaceURI,  "#" . $memElement['id'], $memberQName->namespaceURI );
									}

									if ( ! $memberFiltered->count() ) continue;
									$memberContexts = array_merge( $memberContexts, $memberFiltered->getContexts() );

									unset( $memTaxonomy );
									unset( $memElement );
									unset( $memberFiltered );
								}

								unset( $members );
								unset( $memberQName );
								unset( $memberLabel );
								unset( $explicitMember );

								if ( ! $currentColumn['in-domain'] && ! $memberContexts ) break;

								$cf = new ContextsFilter( $instance, $memberContexts );

								unset( $memberContexts );

								if ( ! $cf->count() ) break;
							}

							// Pull the facts with these contexts
							if ( ! $cf->count() ) continue;

							// $filteredFacts = array_filter( $facts, function( &$fact ) use( $cf ) { return (bool)$cf->getContext( $fact['contextRef'] ); } );
							// $columns[ $columnIndex ]['aggregates'] = $filteredFacts;
							$columns[ $columnIndex ]['potentialContextRefs'] = array_keys( $cf->getContexts() );

							unset( $cf );
							unset( $segment );
							unset( $year );
							unset( $filteredFacts );
							unset( $explicitMembers );
						}

						unset( $fact ); // Gets confusing having old values hanging around

						if ( $isNumeric && $allowAggregations ) // Aggregations tend not to work except in simple examples
						{
							$contextRefs = array();
							// TODO Looping in this order works while the columns are sorted such that the details appear before the totals
							//		This will need to change if the presentation ever allows totals to appear first
							foreach ( $contextRefColumns as $contextRef => $columnId )
							{
								if ( ! isset( $columnRefs[ $columnId ] ) ) continue;
								$columnIndex = $columnRefs[ $columnId ];
								$column = $lastRowLayout[ $columnIndex ];

								if ( isset( $column['in-domain'] ) && $column['in-domain'] )
								{
									if ( $contextRefs && isset( $columns[ $columnIndex ]['potentialContextRefs'] ) )
									{
										$matchedContextRefs = array_intersect( $columns[ $columnIndex ]['potentialContextRefs'], $contextRefs );

										$filteredFacts = $contextRefs
											? array_filter( $facts, function( &$fact ) use( &$matchedContextRefs )
												{
													return in_array( $fact['contextRef'], $matchedContextRefs );
												} )
											: array();
										$columns[ $columnIndex ]['aggregates'] = $filteredFacts;
										$contextRefs = array_diff( $contextRefs, $matchedContextRefs );
									}

									unset( $columns[ $columnIndex ]['potentialContextRefs'] );
								}
								else
								{
									$contextRefs[] = $contextRef;
								}

								unset( $column );
								unset( $columnIndex );
							}

							unset( $contextRef );
							unset( $columnId );
							unset( $contextRefs );
							unset( $facts );
						}

						ksort( $columns );
						$rows[ $label ] = array(
							'columns' => $columns,
							'taxonomy' => $nodeTaxonomy,
							'element' => $nodeElement,
							'node' => $node,
							'parentQName' => $parentConceptQName,
							'pattern' => isset( $node['patterntype'] ) ? $node['patterntype'] : $parentPattern,
							'order' => $order++  // Order within parent
						);

						if ( $rollupTotal )
						{
							$rows[ $label ]['calcDetails'] = $calculationDetails;
							$rows[ $label ]['calcTotals'] = $calculationTotals;
							if ( $parentLabel )
							{
								$rows[ $parentLabel ] = $rows[ $label ];
							}
							unset( $calculationTotals );
							unset( $calcItemLabel );
						}
						unset( $columns );

						if ( $hasOpenBalance )
						{
							if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodEndLabel )
							{
								$hasOpenBalance = false;
							}
						}
					}
				} //if $lineItems

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				$rows = array_merge( $getFactsLayout( $node['children'], $contexts, $label, isset( $node['patterntype'] ) ? $node['patterntype'] : $parentPattern, $lineItems, isset( $node['modelType'] ) ? $node['modelType'] : $parentModelType, "{$nodeTaxonomy->getPrefix()}:{$nodeElement['name']}" ), $rows );
				if ( isset( $rows[ $label ] ) )
				{
					$rows[ $label ]['taxonomy'] = $nodeTaxonomy ;
					$rows[ $label ]['element'] = $nodeElement;
					$rows[ $label ]['node'] = $node;
				}

			} // foreach $nodes

			return $rows;
		};

		// Create a set of labels of the sub-nodes
		$getSubNodeLabels = function( $nodes ) use( &$getSubNodeLabels )
		{
			$result = array();
			foreach ( $nodes as $nodeLabel => $node )
			{
				if ( $node['modelType'] == 'cm.xsd#cm_Concept' )
				{
					$result[] = $nodeLabel;
				}
				if ( ! isset( $node['children'] ) ) continue;
				$result = array_merge( $result, $getSubNodeLabels( $node['children'] ) );
			}
			return $result;
		};

		$factsLayout = array_filter( $getFactsLayout( $nodes, $contexts, null, 'set', $lineItems, null, null ), function( $node )
		{
			return count( $node['columns'] );
		} );

		if ( ! $factsLayout )
		{
			return '';
		}

		// Update the last entry with details of the current report
		end( $resultFactsLayout );
		$resultFactsLayout[ key( $resultFactsLayout ) ] = array(
			'axes' => array_keys( array_filter( $axes, function( $axis ) { return isset( $axis['dimension'] ); } ) ),
			'data' => $factsLayout
		);

		if ( ! $this->includeReport ) return '';

		$dropEmptyColumns = function( $rows, &$columnHierarchy, &$columnCount, &$headerColumnCount, &$columnRefs, &$columnLayout, &$contextRefColumns, &$factsLayout, $foundDroppableTypes ) use( &$createContextRefColumns, $hasReportDateAxis, &$removeColumn )
		{
			$columnsToDrop = array();
			$flipped = array_flip( $columnRefs );
			for ( $columnIndex = 0; $columnIndex<$headerColumnCount; $columnIndex++ )
			{
				$empty = true;
				$zerosOnly = true;
				$firstRow = reset( $rows );
				$lastRow = end( $rows );

				foreach ( $rows as $label => $row )
				{
					if ( ! isset( $row['columns'][ $columnIndex ] ) ) continue;
					if ( $row['element']['periodType'] == 'instant' && isset( $row['node']['preferredLabel'] ) )
					{
						$preferredLabel = $row['node']['preferredLabel'];
						$balanceItem =	$preferredLabel == XBRL_Constants::$labelRolePeriodEndLabel ||
										$preferredLabel == XBRL_Constants::$labelRolePeriodStartLabel ||
										$hasReportDateAxis && ( $row == $firstRow || $row == $lastRow ) ;
						if ( $balanceItem ) continue;  // Ignore closing balance
					}

					if ( ! $row['columns'][ $columnIndex ]['value'] ) continue;

					$empty = false;
					break;
				}
				if ( $empty ) $columnsToDrop[] = $flipped[ $columnIndex ];
			}

			if ( ! $columnsToDrop ) return;

			foreach ( $columnsToDrop as $columnIndex )
			{
				$removeColumn( $columnHierarchy, $columnIndex );
				$headerColumnCount--;
				$columnCount--;
				$columnLayout = array();
				$contextRefColumns = $createContextRefColumns( $columnHierarchy );
				$columnRefs = array_flip( array_values( array_unique( $contextRefColumns ) ) );
			}

			// BMS 2019-06-06 In the future it may be better to index the column facts by
			//                the index in the leaf layer of the columnHierarchy structure.
			// Correct the facts layout rows
			foreach ( $factsLayout as $rowIndex => $row )
			{
				// Make sure the facts are sorted in column order so they are shuffled into their new places without causing a clash
				ksort( $row['columns'] );

				// Create a new column index for each fact, effectively shuffling them up
				foreach ( $row['columns'] as $columnIndex => $fact )
				{
					if ( ! isset( $contextRefColumns[ $fact['contextRef'] ] ) ) continue;
					unset( $factsLayout[ $rowIndex ]['columns'][ $columnIndex ] );
					$newColumnIndex = $columnRefs[ $contextRefColumns[ $fact['contextRef'] ] ];
					$factsLayout[ $rowIndex ]['columns'][ $newColumnIndex ] = $fact;
				}
			}
		};

		$droppableTypesList = array( 'adjustment', 'rollup', 'rollforward', 'rollforwardinfo', 'set' );
		$foundDroppableTypes = array_filter( $factSetTypes, function( $type ) use ( $droppableTypesList ) { return in_array( $type, $droppableTypesList ); } );
		if ( $lineItems || $foundDroppableTypes )
		{
			$dropEmptyColumns( $factsLayout, $columnHierarchy, $columnCount, $headerColumnCount, $columnRefs, $columnLayout, $contextRefColumns, $factsLayout, $foundDroppableTypes );
		}

		// Remove single member rows. These will be multi-member axes but for which there is data for only one member
		$r = count( $columnLayout ) - 1;
		while ( $r > 1 )
		{
			$memberLabels = array_unique( array_map( function( $column ) { return $column['member-label']; }, $columnLayout[ $r ] ) );
			if ( count( $memberLabels ) == 1 )
			{
				$memberLabel = reset( $memberLabels );
				if ( ! is_null( $memberLabel ) )
				{
					$dimensionLabel = $columnLayout[ $r ][0]['dimension-label'];
					// Add the dimension to the list of single members
					$singleMemberAxes[ $dimensionLabel ] = $axes[ $dimensionLabel ];
					// But leave on the member with member label
					$memTaxonomy = $this->taxonomy->getTaxonomyForXSD( $memberLabel );
					$memElement = $memTaxonomy->getElementById( $memberLabel );
					$singleMemberAxes[ $dimensionLabel ]['members'] = array( $memberLabel => new QName( $memTaxonomy->getPrefix(), $memTaxonomy->getNamespace(), $memElement['name'] ) );
					$pos = array_search( $dimensionLabel, $multiMemberAxes );
					unset( $multiMemberAxes[ $pos ] );
					$multiMemberAxesCount = count( $multiMemberAxes );

					unset( $columnLayout[ $r ] );
					unset( $columnLayout[ $r - 1 ] );
					$headerRowCount -= 2;
				}
			}
			$r--;
			$r--;
		}
		unset( $r );
		$columnLayout = array_values( $columnLayout );

		// Generate a top for the report table
		$top = function( $reportDateColumn, $headerColumnCount, $columnWidth ) use ( &$top, $lang )
		{
			return
				"	<div class='report-section' style='display: grid; grid-template-columns: 1fr; '>" .
				"		<div style='display: grid; grid-template-columns: auto 1fr;'>" .
				( $this->includeWidthcontrols ?
				"			<div class='report-table-controls'>" .
				"				<div class='control-header'>" . $this->getConstantTextTranslation( $lang, 'Columns' ) . ":</div>" .
				"				<div class='control-wider'>&lt;&nbsp;" . $this->getConstantTextTranslation( $lang, 'Wider' ) . "&nbsp;&gt;</div><div>|</div>" .
				"				<div class='control-narrower'>&gt;&nbsp;" . $this->getConstantTextTranslation( $lang, 'Narrower' ) . "&nbsp;&lt;</div>" .
				"			</div><div></div>"
					: ''
				) .
				"			<div class='report-table' style='display: grid; grid-template-columns: 400px $reportDateColumn repeat( $headerColumnCount, $columnWidth ); grid-template-rows: repeat(10, auto);' >";
		};

		// Generate a tail for the report table
		$tail = function( &$footnotes, $hasReportDateAxis, $headerColumnCount, $lastLayoutColumns = null ) use( &$tail )
		{
			$reportTail =
				"				<div class='report-line line-item abstract final'></div>";

			if ( $hasReportDateAxis )
			{
				$reportTail .=
					"				<div class='report-line line-item abstract final'></div>";
			}

			$firstFact = "first-fact";
			for ( $i = 0; $i < $headerColumnCount; $i++ )
			{
				$domain = $lastLayoutColumns && ( $lastLayoutColumns[ $i ]['default-member'] || $lastLayoutColumns[ $i ]['domain-member'] || $lastLayoutColumns[ $i ]['in-domain'] ) ? 'domain' : '';
				$reportTail .= "				<div class='report-line abstract-value final $firstFact $domain' ></div>";
				$firstFact = '';
			}

			$reportTail .=
				"			</div>" .
				"			<div></div>" .
				"		</div>";

			if ( $footnotes )
			{
				$footnoteHtml = "";
				foreach ( $footnotes as $hash => $footnote )
				{
					$footnoteHtml .= "<div class='footnote-id'>{$footnote['id']}</div><div class='footnote-text'>{$footnote['text']}</div>";
				}
				$reportTail .=
					"		<div class='xbrl-footnotes'>" .
					"			<div class='footnote-header'>Footnotes</div>" .
					"			<div class='footnote-list'>" .
					"				$footnoteHtml" .
					"			</div>" .
					"		</div>";
			}

			$reportTail .=
				"	</div>" .
				"";

			return $reportTail;
		};

		$reportDateColumn = $hasReportDateAxis ? ' auto ' : '';

		// Now workout the layout.
		$createLayout = function(
				$accumulatedTables, &$footnotes, $nodes, $lineItems = false, $patternType = 'set',
				$main = false,  &$row = 0, $headersDisplayed = false, $depth = 0, $excludeEmptyHeadrers = false, $lasts = array() )
			use( &$createLayout, &$getStatedFacts, &$getSubNodeLabels, $instance, &$axes,
				 $columnCount, &$columnLayout, &$columnRefs, &$contextRefColumns, $elr,
				 &$contexts, $factsLayout, &$resultFactsLayout, $headerColumnCount, $headerRowCount, $rowCount,
				 &$factSetTypes, $hasReportDateAxis, &$tail, &$top, &$network,
				 $entityQName, &$report, $observer, &$nodesToProcess,
				 $reportDateColumn, &$singleMemberAxes, $lang, &$allowConstrained
			)
		{
			$divs = array();
			$trailingNodes = true;

			$renderHeader = function( $nodeTaxonomy, $nodeElement, $columnCount, $columnLayout, $headerRowCount, $hasReportDateAxis, $lineItems, $text, &$divs ) use( $lang, $elr )
			{
				// This is the line-item header
				$divs[] =	"			<div class='report-header line-item' style='grid-area: 1 / 1 / span $headerRowCount / span 1;'>$text</div>";
				if ( $hasReportDateAxis )
				{
					$reportDateAxisTaxonomy = $nodeTaxonomy->getTaxonomyForXSD( $hasReportDateAxis );
					$reportDateAxisElement = $reportDateAxisTaxonomy->getElementById( $hasReportDateAxis );
					$text = $reportDateAxisTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $reportDateAxisTaxonomy->getTaxonomyXSD() . '#' . $reportDateAxisElement['id'], null, $lang, $elr );
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
			};

			$firstRow = reset( $nodes );
			$lastRow = end( $nodes );
			if ( $patternType == 'rollup' ) $depth++;

			foreach ( $nodes as $label => $node )
			{
				if ( $nodesToProcess && ! in_array( $label, $nodesToProcess ) ) continue;

				$first = $node == $firstRow;
				$last = $node == $lastRow;
				$trailingNodes = true;

				$abstractLineItems = $node['modelType'] == 'cm.xsd#cm_Abstract';
				$thisLineItems = $node['modelType'] == 'cm.xsd#cm_LineItems';
				$lineItems |= $thisLineItems | $abstractLineItems;
				if ( $lineItems )
				{
					/** @var XBRL $nodeTaxonomy */
					$nodeTaxonomy = $this->taxonomy->getTaxonomyForXSD( $label );
					$nodeElement = $nodeTaxonomy->getElementById( $label );
					$preferredLabels = isset( $node['preferredLabel'] ) ? array( $node['preferredLabel'] ) : null;
					// Do this because $label includes the preferred label roles and the label passed cannot include it
					$title =  "{$nodeTaxonomy->getPrefix()}:{$nodeElement['name']}";
					$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $nodeTaxonomy->getTaxonomyXSD() . '#' . $nodeElement['id'], $preferredLabels, $lang /* , $elr */ );
					if ( ! $text )
					{
						$text = $nodeElement['name'];
					}

					if ( isset( $nodeElement['balance'] ) || isset( $nodeElement['periodType'] ) )
					{
						$titleSuffix = array();
						if ( isset( $nodeElement['balance'] ) ) $titleSuffix[] = $nodeElement['balance'];
						if ( isset( $nodeElement['periodType'] ) ) $titleSuffix[] = $nodeElement['periodType'];
						$title .= " (" . implode( " - ", $titleSuffix ) . ")";
					}

					// This is where the headers are laid out
					if ( ! $headersDisplayed )
					{
						$renderHeader( $nodeTaxonomy, $nodeElement, $columnCount, $columnLayout, $headerRowCount, $hasReportDateAxis, $lineItems, $text, $divs );
						$headersDisplayed = true;
					}

					if ( $thisLineItems )
					{
					}
					else if ( $node['modelType'] == 'cm.xsd#cm_Table' || $node['modelType'] == 'cm.xsd#cm_Axis' )
					{
					}
					else if ( $node['modelType'] == 'cm.xsd#cm_Abstract' )
					{
						if ( $excludeEmptyHeadrers )
						{
							$subNodeLabels = $getSubNodeLabels( $node['children'] );
							// Create a set of fact layout elements for the sub-nodes
							$subNodesFactsLayout = array_filter( array_intersect_key( $factsLayout, array_flip( $subNodeLabels ) ), function( $node )
							{
								return count( $node['columns'] );
							} );

							if ( ! $subNodesFactsLayout )
							{
								continue;
							}
						}

						$row++;
						$main = false;
						if ( isset( $node['patterntype'] ) )
						{
							if ( $node['patterntype'] == 'rollup' )
							{
								$main = $patternType != $node['patterntype'];
							}
							// $patternType = $node['patterntype'];
						}
						// Abstract rows laid out here
						$startDateAxisStyle = $hasReportDateAxis ? 'style="grid-column-start: span 2;"' : '';
						$divs[] = "		<div class='report-line line-item abstract depth$depth' data-row='$row' $startDateAxisStyle title='$title' name='{$nodeElement['name']}'>$text</div>";
						$firstFact = "first-fact";
						$lastLayoutColumns = end( $columnLayout );
						for ( $i = 0; $i < $headerColumnCount; $i++ )
						{
							$domain = $lastLayoutColumns[ $i ]['default-member'] || $lastLayoutColumns[ $i ]['domain-member'] || $lastLayoutColumns[ $i ]['in-domain'] ? 'domain' : '';
							$divs[] = "<div class='report-line abstract-value $firstFact $domain' data-row='$row'></div>";
							$firstFact = '';
						}
					}
					else
					{
						// All other (concept) rows laid out here
						if ( ! $allowConstrained )
						{
							if ( $headerColumnCount > 1 )
							{
								$allowConstrained = true;
							}
							else if ( $patternType != 'text' )
							{
								$allowConstrained = ! $this->taxonomy->context->types->resolvesToBaseType($nodeElement['type'], array( 'nonnum:escapedItemType' ) );
							}
						}

						$row++;
						$totalClass = isset( $node['total'] ) && $node['total'] ? 'total' : '';
						$totalClass .= $main && $totalClass ? ' main' : '';

						$rowDetails = isset( $factsLayout[ $label ] ) ? $factsLayout[ $label ] : array();
						if ( $rowDetails )
						{
							$columnFacts = isset( $rowDetails['columns'] ) ? $rowDetails['columns'] : array();
							ksort( $columnFacts );

							// This is used to hold a map of contexts for formulas. Normally the map will be contextRef -> contextRef
							// But when there is a roll forward fact set then it will allow the contextRef of a fact of a formula to
							// be remapped to the correct one for the fact set
							$periodStart = isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodStartLabel;
							$contextRefMap = $periodStart
								? array_reduce( $columnFacts, function( $carry, $fact ) { $carry[ $fact['contextRef'] ] = isset( $fact['priorContextRef'] ) ? $fact['priorContextRef'] : $fact['contextRef']; return $carry; }, array() )
								: array();

							$rowClarkName = "{{$rowDetails['taxonomy']->getNamespace()}}{$rowDetails['element']['name']}";
							$conceptResults = isset( $report['formulasummaries'][ $elr ][ $rowClarkName ] )
								? $report['formulasummaries'][ $elr ][ $rowClarkName ]
								: ( isset( $report['formulasummaries'][ \XBRL_Constants::$defaultLinkRole ][ $rowClarkName ] )
									? $report['formulasummaries'][ \XBRL_Constants::$defaultLinkRole ][ $rowClarkName ]
									: array()
								  );

							// This line MUST appear after preferred labels have been processed
							$divs[] = "		<div class='report-line line-item $totalClass depth$depth' data-row='$row' title='$title'>$text</div>";

							$columns = array();
							// Look for the fact with $contextRef
							if ( $hasReportDateAxis )
							{
								$axis = $axes[ $hasReportDateAxis ];
								// Find the segment with $hasReportDateAxis
								if ( isset( $node['preferredLabel'] ) && $last ) // && $node['preferredLabel'] == XBRL_Constants::$labelRoleRestatedLabel )
								{
									$totalClass = 'total';
								}

								$reportAxisMemberClass = 'report-axis';
								$fact = reset( $columnFacts );
								$text = ''; // By default there is no text for the column
								$occ = $instance->getContextSegment( $contexts[ $fact['contextRef'] ] );
								if ( $occ && isset( $occ['explicitMember'] ) )
								{
									$reportDateAxisTaxonomy = $nodeTaxonomy->getTaxonomyForXSD( $hasReportDateAxis );
									$reportDateAxisElement = $reportDateAxisTaxonomy->getElementById( $hasReportDateAxis );
									$qname = $reportDateAxisTaxonomy->getPrefix() . ":" . $reportDateAxisElement['name'];

									$explicitMembers = $occ['explicitMember'];
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
													$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $memberTaxonomy->getTaxonomyXSD() . '#' . $memberElement['id'], null, $lang, $elr );
												}
											}
										}
									}
								}
								else if ( $axis['default-member'] )
								{
									$text = $nodeTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $axis['default-member'], null, $lang, $elr );
									$reportAxisMemberClass .= ' default-member';
								}

								$divs[] = "<div class='report-line member-label value $reportAxisMemberClass' title='$title'>$text</div>";
							}
							else if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRolePeriodEndLabel && $patternType == 'rollforward' )
							{
								$totalClass = 'total';
							}
							else if ( $patternType == 'complex' && ( $this->findConceptInFormula( $report['formulasummaries'][ $elr ], $nodeTaxonomy, $nodeElement ) /* || $this->findConceptInFormula( $report['formulasummaries'][ \XBRL_Constants::$defaultLinkRole ], $nodeTaxonomy, $nodeElement ) */ ) )
							{
								$totalClass = 'total';
							}

							// The last row of the column layout is a list of columns and ids
							$lastRowLayout = end( $columnLayout );
							$formulaTestToReport = false;

							foreach ( $columnFacts as $columnIndex => $fact )
							{
								if ( isset( $fact['contextRefRestated'] ) && $fact['contextRefRestated'] )
								{
									$fact['contextRef'] = $fact['contextRefRestated'];
								}

								$footnoteIds = array();

								$fn = $instance->getFootnoteForFact( $fact );
								if ( $fn )
								{
									foreach ( $fn as $index => $footnote )
									{
										if ( isset( $footnotes[ md5( $footnote ) ] ) )
										{
											$footnoteIds[] = $footnotes[ md5( $footnote ) ]['id'];
											continue;
										}

										$footnotes[ md5( $footnote ) ] = array( 'text' => $footnote );
										$footnotes[ md5( $footnote ) ]['id'] = count( $footnotes );
										$footnoteIds[] = count( $footnotes );
									}
								}

								// Check that the column is still reportable.  It might have been removed as empty
								if ( ! isset( $lastRowLayout[ $columnIndex ] ) ) continue;
								$factIsNumeric = is_numeric( $fact['value'] );
								$currentColumn = $lastRowLayout[ $columnIndex ];
								$type = (string) XBRL_Instance::getElementType( $fact );
								$valueClass = empty( $type ) ? '' : $nodeTaxonomy->valueAlignment( $type, $instance );
								$columnTotalClass = '';
								if ( ( $currentColumn['default-member'] || $currentColumn['domain-member'] || $currentColumn['in-domain'] /** || $currentColumn['root-member'] */ ) )
								{
									if ( $valueClass != 'left' )
									{
										$columnTotalClass = ' domain';
									}
									$valueClass .= ' domain';
								}

								if ( $columnIndex === 0 ) $valueClass .= ' first-fact';

								$copyFact = $fact;
								if ( isset( $node['preferredLabel'] ) && $node['preferredLabel'] == XBRL_Constants::$labelRoleNegatedLabel && $factIsNumeric )
								{
									$copyFact['value'] *= -1;
								}

								// if ( isset( $fact['decimals'] ) && $fact['decimals'] < 0 ) $fact['decimals'] = 0;
								$value = $nodeTaxonomy->formattedValue( $copyFact, $instance, false );
								if ( strlen( $copyFact['value'] ) && is_numeric( $copyFact['value'] ) )
								{
									if ( $this->negativeStyle == NEGATIVE_AS_BRACKETS )
									{
										if ( $copyFact['value'] < 0 )
										{
											$valueClass .= ' neg';
											$copyFact['value'] = abs( $fact['value'] );
											$value = "(" . $nodeTaxonomy->formattedValue( $copyFact, $instance, false ) . ")";
										}
										else $valueClass .= ' pos';
									}
								}

								$footnoteClass = "";
								$footnoteDiv = "<div></div>";
								if ( $footnoteIds )
								{
									$footnoteClass = 'xbrl-footnote';
									$footnoteDiv = "<div class='footnote-id'>" . implode( ',', $footnoteIds ) . "</div>";
								}

								$valueDiv = "<div class='value'>$value</div>";

								$title  = '';
								$statusDiv = '<div></div>';
								$statusClass = '';

								// Begin checking for formula test results
								// The context of the formula may not be the correct reporting formula in an open/close (roll forward) fact set
								// So use the context ref map if one is available
								$contextRef = $periodStart && $contextRefMap && isset( $contextRefMap[ $fact['contextRef'] ] ) ? $contextRefMap[ $fact['contextRef'] ] : $fact['contextRef'];
								$isTotalRow = strpos( $totalClass, 'total' ) !== false;
								if ( isset( $conceptResults[ $contextRef ] ) && ( $isTotalRow || $currentColumn['dimension-label'] ) )
								{
									$formulaTestToReport = $conceptResults[ $contextRef ];
								}

								// Allow a formula to take precedence over a calculation network
								if ( $formulaTestToReport && ( $isTotalRow || $currentColumn['in-domain'] || ! $currentColumn['dimension-label'] ) )
								{
									if ( $formulaTestToReport['satisfied'])
									{
										$statusClass = 'match';
										$title = "The formula calculation value matches the reported value ({$formulaTestToReport['message']})";
									}
									else
									{
										$statusClass = "mismatch";
										$difference = $formulaTestToReport['value'] - $fact['value'];
										$title = "The formula calculation does not match the reported value. The difference is $difference ({$formulaTestToReport['message']})";
										unset( $difference );
									}

									$statusDiv = "<div class='$statusClass'></div>";

									// The test has been reported
									$formulaTestToReport = false;
								}
								else
								{
									if ( isset( $rowDetails['calcTotals'] ) )
									{
										$totalValue = $instance->getNumericPresentation( $fact );
										if ( isset( $rowDetails['calcTotals'][ $columnIndex ] ) )
										{
											$calcTotal = $rowDetails['calcTotals'][ $columnIndex ];
											if ( ( $decimals = $instance->getDecimals( $fact ) ) && is_integer( $decimals ) )
											{
												$calcTotal = round( $calcTotal, $decimals );
												unset( $decimals );
											}
											// $calcDetails = join( ' + ', array_values( $rowDetails['calcDetails'][ $columnIndex ] ) );
											$calcDetails = join( ' + ', XBRL::array_reduce_key( $rowDetails['calcDetails'][ $columnIndex ], function( $carry, $detail, $key )
											{
												$carry[] = "($key) $detail";
												return $carry;
											}, array() ) );
											if ( $calcTotal == $totalValue )
											{
												$statusClass = 'match';
												$title = "The rollup total matches the sum of the report component values ($calcTotal = $calcDetails)";
											}
											else
											{
												$statusClass = "mismatch";
												$difference = $calcTotal - $totalValue;
												$title = "The rollup total does not match the sum of the calculation components. The difference is $difference ($calcTotal = $calcDetails)";
												unset( $difference );
											}
											unset( $calcDetails );

											$statusDiv = "<div class='$statusClass'></div>";
										}
										unset( $totalValue );
										unset( $calcTotal );
									}
									else if ( $factIsNumeric && $currentColumn['in-domain'] && $fact['aggregates'] )
									{
										$totalValue = $instance->getNumericPresentation( $fact );
										// Add up the components
										$aggregateValues = array();
										foreach ( $fact['aggregates'] as $componentFact )
										{
											if ( ! is_numeric( $componentFact['value'] ) ) continue;
											$aggregateValues[] = $instance->getNumericPresentation( $componentFact );
										}
										$aggregateValue = array_sum( $aggregateValues );
										$aggregateValuesList = join( ', ', $aggregateValues );

										if ( $aggregateValue == $totalValue )
										{
											if ( $statusClass != 'mismatch' ) // If the rollup reported a mismatch continue to do so but update the title
											{
												$statusClass = 'match';
											}
											if ( $title ) $title .= ".  ";
											$title .= "The aggregate total matches the sum of the component member values ($aggregateValuesList)";
										}
										else
										{
											$statusClass = "mismatch";
											if ( $title ) $title .= ".  ";
											$title .= "The aggregate total does not match the sum of the component member values ($aggregateValue: $aggregateValuesList)";
										}

										$statusDiv = "<div class='$statusClass'></div>";

										unset( $aggregateValue );
										unset( $aggregateValues );
										unset( $aggregateValuesList );
										unset( $totalValue );
										unset( $componentFact );
									}
								}

								if ( $title )
								{
									$title = "title='$title'";
								}

								$columns[ $columnIndex ] = "<div class='report-line value $totalClass $columnTotalClass $valueClass $statusClass $footnoteClass' $title data-row='$row'>$statusDiv$footnoteDiv$valueDiv</div>";
							}

							unset( $fact ); // Gets confusing having old values hanging around
							unset( $columnFacts );

							// Fill in
							if ( is_array( $lastRowLayout ) )
							foreach ( $lastRowLayout as $columnIndex => $column )
							{
								if ( isset( $columns[ $columnIndex ] ) ) continue;
								$firstFact = $columnIndex === 0 ? 'first-fact' : '';
								$domain = $column['default-member'] || $column['domain-member'] || $column['in-domain'] ? 'domain' : '';
								$columns[ $columnIndex ] = "<div class='report-line no value $totalClass $firstFact $domain' data-row='$row'></div>";
							}

							ksort( $columns );
							$divs = array_merge( $divs, $columns );

							if ( $totalClass )
							{
								$lastLayoutColumns = end( $columnLayout );
								for ( $c = 0; $c < $columnCount - count( $columns); $c++ )
								{
									$divs[] = "<div class='report-line line-item after-total'></div>";
								}
								$firstFact = "first-fact";
								for ( $c = 0; $c < count( $columns ); $c++ )
								{
									$domain = $lastLayoutColumns[ $c ]['default-member'] || $lastLayoutColumns[ $c ]['domain-member'] || $lastLayoutColumns[ $c ]['in-domain'] ? 'domain' : '';
									$divs[] = "<div class='report-line value after-total $firstFact $domain'></div>";
									$firstFact = '';
								}
							}
							unset( $columns );
						}
					}
				}

				if ( ! isset( $node['children'] ) || ! $node['children'] ) continue;

				// May need to present a sub table
				if ( isset( $network['tables'][ $label ] ) && ! isset( $accumulatedTables[ $label ] ) )
				{
					// Nested report

					// Create the next report section
					$nextAccumulatedTables = $accumulatedTables;
					$nextAccumulatedTables[ $label ] = $network['tables'][ $label ];

					$resultFactsLayout[ $label ] = array();
					$render = $this->renderReportTable( $network, $node['children'], $elr, $instance, $entityQName, $report, $observer, $resultFactsLayout, $nextAccumulatedTables, $nodesToProcess, true, $excludeEmptyHeadrers, $row, array_merge( $lasts, array( $last ) ), $allowConstrained, $lang, $text );

					if ( ! $render ) continue;

					// Close out the earlier section
					$divs[] = $tail( $footnotes, $hasReportDateAxis, $headerColumnCount, end( $columnLayout ) );
					$divs[] = $render;

					// Only report a sub-table if there is something worth reporting.
					// If its the last row of the last sub-report or the report did not produce ab output
					if ( count( $lasts ) == count( array_filter( $lasts ) ) )
					{
						$trailingNodes = false;
						continue;
					}

					// Create slicers and opening <div> elements for the rest of the previous section
					$divs[] = $this->renderSlicers( $network, $instance, $entityQName, $elr, $singleMemberAxes, null, new ContextsFilter( $instance, $contexts ), $lang );
					$columnWidth = $headerColumnCount == 1 || array_search( 'text', $factSetTypes ) ? 'minmax(300px, max-content)' : '100px';
					$divs[] = $top( $reportDateColumn, $headerColumnCount, $columnWidth );
					unset( $nextAccumulatedTables );

					// Make sure the headings are repeated
					$headersDisplayed = false;
				}
				else
				{
					$result = $createLayout( $accumulatedTables, $footnotes, $node['children'], $lineItems, isset( $node['patterntype'] ) ? $node['patterntype'] : 'set', $main, $row, $headersDisplayed, $depth, $excludeEmptyHeadrers, array_merge( $lasts, array( $last ) ) );
					$divs = array_merge( $divs, $result['divs'] );
					$headersDisplayed = $result['headersDisplayed'];
					$trailingNodes = $result['trailingNodes'];
				}
			}

			return array( 'divs' => $divs, 'trailingNodes' => $trailingNodes, 'headersDisplayed' => $headersDisplayed, 'lastLayoutColumns' => end( $columnLayout ) );
		};

		$columnWidth = ! $hasReportDateAxis && ( $headerColumnCount == 1 || array_search( 'text', $factSetTypes ) ) ? 'minmax(300px, max-content)' : '100px';

		$footnotes = array();

		$layout = $createLayout( $accumulatedTables, $footnotes, $nodes, $lineItems );
		$reportTable = $this->renderSlicers( $network, $instance, $entityQName, $elr, $singleMemberAxes, null, new ContextsFilter( $instance, $contexts ), $lang ) .
						$top( $reportDateColumn, $headerColumnCount, $columnWidth ) .
						implode( '', $layout['divs'] );

		// When there are no trailing nodes, no header will be added so no $tail is required.
		if ( $layout['trailingNodes'] )
		{
			$reportTable .= $tail( $footnotes, $hasReportDateAxis, $headerColumnCount, $layout['lastLayoutColumns'] );
		}

		return $reportTable;
	}

	/**
	 * Render a report with columns for any years and dimensions
	 * @param array $network			An array generated by the validsateDLR process
	 * @param string $elr				The extended link role URI
	 * @param XBRL_Instance $instance	The instance being reported
	 * @param QName $entityQName
	 * @param array	$report				The evaluated formulas
	 * @param \Log_observer $observer		An obsever with any validation errors
	 * @param bool $hasReport
	 * @param bool $echo				If true the HTML will be echoed
	 * @param array $factsData			@reference If not null an array
	 * @return string
	 */
	private function renderNetworkReport( $network, $elr, $instance, $entityQName, $report, $observer, &$hasReport = false, $lang = null, $echo = true, &$factsData = null )
	{
		$componentTable = $this->renderComponentTable( $network, $elr, $lang );

		$structureTable = $this->renderModelStructure( $network, $elr, $lang );

		// Filter the contexts by axes
		// All the contexts without
		$accumulatedTables = isset( $network['tables'][null]) ? array( $network['tables'][null] ) : array();

		if ( count( $network['hierarchy'] ) == 1 && isset( $network['tables'][ key( $network['hierarchy'] ) ] ) )
		{
			$accumulatedTables[ key( $network['hierarchy'] ) ] = $network['tables'][ key( $network['hierarchy'] ) ];
		}

		$nodesToProcess = null;

		$factsLayouts[] = array();
		$excludeEmptyHeadrers = false;
		$allowConstrained = false;
		$row = 0;
		$reportTable = $this->renderReportTable(
			$network, $network['hierarchy'], $elr, $instance, $entityQName, $report,
			$observer, $factsLayouts, $accumulatedTables, $nodesToProcess,
			false, $excludeEmptyHeadrers, $row, array(), $allowConstrained, $lang );

		$factsLayouts = array_filter( $factsLayouts );
		$hasReport = ! empty( $reportTable );

		if ( $this->includeReport && ! $reportTable )
		{
			$reportTable =
				"	<div style='display: grid; grid-template-columns: 1fr; '>" .
				"		<div style='display: grid; grid-template-columns: auto 1fr;'>" .
				$this->getConstantTextTranslation( $lang, "There is no data to report" ) .
				"		</div>" .
				"	</div>";
		}

		if ( is_array( $factsData ) )
		{
			$factsData = $this->createFactsData( $network, $elr, $instance, $entityQName, $factsLayouts, $lang );
		}

		$renderFactsTable = $this->renderFactsTable( $network, $elr, $instance, $entityQName, $factsLayouts, $lang );

		$businessRules = $this->renderBusinessRules( $network, $elr, $instance, $entityQName, $factsLayouts, $report, $lang );

		XBRL_Log::getInstance()->info( $elr );

		$checkboxes = array();
		$count = 0;

		if ( $this->includeComponent )
		{
			$count++;
			$checked = 'checked';
			$checkboxes['includeComponent'] =	"<input type='checkbox' name='report-selection' id='report-selection-component' data-class='component-table' $checked />" .
												"<label for='report-selection-component'>" . $this->getConstantTextTranslation( $lang, 'Component' ) . "</label>";
		}

		if ( $this->includeStructure )
		{
			$count++;
			$checked = $this->showAllGrids ? 'checked' : '';
			$checkboxes['includeStructure'] =	"<input type='checkbox' name='report-selection' id='report-selection-structure' data-class='structure-table' $checked />" .
												"<label for='report-selection-structure'>" . $this->getConstantTextTranslation( $lang, 'Structure' ) . "</label>";
		}

		if ( $this->includeSlicers )
		{
			$count++;
			$checked = 'checked';
			$checkboxes['includeSlicers'] =		"<input type='checkbox' name='report-selection' id='report-selection-slicers' data-class='slicers-table' $checked />" .
												"<label for='report-selection-slicers'>" . $this->getConstantTextTranslation( $lang, 'Slicers' ) . "</label>";
		}

		if ( $this->includeReport )
		{
			$count++;
			$checked = 'checked';
			$checkboxes['includeReport'] =		"<input type='checkbox' name='report-selection' id='report-selection-report' data-class='report-section' $checked />" .
												"<label for='report-selection-report'>" . $this->getConstantTextTranslation( $lang, 'Rendering' ) . "</label>";
		}

		if ( $this->includeFactsTable )
		{
			$count++;
			$checked = $this->showAllGrids ? 'checked' : '';
			$checkboxes['includeFactsTable'] =	"<input type='checkbox' name='report-selection' id='report-selection-facts' data-class='facts-section' $checked />" .
												"<label for='report-selection-facts'>" . $this->getConstantTextTranslation( $lang, 'Facts' ) . "</label>";
		}

		if ( $this->includeBusinessRules )
		{
			$count++;
			$checked = $this->showAllGrids ? 'checked' : '';
			$checkboxes['includeBusinessRules'] =	"<input type='checkbox' name='report-selection' id='report-selection-Rules' data-class='business-rules-section' $checked />" .
													"<label for='report-selection-rules'>" . $this->getConstantTextTranslation( $lang, 'Rules' ) . "</label>";
		}

		$allowConstrained = $allowConstrained && $count < 2 || ( ! $this->showAllGrids && $this->includeCheckboxControls );

		$hasTextFactSet = function() use( $network )
		{
			$hasText = false;
			\XBRL::processAllNodes( $network['hierarchy'], function( $node, $id ) use( &$hasText )
			{
				if ( $node['modelType'] == 'cm.xsd#cm_LineItems' )
				{
					if ( $node['patterntype'] == 'text' )
					{
						$hasText = true;
						return false;
					}
				}

				return true;
			} );

			return $hasText;
		};

		// If a network includes a text block then there should be no column constraints
		if ( $allowConstrained && $hasTextFactSet() ) $allowConstrained = false;

		$report = ( $this->includeCheckboxControls ?
			"<div class='report-selection'>" .
			"	<span class='report-selection-title'>" . $this->getConstantTextTranslation( $lang, 'Rendering sections' ) . ":</span>" .
				join( '', $checkboxes ) .
			"</div>" : '' ) .

			// This is just an idea
			// $this->renderFactSetLinks( $network, $elr ) .

			"<div class='report-outer " . ( $allowConstrained ? "allow-constrained" : "" ) . "'>" .
			"	<div class='model-structure constrained'>" .
				$componentTable . $structureTable . $reportTable . $renderFactsTable . $businessRules .
			"	</div>" .
			"	<div></div>" .
			"</div>";

		if ( $echo )
		{
			// file_put_contents("report.xml", $report );
			echo $report;
		}

		return $report;
	}

	/**
	 * Render a report with information about a taxonomy
	 * @param array $network			An array generated by the validsateDLR process
	 * @param Log_observer $observer	An obsever with any validation errors
	 * @param bool $hasReport
	 * @param bool $echo				If true the HTML will be echoed
	 * @return string
	 */
	public function renderTaxonomy( $networks, $observer, $lang = null, $echo = true, $allowConstrained = false )
	{
		$result = array();

		foreach( $networks as $elr => $network )
		{
			$componentTable = $this->renderComponentTable( $network, $elr, $lang );

			$structureTable = $this->renderModelStructure( $network, $elr, $lang );

			$report = "<div class='report-outer " . ( $allowConstrained ? "allow-constrained" : "" ) . "'>" .
				"	<div class='model-structure constrained'>" .
					$componentTable . $structureTable .
				"	</div>" .
				"	<div></div>" .
				"</div>";

			if ( $echo )
			{
				// file_put_contents("report.xml", $report );
				echo $report;
			}

			// error_log( $elr );
			$result[ $elr ] = array(
				'entities' => array( 'n/a' => $report ),
				'text' => $networks[ $elr ]['text'],
				'hasReport' => true
			);
		}

		return $result;
	}

	/**
	 * Render a report with columns for any years and dimensions
	 * @param array $network			An array generated by the validsateDLR process
	 * @param string $elr				The extended link role URI
	 * @param XBRL_Instance $instance	The instance being reported
	 * @param QName $entityQName
	 * @param array $factsLayout
	 * @param array $report
	 * @param string|null $lang			(optional: default = null) The language to use or null for the default
	 * @return string
	 */
	private function renderBusinessRules( $network, $elr, $instance, $entityQName, $reportFactsLayout, $report, $lang = null )
	{
		if ( ! $this->includeBusinessRules ) return '';

		$hideSection = $this->showAllGrids ? '' : 'hide-section';

		if ( ! isset( $this->calculationNetworks[ $elr]['calculations'] ) && ! isset( $report['formulasummaries'][ $elr] ) )
		{
			return "<div class='business-rules-section $hideSection'>" . $this->getConstantTextTranslation( $lang, 'There are no business rules' ) . "</div>";
		}

		$reportTable = '';

		if ( isset( $report['formulasummaries'][ $elr] ) )
		{
			$reportTable .=
				"	<div class='business-rules-section $hideSection' style='display: grid; grid-template-columns: auto auto; '>" .
				"		<div>" . $this->getConstantTextTranslation( $lang, 'Business Rules (formulas)' ) . "</div><div></div>";

			$summaries = &$report['formulasummaries'][ $elr];

			foreach( $network['concepts'] as $label => /** @var \lyquidity\xml\QName $qName */ $qName )
			{
				if ( ! isset( $summaries[ $qName->clarkNotation() ] ) ) continue;

				$reportTable .= "<div class='business-rules-table formulas' style='display: grid; grid-template-columns: 1fr;'>";

				$first = true;
				// $evaluations = count( $summaries[ $qName->clarkNotation() ] );
				foreach( $summaries[ $qName->clarkNotation() ] as $contextRef => $evaluation )
				{
					if ( $first )
					{
						$factTotalText = $this->taxonomy->getTaxonomyDescriptionForIdWithDefaults( $label, null, $lang, $elr );
						$header = "$factTotalText ({$evaluation['concept']})";

						$reportTable .=
							"			<div class='business-rules-roles formulas'>$header</div>" .
							"			<div class='business-rules-rows' style='display: grid; grid-template-columns: auto 1fr auto;' >" .
							"				<div class='business-rules-header context'>" . $this->getConstantTextTranslation( $lang, 'Context' ) . "</div>" .
							"				<div class='business-rules-header formula'>" . $this->getConstantTextTranslation( $lang, 'Formula' ) . "</div>" .
							"				<div class='business-rules-header details-link last'></div>";

						unset( $factTotalText );
						unset( $header );
					}

					$first = false;

					$status = $evaluation['satisfied'] ? 'match' : 'mismatch';
					$message = str_replace( '$','', $evaluation['message'] );

					$reportTable .=
						"			<div class='business-rules-row line-item'>{$evaluation['context']}</div>" .
						"			<div class='business-rules-row formula' title='{$evaluation['id']}/{$evaluation['label']}'>{$message}</div>" .
						"			<div class='business-rules-row calculated details-link last {$status}' title='Click to review evaluation details'>" .
						"				<a target='_blank' href='report-formulas.html?xbrl-validate-nonce=!!!&xbrl_validate_action=get-instance-formulas&xbrl-validate-log-id=!!!&formula-label={$evaluation['id']}'>...</a>" .
						"			</div>";
				}

				$reportTable .=
					"			<div class='business-rules-row final'></div>" .
					"			<div class='business-rules-row final'></div>" .
					"			<div class='business-rules-row final last'></div>";

				$reportTable .= "</div><div></div>";

				// error_log("$reportTable");

				unset( $contextRefs );
				unset( $evaluation );
				unset( $first );
			}

			unset( $label );
			unset( $qName );
			unset( $summaries );

			$reportTable .=
				"	</div>" .
				"<div></div>" .
				"";
		}

		if ( isset( $this->calculationNetworks[ $elr]['calculations'] ) )
		{
			$reportTable .=
				"	<div class='business-rules-section $hideSection' style='display: grid; grid-template-columns: auto 1fr; '>" .
				"		<div>" . $this->getConstantTextTranslation( $lang, 'Business Rules (calculations)' ) . "</div><div></div>";

			// Report each total
			foreach ( $this->calculationNetworks[ $elr]['calculations'] as $calcTotalLabel => $calculations )
			{
				$calcTotalText = $this->taxonomy->getTaxonomyDescriptionForIdWithDefaults( $calcTotalLabel, null, $lang, $elr );
				$calcTaxonomy = $this->taxonomy->getTaxonomyForXSD( $calcTotalLabel );
				$calcElement = $calcTaxonomy->getElementById( $calcTotalLabel );
				$header = "$calcTotalText ({$calcTaxonomy->getPrefix()}:{$calcElement['name']})";

				// Find the $factsLayout containing $calcTotalLabel
				$totalFactsLayout = array_filter( $reportFactsLayout, function( $factsLayout ) use( $calcTotalLabel )
				{
					return isset( $factsLayout['data'][ $calcTotalLabel ] );
				} );
				if ( ! $totalFactsLayout ) continue;
				$totalFactsLayout = array_map( function( $factsLayout ) { return $factsLayout['data']; }, $totalFactsLayout );
				// if ( ! isset( $factsLayout[ $calcTotalLabel ] ) ) continue;

				foreach ( $totalFactsLayout as $reportLabel => $factsLayout )
				{
					$columnCount = max( array_map( function( $row ) { return count( $row['columns'] ); }, $factsLayout ) );

					// And each period
					for ( $columnIndex = 0; $columnIndex < $columnCount; $columnIndex++ )
					{
						$totalRow = $factsLayout[ $calcTotalLabel ];
						if ( ! isset( $totalRow['calcTotals'][ $columnIndex ] ) ) continue;

						$contextRefs = array_unique( array_filter( array_values( array_map( function( $row ) use( $columnIndex )
						{
							return isset( $row['columns'][ $columnIndex ]['contextRef'] )
								? $row['columns'][ $columnIndex ]['contextRef']
								: false;
						}, $factsLayout ) ) ) );

						if ( ! $contextRefs ) continue;

						$contextsFilter = $instance-> getContexts()->getContextsByRef( $contextRefs );
						if ( ! $contextsFilter->count() )
						{
							// This should never happen
							error_log( "Oops.  No valid contexts found for context refs: " . implode( ",", $contextRefs ) );
							continue;
						}

						if ( $this->includeSlicers )
						{
							$reportTable .= $this->renderSlicers( $network, $instance, $entityQName, $elr, null, 'business-rules-slicers-table', $contextsFilter, $lang ) . "<div></div>";
						}

						$reportTable .=
							"		<div class='business-rules-table calculations' style='display: grid; grid-template-columns: 1fr;'>" .
							"			<div class='business-rules-roles'>$header</div>" .
							"			<div class='business-rules-rows' style='display: grid; grid-template-columns: 400px  repeat( 4, auto );' >" .
							"				<div class='business-rules-header line-item'>" . $this->getConstantTextTranslation( $lang, 'Line item' ) . "</div>" .
							"				<div class='business-rules-header calculated'>" . $this->getConstantTextTranslation( $lang, 'Calculated' ) . "</div>" .
							"				<div class='business-rules-header sign'></div>" .
							"				<div class='business-rules-header balance'>" . $this->getConstantTextTranslation( $lang, 'Balance' ) . "</div>" .
							"				<div class='business-rules-header decimals last'>" . $this->getConstantTextTranslation( $lang, 'Decimals' ) . "</div>";

						foreach ( $calculations as $calcLabel => $calcItem )
						{
							$row = isset( $factsLayout[ $calcLabel ] ) ? $factsLayout[ $calcLabel ] : null;
							if ( ! isset( $row['columns'][ $columnIndex ] ) ) continue;

							$calcTaxonomy = $this->taxonomy->getTaxonomyForXSD( $calcLabel );
							$calcElement = $calcTaxonomy->getElementById( $calcLabel );
							$calcQName = "{$calcTaxonomy->getPrefix()}:{$calcElement['name']}";

							$text = $calcTaxonomy->getTaxonomyDescriptionForIdWithDefaults( $calcLabel, null, $lang, $elr );
							$value = $row ? $instance->getNumericPresentation( $row['columns'][ $columnIndex ] ) : '';
							$sign = isset( $calcItem['weight'] ) && $calcItem['weight'] < 0 ? '-' : '+';

							$valueClass = "";
							if ( $this->negativeStyle == NEGATIVE_AS_BRACKETS )
							{
								if ( $value < 0 )
								{
									$valueClass = ' neg';
									$value = "(" . abs( $value ) . ")";
								}
								else $valueClass = ' pos';
							}

							$balance = isset( $calcElement['balance'] ) ? $calcElement['balance'] : '';
							$decimals = isset( $row['columns'][ $columnIndex ]['decimals'] ) ? $row['columns'][ $columnIndex ]['decimals'] : 'INF';
							$reportTable .=
								"				<div class='business-rules-row line-item' title='$calcQName'>$text</div>" .
								"				<div class='business-rules-row calculated $valueClass'>$value</div>" .
								"				<div class='business-rules-row sign'>$sign</div>" .
								"				<div class='business-rules-row balance'>$balance</div>" .
								"				<div class='business-rules-row decimals last'>$decimals</div>";
						}

						$calcTaxonomy = $this->taxonomy->getTaxonomyForXSD( $calcTotalLabel );
						$calcElement = $calcTaxonomy->getElementById( $calcTotalLabel );
						$calcQName = "{$calcTaxonomy->getPrefix()}:{$calcElement['name']}";

						$totalValue = $instance->getNumericPresentation($totalRow['columns'][ $columnIndex ]);
						$matchClass = $totalValue == $totalRow['calcTotals'][ $columnIndex ] ? 'match' : 'mismatch';

						$valueClass = "";
						if ( $this->negativeStyle == NEGATIVE_AS_BRACKETS )
						{
							if ( $totalValue < 0 )
							{
								$valueClass .= ' neg';
								$totalValue = "(" . abs( $totalValue ) . ")";
							}
							else $valueClass .= ' pos';
						}

						$balance = isset( $calcElement['balance'] ) ? $calcElement['balance'] : '';
						$decimals = isset( $totalRow['columns'][ $columnIndex ]['decimals'] ) ? $totalRow['columns'][ $columnIndex ]['decimals'] : 'INF';
						$reportTable .=
							"				<div class='business-rules-row line-item' title='$calcQName'>$calcTotalText</div>" .
							"				<div class='business-rules-row calculated total $valueClass $matchClass'>$totalValue</div>" .
							"				<div class='business-rules-row sign'></div>" .
							"				<div class='business-rules-row balance'>$balance</div>" .
							"				<div class='business-rules-row decimals last'>$decimals</div>";

						$reportTable .=
							"				<div class='business-rules-row line-item final'></div>" .
							"				<div class='business-rules-row calculated final'></div>" .
							"				<div class='business-rules-row sign final'></div>" .
							"				<div class='business-rules-row balance final'></div>" .
							"				<div class='business-rules-row decimals final last'></div>";

						$reportTable .=
							"			</div>" .
							"		</div>" .
							"		<div></div>";
					}
				}
			}

			$reportTable .=
				"	</div>" .
				"";
		}

		return $reportTable;
	}

	/**
	 * Validate the the taxonomy formulas against the instance
	 * @param $results array
	 * @param XBRL_Instance $instance
	 * @return XBRL_Formulas
	 */
	function validateFormulas( &$results, $instance, $indent, $roleFilterPart = null )
	{
		$log = XBRL_Log::getInstance();

		$results['formulas'] = array();
		$results['consistency'] = array();

		$this->taxonomy = $instance->getInstanceTaxonomy();
		if ( ! $this->taxonomy->getHasFormulas( true ) )
		{
			// $results['formulas'] = 'There are no formulas';
			$log->warning( "$indent  There are no formulas" );
			return null;
		}

		$formulas = new XBRL_Formulas();
		if ( ! $formulas->processFormulasAgainstInstances( $instance, null, null, $roleFilterPart ) )
		{
			// Report the failure
			$log->formula_validation( "Formulas failed", "The test failed to complete",
				array(
				)
			);
		}

		/**
		 * @var ConsistencyAssertion $consistencyAssertion
		 */
		foreach ( $formulas->getConsistencyAssertions() as $assertionName => $consistencyAssertion )
		{
			/*
				This block can be tested using these conformance examples
				30000 Assertions\31210-ConsistencyAssertion-Processing\31210-abs-low-ok-instance.xml
				30000 Assertions\31210-ConsistencyAssertion-Processing\31210-abs-low-not-ok-instance.xml
			 */
			$consistency = array(
				'id' => $consistencyAssertion->id,
				'label' => $consistencyAssertion->label,
				'description' => trim( $consistencyAssertion->description ),
				'type' => 'consistency',
				'absolute radius' => $consistencyAssertion->absoluteAcceptanceRadius,
				'proportional radius' => $consistencyAssertion->proportionalAcceptanceRadius,
				'radius-value' => $consistencyAssertion->getRadiusValue(),
				'strict' => $consistencyAssertion->strict,
				'satisfied' => $consistencyAssertion->getSatisfied(),
				'unsatisfied' => $consistencyAssertion->getUnsatisfied(),
				'formulas' => array(),
			);

			foreach ( $consistencyAssertion->formulas as $formulaName => $consistencyFormulas )
			{
				/** @var Formula $formula */
				foreach ( $consistencyFormulas as $formula )
				{
					$consistencyFormula = array(
						'id' => $formula->id,
						'label' => $formula->label,
						'value' => $formula->value,
						'linkbase' => $consistencyAssertion->linkbase,
						'parameters' => array(),
						'evaluations' => array(),
					);

					$consistencyFormula['parameters'] = array_map( function( $parameter )
					{
						return array(
							'label' => $parameter->label,
							'select' => $parameter->select
						);
					}, $formula->parameters );

					foreach ( $formula->factsContainer->facts[ $formula->label ] as $key => $derivedFact )
					{
						$evaluationResult = $formula->evaluationResults[ $key ];

						// Convert the vars indexed by clarkname to one indexed by prefix:localname
						$vars = array_reduce( array_keys( $evaluationResult['vars'] ), function( $carry, $clarkname ) use( $formula, &$evaluationResult )
						{
							/** @var QName $qname */
							$qname = qnameClarkName( $clarkname );
							$prefix = $formula->nsMgr->lookupPrefix( $qname->namespaceURI );
							$carry[ $prefix ? "$prefix:{$qname->localName}" : $clarkname ] = $evaluationResult['vars'][ $clarkname ];
							return $carry;

						}, array() );

						$derivedResult = $formula->factsContainer->facts[ $formula->label ][ $key ];
						$derivedResult['concept'] = $derivedResult['concept']->prefix . ';' . $derivedResult['concept']->localName;

						$evaluation = array(
							'derived result' => $derivedResult,
							'matched facts' => array_map( function( $matchedFact ) use ( $formula )
								{
									return $formula->getVariableDetails( $matchedFact, false );
								}, $consistencyAssertion->aspectMatchedInputFacts[ $key ] ),
							'substituted' => $formula->createDefaultMessage( $formula->value, $vars ),
						);

						foreach ( $vars as $name => $variable )
						{
							$evaluation['variables'][ $name ] = $formula->getVariableDetails( $variable, false );
						}

						$consistencyFormula['evaluations'][] = $evaluation;
					}

					$consistency['formulas'][ $formulaName ][] = $consistencyFormula;
				}
			}

			$results['consistency'][] = $consistency;
		}

		/** @var array(\XBRL\Formulas\Resources\Variables\VariableSet) $variableSetsForQName */
		foreach ( $formulas->getVariableSets() as $variableSetQName => $variableSetsForQName )
		{
			foreach ( $variableSetsForQName as /** @var VariableSetAssertion $variableSet */ $variableSet )
			{
				if ( $variableSet instanceof \XBRL\Formulas\Resources\Formulas\Formula ) continue;

				$formula = array(
					'id' => $variableSet->id,
					'label' => $variableSet->label,
					'description' => trim( $variableSet->description ),
					'linkbase' => $variableSet->linkbase,
					'test' => $variableSet->test,
					// 'severity' => $variableSet->severity
				);

				/*
				 	These tests use parameters

				 	BUC42-ReissueReport
					BUC43-Reclassification
					Templates\us-gaap\290000-004
					Templates\us-gaap\290000-004
					Templates\us-gaap\333000-001
					Templates\us-gaap\780000-001
					Templates\us-gaap\800000-001
					Templates\us-gaap\800000-002
					Templates\us-gaap\995400-002

				 */
				$parameters = array();

				foreach ( $variableSet->parameters as $name => /** @var Parameter $parameter */ $parameter )
				{
					$parameters[ $name ] = array(
						'label' => $parameter->label,
						'select' => $parameter->select
					);
				}

				$formula['parameters'] = array_map( function( $parameter )
				{
					return array(
						'label' => $parameter->label,
						'select' => $parameter->select
					);
				}, $variableSet->parameters );

				if ( $variableSet instanceof ExistenceAssertion )
				{
					/**
					 * @var ExistenceAssertion $existenceAssertion
					 */
					$existenceAssertion = $variableSet;
					$formula['type'] = 'existence';
					$formula['error'] = ! $existenceAssertion->success;
					$formula['satisfied'] = array();
					$formula['unsatisfied'] = array();

					// if ( $existenceAssertion->test )
					// {
					//	$formula['substituted'] = $existenceAssertion->createDefaultMessage( $variableSet->test, $satisfied['vars']);
					// }

					// For each fact in each satisfied put the node name, context ref and unit
					foreach ( $existenceAssertion->satisfied as $index => $satisfied )
					{
						$item = array();

						// This can used by messages but is not needed here
						unset( $satisfied['vars'][ "{{$variableSet->namespace}}test-expression" ] );

						if ( $existenceAssertion->test )
						{
							$item['substituted'] = $existenceAssertion->createDefaultMessage( $variableSet->test, $satisfied['vars'] );
						}

						foreach ( $satisfied['vars'] as $name => $variable )
						{
							$item['variables'][ $name ] = $existenceAssertion->getVariableDetails( $variable, false );
						}

						$formula['satisfied'][] = $item;
					}

					// For each fact in each unsatisfied put the node name, context ref and unit
					foreach ( $existenceAssertion->notSatisfied as $index => $unsatisfied )
					{
						$item = array();

						// This can used by messages but is not needed here
						unset( $unsatisfied['vars'][ "{{$variableSet->namespace}}test-expression" ] );

						if ( $existenceAssertion->test )
						{
							$item['substituted'] = $existenceAssertion->createDefaultMessage( $variableSet->test, $unsatisfied['vars'] );
						}

						foreach ( $unsatisfied['vars'] as $name => $variable )
						{
							$item['variables'][ $name ] = $existenceAssertion->getVariableDetails( $variable, false );
						}

						$formula['unsatisfied'][] = $item;
					}
				}
				else if ( $variableSet instanceof ValueAssertion )
				{
					/**
					 * @var ValueAssertion $valueAssertion
					 */
					$valueAssertion = $variableSet;
					$formula['type'] = 'value';
					$formula['error'] = count( $valueAssertion->unsatisfied ) > 0;
					$formula['satisfied'] = array();
					$formula['unsatisfied'] = array();

					// For each fact in each satisfied put the node name, context ref and unit
					foreach ( $valueAssertion->satisfied as $index => $satisfied )
					{
						$item = array();

						// This can used by messages but is not needed here
						unset( $satisfied['vars'][ "{{$variableSet->namespace}}test-expression" ] );

						$item['substituted'] = $valueAssertion->createDefaultMessage( $variableSet->test, $satisfied['vars'] );
						foreach ( $satisfied['vars'] as $name => $variable )
						{
							$item['variables'][ $name ] = $valueAssertion->getVariableDetails( $variable, false );
						}

						$formula['satisfied'][] = $item;
					}

					// For each fact in each unsatisfied put the node name, context ref and unit
					foreach ( $valueAssertion->unsatisfied as $index => $unsatisfied )
					{
						$item = array();

						// This can used by messages but is not needed here
						unset( $unsatisfied['vars'][ "{{$variableSet->namespace}}test-expression" ] );

						$item['substituted'] = $valueAssertion->createDefaultMessage( $variableSet->test, $unsatisfied['vars'] );
						foreach ( $unsatisfied['vars'] as $name => $variable )
						{
							$item['variables'][ $name ] = $valueAssertion->getVariableDetails( $variable );
						}

						$formula['unsatisfied'][] = $item;
					}
				}

				if ( $variableSet->generatedSatisifiedMessages || $variableSet->generatedUnsatisifiedMessages )
				{
					foreach ( $variableSet->generatedSatisifiedMessages as $message )
					{
						$formula['messages']['satisfied'][] = $message;
						$log->info( "$indent    " . trim( $message ) );
					}
					foreach ( $variableSet->generatedUnsatisifiedMessages as $message )
					{
						$formula['messages']['unsatisfied'][] = $message;
						$log->info( "$indent    " . trim( $message ) );
					}
				}
				else
				{
					$formula['messages'] = "No messages have been generated because no formulas have been evaluated.  This may be because no message was defined or because data is missing.  ";
					$log->info( "$indent    {$formula['messages']}" );
				}
				$log->info( "$indent        for {$variableSet->label} in {$variableSet->extendedLinkRoleUri}" );

				$results['formulas'][] = $formula;
			}
		}

		return $this->getFormulaSummaries( $formulas );
	}

	/**
	 * Caches the formulas summaries
	 * @var array
	 */
	private $formulaSummeriesCache = array();

	/**
	 * An empty array to return by reference
	 * @var array
	 */
	private $emptyArray = array();

	/**
	 * Returns an array of summaries
	 * @param XBRL_Formulas $formulas
	 * @return array
	 */
	public function &getFormulaSummaries( $formulas )
	{
		if ( ! $formulas ) return $this->emptyArray;
		if ( ! $this->formulaSummeriesCache ) $this->formulaSummeriesCache = $formulas->getValueAssertionFormulaSummaries();

		return $this->formulaSummeriesCache;
	}

	/**
	 * Returns an array of summaries
	 * @param XBRL_Formulas $formulas
	 * @param string $roleUri
	 * @return array
	 */
	public function &getFormulaSummariesForRole( $formulas, $roleUri )
	{
		$summaries =& $this->getFormulaSummaries( $formulas );
		if ( ! isset( $summaries[ $roleUri ] ) ) return $this->emptyArray;
		return $summaries[ $roleUri ];
	}
}
