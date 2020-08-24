<?php

/**
 * XBRL specification constants
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
 * Include the QName code
 */
require_once( 'XBRL-QName.php' );

define( "ASSERTION_SEVERITY_OK", "OK" );
define( "ASSERTION_SEVERITY_ERROR", "ERROR" );
define( "ASSERTION_SEVERITY_WARNING", "WARNING" );

define( "STANDARD_PREFIX_XBRL", "xbrl" );
define( "STANDARD_PREFIX_XBRLI", "xbrli" );
define( "STANDARD_PREFIX_LINK", "link" );
define( "STANDARD_PREFIX_UTR", "utr" );
define( "STANDARD_PREFIX_UTR_ERROR", "utre" );
define( "STANDARD_PREFIX_XBRLDT", "xbrldt" );
define( "STANDARD_PREFIX_XBRLDT_ERROR", "xbrldte" );
define( "STANDARD_PREFIX_XBRLDI", "xbrldi" );
define( "STANDARD_PREFIX_XBRLDI_ERROR", "xbrldie" );
define( "STANDARD_PREFIX_XLINK", "xlink" );
define( "STANDARD_PREFIX_XL", "xl" );
// BMS 2018-04-09 Fixing the kluge
define( "STANDARD_PREFIX_SCHEMA", "xs" );
define( "STANDARD_PREFIX_SCHEMA_ALTERNATIVE", "xsd" );
define( "STANDARD_PREFIX_SCHEMA_INSTANCE", "xsi" );
define( "STANDARD_PREFIX_GENERIC", "gen" );
define( "STANDARD_PREFIX_ISO4217", "iso4217" );
define( "STANDARD_PREFIX_IX", "ix" );
define( "STANDARD_PREFIX_IX11", "ix11" );
define( "STANDARD_PREFIX_IXT", "ixt" );
define( "STANDARD_PREFIX_LABEL", "label" );
define( "STANDARD_PREFIX_MODEL", "model" );
define( "STANDARD_PREFIX_REFERENCE", "reference" );
define( "STANDARD_PREFIX_REFERENCE_ERROR", "xbrlre" );
define( "STANDARD_PREFIX_LABEL_ERROR", "xbrlle" );
define( "STANDARD_PREFIX_GENERIC_ERROR", "xbrlgene" );
define( "STANDARD_PREFIX_XML", "xml" );
define( "STANDARD_PREFIX_TABLE", "table" );
define( "STANDARD_PREFIX_TABLE_ERROR", "xbrlte" );
define( "STANDARD_PREFIX_TABLE_MODEL", "tablemodel" );
define( "STANDARD_PREFIX_FUNCTION_INSTANCE", "xfi" );
define( "STANDARD_PREFIX_FUNCTION_INSTANCE_ERROR", "xfie" );
define( "STANDARD_PREFIX_VARIABLE", "variable" );
define( "STANDARD_PREFIX_VARIABLE_ERROR", "xbrlve" );
define( "STANDARD_PREFIX_FORMULA", "formula" );
define( "STANDARD_PREFIX_FORMULA_TUPLE", "tuple" );
define( "STANDARD_PREFIX_FILTER_DIMENSION", "df" );
define( "STANDARD_PREFIX_ASPECT_TEST", "aspectTest" );
define( "STANDARD_PREFIX_XPATH_FUNCTIONS", "fn" );
define( "STANDARD_PREFIX_VALIDATION", "validation" );
define( "STANDARD_PREFIX_VALIDATION_MESSAGE", "valm" );
define( "STANDARD_PREFIX_VALIDATION_MESSAGE_ERROR", "xbrlvalme" );
define( "STANDARD_PREFIX_VALUE", "va" );
define( "STANDARD_PREFIX_EXISTENCE", "ea" );
define( "STANDARD_PREFIX_CONSISTENCY", "ca" );
define( "STANDARD_PREFIX_MESSAGE", "msg" );
define( "STANDARD_PREFIX_MESSAGE_ERROR", "xbrlmsge" );
define( "STANDARD_PREFIX_ACF", "acf" );
define( "STANDARD_PREFIX_BF", "bf" );
define( "STANDARD_PREFIX_CA", "ca" );
define( "STANDARD_PREFIX_CF", "cf" );
define( "STANDARD_PREFIX_CFI", "cfi" );
define( "STANDARD_PREFIX_CF_ERROR", "xbrlcfe" );
define( "STANDARD_PREFIX_CRF", "crf" );
define( "STANDARD_PREFIX_DF", "df" );
define( "STANDARD_PREFIX_EA", "ea" );
define( "STANDARD_PREFIX_EF", "ef" );
define( "STANDARD_PREFIX_GF", "gf" );
define( "STANDARD_PREFIX_PF", "pf" );
define( "STANDARD_PREFIX_RF", "rf" );
define( "STANDARD_PREFIX_TF", "tf" );
define( "STANDARD_PREFIX_UF", "uf" );
define( "STANDARD_PREFIX_VA", "va" );
define( "STANDARD_PREFIX_REG", "reg" );
define( "STANDARD_PREFIX_FUNCTION", "fcn" );
define( "STANDARD_PREFIX_CONFORMANCE_FUNCTION", "cfcn" );
define( "STANDARD_PREFIX_FUNCTION_FORMULA", "xff" );
define( "STANDARD_PREFIX_FUNCTION_FORMULA_ERROR", "xffe" );
define( "STANDARD_PREFIX_IMPLICIT_FILTER_ERROR", "xbrlife" );
define( "STANDARD_PREFIX_INSTANCES", "instances" );
define( "STANDARD_PREFIX_LYQUIDITY", "lyquidity" );
define( "STANDARD_PREFIX_ASPECT", "aspect" );
define( "STANDARD_PREFIX_SEVERITY", "sev" );
define( "STANDARD_PREFIX_ENUMERATIONS", "enum" );
define( "STANDARD_PREFIX_ENUMERATIONS_TAXONOMY_ERRORS", "enumte" );
define( "STANDARD_PREFIX_ENUMERATIONS_INSTANCE_ERRORS", "enumie" );
/** PWD */
define( "STANDARD_PREFIX_ENUMERATIONS_20", "enum20" );
define( "STANDARD_PREFIX_ENUMERATIONS_TAXONOMY_ERRORS_20", "enumte20" );
define( "STANDARD_PREFIX_ENUMERATIONS_INSTANCE_ERRORS_20", "enumie20" );
define( "STANDARD_PREFIX_GENERIC_PREFERRED_LABEL", "gpl" );
define( "STANDARD_PREFIX_GENERIC_PREFERRED_LABEL_ERROR", "gple" );

define( "STANDARD_PREFIX_DTR_NUMERIC", "num" );
define( "STANDARD_PREFIX_DTR_NONNUMERIC", "nonnum" );
define( "STANDARD_PREFIX_DTR_TYPES", "dtr-types" );

/**
 * A collection of constants from the XBRL 2.1, XBRL Dimensions 1.0 and XBRL Formula specifications
 *
 */
class XBRL_Constants
{

	/**
	 * Cache of previously generated XBRL QNames.
	 * @var array
	 */
	private static $qnames = array();

	/**
	 * Convert a local name with a prefix into a QName instance with resolved namespace
	 * using the set of $standardPrefixes to lookup the prefix
	 *
	 * @param string $localName Local name with prefix
	 * @return null|QName
	 */
	public static function qnameNS( $localName )
	{
		if ( ! isset( self::$qnames[ $localName ] ) )
		{
			self::$qnames[ $localName ] = qname( $localName, XBRL_Constants::$standardPrefixes );
		}
		return self::$qnames[ $localName ];
	}

