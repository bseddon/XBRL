<?php

/**
 * UK GAAP taxonomy implementation
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
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

/**
 * Define the namespaces of the entry points supported by this taxonomy
 * @var array
 */
$entrypoint_namespaces = array(
	"http://www.xbrl.org/uk/gaap/core/2009-09-01",
	"http://www.xbrl.org/uk/gaap/core-full/2009-09-01",
	"http://www.xbrl.org/uk/gaap/core-all/2009-09-01",
);

/**
 * Register namespace to class map entries
 *
 * This call defines the namespaces that apply to the use of the XBRL decendent class defined in this file.
 * The static function XBRL::withtaxonomy() will use the information provided by this call to instantiate
 * the correct (this) class.
 */
XBRL::add_namespace_to_class_map_entries( array_merge( array(
	"http://www.xbrl.org/uk/all/types/2009-09-01",
	"http://www.xbrl.org/uk/all/common/2009-09-01",
	"http://www.xbrl.org/uk/all/ref/2009-09-01",
	"http://www.xbrl.org/uk/cd/countries/2009-09-01",
	"http://www.xbrl.org/uk/cd/currencies/2009-09-01",
	"http://www.xbrl.org/uk/cd/exchanges/2009-09-01",
	"http://www.xbrl.org/uk/cd/languages/2009-09-01",
	"http://www.xbrl.org/uk/cd/business/2009-09-01",
	"http://www.xbrl.org/uk/all/gaap-ref/2009-09-01",
	"http://www.xbrl.org/uk/reports/direp/2009-09-01",
	"http://www.xbrl.org/uk/reports/aurep/2009-09-01",
), $entrypoint_namespaces ), "XBRL_UK_GAAP" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces, "XBRL_UK_GAAP" );

/**
 * Register XSD to compiled taxonomy entries
 */
XBRL::add_xsd_to_compiled_map_entries( array( "uk-gaap-full-2009-09-01.xsd", "uk-gaap-main-2009-09-01.xsd" ), "uk-gaap" );

/**
 * Implements an XBRL descendent for the UK GAAP taxonomy.
 * @author Bill Seddon
 */
class XBRL_UK_GAAP extends XBRL
{

	/**
	 * http://www.xbrl.org/uk/gaap/core-full/2009-09-01
	 * @var string
	 */
	public static $uk_GAAP_FULL_NS	= "http://www.xbrl.org/uk/gaap/core-full/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/gaap/core-all/2009-09-01
	 * @var string
	 */
	public static $uk_GAAP_ALL_NS	= "http://www.xbrl.org/uk/gaap/core-all/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/gaap/core/2009-09-01
	 * @var string
	 */
	public static $uk_GAAP_NS		= "http://www.xbrl.org/uk/gaap/core/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/all/ref/2009-09-01
	 * @var string
	 */
	public static $uk_CD_REF_NS     = "http://www.xbrl.org/uk/all/ref/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/cd/countries/2009-09-01
	 * @var string
	 */
	public static $uk_Countries_NS	= "http://www.xbrl.org/uk/cd/countries/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/cd/currencies/2009-09-01
	 * @var string
	 */
	public static $uk_CURR_NS		= "http://www.xbrl.org/uk/cd/currencies/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/reports/direp/2009-09-01
	 * @var string
	 */
	public static $uk_DIREP_NS		= "http://www.xbrl.org/uk/reports/direp/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/cd/exchanges/2009-09-01
	 * @var string
	 */
	public static $uk_EXCH_NS		= "http://www.xbrl.org/uk/cd/exchanges/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/cd/exchanges/2009-09-01
	 * @var string
	 */
	public static $uk_LANG_NS		= "http://www.xbrl.org/uk/cd/languages/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/all/types/2009-09-01
	 * @var string
	 */
	public static $uk_TYPES_NS		= "http://www.xbrl.org/uk/all/types/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/all/common/2009-09-01
	 * @var string
	 */
	public static $uk_COMMON_NS	    = "http://www.xbrl.org/uk/all/common/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/cd/business/2009-09-01
	 * @var string
	 */
	public static $uk_BUS_NS		= "http://www.xbrl.org/uk/cd/business/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/all/gaap-ref/2009-09-01
	 * @var string
	 */
	public static $uk_GAAP_REF_NS	= "http://www.xbrl.org/uk/all/gaap-ref/2009-09-01";
	/**
	 * http://www.xbrl.org/uk/reports/aurep/2009-09-01
	 * @var string
	 */
	public static $uk_AUREP_NS	    = "http://www.xbrl.org/uk/reports/aurep/2009-09-01";

