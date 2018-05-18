<?php

/**
 * UK FRC taxonomy implementation
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright (C) 2016 Lyquidity Solutions Limited
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
	"http://xbrl.frc.org.uk/fr/2014-09-01/core",
	"http://xbrl.frc.org.uk/fr/2014-09-01/core-full",
);

/**
 * Register namespace to class map entries
 *
 * This call defines the namespaces that apply to the use of the XBRL decendent class defined in this file.
 * The static function XBRL::withtaxonomy() will use the information provided by this call to instantiate
 * the correct (this) class.
 */
XBRL::add_namespace_to_class_map_entries( array_merge( array(
	"http://xbrl.frc.org.uk/general/2014-09-01/common",
	"http://xbrl.frc.org.uk/cd/2014-09-01/business",
	"http://xbrl.frc.org.uk/reports/2014-09-01/direp",
	"http://xbrl.frc.org.uk/reports/2014-09-01/accrep",
	"http://xbrl.frc.org.uk/reports/2014-09-01/aurep",
	"http://xbrl.frc.org.uk/cd/2014-09-01/countries",
	"http://xbrl.frc.org.uk/cd/2014-09-01/currencies",
	"http://xbrl.frc.org.uk/cd/2014-09-01/languages",
	"http://xbrl.frc.org.uk/general/2014-09-01/types",
	"http://xbrl.frc.org.uk/general/2014-09-01/ref",
	XBRL_UK_FRC::$frc_FRS_101_NS,
	XBRL_UK_FRC::$frc_FRS_102_NS,
), $entrypoint_namespaces ), "XBRL_UK_FRC" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces, "XBRL_UK_FRC" );

/**
 * Register XSD to compiled taxonomy entries
 */
XBRL::add_xsd_to_compiled_map_entries( array( "frs-core-full-2009-09-01.xsd" ), "frc-gaap" );

/**
 * Implements an XBRL descendent for the UK GAAP taxonomy.
 * @author Bill Seddon
 */
class XBRL_UK_FRC extends XBRL
{
	/**
	 * http://xbrl.frc.org.uk/fr/2014-09-01/core-full
	 * @var string
	 */
	public static $frc_GAAP_FULL_NS	= "http://xbrl.frc.org.uk/fr/2014-09-01/core-full";
	/**
	 * http://xbrl.frc.org.uk/FRS-101/2014-09-01
	 * @var string
	 */
	public static $frc_FRS_101_NS	= "http://xbrl.frc.org.uk/FRS-101/2014-09-01";
	/**
	 * http://xbrl.frc.org.uk/FRS-102/2014-09-01
	 * @var string
	 */
	public static $frc_FRS_102_NS	= "http://xbrl.frc.org.uk/FRS-102/2014-09-01";
	/**
	 * http://xbrl.frc.org.uk/fr/2014-09-01/core
	 * @var string
	 */
	public static $frc_GAAP_NS		= "http://xbrl.frc.org.uk/fr/2014-09-01/core";

