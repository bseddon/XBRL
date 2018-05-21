<?php

/**
 * Implements class to hold and manage types.
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
 */

use lyquidity\xml\schema\SchemaTypes;

/**
 * Class implementation
 */
class XBRL_Types extends \lyquidity\xml\schema\SchemaTypes
{
	/**
	 * The name of the persistence file
	 *
	 * @var string $filename
	 */
	private static $filename = "types.json";

	/**
	 * Mock static constructor
	 */
	public static function __static()
	{
		parent::__static();
	}

	/**
	 * Get an instance of the types singleton
	 * @param Function $instance (optional) A potentially descendant instance to use
	 * @return SchemaTypes
	 */
	public static function &getInstance( $instance = null )
	{
		if ( parent::hasInstance() )
		{
			return parent::getInstance();
		}

		$instance = parent::getInstance( new self() );
		$instance->fromFile();
		return $instance;
	}

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Remove all existing element information
	 * @return void
	 */
	public function clearElements()
	{
		parent::clearElements();
		$types = new self();
		$types->fromFile();
		$this->elements = $types->elements;
	}

	/**
	 * Used to create/re-create the XBRL schema types cache file
	 */
	public static function createJSONFile()
	{
		$xbrlSchemaFolder = dirname( __FILE__ ) . "/../xbrl";

		$global = XBRL_Global::getInstance();
		$global->useCache = true;
		$global->initializeCache();
		$global->setEntityLoader( $global->cacheLocation );
		$log = XBRL_Log::getInstance()->debugLog();
		$types = new XBRL_Types();

		$types->createBaseTypes();

		$types->processSchema( "$xbrlSchemaFolder/core/xbrl-instance-2003-12-31.xsd", true );
		$types->processSchema( "$xbrlSchemaFolder/xbrldt/xbrldt-2005.xsd", true );

		// Add a custom type for validation purposes.  These are needed by the XPath 2.0
		// node checking but are not part of the Xml built-in types so are added here.
		// $types->AddSimpleType( "xs", "yearMonthDuration", "xsd:duration" );
		// $types->AddSimpleType( "xs", "dayTimeDuration", "xsd:duration" );

		if ( ! $types->toJSONFile() )
		{
			$log->err( "Problem" );
		}
	}

	/**
	 * Save the type information
	 * @param $filename
	 */
	public function toJSONFile( $filename = null )
	{
		$typesFilename = is_null( $filename )
			? dirname( __FILE__ ) . "/" . XBRL_Types::$filename
			: $filename;

		$json = $this->toJSON();
		if ( $json === false ) return false;

		if ( file_put_contents( $typesFilename, $json ) ) return true;

		XBRL_Log::getInstance()->err( "Failed to write '$typesFilename'" );

		return false;
	}

	/**
	 * Allows a constructor to load types from a json file
	 * @param $source File name
	 * @return bool
	 */
	public function fromFile( $source = null )
	{
		$typesFilename = is_null( $source )
			? dirname( __FILE__ ) . "/" . XBRL_Types::$filename
			: $source;

		if ( ! file_exists( $typesFilename ) ) return;

		$json = file_get_contents( $typesFilename );
		if ( $json === false ) return false;

		return $this->fromJSON( $json );
	}

	/**
	 * A cache of the types array to prevent it being reconstructed every call
	 * @var array $xbrlItemTypesCache
	 */
	private $xbrlItemTypesCache = null;

	/**
	 * Returns a list of all the xBRL defined types
	 * @return string[]
	 */
	public function xbrlItemTypeNames()
	{
		if ( is_null( $this->xbrlItemTypesCache ) )
		{
			$this->xbrlItemTypesCache = array_keys( $this->xbrlItemTypes() );
		}
		return $this->xbrlItemTypesCache;
	}