	// TODO: Create this list programatically.  For example, all the entity officer member names, notes that has a string type, address lines, etc.
	/**
	 * An array of element ids that when they appear in a report their values should be treated as text.
	 * This has a specific meaning in the default report: the associated values are not shown tied to a
	 * specific financial year.
	 * @var array[string]
	 */
	private static $textItems = array(
		"uk-bus_DescriptionShareType",
		"uk-gaap_TangibleFixedAssetsPolicy",
		"uk-gaap_DescriptionDepreciationMethodRateOrUsefulEconomicLifeForTangibleFixedAssets",
		"uk-gaap_TurnoverPolicy",
		"uk-gaap_StatementOnBasisMeasurementPreparationAccounts",
		"uk-direp_StatementOnQualityCompletenessInformationProvidedToAuditors",
		"uk-bus_NameEntityOfficer",
		"uk-bus_PrincipalLocation-CityOrTown",
		"uk-bus_AddressLine2",
		"uk-bus_AddressLine1",
		"uk-bus_CountyRegion",
		"uk-bus_PostalCodeZip",
		"uk-bus_NameThirdPartyAgent",
		"uk-bus_UKCompaniesHouseRegisteredNumber",
		"uk-bus_EntityCurrentLegalOrRegisteredName",
		"uk-bus_BusinessReportName",
		"uk-aurep_NameSeniorStatutoryAuditor",
		"uk-aurep_OpinionAuditorsOnEntity",
		"uk-bus_LegalFormOfEntity",
		"uk-bus_EntityAccountsType",
		"uk-curr_PoundSterling",
		"uk-direp_DirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct",
		"uk-direp_AccountsAreInAccordanceWithSpecialProvisionsCompaniesActRelatingToSmallCompanies",
		"uk-bus_EntityDormant",
		"uk-bus_EntityTrading",
		"uk-gaap_DateApprovalAccounts",
		"uk-gaap_NetGoodwill",
		"uk-direp_DirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct",
		"uk-gaap_CompanyEntitledToExemptionUnderSection477CompaniesAct2006",
		"uk-direp_CompanyHasActedAsAnAgentDuringPeriod",
		"uk-gaap_CompanyExemptFromPreparingCashFlowStatementUnderFRS1",
		"uk-gaap_MembersHaveNotRequiredCompanyToObtainAnAudit",
		"uk-direp_AccountsAreInAccordanceWithSpecialProvisionsCompaniesActRelatingToSmallCompanies",
		"uk-gaap_AccountsHaveBeenPreparedInAccordanceWithProvisionsSmallCompaniesRegime",
		"uk-gaap_AccountsPreparedUnderHistoricalCostConventionInAccordanceWithFRSSE",
		"uk-bus_OrdinaryShareClass1",
		"uk-bus_OrdinaryShareClass2",
		"uk-countries_England",
		"uk-countries_Scotland",
		"uk-countries_NorthernIreland",
		"uk-countries_Wales",
		"uk-gaap_DescriptionGuarantee",
		"uk-bus_BalanceSheetDate",
		"uk-gaap_InvestmentPropertiesPolicy",
		"uk-gaap_FinancialInstrumentsPolicy",
		"uk-bus_Non-cumulativeShares",
		"uk-gaap_StocksWorkInProgressLong-termContractsPolicy",
		"uk-bus_StartDateForPeriodCoveredByReport",
		"uk-bus_EndDateForPeriodCoveredByReport",
		"uk-bus_DescriptionPeriodCoveredByReport",
		"uk-gaap_RelatedPartyTransactionExemptionBeingClaimed",
		"uk-aurep_DateAuditorsReport",
		"uk-direp_DateSigningDirectorsReport",
		"uk-bus_CompanySecretary",
		"uk-bus_CompanySecretaryDirector",
		"uk-bus_Director1",
		"uk-bus_Director2",
		"uk-bus_Director3",
		"uk-bus_Director4",
		"uk-gaap_AllTangibleFixedAssetsDefault",
		"uk-gaap_LandBuildings",
		"uk-gaap_StatementThatThereWereNoGainsLossesInPeriodOtherThanThoseInProfitLossAccount",
		"uk-gaap_NumberSharesAllotted",
		"uk-bus_EntityAccountantsOrAuditors",
	);