	/* -----------------------------------------------------------------------------
	 * Standard prefixes
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * A list of the standard prefixes used by XBRL and the correponding namespace
	 */
	public static $standardPrefixes = array(
		STANDARD_PREFIX_XPATH_FUNCTIONS					=> "http://www.w3.org/2005/xpath-functions",
		STANDARD_PREFIX_LINK							=> "http://www.xbrl.org/2003/linkbase",
		STANDARD_PREFIX_GENERIC							=> "http://xbrl.org/2008/generic",
		STANDARD_PREFIX_ISO4217							=> "http://www.xbrl.org/2003/iso4217",
		STANDARD_PREFIX_IX								=> "http://www.xbrl.org/2008/inlineXBRL",
		STANDARD_PREFIX_IX11							=> "http://www.xbrl.org/2013/inlineXBRL",
		STANDARD_PREFIX_IXT								=> "http://www.xbrl.org/inlineXBRL/transformation/2010-04-20",
		STANDARD_PREFIX_LABEL							=> "http://xbrl.org/2008/label",
		STANDARD_PREFIX_MODEL							=> "http://www.eurofiling.info/xbrl/ext/model",
		STANDARD_PREFIX_REFERENCE						=> "http://xbrl.org/2008/reference",
		STANDARD_PREFIX_REFERENCE_ERROR					=> "http://xbrl.org/2008/reference/error",
		STANDARD_PREFIX_UTR								=> "http://www.xbrl.org/2009/utr",
		STANDARD_PREFIX_UTR_ERROR						=> "http://www.xbrl.org/2009/utr/errors",
		STANDARD_PREFIX_LABEL_ERROR						=> "http://xbrl.org/2008/label/error",
		STANDARD_PREFIX_XBRLDT							=> "http://xbrl.org/2005/xbrldt",
		STANDARD_PREFIX_XBRLDT_ERROR					=> "http://xbrl.org/2005/xbrldt/errors",
		STANDARD_PREFIX_XBRLDI							=> "http://xbrl.org/2006/xbrldi",
		STANDARD_PREFIX_XBRLDI_ERROR					=> "http://xbrl.org/2005/xbrldi/errors",
		STANDARD_PREFIX_GENERIC_ERROR					=> "http://xbrl.org/2008/generic/error",
		STANDARD_PREFIX_XBRLI							=> "http://www.xbrl.org/2003/instance",
		STANDARD_PREFIX_XLINK							=> "http://www.w3.org/1999/xlink",
		STANDARD_PREFIX_XL								=> "http://www.xbrl.org/2003/XLink",
		STANDARD_PREFIX_SCHEMA							=> "http://www.w3.org/2001/XMLSchema",
		STANDARD_PREFIX_SCHEMA_ALTERNATIVE				=> "http://www.w3.org/2001/XMLSchema",
		"xhtml"											=> "http://www.w3.org/1999/xhtml",
		STANDARD_PREFIX_SCHEMA_INSTANCE					=> "http://www.w3.org/2001/XMLSchema-instance",
		STANDARD_PREFIX_XML								=> "http://www.w3.org/XML/1998/namespace",
		STANDARD_PREFIX_TABLE							=> "http://xbrl.org/2014/table",
		STANDARD_PREFIX_TABLE_ERROR						=> "http://xbrl.org/2014/table/error",
		STANDARD_PREFIX_TABLE_MODEL						=> "http://xbrl.org/2014/table/model",
		STANDARD_PREFIX_FUNCTION_INSTANCE_ERROR			=> "http://www.xbrl.org/2008/function/instance/error",
		STANDARD_PREFIX_FUNCTION_INSTANCE				=> "http://www.xbrl.org/2008/function/instance",
		STANDARD_PREFIX_VARIABLE						=> "http://xbrl.org/2008/variable",
		STANDARD_PREFIX_FORMULA							=> "http://xbrl.org/2008/formula",
		STANDARD_PREFIX_FORMULA_TUPLE					=> "http://xbrl.org/2010/formula/tuple",
		STANDARD_PREFIX_FILTER_DIMENSION				=> "http://xbrl.org/2008/filter/dimension",
		STANDARD_PREFIX_VARIABLE_ERROR					=> "http://xbrl.org/2008/variable/error",
		STANDARD_PREFIX_ASPECT							=> "http://xbrl.org/2008/variable/aspect", // This one is used by aspect tests
		STANDARD_PREFIX_ASPECT_TEST						=> "http://xbrl.org/2008/variable/aspectTest",
		STANDARD_PREFIX_VALIDATION						=> "http://xbrl.org/2008/validation",
		STANDARD_PREFIX_VALIDATION_MESSAGE				=> "http://xbrl.org/2010/message/validation",
		STANDARD_PREFIX_VALIDATION_MESSAGE_ERROR		=> "http://xbrl.org/2010/message/validation/error",
		STANDARD_PREFIX_VALUE							=> "http://xbrl.org/2008/assertion/value",
		STANDARD_PREFIX_EXISTENCE						=> "http://xbrl.org/2008/assertion/existence",
		STANDARD_PREFIX_CONSISTENCY						=> "http://xbrl.org/2008/assertion/consistency",
		STANDARD_PREFIX_MESSAGE							=> "http://xbrl.org/2010/message",
		STANDARD_PREFIX_MESSAGE_ERROR					=> "http://xbrl.org/2010/message/error",
		STANDARD_PREFIX_ACF								=> "http://xbrl.org/2010/filter/aspect-cover",
		STANDARD_PREFIX_BF								=> "http://xbrl.org/2008/filter/boolean",
		STANDARD_PREFIX_CA								=> "http://xbrl.org/2008/assertion/consistency",
		STANDARD_PREFIX_CF								=> "http://xbrl.org/2008/filter/concept",
		STANDARD_PREFIX_CF_ERROR						=> "http://xbrl.org/2008/filter/concept/error",
		STANDARD_PREFIX_CFI								=> "http://xbrl.org/2010/custom-function",
		STANDARD_PREFIX_CRF								=> "http://xbrl.org/2010/filter/concept-relation",
		STANDARD_PREFIX_DF								=> "http://xbrl.org/2008/filter/dimension",
		STANDARD_PREFIX_EA								=> "http://xbrl.org/2008/assertion/existence",
		STANDARD_PREFIX_EF								=> "http://xbrl.org/2008/filter/entity",
		STANDARD_PREFIX_PF								=> "http://xbrl.org/2008/filter/period",
		STANDARD_PREFIX_GF								=> "http://xbrl.org/2008/filter/general",
		STANDARD_PREFIX_RF								=> "http://xbrl.org/2008/filter/relative",
		STANDARD_PREFIX_TF								=> "http://xbrl.org/2008/filter/tuple",
		STANDARD_PREFIX_UF								=> "http://xbrl.org/2008/filter/unit",
		STANDARD_PREFIX_VA								=> "http://xbrl.org/2008/assertion/value",
		STANDARD_PREFIX_REG								=> "http://xbrl.org/2008/registry",
		STANDARD_PREFIX_FUNCTION						=> "http://xbrl.org/2008/function",
		STANDARD_PREFIX_CONFORMANCE_FUNCTION			=> "http://xbrl.org/2008/conformance/function",
		STANDARD_PREFIX_FUNCTION_FORMULA				=> "http://www.xbrl.org/2010/function/formula",
		STANDARD_PREFIX_FUNCTION_FORMULA_ERROR			=> "http://www.xbrl.org/2010/function/formula/error",
		STANDARD_PREFIX_IMPLICIT_FILTER_ERROR			=> "http://xbrl.org/2008/filter/implicit/error",
		STANDARD_PREFIX_INSTANCES						=> "http://xbrl.org/2010/variable/instance",
		STANDARD_PREFIX_LYQUIDITY						=> "http://lyquidity.com/2017/functions",
		STANDARD_PREFIX_SEVERITY						=> "http://xbrl.org/2016/assertion-severity",
		STANDARD_PREFIX_ENUMERATIONS					=> "http://xbrl.org/2014/extensible-enumerations",
		STANDARD_PREFIX_ENUMERATIONS_INSTANCE_ERRORS	=> "http://xbrl.org/2014/extensible-enumerations/instance-errors",
		STANDARD_PREFIX_ENUMERATIONS_TAXONOMY_ERRORS	=> "http://xbrl.org/2014/extensible-enumerations/taxonomy-errors",
		STANDARD_PREFIX_ENUMERATIONS_20					=> "http://xbrl.org/PWD/2017-09-05/extensible-enumerations-2.0",
		STANDARD_PREFIX_ENUMERATIONS_INSTANCE_ERRORS_20	=> "http://xbrl.org/PWD/2017-09-05/extensible-enumerations-2.0/instance-errors",
		STANDARD_PREFIX_ENUMERATIONS_TAXONOMY_ERRORS_20	=> "http://xbrl.org/PWD/2017-09-05/extensible-enumerations-2.0/taxonomy-errors",
		STANDARD_PREFIX_GENERIC_PREFERRED_LABEL			=> "http://xbrl.org/2013/preferred-label",
		STANDARD_PREFIX_GENERIC_PREFERRED_LABEL_ERROR	=> "http://xbrl.org/2013/preferred-label/error",
		STANDARD_PREFIX_DTR_NUMERIC						=> "http://www.xbrl.org/dtr/type/numeric",
		STANDARD_PREFIX_DTR_NONNUMERIC					=> "http://www.xbrl.org/dtr/type/non-numeric",
		STANDARD_PREFIX_DTR_TYPES						=> "http://www.xbrl.org/dtr/type/2020-01-21",
	);

	/**
	 * A list of the standard XBRL namespaces indexed by namespace.  Populated by the static constructor.
	 */
	public static $standardNamespaces = array();

	/**
	 * A list of the locations of the standard schemas
	 */
	public static $standardNamespaceSchemaLocations = array(
		STANDARD_PREFIX_GENERIC						=> "http://www.xbrl.org/2008/generic-link.xsd",
		STANDARD_PREFIX_LABEL						=> "http://www.xbrl.org/2008/generic-label.xsd",
		"genLabel"									=> "http://www.xbrl.org/2008/generic-label.xsd",
		STANDARD_PREFIX_REFERENCE					=> "http://www.xbrl.org/2008/generic-reference.xsd",
		"genReference"								=> "http://www.xbrl.org/2008/generic-reference.xsd",
		STANDARD_PREFIX_LINK						=> "http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd",
		STANDARD_PREFIX_XLINK						=> "http://www.xbrl.org/2003/xlink-2003-12-31.xsd",
		STANDARD_PREFIX_XBRLI						=> "http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd",
		STANDARD_PREFIX_XBRLDT						=> "http://www.xbrl.org/2005/xbrldt-2005.xsd",
		STANDARD_PREFIX_XBRLDI						=> "http://www.xbrl.org/2006/xbrldi-2006.xsd",
		STANDARD_PREFIX_XL							=> "http://www.xbrl.org/2003/xl-2003-12-31.xsd",
		STANDARD_PREFIX_DTR_NUMERIC					=> "http://www.xbrl.org/dtr/type/numeric-2009-12-16.xsd",
		STANDARD_PREFIX_DTR_NONNUMERIC				=> "http://www.xbrl.org/dtr/type/nonnumeric-2009-12-16.xsd",
		STANDARD_PREFIX_FORMULA						=> "http://www.xbrl.org/2008/formula.xsd",
		STANDARD_PREFIX_CONSISTENCY					=> "http://www.xbrl.org/2008/consistency-assertion.xsd",
		STANDARD_PREFIX_EXISTENCE					=> "http://www.xbrl.org/2008/existence-assertion.xsd",
		STANDARD_PREFIX_FILTER_DIMENSION			=> "http://www.xbrl.org/2008/dimension-filter.xsd",
		STANDARD_PREFIX_VALUE						=> "http://www.xbrl.org/2008/value-assertion.xsd",
		STANDARD_PREFIX_VA							=> "http://www.xbrl.org/2008/value-assertion.xsd",
		STANDARD_PREFIX_PF							=> "http://www.xbrl.org/2008/period-filter.xsd",
		STANDARD_PREFIX_CF							=> "http://www.xbrl.org/2008/concept-filter.xsd",
		STANDARD_PREFIX_DF							=> "http://www.xbrl.org/2008/dimension-filter.xsd",
		STANDARD_PREFIX_GF							=> "http://www.xbrl.org/2008/general-filter.xsd",
		STANDARD_PREFIX_UF							=> "http://www.xbrl.org/2008/unit-filter.xsd",
		STANDARD_PREFIX_EF							=> "http://www.xbrl.org/2008/entity-filter.xsd",
		STANDARD_PREFIX_VARIABLE					=> "http://www.xbrl.org/2008/variable.xsd",
		STANDARD_PREFIX_VALIDATION_MESSAGE			=> "http://www.xbrl.org/2010/validation-message.xsd",
		STANDARD_PREFIX_VALIDATION_MESSAGE_ERROR	=> "http://www.xbrl.org/2010/validation-message.xsd",
		STANDARD_PREFIX_VALIDATION					=> "http://www.xbrl.org/2008/validation.xsd",
		STANDARD_PREFIX_MESSAGE						=> "http://www.xbrl.org/2010/generic-message.xsd",
	);

