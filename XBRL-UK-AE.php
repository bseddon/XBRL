<?php

/**
 * Audit Exempt taxonomy implementation
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
	"http://www.companieshouse.gov.uk/ef/xbrl/uk/fr/gaap/ae/2009-06-21",
);

/**
 * Register namespace to class map entries
 *
 * This call defines the namespaces that apply to the use of the XBRL decendent class defined in this file.
 * The static function XBRL::withtaxonomy() will use the information provided by this call to instantiate
 * the correct (this) class.
 */
XBRL::add_namespace_to_class_map_entries( array_merge( array(
	"http://www.xbrl.org/uk/fr/gcd/2004-12-01",
	"http://www.xbrl.org/uk/fr/gaap/pt/2004-12-01",
	"http://www.companieshouse.gov.uk/ef/xbrl/uk/fr/gaap/ae/2009-06-21",
	"http://www.xbrl.org/uk/fr/gaap/ci/2004-12-01",
), $entrypoint_namespaces ), "XBRL_UK_AE" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces, "XBRL_UK_AE" );

/**
 * Register XSD to compiled taxonomy entries
 */
XBRL::add_xsd_to_compiled_map_entries( array( "uk-gaap-ae-2009-06-21.xsd" ), "uk-ae" );

/**
 * Implements an XBRL descendent for the UK AE taxonomy.
 * @author Bill Seddon
 */
class XBRL_UK_AE extends XBRL
{

	/**
	 * http://www.companieshouse.gov.uk/ef/xbrl/uk/fr/gaap/ae/2009-06-21
	 * @var string
	 */
	public static $uk_AE_NS			= "http://www.companieshouse.gov.uk/ef/xbrl/uk/fr/gaap/ae/2009-06-21";
	/**
	 * http://www.xbrl.org/uk/fr/gaap/ci/2004-12-01
	 * @var string
	 */
	public static $uk_AE_CI_NS      = "http://www.xbrl.org/uk/fr/gaap/ci/2004-12-01";
	/**
	 * http://www.xbrl.org/uk/fr/gcd/2004-12-01
	 * @var string
	 */
	public static $uk_AE_GCD_NS     = "http://www.xbrl.org/uk/fr/gcd/2004-12-01";
	/**
	 * http://www.xbrl.org/uk/fr/gaap/pt/2004-12-01
	 * @var string
	 */
	public static $uk_AE_PT_NS      = "http://www.xbrl.org/uk/fr/gaap/pt/2004-12-01";

	/**
	 * The URL of the Commercial & Industrial taxonomy schema document
	 * @var string
	 */
	public static $uk_CI_XSD		= "http://www.companieshouse.gov.uk/ef/xbrl/gaap/ci/2004-12-01/uk-gaap-ci-2004-12-01.xsd";

	/**
	 * An array of element ids that when they appear in a report their values should be treated as text.
	 * This has a specific meaning in the default report: the associated values are not shown tied to a
	 * specific financial year.
	 * @var string[]
	 */
	public static $textItems = array(
		"uk-gaap-ae_AccountingPolicies",
		"uk-gaap-ae_AccountsAreInAccordanceWithSpecialProvisionsCompaniesActRelatingToSmallCompanies",
		"uk-gaap-ae_CategoryItem",
		"uk-gaap-ae_CompanyEntitledToExemptionUnderSection477CompaniesAct2006",
		"uk-gaap-ae_CompanyNotDormant",
		"uk-gaap-ae_ContentAccountingPolicy",
		"uk-gaap-ae_DateAccountsReceived",
		"uk-gaap-ae_DirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct",
		"uk-gaap-ae_MembersHaveNotRequiredCompanyToObtainAnAudit",
		"uk-gaap-ae_TitleAccountingPolicy",
		"uk-gaap-ae_TransactionsWithDirectors",
		"uk-gaap-ae_TypeDepreciation",
		"uk-gaap-pt_AccountsAreInAccordanceWithSpecialProvisionsCompaniesActRelatingToSmallCompanies",
		"uk-gaap-pt_CompanyEntitledToExemptionUnderSection477CompaniesAct2006",
		"uk-gaap-pt_DateApproval",
		"uk-gaap-pt_NameApprovingDirector",
		"uk-gaap-pt_StatementOnRelatedPartyDisclosure",
	);