	/**
	 * Elements for which the value should be used as a label.  Usually used with tuples.
	 * @var array[string]
	 */
	public static $labelItems = array();

	/**
	 * Default contructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get a list of all the members
	 * @param array $dimensionalNode
	 * @return array An array of the member ids
	 */
	private function getValidMembers( $dimensionalNode )
	{
		$result = array();
		if ( ! $dimensionalNode || $dimensionalNode['nodeclass'] !== 'dimensional' )
		{
			return $result;
		}

		if ( $dimensionalNode['taxonomy_element']['type'] === 'uk-types:domainItemType' )
		{
			$result[ $dimensionalNode['taxonomy_element']['id'] ] = true;
		}

		if ( ! isset( $dimensionalNode['children'] ) )
		{
			return $result;
		}

		foreach ( $dimensionalNode['children'] as $nodeKey => $node )
		{
			$result += $this->getValidMembers( $node );
		}

		return $result;
	}

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action when the main taxonomy is loaded
	 */
	public function afterMainTaxonomy()
	{
		parent::afterMainTaxonomy();
		// Do nothing - for now
		// $this->example2();
	}

	/**
	 * Callback function used by the examples.  This example illustrates how the hypercube associated with a primary item may be retrieved.
	 * This example is not accurate because the primary items need to be reviewed in a more robust way.  See how they are processed in
	 * the function XBRL->assignNodeHypercubes()
	 * @param array $node An array of nodes
	 * @param XBRL|XBRL_UK_GAAP $taxonomy The taxonomy
	 * @return string A comma delimited list of hypercube labels
	 */
	function callback ( $node, $taxonomy )
	{
		if ( $node['nodeclass'] === 'simple' ) return;

		$dimensionItems	= $taxonomy->getDefinitionDimensionItems(); // These are element names in a defintion linkbase that are not primary items
		$primaryItems	= $taxonomy->getDefinitionPrimaryItems();

		$primaryItem = $primaryItems[ $node['label'] ];
		if ( ! $primaryItem || ! isset( $primaryItem['roles'] ) ) return;

		$hypercubes = array();
		$hypercubeRoles = $primaryItem['roles'];
		foreach ( $primaryItem['roles'] as $role )
		{
			$roleHypercubes = $taxonomy->getDefinitionRoleHypercubes( $role );
			if ( ! $roleHypercubes ) continue;

			foreach ( $roleHypercubes as $hypercubeKey => $hypercube )
			{
				$hypercubes[] = $hypercubeKey;
			}
		}

		return implode( ",", $hypercubes );
	}

	/**
	 * Generates a node summary for a specific presentation hierarchy node
	 */
	function example2()
	{
		/**
		 * @var $tax XBRL
		 */
		$full = $this->getTaxonomyForNamespace( XBRL_UK_GAAP::$uk_GAAP_FULL_NS );
		$core = $this->getTaxonomyForNamespace( XBRL_UK_GAAP::$uk_GAAP_NS );

		$refs = $full->getPresentationRoleRefs();
		$hypercubes = $full->getDefinitionHypercubes();

		$this->getDefinitionRoleHypercubes( 'http://www.xbrl.org/uk/role/Notes' );
		$notes = $refs['http://www.xbrl.org/uk/role/Notes'];

		$fixedAssets = $notes['hierarchy']['uk-gaap_NotesToFinancialStatementsDetailedDisclosuresHeading']['children']['uk-gaap_NotesOnBalanceSheetHeading']['children']['uk-gaap_FixedAssetsHeading']['children'];
		$summary = $full->summarizeNodes( $fixedAssets, $notes['locators'], array( 'callback' => 'callback', 'description' => false ) );

		file_put_contents( 'summary.json', json_encode( $summary ) );

		exit;
	}