	/**
	 * Initialize the static class
	 */
	public static function __static()
	{
		$prefixes = array( STANDARD_PREFIX_SCHEMA, STANDARD_PREFIX_XBRLI, STANDARD_PREFIX_LINK, STANDARD_PREFIX_XLINK, STANDARD_PREFIX_GENERIC, STANDARD_PREFIX_XBRLDT, STANDARD_PREFIX_XBRLDI );
		self::$standardNamespaces = $temp = array_flip( array_intersect_key( self::$standardPrefixes, array_flip( $prefixes ) ) );

		self::$standardArcElements = array_flip(
			array(
				"definitionArc",
				"calculationArc",
				"presentationArc",
				"labelArc",
				"referenceArc",
				"footnoteArc",
			)
		);

		self::$standardLinkElements = array_flip(
			array(
				"definitionLink",
				"calculationLink",
				"presentationLink",
				"labelLink",
				"referenceLink",
				"footnoteLink",
				"label",
				"footnote",
				"reference",
			)
		);

		foreach ( self::$standardArcElements as $arc => $value )
		{
			$qname = self::qnameNS( "link:$arc" );
			self::$standardArcQnames[ (string) $qname ] = $qname;
		}

		foreach ( self::$standardLinkElements as $link => $value )
		{
			/**
			 * @var QName qname
			 */
			$qname = self::qnameNS( "link:$link" );
			self::$standardExtLinkQnamesAndResources[ (string) $qname ] = $qname;
		}

		self::$standardExtLinkQnames = array_filter(
			self::$standardExtLinkQnamesAndResources,
			function( $qname ) { return XBRL::endsWith( $qname->localName, 'Link' ); }
		);

		self::$resourceArcRoles = array_flip(
			array(
				self::$arcRoleConceptLabel,
				self::$arcRoleConceptReference,
				self::$arcRoleFactFootnote,
				self::$genericElementLabel,
				self::$genericElementReference,
			)
		);

		self::$standardLabelRoles = array(
			self::$labelRoleLabel,
			self::$labelRoleTerseLabel,
			self::$labelRoleVerboseLabel,
			self::$labelRolePositiveLabel,
			self::$labelRolePositiveTerseLabel,
			self::$labelRolePositiveVerboseLabel,
			self::$labelRoleNegativeLabel,
			self::$labelRoleNegativeTerseLabel,
			self::$labelRoleNegativeVerboseLabel,
			self::$labelRoleZeroLabel,
			self::$labelRoleZeroTerseLabel,
			self::$labelRoleZeroVerboseLabel,
			self::$labelRoleTotalLabel,
			self::$labelRolePeriodStartLabel,
			self::$labelRolePeriodEndLabel,
			self::$labelRoleDocumentation,
			self::$labelRoleDefinitionGuidance,
			self::$labelRoleDisclosureGuidance,
			self::$labelRolePresentationGuidance,
			self::$labelRoleMeasurementGuidance,
			self::$labelRoleCommentaryGuidance,
			self::$labelRoleExampleGuidance,
		);

		self::$standardRoles = array_flip(
			array_merge(
				self::$standardLabelRoles,
				self::$standardReferenceRoles,
				self::$standardLinkbaseRefRoles,
				array(
						self::$defaultLinkRole,
						self::$footnote,
				)
			)
		);

		self::$standardArcRoles = array_flip(
			array(
				self::$linkCalculationArc,
				self::$linkDefinitionArc,
				self::$linkFootnoteArc,
				self::$linkLabelArc,
				self::$linkPresentationArc,
				self::$linkReferenceArc,
			)
		);

		self::$standardArcroleCyclesAllowed = array(
			self::$arcRoleConceptLabel		=> array( "any", null ),
			self::$arcRoleConceptReference	=> array( "any", null ),
			self::$arcRoleFactFootnote		=> array( "any", null ),
			self::$arcRoleParentChild		=> array( "undirected", "xbrl.5.2.4.2" ),
			self::$arcRoleSummationItem		=> array( "any", "xbrl.5.2.5.2" ),
			self::$arcRoleGeneralSpecial	=> array( "undirected", "xbrl.5.2.6.2.1" ),
			self::$arcRoleEssenceAlias		=> array( "undirected", "xbrl.5.2.6.2.1" ),
			self::$arcRoleSimilarTuples		=> array( "any", "xbrl.5.2.6.2.3" ),
			self::$arcRoleRequiresElement	=> array( "any", "xbrl.5.2.6.2.4" ),
		);

		self::$consecutiveArcrole = array( // can be list of or single arcrole
			'all'						=> array( self::$arcRoleDimensionDomain, self::$arcRoleHypercubeDimension ),
			'notAll'					=> array( self::$arcRoleDimensionDomain, self::$arcRoleHypercubeDimension ),
			'hypercubeDimension'		=> self::$arcRoleDimensionDomain,
			'dimensionDomain'			=> array( self::$arcRoleDomainMember, self::$arcRoleAll, self::$arcRoleNotAll ),
			'domainMember'				=> array( self::$arcRoleDomainMember, self::$arcRoleAll, self::$arcRoleNotAll ),
			'dimensionDefault'			=> array(),
		);

		self::$hasHypercube = array(
			self::$arcRoleAll,
			self::$arcRoleNotAll,
		);

		self::$standardLinkbaseRefRoles = array(
			self::$CalculationLinkbaseRef,
			self::$DefinitionLinkbaseRef,
			self::$LabelLinkbaseRef,
			self::$PresentationLinkbaseRef,
			self::$ReferenceLinkbaseRef,
		);

		self::$arcCustomAttributesExclusions = array(
			'http://www.w3.org/2000/xmlns',
			self::$standardPrefixes[ STANDARD_PREFIX_XLINK ],
			"use",
			"priority",
			"order",
		);

		self::$xbrliSubstitutionHeads = array(
			self::$xbrldtDimensionItem,
			self::$xbrldtHypercubeItem,
			self::$xbrliItem,
			self::$xbrliTuple,
		);

		self::$arcRoleDimensional = array(
			XBRL_Constants::$arcRoleAll,
			XBRL_Constants::$arcRoleNotAll,
			XBRL_Constants::$arcRoleDimensionDomain,
			XBRL_Constants::$arcRoleDomainMember,
			XBRL_Constants::$arcRoleDimensionMember,
			XBRL_Constants::$arcRoleHypercubeDimension,
			XBRL_Constants::$arcRoleDimensionDefault,
		);
	}

	/* -----------------------------------------------------------------------------
	 * Standard Xml elements
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * A list of those attributes that should not be included in the identification of equivalent arcs
	 *
	 * @var array
	 */
	public static $arcCustomAttributesExclusions;

	// BMS 2018-04-09 Test candidates changed.

	/**
	 * Standard Xml element xs:schema
	 * @var string
	 */
	public static $xsdSchema = "xs:schema";
	/**
	 * Standard Xml element xs:appinfo
	 * @var string
	 */
	public static $xsdAppinfo = "xs:appinfo";
	/**
	 * Standard Xml element xs:anyType
	 * @var string
	 */
	public static $xsdDefaultType = "xs:anyType";
	/**
	 * Standard Xml element xsi:nil
	 * @var string
	 */
	public static $xsiNil = "xsi:nil";
	/**
	 * Standard Xml element xml:lang
	 * @var string
	 */
	public static $xmlLang = "xml:lang";

	/* -----------------------------------------------------------------------------
	 * Instances elements
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Instances element xbrli:xbrl
	 * @var string
	 */
	public static $xbrliXbrl		= "xbrli:xbrl";
	/**
	 * Instances element xbrli:item
	 * @var string
	 */
	public static $xbrliItem		= "xbrli:item";
	/**
	 * Instances element xbrli:numerator
	 * @var string
	 */
	public static $xbrliNumerator	= "xbrli:numerator";
	/**
	 * Instances element xbrli:denominator
	 * @var string
	 */
	public static $xbrliDenominator	= "xbrli:denominator";
	/**
	 * Instances element xbrli:tuple
	 * @var string
	 */
	public static $xbrliTuple		= "xbrli:tuple";
	/**
	 * Instances element xbrli:context
	 * @var string
	 */
	public static $xbrliContext		= "xbrli:context";
	/**
	 * Instances element xbrli:period
	 * @var string
	 */
	public static $xbrliPeriod		= "xbrli:period";
	/**
	 * Instances element xbrli:identifier
	 * @var string
	 */
	public static $xbrliIdentifier	= "xbrli:identifier";
	/**
	 * Instances element xbrli:unit
	 * @var string
	 */
	public static $xbrliUnit		= "xbrli:unit";

	/**
	 * segment
	 * @var string
	 */
	public static $xbrliSegment		= "segment";

	/**
	 * segment
	 * @var string
	 */
	public static $xbrliScenario	= "scenario";

	/* -----------------------------------------------------------------------------
	 * Instances element attributes
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Instances element type attribute xbrli:stringItemType
	 * @var string
	 */
	public static $xbrliStringItemType		= "xbrli:stringItemType";
	/**
	 * Instances element type attribute xbrli:monetaryItemType
	 * @var string
	 */
	public static $xbrliMonetaryItemType	= "xbrli:monetaryItemType";
	/**
	 * Instances element type attribute xbrli:dateItemType
	 * @var string
	 */
	public static $xbrliDateItemType		= "xbrli:dateItemType";
	/**
	 * Instances element type attribute xbrli:durationItemType
	 * @var string
	 */
	public static $xbrliDurationItemType	= "xbrli:durationItemType";
	/**
	 * Instances element type attribute xbrli:booleanItemType
	 * @var string
	 */
	public static $xbrliBooleanItemType		= "xbrli:booleanItemType";
	/**
	 * Instances element type attribute xbrli:QNameItemType
	 * @var string
	 */
	public static $xbrliQNameItemType		= "xbrli:QNameItemType";