	/**
	 * Elements for which the value should be used as a label.  Usually used with tuples.
	 * @var array[string]
	 */
	public static $labelItems = array(
			"uk-gaap-ae_TitleAccountingPolicy" => "uk-gaap-ae_ContentAccountingPolicy",
	);

	/**
	 * Default contructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Example of overridding the import schema method which can be used to inject missing schemas
	 * {@inheritDoc}
	 * @see XBRL::importSchema()
	 * @param string $schemaLocation The location of the schema file being loaded
	 * @param int $depth (optional) The nesting depth
	 * @param bool $mainSchema True if the schema being loaded is the main DTS schema (entry point)
	 * @return void
	 */
	protected function importSchema( $schemaLocation, $depth = 0, $mainSchema = false )
	{
		parent::importSchema( $schemaLocation, $depth, $mainSchema );
	}

	/**
	 * A variable used to record whether the CI taxonomy has been loaded
	 * @var bool $ci_loaded
	 */
	private $ci_loaded = false;

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action before each taxonomy is loaded
	 * @param string $taxonomy_schema The name of the taxonomy being loaded
	 */
	protected function beforeLoadTaxonomy( $taxonomy_schema )
	{
		$xsd = "uk-gaap-ae-2009-06-21.xsd";

		if ( $this->ci_loaded || basename( $taxonomy_schema ) !== $xsd ) return;

		// Prevent recursive calls
		$this->ci_loaded = true;

		// Path relative to the AE taxonomy file
		$xsd = "../../ci/2004-12-01/uk-gaap-ci-2004-12-01.xsd";
		$this->log()->info( "Adding taxonomy $xsd" );
		$this->importSchema( XBRL::resolve_path( $taxonomy_schema, $xsd ) );
		$this->log()->info( $this->getSchemaLocation() );
	}