	/**
	 * Another example
	 */
	function example()
	{
		/**
		 * @var XBRL $tax
		 */
		$tax = $this->getTaxonomyForNamespace( XBRL_UK_GAAP::$uk_GAAP_FULL_NS );

		$refs = $tax->getPresentationRoleRefs();

		$notes = $refs['http://www.xbrl.org/uk/role/Notes'];

		/**
		 * @var $taxonomy_base_name string
		 */
		$taxonomy_base_name = pathinfo( $this->getSchemaLocation(), PATHINFO_BASENAME );
		$tax = $this;

		foreach ( $notes['locators'] as $locatorKey => $locator )
		{
			$parts = explode( '#', $locator );
			if ( strpos( $parts[0], $taxonomy_base_name ) === false )
			{
				$tax = $this->getTaxonomyForXSD( $parts[0] );
				$taxonomy_base_name = pathinfo( $tax->getSchemaLocation(), PATHINFO_BASENAME );
			}

			$element = $tax->getElementById( $parts[1] );

			if ( ! $element || $element['type'] !== 'xbrli:stringItemType' ) continue;

			if ( isset( $this->textItems[ $parts[1] ] ) )
			{
				$this->log()->info( "{$parts[1]}" );
			}
		}

		return;

	}

	/**
	 * This function is overridden to add the members to the parent node before it is deleted
	 *
	 * @param array $dimensionalNode A node which has element 'nodeclass' === 'dimensional'
	 * @param array $parentNode The parent node so it can be updated
	 * @return bool True if the dimensional information should be deleted
	 */
	protected function beforeDimensionalPruned( $dimensionalNode, &$parentNode )
	{
		return parent::beforeDimensionalPruned( $dimensionalNode, $parentNode );

		// The dimensional information probably contains valid dimensional information
		// That indicate which members of possible hypercubes are valid for the nodes
		// of the parent.

		$members = $this->getValidMembers( $dimensionalNode );
		if ( count( $members ) )
		{
			$parentNode['members'] = $members;
		}

		return true;
	}

	/**
	 * A map used by getValidDimensionMembersForNode
	 * @var array $nodeDimensionMap
	 */
	private static $nodeDimensionMap = array(
		'contactInfo' => array(
			array(
				'namespace'			=> 'http://www.xbrl.org/uk/cd/business/2009-09-01',
				'dimension-role'	=> 'http://www.xbrl.org/uk/cd/role/Dimension-EntityContactType',
				'dimension'			=> 'uk-bus_EntityContactTypeDimension',
			),
		),
		'thirdParty' => array(
			array(
				'namespace'			=> 'http://www.xbrl.org/uk/cd/business/2009-09-01',
				'dimension-role'	=> 'http://www.xbrl.org/uk/cd/role/Dimension-ThirdPartyAgentType',
			),
		),
		/*
		'tangibleFixedAssets' => array(
			array(
				'hypercube' => 'uk-gaap_TangibleFixedAssetsHypercube',
				'dimension' => 'uk-gaap_TangibleFixedAssetClassesDimension',
			),
			array(
				'hypercube' => 'uk-gaap_TangibleFixedAssetsHypercube',
				'dimension' => 'uk-gaap_TangibleFixedAssetOwnershipDimension',
			),
		),
		*/
	);

	/**
	 * A map used by getValidDimensionMembersForNode
	 * @var array $ancestorMap
	 */
	private static $ancestorMap = array(
		"uk-bus-2009-09-01.xsd#uk-bus_EntityContactsWebsiteInformationHeading"	=> 'contactInfo',
		"uk-bus-2009-09-01.xsd#uk-bus_GeneralContactInformationHeading"			=> 'contactInfo',
		"uk-bus-2009-09-01.xsd#uk-bus_ThirdPartyAgentsHeading"					=> 'thirdParty',
		// "uk-gaap-2009-09-01.xsd#uk-gaap_TangibleFixedAssets"					=> 'tangibleFixedAssets',
		// "uk-gaap-2009-09-01.xsd#uk-gaap_TangibleFixedAssetsCostOrValuation"	=> 'tangibleFixedAssets',
	);