	/**
	 * Instances element type attribute xbrli:pure
	 * @var string
	 */
	public static $xbrliPure				= "xbrli:pure";
	/**
	 * Instances element type attribute xbrli:pure
	 * @var string
	 */
	public static $xbrliPureItemType		= "xbrli:pureItemType";
	/**
	 * Instances element type attribute xbrli:shares
	 * @var string
	 */
	public static $xbrliShares				= "xbrli:shares";
	/**
	 * Instances element type attribute xbrli:shares
	 * @var string
	 */
	public static $xbrliSharesItemType		= "xbrli:sharesItemType";
	/**
	 * Instances element type attribute xbrli:dateUnion
	 * @var string
	 */
	public static $xbrliDateUnion			= "xbrli:dateUnion";
	/**
	 * Instances element type attribute xbrli:decimalsType
	 * @var string
	 */
	public static $xbrliDecimalsUnion		= "xbrli:decimalsType";
	/**
	 * Instances element type attribute xbrli:precisionType
	 * @var string
	 */
	public static $xbrliPrecisionUnion		= "xbrli:precisionType";
	/**
	 * Instances element type attribute xbrli:nonZeroDecimal
	 * @var string
	 */
	public static $xbrliNonZeroDecimalUnion	= "xbrli:nonZeroDecimal";

	/* -----------------------------------------------------------------------------
	 * Instances element tuple attributes
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * An array of the two types used as substitution group heads for concepts
	 * @var array
	 */
	public static $xbrliSubstitutionHeads = array();

	/* -----------------------------------------------------------------------------
	 * Link elements
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Link link element link:linkbase
	 * @var string
	 */
	public static $linkLinkbase				= "link:linkbase";
	/**
	 * Link link element link:labelLink
	 * @var string
	 */
	public static $linkLabelLink			= "link:labelLink";
	/**
	 * Link link element link:referenceLink
	 * @var string
	 */
	public static $linkReferenceLink		= "link:referenceLink";
	/**
	 * Link link element link:footnoteLink
	 * @var string
	 */
	public static $linkFootnoteLink			= "link:footnoteLink";
	/**
	 * Link link element link:presentationLink
	 * @var string
	 */
	public static $linkPresentationLink		= "link:presentationLink";
	/**
	 * Link link element link:calculationLink
	 * @var string
	 */
	public static $linkCalculationLink		= "link:calculationLink";
	/**
	 * Link link element link:definitionLink
	 * @var string
	 */
	public static $linkDefinitionLink		= "link:definitionLink";

	/**
	 * Link locator element
	 * @var string
	 */
	public static $linkLoc					= "link:loc";
	/**
	 * Link arc element
	 * @var string
	 */
	public static $linkLabelArc				= "link:labelArc";
	/**
	 * Link arc element
	 * @var string
	 */
	public static $linkReferenceArc			= "link:referenceArc";
	/**
	 * Link arc element
	 * @var string
	 */
	public static $linkFootnoteArc			= "link:footnoteArc";
	/**
	 * Link arc element
	 * @var string
	 */
	public static $linkPresentationArc		= "link:presentationArc";
	/**
	 * Link arc element
	 * @var string
	 */
	public static $linkCalculationArc		= "link:calculationArc";
	/**
	 * Link arc element
	 * @var string
	 */
	public static $linkDefinitionArc		= "link:definitionArc";

	/* -----------------------------------------------------------------------------
	 * Variable arcs
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Variable arc element
	 * @var string
	 */
	public static $linkVariableArc			= "variable:variableArc";
	/**
	 * Variable arc element
	 * @var string
	 */
	public static $linkVariableFilterArc	= "variable:variableFilterArc";
	/**
	 * Variable arc element
	 * @var string
	 */
	public static $linkVariableSetFilterArc	= "variable:variableSetFilterArc";

	// <variable:precondition>
	// <variable:generalVariable>
	// <variable:equalityDefinition>
	// <variable:function>
	// <variable:input>
	// <variable:parameter>
	// <variable:generalVariable>
	// <variable:factVariable>
	// <variable:filter>
	// <variable:variableSet>
	//
	// <variable:variableFilterArc>
	// <variable:variableArc>
	// <variable:variableSetFilterArc>

	/* -----------------------------------------------------------------------------
	 * Arc attributes
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Arc attribute link:label
	 * @var string
	 */
	public static $linkLabel				= "link:label";
	/**
	 * Arc attribute link:reference
	 * @var string
	 */
	public static $linkReference			= "link:reference";
	/**
	 * Arc attribute link:part
	 * @var string
	 */
	public static $linkPart					= "link:part";
	/**
	 * Arc attribute link:footnote
	 * @var string
	 */
	public static $linkFootnote				= "link:footnote";

	/* -----------------------------------------------------------------------------
	 * Arc roles
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * The prefix used for all role namespaces
	 * @var string
	 */
	public static $rolePrefix				= "http://www.xbrl.org/2003/role/";

	/**
	 * http://www.xbrl.org/2003/role/link
	 * @var string
	 */
	public static $defaultLinkRole			= "http://www.xbrl.org/2003/role/link";

	/**
	 * http://www.xbrl.org/2008/role/link
	 * @var string
	 */
	public static $defaultLinkRole2008			= "http://www.xbrl.org/2008/role/link";

	/**
	 * http://www.xbrl.org/2003/role/footnote
	 * @var string
	 */
	public static $footnote					= "http://www.xbrl.org/2003/role/footnote";

	/**
	 * http://www.xbrl.org/2003/role/reference
	 * @var string
	 */
	public static $reference2003			= "http://www.xbrl.org/2003/role/reference";

	/**
	 * http://www.xbrl.org/2008/role/reference
	 * @var string
	 */
	public static $reference2008			= "http://www.xbrl.org/2008/role/reference";

	/**
	 * The type value of an extended arc
	 * @var string $arcRoleTypeExtended
	 */
	public static  $arcRoleTypeExtended		= "extended";

	/**
	 * The type value of a simple arc
	 * @var string $arcRoleTypeSimple
	 */
	public static  $arcRoleTypeSimple		= "simple";

	/**
	 * http://www.w3.org/1999/xlink/properties/linkbase
	 * @var string
	 */
	public static $arcRoleLinkbase			= "http://www.w3.org/1999/xlink/properties/linkbase";

	/**
	 * http://www.xbrl.org/2003/arcrole/
	 * @var string
	 */
	public static $arcRoleBase				= "http://www.xbrl.org/2003/arcrole/";
	/**
	 * http://www.xbrl.org/2003/arcrole/concept-label
	 * @var string
	 */
	public static $arcRoleConceptLabel		= "http://www.xbrl.org/2003/arcrole/concept-label";
	/**
	 * http://www.xbrl.org/2003/arcrole/concept-reference
	 * @var string
	 */
	public static $arcRoleConceptReference	= "http://www.xbrl.org/2003/arcrole/concept-reference";
	/**
	 * http://www.xbrl.org/2003/arcrole/fact-footnote
	 * @var string
	 */
	public static $arcRoleFactFootnote		= "http://www.xbrl.org/2003/arcrole/fact-footnote";
	/**
	 * http://www.xbrl.org/2003/arcrole/parent-child
	 * @var string
	 */
	public static $arcRoleParentChild		= "http://www.xbrl.org/2003/arcrole/parent-child";
	/**
	 * http://www.xbrl.org/2003/arcrole/summation-item
	 * @var string
	 */
	public static $arcRoleSummationItem		= "http://www.xbrl.org/2003/arcrole/summation-item";
	/**
	 * http://www.xbrl.org/2003/arcrole/essence-alias
	 * @var string
	 */
	public static $arcRoleEssenceAlias		= "http://www.xbrl.org/2003/arcrole/essence-alias";
	/**
	 * http://www.xbrl.org/2003/arcrole/similar-tuples
	 * @var string
	 */
	public static $arcRoleSimilarTuples		= "http://www.xbrl.org/2003/arcrole/similar-tuples";
	/**
	 * http://www.xbrl.org/2003/arcrole/requires-element
	 * @var string
	 */
	public static $arcRoleRequiresElement	= "http://www.xbrl.org/2003/arcrole/requires-element";
	/**
	 * http://www.xbrl.org/2003/arcrole/general-special
	 * @var string $arcRoleGeneralSpecial
	 */
	public static $arcRoleGeneralSpecial	= "http://www.xbrl.org/2003/arcrole/general-special";

	/* Validation message specification roles */

	/**
	 * http://xbrl.org/arcrole/2010/assertion-satisfied-message
	 * @var string $arcRoleAssertionSatisfiedMessage
	 */
	public static $arcRoleAssertionSatisfiedMessage = "http://xbrl.org/arcrole/2010/assertion-satisfied-message";

	/**
	 * http://xbrl.org/arcrole/2010/assertion-unsatisfied-message
	 * @var string $arcRoleAssertionUnsatisfiedMessage
	 */
	public static $arcRoleAssertionUnsatisfiedMessage = "http://xbrl.org/arcrole/2010/assertion-unsatisfied-message";

	/**
	 * http://xbrl.org/arcrole/2016/assertion-unsatisfied-severity
	 * @var string $arcRoleAssertionUnsatisfiedSeverity
	 */
	public static $arcRoleAssertionUnsatisfiedSeverity = "http://xbrl.org/arcrole/2016/assertion-unsatisfied-severity";

	/* Generic message roles */

	/**
	 * http://www.xbrl.org/2010/role/message (standard message)
	 * @var string $arcRoleGenericMessage
	 */
	public static $arcRoleGenericMessage = "http://www.xbrl.org/2010/role/message";

	/**
	 * http://www.xbrl.org/2010/role/verboseMessage
	 * @var string $arcRoleGenericMessageVerbose
	 */
	public static $arcRoleGenericMessageVerbose = "http://www.xbrl.org/2010/role/verboseMessage";

	/**
	 * http://www.xbrl.org/2010/role/terseMessage
	 * @var string $arcRoleGenericMessageTerse
	 */
	public static $arcRoleGenericMessageTerse = "http://www.xbrl.org/2010/role/terseMessage";

	/* Assertion roles */

	/**
	 * http://xbrl.org/arcrole/2008/variable-set
	 * @var string $arcRoleVariableFilter
	 */
	public static $arcRoleAssertionSet					= "http://xbrl.org/arcrole/2008/assertion-set";

	/**
	 * http://xbrl.org/arcrole/2008/consistency-assertion-formula
	 * @var string $arcRoleAssertionConsistencyFormula
	 */
	public static $arcRoleAssertionConsistencyFormula = "http://xbrl.org/arcrole/2008/consistency-assertion-formula";

	/* Variable roles */

	/**
	 * http://xbrl.org/arcrole/2008/variable-filter
	 * @var string $arcRoleVariableFilter
	 */
	public static $arcRoleBooleanFilter					= "http://xbrl.org/arcrole/2008/boolean-filter";