	/**
	 * Returns a list of all the xBRL defined types
	 * @return string[][]
	 */
	public function xbrlItemTypes()
	{
		// BMS 2018-04-09 Test candidates changed.
		return array(
			'xbrli:decimalItemType' => array( 'type' => 'xbrli:decimalItemType', 'base' => 'xs:decimal', 'unitRef' => 'yes' ),
			'xbrli:floatItemType' => array( 'type' => 'xbrli:floatItemType', 'base' => 'xs:float', 'unitRef' => 'yes' ),
			'xbrli:doubleItemType' => array( 'type' => 'xbrli:doubleItemType', 'base' => 'xs:double', 'unitRef' => 'yes' ),
			// The following numeric types are all based on the XML Schema built-in types that are derived by restriction from decimal.
			'xbrli:integerItemType' => array( 'type' => 'xbrli:integerItemType', 'base' => 'xs:integer', 'unitRef' => 'yes' ),
			'xbrli:nonPositiveIntegerItemType' => array( 'type' => 'xbrli:nonPositiveIntegerItemType', 'base' => 'xs:nonPositiveInteger', 'unitRef' => 'yes' ),
			'xbrli:negativeIntegerItemType' => array( 'type' => 'xbrli:negativeIntegerItemType', 'base' => 'xs:negativeInteger', 'unitRef' => 'yes' ),
			'xbrli:longItemType' => array( 'type' => 'xbrli:longItemType', 'base' => 'xs:long', 'unitRef' => 'yes' ),
			'xbrli:intItemType' => array( 'type' => 'xbrli:intItemType', 'base' => 'xs:int', 'unitRef' => 'yes' ),
			'xbrli:shortItemType' => array( 'type' => 'xbrli:shortItemType', 'base' => 'xs:short', 'unitRef' => 'yes' ),
			'xbrli:byteItemType' => array( 'type' => 'xbrli:byteItemType', 'base' => 'xs:byte', 'unitRef' => 'yes' ),
			'xbrli:nonNegativeIntegerItemType' => array( 'type' => 'xbrli:nonNegativeIntegerItemType', 'base' => 'xs:nonNegativeInteger', 'unitRef' => 'yes' ),
			'xbrli:unsignedLongItemType' => array( 'type' => 'xbrli:unsignedLongItemType', 'base' => 'xs:unsignedLong', 'unitRef' => 'yes' ),
			'xbrli:unsignedIntItemType' => array( 'type' => 'xbrli:unsignedIntItemType', 'base' => 'xs:unsignedInt', 'unitRef' => 'yes' ),
			'xbrli:unsignedShortItemType' => array( 'type' => 'xbrli:unsignedShortItemType', 'base' => 'xs:unsignedShort', 'unitRef' => 'yes' ),
			'xbrli:unsignedByteItemType' => array( 'type' => 'xbrli:unsignedByteItemType', 'base' => 'xs:unsignedByte', 'unitRef' => 'yes' ),
			'xbrli:positiveIntegerItemType' => array( 'type' => 'xbrli:positiveIntegerItemType', 'base' => 'xs:positiveInteger', 'unitRef' => 'yes' ),
			// The following numeric types are all types that have been identified as having particular relevance to the domain space addressed by XBRL and are hence included in addition to the built-in types from XML Schema.
			'xbrli:monetaryItemType' => array( 'type' => 'xbrli:monetaryItemType', 'base' => 'xbrl', 'unitRef' => ':monetary	yes' ),
			'xbrli:sharesItemType' => array( 'type' => 'xbrli:sharesItemType', 'base' => 'xbrl', 'unitRef' => ':shares	yes' ),
			'xbrli:pureItemType' => array( 'type' => 'xbrli:pureItemType', 'base' => 'xbrl', 'unitRef' => ':pure	yes' ),
			// type with the numerator being a decimal and the denominator being a non-zero, decimal (xbrli:nonZeroDecimal)
			'xbrli:fractionItemType' => array( 'type' => 'xbrli:fractionItemType', 'base' => 'xs:complex', 'unitRef' => 'yes' ),
			// The following non-numeric types are all based on XML Schema built-in types that are not derived from either decimal or string.
			'xbrli:stringItemType' => array( 'type' => 'xbrli:stringItemType', 'base' => 'xs:string', 'unitRef' => 'no' ),
			'xbrli:booleanItemType' => array( 'type' => 'xbrli:booleanItemType', 'base' => 'xs:Boolean', 'unitRef' => 'no' ),
			'xbrli:hexBinaryItemType' => array( 'type' => 'xbrli:hexBinaryItemType', 'base' => 'xs:hexBinary', 'unitRef' => 'no' ),
			'xbrli:base64BinaryItemType' => array( 'type' => 'xbrli:base64BinaryItemType', 'base' => 'xs:base64Binary', 'unitRef' => 'no' ),
			'xbrli:anyURIItemType' => array( 'type' => 'xbrli:anyURIItemType', 'base' => 'xs:anyURI', 'unitRef' => 'no' ),
			'xbrli:QNameItemType' => array( 'type' => 'xbrli:QNameItemType', 'base' => 'xs:QName', 'unitRef' => 'no' ),
			'xbrli:durationItemType' => array( 'type' => 'xbrli:durationItemType', 'base' => 'xs:duration', 'unitRef' => 'no' ),
			'xbrli:dateTimeItemType' => array( 'type' => 'xbrli:dateTimeItemType', 'base' => 'xbrl', 'unitRef' => ':dateUnion (union of date and dateTime)	no' ),
			'xbrli:timeItemType' => array( 'type' => 'xbrli:timeItemType', 'base' => 'xs:time', 'unitRef' => 'no' ),
			'xbrli:dateItemType' => array( 'type' => 'xbrli:dateItemType', 'base' => 'xs:date', 'unitRef' => 'no' ),
			'xbrli:gYearMonthItemType' => array( 'type' => 'xbrli:gYearMonthItemType', 'base' => 'xs:gYearMonth', 'unitRef' => 'no' ),
			'xbrli:gYearItemType' => array( 'type' => 'xbrli:gYearItemType', 'base' => 'xs:gYear', 'unitRef' => 'no' ),
			'xbrli:gMonthDayItemType' => array( 'type' => 'xbrli:gMonthDayItemType', 'base' => 'xs:gMonthDay', 'unitRef' => 'no' ),
			'xbrli:gDayItemType' => array( 'type' => 'xbrli:gDayItemType', 'base' => 'xs:gDay', 'unitRef' => 'no' ),
			'xbrli:gMonthItemType' => array( 'type' => 'xbrli:gMonthItemType', 'base' => 'xs:gMonth', 'unitRef' => 'no' ),
			// The following non-numeric types are all based on the XML Schema built-in types that are derived by restriction (and/or list) from string.
			'xbrli:normalizedStringItemType' => array( 'type' => 'xbrli:normalizedStringItemType', 'base' => 'xs:normalizedString', 'unitRef' => 'no' ),
			'xbrli:tokenItemType' => array( 'type' => 'xbrli:tokenItemType', 'base' => 'xs:token', 'unitRef' => 'no' ),
			'xbrli:languageItemType' => array( 'type' => 'xbrli:languageItemType', 'base' => 'xs:language', 'unitRef' => 'no' ),
			'xbrli:NameItemType' => array( 'type' => 'xbrli:NameItemType', 'base' => 'xs:Name', 'unitRef' => 'no' ),
			'xbrli:NCNameItemType' => array( 'type' => 'xbrli:NCNameItemType', 'base' => 'xs:NCName', 'unitRef' => 'no' ),
		);
	}

}

XBRL_Types::__static();