	/**
	 * Provides this implementation an opportunity to provide a list of valid dimension members for a node
	 * Doing this allows the use of elements in an instance document to be disambiguated.
	 * This function will be overridden in descendents
	 * @param array $node The node of the element being processed
	 * @param array $ancestors An array containing the ids of the nodes leading to and including the current node
	 * @return array Returns an empty array
	 */
	public function getValidDimensionMembersForNode( $node, $ancestors )
	{
		// If the ancestors of the current $node includes any of the items in
		// XBRL_UK_GAAP::$ancestorMap get a list of the members of the corresponding
		// to dimensions in the XBRL_UK_GAAP::$nodeDimensionMap of the uk-bus_EntityContactInfoHypercube
		$found = array_intersect_key( XBRL_UK_GAAP::$ancestorMap, array_flip( $ancestors ) );
		if ( ! $found ) return array();
		$mapName = array_shift( $found );

		$maps = isset( XBRL_UK_GAAP::$nodeDimensionMap[ $mapName ] ) ? XBRL_UK_GAAP::$nodeDimensionMap[ $mapName ] : array();
		$members = array();

		foreach ( $maps as $mapKey => $map )
		{
			$tax = $this->getTaxonomyForNamespace( $map['namespace'] );
			$members += $tax->getDefinitionRoleDimensionMembers( $map['dimension-role'] );
		}

		return $members;
	}

	/**
	 * This function provides an opportunity for a taxonomy to sanitize and/or translate a string
	 *
	 * @param string $text The text to be sanitized
	 * @param string $type An optional element type such as num:integer
	 * @param string $language An optional language locale
	 * @return string The sanitized string
	 */
	public function sanitizeText( $text, $type = null, $language = null )
	{
		$text = preg_replace( "/\[heading\]/", "", $text );
		$text = preg_replace( "/\[Dimension\]/", "", $text );
		$text = preg_replace( "/\[default\]/", "", $text );
		$text = str_replace( utf8_encode( '£' ), "&pound;", $text ); // This is necessary to make sure the whole of the unicode character is replaced.

		return rtrim( $text );
	}

	/**
	 * Returns the value of $elemment formatted according to the type defined in the taxonomy
	 * @param array $element A representation of an element from an instance document
	 * @param XBRL_Instance $instance An instance of an instance class related to the $element
	 * @param bool $includeCurrency True if the returned monetary value should include a currency symbol
	 * @return mixed
	 */
	public function formattedValue( $element, $instance = null, $includeCurrency = true )
	{
		$value = $element['value'];
		$type = XBRL_Instance::getElementType( $element );

		switch ( $type )
		{
			case 'xbrli:monetaryItemType':
			case 'xbrli:sharesItemType':
				$element['value'] = str_replace( ',', '', $element['value'] );
				return parent::formattedValue( $element, $instance, $includeCurrency );

			case 'uk-types:fixedItemType':
				return parent::formattedValue( $element, $instance, $includeCurrency );

			default:
				return parent::formattedValue( $element, $instance, $includeCurrency );
		}
	}

	/**
	 * Return the value of the element after removing any formatting.
	 * @param array $element
	 * @return float
	 * {@inheritDoc}
	 * @see XBRL::removeValueFormatting()
	 */
	public function removeNumberValueFormatting( $element )
	{
		return parent::removeNumberValueFormatting( $element );
	}

	/**
	 * Gets the alignment for the element based on the type
	 * @param string $namespace
	 * @param string $name
	 * @return string The alignment to use
	 */
	public function valueAlignmentForNamespace( $namespace, $name )
	{
		$prefix = "";

		switch ( $namespace )
		{
			case "http://www.xbrl.org/uk/all/types/2009-09-01":

				$prefix = "uk-types";
				break;

			default:
				return parent::valueAlignmentForNamespace( $namespace, $name );
		}

		$type = "$prefix:$name";

		switch ( $type )
		{
			default:
				return "left";
		}

	}

	/**
	 * Get the default currency
	 */
	public function getDefaultCurrency()
	{
		return "GBP";
	}

	/**
	 * Return a default for the language code. Can be overridden.
	 */
	public function getDefaultLanguage()
	{
		return 'en';
	}