	/**
	 * http://xbrl.org/arcrole/2008/variable-filter
	 * @var string $arcRoleVariableFilter
	 */
	public static $arcRoleVariableFilter				= "http://xbrl.org/arcrole/2008/variable-filter";

	/**
	 * http://xbrl.org/arcrole/2008/variable-set
	 * @var string $arcRoleVariableFilter
	 */
	public static $arcRoleVariableSet					= "http://xbrl.org/arcrole/2008/variable-set";

	/**
	 * http://xbrl.org/arcrole/2008/variable-set-filter
	 * @var string $arcRoleVariableSetFilter
	 */
	public static $arcRoleVariableSetFilter				= "http://xbrl.org/arcrole/2008/variable-set-filter";

	/**
	 * http://xbrl.org/arcrole/2008/variable-set-precondition
	 * @var string $arcRoleVariableSetPrecondition
	 */
	public static $arcRoleVariableSetPrecondition		= "http://xbrl.org/arcrole/2008/variable-set-precondition";

	/**
	 * http://xbrl.org/arcrole/2008/equality-definition
	 * @var string $arcRoleVariableEqualityDefinition
	 */
	public static $arcRoleVariableEqualityDefinition	= "http://xbrl.org/arcrole/2008/equality-definition";

	/**
	 * http://xbrl.org/arcrole/2008/consistency-assertion-parameter
	 * @var string $arcRoleAssertionConsistencyParameter
	 */
	public static $arcRoleAssertionConsistencyParameter = "http://xbrl.org/arcrole/2008/consistency-assertion-parameter";

	/**
	 * http://xbrl.org/arcrole/2010/function-implementation
	 * @var string $arcRoleCustomFunctionImplementation
	 */
	public static $arcRoleCustomFunctionImplementation = "http://xbrl.org/arcrole/2010/function-implementation";

	/**
	 * http://xbrl.org/arcrole/2010/instance-variable
	 * @var string $arcRoleInstanceVariable
	 */
	public static $arcRoleInstanceVariable = "http://xbrl.org/arcrole/2010/instance-variable";

	/**
	 * http://xbrl.org/arcrole/2010/formula-instance
	 * @var string $arcRoleFormulaInstance
	 */
	public static $arcRoleFormulaInstance = "http://xbrl.org/arcrole/2010/formula-instance";

	/**
	 * http://xbrl.org/arcrole/2010/variables-scope
	 * @var string $arcRoleVariablesScope
	 */
	public static $arcRoleVariablesScope = "http://xbrl.org/arcrole/2010/variables-scope";

	/** Conceptual model arc roles */

	/**
	 * http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/class-subClass
	 * @var string
	 */
	public static $arcRoleConceptualModeSubClass = 'http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/class-subClass';
	/**
	 * http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/parentCategory-allowedChildCategory
	 * @var string
	 */
	public static $arcRoleConceptualModelAllowed = 'http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/parentCategory-allowedChildCategory';
	/**
	 * http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/parentCategory-disallowedChildCategory
	 * @var string
	 */
	public static $arcRoleConceptualModelDisAllowed = 'http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/parentCategory-disallowedChildCategory';
	/**
	 * http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/parentCategory-discouragedChildCategory
	 * @var string
	 */
	public static $arcRoleConceptualModelDiscouraged = 'http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/parentCategory-discouragedChildCategory';
	/**
	 * http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/class-equivalentClass
	 * @var string
	 */
	public static $arcRoleConceptualModelClassEquivalent = 'http://xbrlsite.azurewebsites.net/2016/conceptual-model/arcrole/class-equivalentClass';

	/* -----------------------------------------------------------------------------
	 * Severity values
	 * -----------------------------------------------------------------------------
	 */
	/**
	 * @var string Returns 'OK'
	 */
	public static $severityOK = ASSERTION_SEVERITY_OK;
	/**
	 * @var string Returns 'ERROR'
	 */
	public static $severityERROR = ASSERTION_SEVERITY_ERROR;
	/**
	 * @var string Returns 'WARNING'
	 */
	public static $severityWARNING = ASSERTION_SEVERITY_WARNING;

	/* -----------------------------------------------------------------------------
	 * Generic links
	 * -----------------------------------------------------------------------------
	 */
	/**
	 * Generic link
	 * @var string
	 */
	public static $genLink					= "gen:link";
	/**
	 * Generic link arc
	 * @var string
	 */
	public static $genArc					= "gen:arc";
	/**
	 * Generic link reference
	 * @var string
	 */
	public static $genReference				= "reference:reference";
	/**
	 * Generic link label
	 * @var string
	 */
	public static $genLabel					= "label:label";

	/**
	 * http://xbrl.org/arcrole/2008/element-reference
	 * For the generic references specification
	 * @var string
	 */
	public static $genericElementReference	= "http://xbrl.org/arcrole/2008/element-reference";

	/**
	 * http://xbrl.org/arcrole/2008/element-label
	 * For the generic labels specification
	 * @var string
	 */
	public static $genericElementLabel		= "http://xbrl.org/arcrole/2008/element-label";

	/**
	 * http://xbrl.org/arcrole/2008/element-label
	 * For the generic labels specification
	 * @var string
	 */
	public static $genericPreferredLabel	= "http://xbrl.org/2010/preferred-label";

	/**
	 * http://www.xbrl.org/2008/role/label
	 * Used for generic standard labels
	 * @var string
	 */
	public static $genericRoleLabel			= "http://www.xbrl.org/2008/role/label";

	/* -----------------------------------------------------------------------------
	 * Dimensions
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * xbrldt:hypercubeItem
	 * @var string
	 */
	public static $xbrldtHypercubeItem		= "xbrldt:hypercubeItem";
	/**
	 * xbrldt:dimensionItem
	 * @var string
	 */
	public static $xbrldtDimensionItem		= "xbrldt:dimensionItem";
	/**
	 * xbrldt:contextElement
	 * @var string
	 */
	public static $xbrldtContextElement		= "xbrldt:contextElement";
	/**
	 * xbrldt:explicitMember
	 * @var string
	 */
	public static $xbrldiExplicitMember		= "xbrldt:explicitMember";
	/**
	 * xbrldt:typedMember
	 * @var string
	 */
	public static $xbrldiTypedMember		= "xbrldt:typedMember";

	/* -----------------------------------------------------------------------------
	 * Dimension roles
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * http://xbrl.org/int/dim
	 * All dimension roles start with this string
	 * @var string
	 */
	public static $dimStartsWith					= "http://xbrl.org/int/dim";
	/**
	 * http://xbrl.org/int/dim/arcrole/all
	 * @var string $arcRoleAll
	 */
	public static $arcRoleAll						= "http://xbrl.org/int/dim/arcrole/all";
	/**
	 * http://xbrl.org/int/dim/arcrole/notAll
	 * @var string $arcRoleNotAll
	 */
	public static $arcRoleNotAll					= "http://xbrl.org/int/dim/arcrole/notAll";
	/**
	 * An array containing all and notAll arcroles
	 * @var array $hasHypercube
	 */
	public static $hasHypercube						= array();
	/**
	 * http://xbrl.org/int/dim/arcrole/hypercube-dimension
	 * @var string
	 */
	public static $arcRoleHypercubeDimension		= "http://xbrl.org/int/dim/arcrole/hypercube-dimension";
	/**
	 * http://xbrl.org/int/dim/arcrole/dimension-domain
	 * @var string
	 */
	public static $arcRoleDimensionDomain			= "http://xbrl.org/int/dim/arcrole/dimension-domain";
	/**
	 * http://xbrl.org/int/dim/arcrole/domain-member
	 * @var string
	 */
	public static $arcRoleDomainMember				= "http://xbrl.org/int/dim/arcrole/domain-member";
	/**
	 * http://xbrl.org/int/dim/arcrole/dimension-member
	 * @var string
	 */
	public static $arcRoleDimensionMember			= "http://xbrl.org/int/dim/arcrole/dimension-member";
	/**
	 * http://xbrl.org/int/dim/arcrole/dimension-default
	 * @var string
	 */
	public static $arcRoleDimensionDefault			= "http://xbrl.org/int/dim/arcrole/dimension-default";

	/**
	 * An array of the standard dimensional roles (set in the cont
	 * @var array
	 */
	public static $arcRoleDimensional = array();

	/* -----------------------------------------------------------------------------
	 * XLink
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Returns xl:arc
	 * @var string
	 */
	public static $xlArc					= "xl:arc";
	/**
	 * Returns xl:extended
	 * @var string
	 */
	public static $xlExtended				= "xl:extended";
	/**
	 * Returns xl:locator
	 * @var string
	 */
	public static $xlLocator				= "xl:locator";
	/**
	 * Returns xl:resource
	 * @var string
	 */
	public static $xlResource				= "xl:resource";
	/**
	 * Returns xl:extendedType
	 * @var string
	 */
	public static $xlExtendedType			= "xl:extendedType";
	/**
	 * Returns xl:locatorType
	 * @var string
	 */
	public static $xlLocatorType			= "xl:locatorType";
	/**
	 * Returns xl:resourceType
	 * @var string
	 */
	public static $xlResourceType			= "xl:resourceType";
	/**
	 * Returns xl:arcType
	 * @var string
	 */
	public static $xlArcType				= "xl:arcType";

	/**
	 * An array of the two possible values for the use attibute
	 * @var array
	 */
	public static $xlinkUseValues			= array( 'optional', 'prohibited' );

	/**
	 * An array of the two possible values for the use attibute
	 * @var array
	 */
	public static $xlinkUseOptional			= 'optional';

		/**
	 * An array of the two possible values for the use attibute
	 * @var array
	 */
	public static $xlinkUseProhibited		= 'prohibited';

	/**
	 * The 'arc' element type attribute value
	 * @var array
	 */
	public static $xlinkTypeArc				= 'arc';

	/**
	 * The 'locator' element type attribute value
	 * @var array
	 */
	public static $xlinkTypeLocator			= 'locator';

	/**
	 * The 'resource' element type attribute value
	 * @var array
	 */
	public static $xlinkTypeResource		= 'resource';

	/* -----------------------------------------------------------------------------
	 * Extensible enumerations
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Returns enum:enumerationItemType
	 * @var string $enumItemType
	 */
	public static $enumItemType				= "enum:enumerationItemType";