	// TODO: Create this list programatically.  For example, all the entity officer member names, notes that has a string type, address lines, etc.
	/**
	 * An array of element ids that when they appear in a report their values should be treated as text.
	 * This has a specific meaning in the default report: the associated values are not shown tied to a
	 * specific financial year.
	 * @var array[string]
	 */
	private static $textItems = array(
		"frs-bus_DescriptionShareType",
		"frs-core_TangibleFixedAssetsPolicy",
		"frs-core_DescriptionDepreciationMethodRateOrUsefulEconomicLifeForTangibleFixedAssets",
		"frs-core_TurnoverPolicy",
		"frs-core_StatementOnBasisMeasurementPreparationAccounts",
		"frs-direp_StatementOnQualityCompletenessInformationProvidedToAuditors",
		"frs-bus_NameEntityOfficer",
		"frs-bus_PrincipalLocation-CityOrTown",
		"frs-bus_AddressLine2",
		"frs-bus_AddressLine1",
		"frs-bus_CountyRegion",
		"frs-bus_PostalCodeZip",
		"frs-bus_NameThirdPartyAgent",
		"frs-bus_UKCompaniesHouseRegisteredNumber",
		"frs-bus_EntityCurrentLegalOrRegisteredName",
		"frs-bus_BusinessReportName",
		"frs-aurep_NameSeniorStatutoryAuditor",
		"frs-aurep_OpinionAuditorsOnEntity",
		"frs-bus_LegalFormOfEntity",
		"frs-bus_EntityAccountsType",
		"frs-curr_PoundSterling",
		"frs-direp_DirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct",
		"frs-direp_AccountsAreInAccordanceWithSpecialProvisionsCompaniesActRelatingToSmallCompanies",
		"frs-bus_EntityDormant",
		"frs-bus_EntityTrading",
		"frs-core_DateApprovalAccounts",
		"frs-direp_DirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct",
		"frs-core_CompanyEntitledToExemptionUnderSection477CompaniesAct2006",
		"frs-direp_CompanyHasActedAsAnAgentDuringPeriod",
		"frs-core_CompanyExemptFromPreparingCashFlowStatementUnderFRS1",
		"frs-core_MembersHaveNotRequiredCompanyToObtainAnAudit",
		"frs-direp_AccountsAreInAccordanceWithSpecialProvisionsCompaniesActRelatingToSmallCompanies",
		"frs-core_AccountsHaveBeenPreparedInAccordanceWithProvisionsSmallCompaniesRegime",
		"frs-core_AccountsPreparedUnderHistoricalCostConventionInAccordanceWithFRSSE",
		"frs-bus_OrdinaryShareClass1",
		"frs-bus_OrdinaryShareClass2",
		"frs-countries_England",
		"frs-countries_Scotland",
		"frs-countries_NorthernIreland",
		"frs-countries_Wales",
		"frs-core_DescriptionGuarantee",
		"frs-bus_BalanceSheetDate",
		"frs-core_InvestmentPropertiesPolicy",
		"frs-core_FinancialInstrumentsPolicy",
		"frs-bus_Non-cumulativeShares",
		"frs-core_StocksWorkInProgressLong-termContractsPolicy",
		"frs-bus_StartDateForPeriodCoveredByReport",
		"frs-bus_EndDateForPeriodCoveredByReport",
		"frs-bus_DescriptionPeriodCoveredByReport",
		"frs-core_RelatedPartyTransactionExemptionBeingClaimed",
		"frs-aurep_DateAuditorsReport",
		"frs-direp_DateSigningDirectorsReport",
		"frs-bus_CompanySecretary",
		"frs-bus_CompanySecretaryDirector",
		"frs-bus_Director1",
		"frs-core_AllTangibleFixedAssetsDefault",
		"frs-core_LandBuildings",
		"frs-core_StatementThatThereWereNoGainsLossesInPeriodOtherThanThoseInProfitLossAccount",
		"frs-core_NumberSharesAllotted",
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

		if ( $dimensionalNode['taxonomy_element']['type'] === 'types:domainItemType' )
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
		// Do nothing - for now
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
	 */
	public function formattedValue( $element, $instance = null )
	{
		$value = $element['value'];
		$type = XBRL_Instance::getElementType( $element );

		switch ( $type )
		{
			case 'xbrli:monetaryItemType':
			case 'xbrli:sharesItemType':
				$element['value'] = str_replace( ',', '', $element['value'] );
				return parent::formattedValue( $element, $instance );

			case 'types:fixedItemType':
				return parent::formattedValue( $element, $instance );

			default:
				return parent::formattedValue( $element, $instance );
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

				$prefix = "types";
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
	 * Whether all roles should be used when collecting primary items,
	 * @return bool True if all roles are to be used as the basis for collecting primary items
	 */
	public function useAllRoles()
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
		"frs-bus_HeadOfficeDefault"				=> "frs-bus-2009-09-01.xsd#frs-bus_HeadOfficeDefault",
		"frs-bus_RegisteredOffice"				=> "frs-bus-2009-09-01.xsd#frs-bus_RegisteredOffice",
		"frs-bus_MainBusiness"					=> "frs-bus-2009-09-01.xsd#frs-bus_MainBusiness",
		"frs-bus_PublicRelations"				=> "frs-bus-2009-09-01.xsd#frs-bus_PublicRelations",
		"frs-bus_InvestorRelations"				=> "frs-bus-2009-09-01.xsd#frs-bus_InvestorRelations",
		"frs-bus_MediaRelations"					=> "frs-bus-2009-09-01.xsd#frs-bus_MediaRelations",
		"frs-bus_SalesMarketing"					=> "frs-bus-2009-09-01.xsd#frs-bus_SalesMarketing",
	);
	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAddressHeading = array(
		"frs-bus_AddressHeading"					=> "frs-bus-2009-09-01.xsd#frs-bus_AddressHeading",
	);
	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAddressParts = array(
		"frs-bus_AddressLine1"					=> "frs-bus-2009-09-01.xsd#frs-bus_AddressLine1",
		"frs-bus_AddressLine2"					=> "frs-bus-2009-09-01.xsd#frs-bus_AddressLine2",
		"frs-bus_AddressLine3"					=> "frs-bus-2009-09-01.xsd#frs-bus_AddressLine3",
		"frs-bus_PrincipalLocation-CityOrTown"	=> "frs-bus-2009-09-01.xsd#frs-bus_PrincipalLocation-CityOrTown",
		"frs-bus_CountyRegion"					=> "frs-bus-2009-09-01.xsd#frs-bus_CountyRegion",
		"frs-bus_PostalCodeZip"					=> "frs-bus-2009-09-01.xsd#frs-bus_PostalCodeZip",
	);
	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAgentLocators = array(
		"frs-bus_EntityAccountantsOrAuditors"	=> "frs-bus-2009-09-01.xsd#frs-bus_EntityAccountantsOrAuditors",
		"frs-bus_EntityBankers"					=> "frs-bus-2009-09-01.xsd#frs-bus_EntityBankers",
		"frs-bus_EntityLawyersOrLegalAdvisers"	=> "frs-bus-2009-09-01.xsd#frs-bus_EntityLawyersOrLegalAdvisers",
		"frs-bus_EntityPublicRelationsAdvisers"	=> "frs-bus-2009-09-01.xsd#frs-bus_EntityPublicRelationsAdvisers",
		"frs-bus_EntityRegistrationAgents"		=> "frs-bus-2009-09-01.xsd#frs-bus_EntityRegistrationAgents",
		"frs-bus_EntityUnderwriters"				=> "frs-bus-2009-09-01.xsd#frs-bus_EntityUnderwriters",
		"frs-bus_AdministratorsForEntity"		=> "frs-bus-2009-09-01.xsd#frs-bus_AdministratorsForEntity",
		"frs-bus_ReceiversForEntity"				=> "frs-bus-2009-09-01.xsd#frs-bus_ReceiversForEntity",
	);
}

?>