	/**
	 * Returns True if the $key is for a row that should be excluded.
	 * Overloads the implementation in XBRL
	 * @param string $key The key to lookup to determine whether the row should be excluded
	 * @param string $type The type of the item being tested (defaults to null)
	 * @return boolean
	 */
	public function excludeFromOutput( $key, $type = null )
	{
		if ( $key === 'http://www.xbrl.org/uk/cd/role/XBRL-Document-Information' ) return true;
		return parent::excludeFromOutput( $key, $type );
	}

	/**
	 * Returns true if instance documents associated with the taxonomy normally provide opening balances.
	 * If they do not, then a user of the taxonomy knows to compute an opening balance from available information.
	 * Override in a descendent implementation.
	 * @return boolean
	 */
	public function openingBalancesSupplied()
	{
		return true;
	}

	/**
	 * Returns true if the element value with the $key is defined as one to display as text
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as text
	 * @param string $type The type of the element
	 * @return boolean Defaults to false
	 */
	public function treatAsText( $key, $type )
	{
		if ( in_array( $key, XBRL_UK_GAAP::$textItems ) ) return true;
		return parent::treatAsText( $key, $type );
	}

	/**
	 * Returns true if the element value with the $key is defined as one that should be used as a label - usually in tuple
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as a label
	 * @return boolean Defaults to false
	 */
	public function treatAsLabel( $key )
	{
		if ( isset( XBRL_UK_GAAP::$labelItems[ $key ] ) ) return XBRL_UK_GAAP::$labelItems[ $key ];
		return parent::treatAsLabel( $key );
	}

	/**
	 * Returns an array of locator(s) and corresponding presentation arc(s) that will be substituted for the $from in the $role
	 *
	 * Overrides XBRL to modify the presentation link base hierarchy.
	 *
	 * @param string $roleUri A role Uri to identify the base presentation link base being modified.
	 * @return array An array of locators and links
	 */
	public function getProxyPresentationNodes( $roleUri )
	{
		// $this->log()->info( "$roleUri" );
		return false;

		if ( ! property_exists( $this, 'proxyPresentationNodes' ) )
		{
			// Entity-Information
			$addarcs = array();
			$heading = reset( array_keys( $this->proxyAddressHeading ) );
			foreach ( $this->proxyLocators as $location => $uri )
			{
				$addarcs[] = array( "from" => $heading, "to" => $location, "order" => 1 );

				$index = 1;
				foreach ( $this->proxyAddressParts as $partKey => $part )
				{
					$addarcs[] = array( "from" => $location, "to" => $partKey, "order" => $index );
					$index++;
				}
			}

			$removearcs = array();
			foreach ( $this->proxyAddressParts as $partKey => $part )
				$removearcs[] = array( "from" => $heading, "to" => $partKey );

			$this->proxyPresentationNodes = array(
				$this->proxyRoleUriEI => array(
					"locators" => $this->proxyLocators,
					"addarcs" => $addarcs,
					"deletenodes" => array(),
					"removearcs" => $removearcs,
				),
			);

			// General-Purpose-Contact-Information
			$addarcs = array();
			$heading = reset( array_keys( $this->proxyAddressHeading ) );
			foreach ( $this->proxyAgentLocators as $location => $uri )
			{
				$addarcs[] = array( "from" => $heading, "to" => $location, "order" => 1 );

				$index = 1;
				foreach ( $this->proxyAddressParts as $partKey => $part )
				{
					$addarcs[] = array( "from" => $location, "to" => $partKey, "order" => $index );
					$index++;
				}
			}

			$removearcs = array();
			foreach ( $this->proxyAddressParts as $partKey => $part )
				$removearcs[] = array( "from" => $heading, "to" => $partKey );

			$this->proxyPresentationNodes[ $this->proxyRoleUriGPCI ] = array(
				"locators" => $this->proxyAgentLocators,
				"addarcs" => $addarcs,
				"deletenodes" => array(),
				"removearcs" => $removearcs,
			);

			// $this->log()->info( json_encode( $this->proxyPresentationNodes ) );
		}

		if ( ! is_array( $this->proxyPresentationNodes ) )
		{
			$this->log()->info( "The property 'proxyPresentationNodes' is not an array" );
			return false;
		}

		if ( ! isset( $this->proxyPresentationNodes[ $roleUri ] ) )
			return false;

		return $this->proxyPresentationNodes[ $roleUri ];
	}