	/**
	 * Returns enum:enumerationSetItemType
	 * @var string $enumSetItemType
	 */
	public static $enumSetItemType			= "enum:enumerationSetItemType";

	/* -----------------------------------------------------------------------------
	 * Inline-XBRL
	 * -----------------------------------------------------------------------------
	 */

	/**
	 * Returns ix:continuation
	 * @var string
	 */
	public static $ixContinuation			= "ix:continuation"; /* 1.1 */
	/**
	 * Returns ix:denominator
	 * @var string
	 */
	public static $ixDenominator			= "ix:denominator";
	/**
	 * Returns ix:exclude
	 * @var string
	 */
	public static $ixExclude				= "ix:exclude";
	/**
	 * Returns ix:footnote
	 * @var string
	 */
	public static $ixFootnote				= "ix:footnote";
	/**
	 * Returns ix:fraction
	 * @var string
	 */
	public static $ixFraction				= "ix:fraction";
	/**
	 * Returns ix:header
	 * @var string
	 */
	public static $ixHeader					= "ix:header";
	/**
	 * Returns ix:hidden
	 * @var string
	 */
	public static $ixHidden					= "ix:hidden";
	/**
	 * Returns ix:nonFraction
	 * @var string
	 */
	public static $ixNonFraction			= "ix:nonFraction";
	/**
	 * Returns ix:nonNumeric
	 * @var string
	 */
	public static $ixNonNumeric				= "ix:nonNumeric";
	/**
	 * Returns ix:numerator
	 * @var string
	 */
	public static $ixNumerator				= "ix:numerator";
	/**
	 * Returns ix:references
	 * @var string
	 */
	public static $ixReferences				= "ix:references";
	/**
	 * Returns ix:relationship
	 * @var string
	 */
	public static $ixRelationship			= "ix:relationship"; /* 1.1 */
	/**
	 * Returns ix:resources
	 * @var string
	 */
	public static $ixResources				= "ix:resources";
	/**
	 * Returns ix:tuple
	 * @var string
	 */
	public static $ixTuple					= "ix:tuple";

	/**
	 * Inline-XBRL attributes
	 * @var array
	 */
	public static $ixAttributes = array(
		"arcrole",
		"contextRef",
		"continuationFrom",
		"continuedAt",
		"decimals",
		"escape",
		"footnoteID",
		"footnoteLinkRole",
		"footnoteRefs",
		"footnoteRole",
		"format",
		"fromRefs",
		"id",
		"linkRole",
		"name",
		"precision",
		"order",
		"scale",
		"sign",
		"target",
		"title",
		"toRefs",
		"tupleID",
		"tupleRef",
		"unitRef",
	);

	// Registries

	/* ------------------------------------------------------------------------------------------
	 * Unit type registry
	 * ------------------------------------------------------------------------------------------
	 */

	/**
	 * http://www.xbrl.org/utr/utr.xml
	 * @var string
	 */
	public static $xbrlUTR						= "http://www.xbrl.org/utr/utr.xml";

	/* ------------------------------------------------------------------------------------------
	 * Data type registry
	 * ------------------------------------------------------------------------------------------
	 */

	/**
	 * http://www.xbrl.org/dtr/type/
	 * The beginning part of all data type roles
	 * @var string
	 */
	public static $dtrTypesStartsWith		= "http://www.xbrl.org/dtr/type/";

	/**
	 * http://www.xbrl.org/dtr/type/numeric
	 * Numeric data type namespace
	 * @var string
	 */

	public static $dtrNumeric				= "http://www.xbrl.org/dtr/type/numeric";
	/**
	 * http://www.xbrl.org/dtr/type/non-numeric
	 * Non-numeric data type namespace
	 * @var string
	 */

	public static $dtrNonnum				= "http://www.xbrl.org/dtr/type/non-numeric";

	/**
	 * http://www.xbrl.org/dtr/type/2020-01-21
	 * Interoperable Taxonomy Architecture (ITA) initiative data type namespace
	 * @var string
	 */
	public static $dtrTypes					= "http://www.xbrl.org/dtr/type/2020-01-21";

	/* ------------------------------------------------------------------------------------------
	 * Standard Labels
	 * ------------------------------------------------------------------------------------------
	 */

	/**
	 * http://www.xbrl.org/2003/role/label
	 * Used for standard labels
	 * @var string
	 */
	public static $labelRoleLabel					= "http://www.xbrl.org/2003/role/label";
	/**
	 * http://www.xbrl.org/2003/role/terseLabel
	 * used for short labels where parts of the description that can be inferred are omitted.
	 * @var string
	 */
	public static $labelRoleTerseLabel				= "http://www.xbrl.org/2003/role/terseLabel";
	/**
	 * http://www.xbrl.org/2003/role/verboseLabel
	 * used for exteneded labels meant to give an exact description
	 * @var string
	 */
	public static $labelRoleVerboseLabel			= "http://www.xbrl.org/2003/role/verboseLabel";
	/**
	 * http://www.xbrl.org/2003/role/positiveLabel
	 * Used for standard labels when a numeric fact has a positive value
	 * @var string
	 */
	public static $labelRolePositiveLabel			= "http://www.xbrl.org/2003/role/positiveLabel";
	/**
	 * http://www.xbrl.org/2003/role/positiveTerseLabel
	 * used for short labels where parts of the description that can be inferred are omitted when a numeric fact has a positive value
	 * @var string
	 */
	public static $labelRolePositiveTerseLabel		= "http://www.xbrl.org/2003/role/positiveTerseLabel";
	/**
	 * http://www.xbrl.org/2003/role/positiveVerboseLabel
	 * used for exteneded labels meant to give an exact description when a numeric fact has a positive value
	 * @var string
	 */
	public static $labelRolePositiveVerboseLabel	= "http://www.xbrl.org/2003/role/positiveVerboseLabel";
	/**
	 * http://www.xbrl.org/2003/role/negativeLabel
	 * Used for standard labels when a numeric fact has a negative value
	 * @var string
	 */
	public static $labelRoleNegativeLabel			= "http://www.xbrl.org/2003/role/negativeLabel";
	/**
	 * http://www.xbrl.org/2003/role/negativeTerseLabel
	 * used for short labels where parts of the description that can be inferred are omitted when a numeric fact has a negative value
	 * @var string
	 */
	public static $labelRoleNegativeTerseLabel		= "http://www.xbrl.org/2003/role/negativeTerseLabel";
	/**
	 * http://www.xbrl.org/2003/role/negativeVerboseLabel
	 * used for exteneded labels meant to give an exact description when a numeric fact has a negative value
	 * @var string
	 */
	public static $labelRoleNegativeVerboseLabel	= "http://www.xbrl.org/2003/role/negativeVerboseLabel";
	/**
	 * http://www.xbrl.org/2003/role/zeroLabel
	 * Used for standard labels when a numeric fact has a zero value
	 * @var string
	 */
	public static $labelRoleZeroLabel				= "http://www.xbrl.org/2003/role/zeroLabel";
	/**
	 * http://www.xbrl.org/2003/role/zeroTerseLabel
	 * used for short labels where parts of the description that can be inferred are omitted when a numeric fact has a zero value
	 * @var string
	 */
	public static $labelRoleZeroTerseLabel			= "http://www.xbrl.org/2003/role/zeroTerseLabel";
	/**
	 * http://www.xbrl.org/2003/role/zeroVerboseLabel
	 * used for exteneded labels meant to give an exact description when a numeric fact has a zero value
	 * @var string
	 */
	public static $labelRoleZeroVerboseLabel		= "http://www.xbrl.org/2003/role/zeroVerboseLabel";
	/**
	 * http://www.xbrl.org/2003/role/totalLabel
	 * Used for labels on concepts that hold a total value
	 * @var string
	 */
	public static $labelRoleTotalLabel				= "http://www.xbrl.org/2003/role/totalLabel";
	/**
	 * http://www.xbrl.org/2003/role/periodStartLabel
	 * Used for labels on concepts presenting values associated with the start of a period.
	 * @var string
	 */
	public static $labelRolePeriodStartLabel		= "http://www.xbrl.org/2003/role/periodStartLabel";
	/**
	 * http://www.xbrl.org/2003/role/periodEndLabel
	 * Used for labels on concepts presenting values associated with the end of a period.
	 * @var string
	 */
	public static $labelRolePeriodEndLabel			= "http://www.xbrl.org/2003/role/periodEndLabel";
	/**
	 * http://www.xbrl.org/2003/role/documentation
	 * A label that provides documentation on a concept
	 * @var string
	 */
	public static $labelRoleDocumentation			= "http://www.xbrl.org/2003/role/documentation";
	/**
	 * http://www.xbrl.org/2003/role/definitionGuidance
	 * Documentation of a concept, providing an explanation of its meaning and its appropriate usage and any other documentation deemed necessary.
	 * @var string
	 */
	public static $labelRoleDefinitionGuidance		= "http://www.xbrl.org/2003/role/definitionGuidance";
	/**
	 * http://www.xbrl.org/2003/role/disclosureGuidance
	 * A precise definition of a concept, providing an explanation of its meaning and its appropriate usage.",
	 * @var string
	 */
	public static $labelRoleDisclosureGuidance		= "http://www.xbrl.org/2003/role/disclosureGuidance";
	/**
	 * http://www.xbrl.org/2003/role/presentationGuidance
	 * An explanation of the disclosure requirements relating to the concept. Indicates whether the disclosure is
	 * mandatory (i.e. prescribed by authoritative literature),
	 * recommended (i.e. encouraged by authoritative literature),
	 * common practice (i.e. not prescribed by authoritative literature, but disclosure is common place), or
	 * structural completeness (i.e. merely included to complete the structure of the taxonomy).
	 * @var string
	 */
	public static $labelRolePresentationGuidance	= "http://www.xbrl.org/2003/role/presentationGuidance";
	/**
	 * http://www.xbrl.org/2003/role/measurementGuidance
	 * An explanation of the rules guiding presentation (placement and/or labeling) of this concept in the context of other concepts in one or more specific types of business reports.
	 * For example, "Net Surplus should be disclosed on the face of the Profit and Loss statement".
	 * @var string
	 */
	public static $labelRoleMeasurementGuidance		= "http://www.xbrl.org/2003/role/measurementGuidance";
	/**
	 * http://www.xbrl.org/2003/role/placementGuidance
	 * An explanation of the method(s) required to be used when measuring values associated with this concept in business reports.
	 * @var string
	 */
	public static $labelRolePlacementGuidance		= "http://www.xbrl.org/2003/role/placementGuidance";