	/**
	 * The CI taxonomy is not imported by any of the schema documents
	 * {@inheritDoc}
	 * @see XBRL::afterLoadTaxonomy()
	 * @param string $taxonomy_schema The name of the taxonomy being loaded
	 */
	protected function afterLoadTaxonomy( $taxonomy_schema )
	{
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
			case "http://www.companieshouse.gov.uk/ef/xbrl/uk/fr/gaap/ae/2009-06-21":
				$prefix = "uk-gaap-ae";
				break;

			case "http://www.xbrl.org/uk/fr/gcd/2004-12-01":

				$prefix = "uk-gcd";
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
	 * This function provides an opportunity for a taxonomy to sanitize and/or translate a string
	 *
	 * @param string $text The text to be sanitized
	 * @param string $type An optional element type such as num:integer
	 * @param string $language An optional language locale
	 * @return string The sanitized string
	 */
	public function sanitizeText( $text, $type = null, $language = null )
	{
		$text = str_replace( utf8_encode( '£' ), "&pound;", $text ); // This is necessary to make sure the whole of the unicode character is replaced.

		return rtrim( $text );
	}

	/**
	 * Returns the value of $elemment formatted according to the type defined in the taxonomy
	 * Overloads the implementation in XBRL
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
			case 'uk-gaap-ae:trueBooleanItemType':
				return "True";

			case 'uk-gaap-ae:CompaniesHouseDocumentAuthenticationType':
			case 'uk-gaap-ae:CompaniesHouseRegisteredNumberType':
				return $value;

			case XBRL_Constants::$xbrliMonetaryItemType:
				$element['value'] = str_replace( ',', '', $element['value'] );
				return parent::formattedValue( $element, $instance );

			case 'xbrli:decimalItemType':
				// If it's the depreciation rate, format at a percent.
				if ( $element['taxonomy_element']['id'] === 'uk-gaap-ae_RateDepreciation' )
				{
					// return sprintf("%.2f%%", $value * 100);
					$formatter = new \NumberFormatter( $this->context->locale, NumberFormatter::PERCENT );
					return $formatter->format( $value );
				}
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
	 * Return a default for the language code. Can be overridden.
	 */
	public function getDefaultLanguage()
	{
		return 'en';
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
		if ( in_array( $key, XBRL_UK_AE::$textItems ) ) return true;
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
		if ( isset( XBRL_UK_AE::$labelItems[ $key ] ) ) return XBRL_UK_AE::$labelItems[ $key ];
		return parent::treatAsLabel( $key );
	}

	/**
	 * Get the default currency
	 */
	public function getDefaultCurrency()
	{
		return "USD";
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

		if ( ! property_exists( $this, 'proxyPresentationNodes' ) )
		{
			// Entity-Information
			$this->proxyPresentationNodes[ $this->proxyRoleUriStandardInformation ] = array(
					"locators"		=> $this->proxyLocatorsSI,
					"addarcs"		=> $this->proxyAddArcsSI,
					"deletenodes"	=> array(),
					"removearcs"	=> array(),
			);
			/*
			// Entity-Information
			$this->proxyPresentationNodes[ $this->proxyRoleUriBalanceSheet ] = array(
					"locators"		=> $this->proxyLocatorsBS,
					"addarcs"		=> $this->proxyAddArcsBS,
					"deletenodes"	=> array(),
					"removearcs"	=> array(),
					"aliases"		=> array(),
			);
			*/
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
	 * http://www.xbrl-uk.org/ExtendedLinkRoles/AE-Standard-Information
	 * @var string
	 */
	private $proxyRoleUriStandardInformation = 'http://www.xbrl-uk.org/ExtendedLinkRoles/AE-Standard-Information';
	/**
	 * http://www.xbrl-uk.org/ExtendedLinkRoles/AE-Balance-Sheet
	 * @var string
	 */
	private $proxyRoleUriBalanceSheet = 'http://www.xbrl-uk.org/ExtendedLinkRoles/AE-Balance-Sheet';

	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyLocatorsSI = array(
		"uk-gcd_EntityInformation"				=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityInformation",
		"uk-gcd_EntityNames"					=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityNames",
		"uk-gcd_EntityCurrentLegalName"			=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityCurrentLegalName",
		"uk-gcd_EntityTradingName"				=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityTradingName",
		"uk-gcd_EntityFormerName"				=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityFormerName",

		"uk-gcd_StatementDatesPeriodsCovered"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_StatementDatesPeriodsCovered",
	);

	/**
	 * A set of locators to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyLocatorsBS = array(
		"uk-gaap-pt_ApprovalDetails"			=> "uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ApprovalDetails",
		"uk-gaap-pt_NameApprovingDirector"		=> "uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_NameApprovingDirector",
	);

	/**
	 * A set of arcs to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAddArcsSI = array(
		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_StandardInformation",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityInformation",
			"order"	=> 1,
		),
		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityInformation",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityNames",
			"order" => 1,
		),
		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityNames",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityCurrentLegalName",
			"order"	=> 1,
		),
		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityNames",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityTradingName",
			"order"	=> 1,
		),
		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityNames",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_EntityFormerName",
			"order"	=> 1,
		),

		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_StandardInformation",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_StatementDatesPeriodsCovered",
			"order"	=> 1,
		),
		array(
			"from"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_StatementDatesPeriodsCovered",
			"to"	=> "uk-gcd-2004-12-01.xsd#uk-gcd_BalanceSheetDate",
			"order"	=> 1,
		),
	);

	/**
	 * A set of arcs to add to the presentation hierarchy
	 * @var array
	 */
	private $proxyAddArcsBS = array(
		array(
			"from"	=> "uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ApprovalByBoard",
			"to"	=> "uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ApprovalDetails",
			"order"	=> 1,
		),
		array(
			"from"	=> "uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ApprovalDetails",
			"to"	=> "uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_NameApprovingDirector",
			"order"	=> 1,
		),
	);

}


?>