	/**
	 * Whether all roles should be used when collecting primary items,
	 * @return bool True if all roles are to be used as the basis for collecting primary items
	 */
	public function useAllRoles()
	{
		return true;
	}

	/**
	 * Provides a descendant implementation a chance to define whether or not common hypercubes should be accumulated for a node.
	 * @param array $node An array of presentation hierarchy nodes
	 * @param string $roleRefKey
	 * @return bool True if primary items are allowed (default: true)
	 */
	protected function accumulateCommonHypercubesForNode( $node, $roleRefKey )
	{
		return true;
	}

	/**
	 * http://www.xbrl.org/uk/cd/role/Entity-Information
	 * @var string
	 */
	private $proxyRoleUriEI		= "http://www.xbrl.org/uk/cd/role/Entity-Information";
	/**
	 * http://www.xbrl.org/uk/cd/role/General-Purpose-Contact-Information
	 * @var string
	 */
	private $proxyRoleUriGPCI	= "http://www.xbrl.org/uk/cd/role/General-Purpose-Contact-Information";

	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyLocators = array(
		"uk-bus_HeadOfficeDefault"				=> "uk-bus-2009-09-01.xsd#uk-bus_HeadOfficeDefault",
		"uk-bus_RegisteredOffice"				=> "uk-bus-2009-09-01.xsd#uk-bus_RegisteredOffice",
		"uk-bus_MainBusiness"					=> "uk-bus-2009-09-01.xsd#uk-bus_MainBusiness",
		"uk-bus_PublicRelations"				=> "uk-bus-2009-09-01.xsd#uk-bus_PublicRelations",
		"uk-bus_InvestorRelations"				=> "uk-bus-2009-09-01.xsd#uk-bus_InvestorRelations",
		"uk-bus_MediaRelations"					=> "uk-bus-2009-09-01.xsd#uk-bus_MediaRelations",
		"uk-bus_SalesMarketing"					=> "uk-bus-2009-09-01.xsd#uk-bus_SalesMarketing",
	);
	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAddressHeading = array(
		"uk-bus_AddressHeading"					=> "uk-bus-2009-09-01.xsd#uk-bus_AddressHeading",
	);
	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAddressParts = array(
		"uk-bus_AddressLine1"					=> "uk-bus-2009-09-01.xsd#uk-bus_AddressLine1",
		"uk-bus_AddressLine2"					=> "uk-bus-2009-09-01.xsd#uk-bus_AddressLine2",
		"uk-bus_AddressLine3"					=> "uk-bus-2009-09-01.xsd#uk-bus_AddressLine3",
		"uk-bus_PrincipalLocation-CityOrTown"	=> "uk-bus-2009-09-01.xsd#uk-bus_PrincipalLocation-CityOrTown",
		"uk-bus_CountyRegion"					=> "uk-bus-2009-09-01.xsd#uk-bus_CountyRegion",
		"uk-bus_PostalCodeZip"					=> "uk-bus-2009-09-01.xsd#uk-bus_PostalCodeZip",
	);
	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAgentLocators = array(
		"uk-bus_EntityAccountantsOrAuditors"	=> "uk-bus-2009-09-01.xsd#uk-bus_EntityAccountantsOrAuditors",
		"uk-bus_EntityBankers"					=> "uk-bus-2009-09-01.xsd#uk-bus_EntityBankers",
		"uk-bus_EntityLawyersOrLegalAdvisers"	=> "uk-bus-2009-09-01.xsd#uk-bus_EntityLawyersOrLegalAdvisers",
		"uk-bus_EntityPublicRelationsAdvisers"	=> "uk-bus-2009-09-01.xsd#uk-bus_EntityPublicRelationsAdvisers",
		"uk-bus_EntityRegistrationAgents"		=> "uk-bus-2009-09-01.xsd#uk-bus_EntityRegistrationAgents",
		"uk-bus_EntityUnderwriters"				=> "uk-bus-2009-09-01.xsd#uk-bus_EntityUnderwriters",
		"uk-bus_AdministratorsForEntity"		=> "uk-bus-2009-09-01.xsd#uk-bus_AdministratorsForEntity",
		"uk-bus_ReceiversForEntity"				=> "uk-bus-2009-09-01.xsd#uk-bus_ReceiversForEntity",
	);
}

?>