	/**
	 * http://www.xbrl.org/2003/role/commentaryGuidance
	 * For labels that give an explanation on an aspect of the concept such as it's definition, the way it should be measured or an example.
	 * @var string
	 */
	public static $labelRoleCommentaryGuidance		= "http://www.xbrl.org/2003/role/commentaryGuidance";
	/**
	 * http://www.xbrl.org/2003/role/exampleGuidance
	 * Any other general commentary on the concept that assists in determining definition, disclosure, measurement, presentation or usage.
	 * @var string
	 */
	public static $labelRoleExampleGuidance		= "http://www.xbrl.org/2003/role/exampleGuidance";

	/**
	 * http://www.xbrl.org/2009/role/negatedLabel
	 * @var string
	 */
	public static $labelRoleNegatedLabel = "http://www.xbrl.org/2009/role/negatedLabel";

	/**
	 * http://www.xbrl.org/2006/role/restatedLabel
	 * @var string
	 */
	public static $labelRoleRestatedLabel = "http://www.xbrl.org/2006/role/restatedLabel";
	/**
	 * Conceptual model dimensions
	 */

	// Semantic categorization:
	/**
	 * Reporting Entity [Axis]
	 */
	public static $dfrReportingEntityAxis = "ReportingEntityAxis";
	/**
	 * Legal Entity [Axis],
	 */
	public static $dfrLegalEntityAxis = "LegalEntityAxis";
	/**
	 * Concept [Axis]
	 */
	public static $dfrConceptAxis = "ConceptAxis";
	/**
	 * Business Segment [Axis]
	 */
	public static $dfrBusinessSegmentAxis = "BusinessSegmentAxis";
	/**
	 * Geographic Area [Axis]
	 */
	public static $dfrGeographicAreaAxis = "GeographicAreaAxis";
	/**
	 * Operating Activities [Axis]
	 */
	public static $dfrOperatingActivitiesAxis = "OperatingActivitiesAxis";
	/**
	 * Instrument [Axis]
	 */
	public static $dfrInstrumentAxis = "InstrumentAxis";
	/**
	 * Range [Axis]
	 */
	public static $dfrRangeAxis = "RangeAxis";

	// Content association:
	/**
	 * Reporting scenario [Axis]
	 */
	public static $dfrReportingScenarioAxis = "ReportingScenarioAxis";

	// Temporal or time-based:
	/**
	 * Calendar Period [Axis]
	 */
	public static $dfrCalendarPeriodAxis = "CalendarPeriodAxis";

	/**
	 * Report Date [Axis]
	 */
	public static $dfrReportDateAxis = "ReportDateAxis";

	/**
	 * Fiscal Period [Axis]
	 */
	public static $dfrFiscalPeriodAxis = "FiscalPeriodAxis";

	/* ------------------------------------------------------------------------------------------
	 * Functions to check or return types
	 * ------------------------------------------------------------------------------------------
	 */

	/**
	 * Return the QName for an ISO4217 currency code
	 * @param string $token
	 * @return null|QName
	 */
	public static function qnIsoCurrency( $token )
	{
		return $token == null
			? null
			: self::qnameNS( "iso4217:" . strtoupper( $token ) );
	}

	/**
	 * Not sure this is needed
	 * @param string $arcrole
	 * @return string
	 */
	public static function baseSetArcroleLabel( $arcrole )  // with sort char in first position
	{
		if ( $arcrole == "XBRL-dimensions" ) return _( "1Dimension" );
		if ( $arcrole == "XBRL-formulae" ) return _( "1Formula" );
		if ( $arcrole == "Table-rendering" ) return _( "1Rendering" );
		if ( $arcrole == self::$arcRoleParentChild ) return _( "1Presentation" );
		if ( $arcrole == self::$arcRoleSummationItem ) return _( "1Calculation" );
		return "2" . ucwords( pathinfo( $arcrole, PATHINFO_BASENAME ) );
	}

	/**
	 * Not sure this is needed
	 * @param string $role
	 * @return string
	 */
	public static function labelroleLabel( $role ) // with sort char in first position
	{
		if ( $role == self::$labelRoleLabel ) return _( "1Standard Label" );
		return "3" . ucwords( pathinfo( $role, PATHINFO_BASENAME ) );
	}

	/**
	 * Test whether the namespace is one of the XML/XBRL standard namespaces
	 * @param string $namespaceURI
	 * @return boolean
	 */
	public static function isStandardNamespace( $namespaceURI)
	{
		return isset( self::$standardNamespaces[ $namespaceURI ] );
	}

	/**
	 * Test whether the type is a valid numeric type
	 * @param string $xsdType
	 * @return boolean
	 */
	public static function isNumericXsdType( $xsdType )
	{
		return in_array( $xsdType, array(
			"integer",
			"positiveInteger",
			"negativeInteger",
			"nonNegativeInteger",
			"nonPositiveInteger",
			"long",
			"unsignedLong",
			"int",
			"unsignedInt",
			"short",
			"unsignedShort",
			"byte",
			"unsignedByte",
			"decimal",
			"float",
			"double",
		) );
	}

	/**
	 * A list of the arc roles from the LRR
	 * @var array
	 */
	public static $linkRoleRegistryRoles = array(
		"http://www.xbrl.org/2006/role/restatedLabel",
		"http://xbrl.us/us-gaap/role/label/negated",
		"http://xbrl.us/us-gaap/role/label/negatedPeriodEnd",
		"http://xbrl.us/us-gaap/role/label/negatedPeriodStart",
		"http://xbrl.us/us-gaap/role/label/negatedTotal",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/periodStartNegativeLabel",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/periodEndNegativeLabel",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/positiveOrNegativeLabel",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/periodStartPositiveOrNegativeLabel",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/periodEndPositiveOrNegativeLabel",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/NotesNumber",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/NotesNumberPeriodStart",
		"http://info.edinet-fsa.go.jp/jp/fr/gaap/role/NotesNumberPeriodEnd",
		"http://www.xbrl.org/2009/role/negatedLabel",
		"http://www.xbrl.org/2009/role/negatedPeriodEndLabel",
		"http://www.xbrl.org/2009/role/negatedPeriodStartLabel",
		"http://www.xbrl.org/2009/role/negatedTotalLabel",
		"http://www.xbrl.org/2009/role/negatedNetLabel",
		"http://www.xbrl.org/2009/role/negatedTerseLabel",
		"http://www.xbrl.org/2009/role/negativePeriodStartLabel",
		"http://www.xbrl.org/2009/role/negativePeriodEndLabel",
		"http://www.xbrl.org/2009/role/negativePeriodStartTotalLabel",
		"http://www.xbrl.org/2009/role/negativePeriodEndTotalLabel",
		"http://www.xbrl.org/2009/role/positivePeriodStartLabel",
		"http://www.xbrl.org/2009/role/positivePeriodEndLabel",
		"http://www.xbrl.org/2009/role/positivePeriodStartTotalLabel",
		"http://www.xbrl.org/2009/role/positivePeriodEndTotalLabel",
		"http://www.xbrl.org/2009/role/netLabel",
		"http://www.xbrl.org/2009/role/deprecatedLabel",
		"http://www.xbrl.org/2009/role/deprecatedDateLabel",
		"http://www.xbrl.org/2009/role/commonPracticeRef",
		"http://www.xbrl.org/2009/role/nonauthoritativeLiteratureRef",
		"http://www.xbrl.org/2009/role/recognitionRef",
	);

	/**
	 * A list of the arcroles from the LRR
	 * @var array
	 */
	public static $linkRoleRegistryArcRoles = array(
			"http://info.edinet-fsa.go.jp/jp/fr/gaap/arcrole/Gross-Net",
			"http://info.edinet-fsa.go.jp/jp/fr/gaap/arcrole/Gross-Allowance",
			"http://info.edinet-fsa.go.jp/jp/fr/gaap/arcrole/Gross-AccumulatedDepreciation",
			"http://info.edinet-fsa.go.jp/jp/fr/gaap/arcrole/Gross-AccumulatedImpairmentLoss",
			"http://info.edinet-fsa.go.jp/jp/fr/gaap/arcrole/Gross-AccumulatedDepreciationAndImpairmentLoss",
			"http://www.xbrl.org/2009/arcrole/fact-explanatoryFact",
			"http://www.xbrl.org/2009/arcrole/dep-concept-deprecatedConcept",
			"http://www.xbrl.org/2009/arcrole/dep-aggregateConcept-deprecatedPartConcept",
			"http://www.xbrl.org/2009/arcrole/dep-dimensionallyQualifiedConcept-deprecatedConcept",
			"http://www.xbrl.org/2009/arcrole/dep-mutuallyExclusiveConcept-deprecatedConcept",
			"http://www.xbrl.org/2009/arcrole/dep-partConcept-deprecatedAggregateConcept",
			"http://www.xbrl.org/2013/arcrole/parent-child",
	);

	/**
	 * Populated in the static constructor
	 */
	public static $standardLabelRoles = array();

	/**
	 * Standard reference roles
	 */
	public static $standardReferenceRoles = array(
		"http://www.xbrl.org/2003/role/reference",
		"http://www.xbrl.org/2003/role/definitionRef",
		"http://www.xbrl.org/2003/role/disclosureRef",
		"http://www.xbrl.org/2003/role/mandatoryDisclosureRef",
		"http://www.xbrl.org/2003/role/recommendedDisclosureRef",
		"http://www.xbrl.org/2003/role/unspecifiedDisclosureRef",
		"http://www.xbrl.org/2003/role/presentationRef",
		"http://www.xbrl.org/2003/role/measurementRef",
		"http://www.xbrl.org/2003/role/commentaryRef",
		"http://www.xbrl.org/2003/role/exampleRef",
	);

	/**
	 * http://www.xbrl.org/2003/role/anyLinkbaseRef
	 * @var string
	 */
	public static $anyLinkbaseRef	= "http://www.xbrl.org/2003/role/anyLinkbaseRef";
	/**
	 * http://www.xbrl.org/2003/role/presentationLinkbaseRef
	 * @var string
	 */
	public static $PresentationLinkbaseRef	= "http://www.xbrl.org/2003/role/presentationLinkbaseRef";
	/**
	 * http://www.xbrl.org/2003/role/referenceLinkbaseRef
	 * @var string
	 */
	public static $ReferenceLinkbaseRef		= "http://www.xbrl.org/2003/role/referenceLinkbaseRef";
	/**
	 * http://www.xbrl.org/2003/role/definitionLinkbaseRef
	 * @var string
	 */
	public static $DefinitionLinkbaseRef	= "http://www.xbrl.org/2003/role/definitionLinkbaseRef";
	/**
	 * http://www.xbrl.org/2003/role/labelLinkbaseRef
	 * @var string
	 */
	public static $LabelLinkbaseRef			= "http://www.xbrl.org/2003/role/labelLinkbaseRef";
	/**
	 * http://www.xbrl.org/2003/role/calculationLinkbaseRef
	 * @var string
	 */
	public static $CalculationLinkbaseRef	= "http://www.xbrl.org/2003/role/calculationLinkbaseRef";
	/**
	 * http://www.xbrl.org/2003/role/genericLinkbaseRef
	 * @var string
	 */
	public static $genericLinkbaseRef	= "http://www.xbrl.org/2003/role/genericLinkbaseRef";

	/**
	 * Standard linkbase roles. Populated in the static constructor
	 */
	public static $standardLinkbaseRefRoles = array();

	/**
	 * Populatd in the static constructor
	 */
	public static $standardRoles = array();

	/**
	 * A list of standard arc roles
	 * @var array $standardArcRole
	 */
	public static $standardArcRoles = array();

	/**
	 * standardLabelRoles, standardReferenceRoles, standardLinkbaseRefRoles, defaultLinkRole, footnote
	 * @param string $role
	 */
	public static function isStandardRole( $role)
	{
		return isset( self::$standardRoles[ $role ] );
	}

	/**
	 * One of the 'total' label roles or on the othe negated total roles from the LRR
	 * @param string $role
	 * @return boolean
	 */
	public static function isTotalRole( $role )
	{
		return in_array( $role, array(
			self::$labelRoleTotalLabel,
			"http://xbrl.us/us-gaap/role/label/negatedTotal", /* LRR */
			"http://www.xbrl.org/2009/role/negatedTotalLabel", /* LRR */
		) );
	}

	/**
	 * One of the 'net' roles from the LRR
	 * @param string $role
	 * @return boolean
	 */
	public static function isNetRole( $role )
	{
		return in_array( $role, array(
			"http://www.xbrl.org/2009/role/netLabel",
			"http://www.xbrl.org/2009/role/negatedNetLabel",
		) );
	}

	/**
	 * One of the standard label roles
	 * @param string $role
	 * @return boolean
	 */
	public static function isLabelRole( $role )
	{
		return in_array( $role, self::$standardLabelRoles ) || $role == self::$genLabel;
	}

	/**
	 * One of the label roles indicating a numeric role or on the othe 'negated' roles from the LRR
	 * @param string $role
	 * @return boolean
	 */
	public static function isNumericRole( $role )
	{
		return in_array( $role, array(
			self::$labelRoleTotalLabel,
			self::$labelRolePositiveLabel,
			self::$labelRoleNegativeLabel,
			self::$labelRoleZeroLabel,
			self::$labelRolePositiveTerseLabel,
			self::$labelRoleNegativeTerseLabel,
			self::$labelRoleZeroTerseLabel,
			self::$labelRolePositiveVerboseLabel,
			self::$labelRoleNegativeVerboseLabel,
			self::$labelRoleZeroVerboseLabel,
			"http://www.xbrl.org/2009/role/negatedLabel", /* LRR */
			"http://www.xbrl.org/2009/role/negatedPeriodEndLabel", /* LRR */
			"http://www.xbrl.org/2009/role/negatedPeriodStartLabel", /* LRR */
			"http://www.xbrl.org/2009/role/negatedTotalLabel", /* LRR */
			"http://www.xbrl.org/2009/role/negatedNetLabel", /* LRR */
			"http://www.xbrl.org/2009/role/negatedTerseLabel", /* LRR */
		) );
	}

	/**
	 * One of the standard arc roles such as parent-child, summation-item
	 * @param string $role
	 * @return boolean
	 */
	public static function isStandardArcrole( $role )
	{
		return in_array( $role, array(
			"http://www.w3.org/1999/xlink/properties/linkbase",
			self::$arcRoleConceptLabel,
			self::$arcRoleConceptReference,
			self::$arcRoleFactFootnote,
			self::$arcRoleParentChild,
			self::$arcRoleSummationItem,
			self::$arcRoleGeneralSpecial,
			self::$arcRoleEssenceAlias,
			self::$arcRoleSimilarTuples,
			self::$arcRoleRequiresElement,
		) );
	}

	/**
	 * Populated in the static constructor
	 */
	public static $standardArcroleCyclesAllowed = array();

	/**
	 * Return the arc element (such as labelArc) corresponding to the supplied arc role (such as concept-label)
	 * @param string $arcrole
	 * @return boolean|null
	 */
	public static function standardArcroleArcElement( $arcrole )
	{
		$arcRoles = array(
			self::$arcRoleConceptLabel		=> "labelArc",
			self::$arcRoleConceptReference	=> "referenceArc",
			self::$arcRoleFactFootnote		=> "footnoteArc",
			self::$arcRoleParentChild		=> "presentationArc",
			self::$arcRoleSummationItem		=> "calculationArc",
			self::$arcRoleGeneralSpecial	=> "definitionArc",
			self::$arcRoleEssenceAlias		=> "definitionArc",
			self::$arcRoleSimilarTuples		=> "definitionArc",
			self::$arcRoleRequiresElement	=> "definitionArc",
		);

		return isset( $arcRoles[ $arcrole ] ) ? $arcRoles[ $arcrole ] : null;
	}

	/**
	 * One of the dimension arcroles or one of the standard definition arcroles (
	 * general-special, essesnce-alias, similar-tuples or requires-element)
	 * @param string $arcrole
	 * @return boolean|null
	 */
	public static function isDefinitionOrXdtArcrole( $arcrole )
	{
		return self::isDimensionArcrole( $arcrole ) ||
			in_array( $arcrole, array(
				self::$arcRoleGeneralSpecial,
				self::$arcRoleEssenceAlias,
				self::$arcRoleSimilarTuples,
				self::$arcRoleRequiresElement,
			) );
	}

	/**
	 * A list of the standard arcs.  Populated in the constructor
	 */
	public static $standardLinkElements = array();

	/**
	 * Test whether an namespace/local name pair is one of the standard link or resource elements
	 * @param string $namespaceURI
	 * @param string $localName
	 * return boolean
	 */
	public static function isStandardResourceOrExtLinkElement( $namespaceURI, $localName )
	{
		return 	$namespaceURI == self::$standardPrefixes[ STANDARD_PREFIX_LINK ] &&
				isset( self::$standardLinkElements[ $localName ] ) ||
				qnameNsLocalName( $namespaceURI, $localName )->equals( self::qnameNS( self::$ixRelationship ) );
	}

	/**
	 * A list of the standard arcs.  Populated in the constructor
	 */
	private static $standardArcElements = array();

	/**
	 * Test whether an namespace/local name pair is one of the standard arc elements
	 * @param string $namespaceURI
	 * @param string $localName
	 * return boolean
	 */
	public static function isStandardArcElement( $namespaceURI, $localName )
	{
		return	$namespaceURI == self::$standardPrefixes[ STANDARD_PREFIX_LINK ]  &&
				isset( self::$standardArcElements[ $localName ] ) ||
				qnameNsLocalName( $namespaceURI, $localName )->equals( self::qnameNS( self::$ixRelationship ) );
	}

	/**
	 * Test whether local name is an arc element (such as definitionArc) and the parent
	 * name is a valid resource or external link element (such as definitionLink)
	 *
	 * @param string $namespaceURI
	 * @param string $localName
	 * @param string $parentNamespaceURI
	 * @param string $parentLocalName
	 * @return boolean
	 */
	public static function isStandardArcInExtLinkElement( $namespaceURI, $localName, $parentNamespaceURI, $parentLocalName )
	{
		return ( self::isStandardArcElement( $namespaceURI, $localName ) &&
				self::isStandardResourceOrExtLinkElement( $parentNamespaceURI, $parentLocalName ) ) ||
				qnameNsLocalName( $namespaceURI, $localName )->equals( self::qnameNS( self::$ixRelationship ) );
	}

	/**
	 * Populated in the static constructor
	 */
	private static $standardExtLinkQnames = array();

	/**
	 * Populated in the static constructor
	 */
	private static $standardExtLinkQnamesAndResources = array();

	/**
	 * Test if the QName is one of the standard external link or resource QName
	 * @param QName $qName
	 * @return boolean
	 */
	public static function isStandardExtLinkQname( $qName )
	{
		return in_array( $qName, self::$standardExtLinkQnamesAndResources );
	}

	/**
	 * Populated in the static constructor
	 */
	private static $standardArcQnames = array();

	/**
	 * Test if $qname is one of the standard arc qnames
	 * @param QName $qName
	 * @return boolean
	 */
	public static function isStandardArcQname( $qName )
	{
		return isset( self::$standardArcQnames[ (string) $qName ] );
	}

	/**
	 * An arcrole that begins http://xbrl.org/int/dim/arcrole/
	 * @param string $arcrole
	 * @return boolean
	 */
	public static function isDimensionArcrole( $arcrole )
	{
		return XBRL::startsWith( $arcrole, "http://xbrl.org/int/dim/arcrole/" );
	}

	/**
	 * Populated in the static constructor
	 * can be list of or single arcrole
	 */
	public static $consecutiveArcrole = array();

	/**
	 * Populated in the static constructor
	 */
	private static $resourceArcRoles = array();

	/**
	 * Test is $arcrole is one of the resoruce arc roles
	 * @param string $arcrole
	 * @return boolean
	 */
	public static function isResourceArcrole( $arcrole )
	{
		return isset( self::$resourceArcRoles[ $arcrole ] ) || isFormulaArcrole( $arcrole );
	}

	/**
	 * A utility function that will be completed when formulas are supported
	 * @param string $arcrole
	 * return boolean
	 */
	public static function isFormulaArcrole( $arcrole )
	{
		return false; // TDB
	}

}

// Call the static constructor
XBRL_Constants::__static();
