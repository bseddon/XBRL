<?php

/**
 * XBRL specification equality tests
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2017 Lyquidity Solutions Limited
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

use lyquidity\xml\schema\SchemaTypes;

/**
 * A static class that provides methods to test the equality of XBRL features
 *
 * The XBRL specification defines several different types of equality:
 *
 * C-Equal	Context-equal: Items or sets or sequences of items having the same item type in s-equal contexts.
 * P-Equal	Parent-equal: instance items or tuples having the same parent.
 * S-Equal	Structure-equal: XML nodes that are either equal in the XML value space, or whose XBRL-relevant
 *			sub-elements and attributes are s-equal.
 * U-Equal	Unit-equal. u-equal numeric items having the same units of measurement.
 * V-Equal	Value-equal: c-equal items having either the same non-numeric value, or numeric values that are
 *			equal within some tolerance defined by the lesser of their respective @precision, implied @precision
 *			or @decimals attributes.
 *
 * In addition these rely on more basic equalities:
 *
 * X-Equal	[XPath 1.0]-equal: The XPath "=" operator returns the value true.
 * A-Equal	Attribute-equal: The two attributes have local names and namespaces that are S-Equal and have values that are X-Equal
 */
class XBRL_Equality {


	/** -------------------------------------------------------------------------------
	 *
	 *  Public static variables
	 *
	 *  ------------------------------------------------------------------------------- */

	/**
	 * A comparison type representing a string value
	 * @var integer
	 */
	public static $EQUALITY_TYPE_STRING		= 0;
	/**
	 * A comparison type reprensenting a value of one of the types xs:decimal, xs:float, or xs:double
	 * @var integer
	 */
	public static $EQUALITY_TYPE_NUMBER		= 1;
	/**
	 * A comparison type reprensentng a value the type xs:boolean
	 * @var integer
	 */
	public static $EQUALITY_TYPE_BOOLEAN	= 2;

	/** -------------------------------------------------------------------------------
	 *
	 *  private variables
	 *
	 *  ------------------------------------------------------------------------------- */

	/**
	 * An instance to use the evaluate XPath equality queries
	 *
	 * @var SimpleXMLElement
	 */
	private static $doc = null;

	/** -------------------------------------------------------------------------------
	 *
	 *  Public functions
	 *
	 *  ------------------------------------------------------------------------------- */

	/**
	 * The two attributes have local names and namespaces that are S-Equal and have values that are X-Equal
	 *
	 * @param string $value1 An attribute in the form 'localname' or 'prefix:localname'
	 * @param string $value2 An attribute in the form 'localname' or 'prefix:localname'
	 * @param string $type1 The type of $value1 (default: XBRL_Equality::$EQUALITY_TYPE_STRING)
	 * @param string $type2 The type of $value2 (default: XBRL_Equality::$EQUALITY_TYPE_STRING)
	 * @param array $namespaces A list of namespaces indexed by prefix from the current document
	 * @param XBRL_Types $types A reference to an XBRL_Types instance
	 * @return bool
	 */
	public static function attribute_equal( $value1, $value2, $type1, $type2, &$namespaces, &$types = null )
	{
		$qname1 = qname( $value1, $namespaces );
		$qname2 = qname( $value2, $namespaces );

		if ( ! is_null( $qname1 ) && ! is_null( $qname1 ) )
		{
			// The qnames should be equivalent
			if ( ! $qname1->equals( $qname2 ) ) return false;

			$value1 = $qname1->localName;
			$value2 = $qname1->localName;
		}
		else if ( is_null( $qname1 ) || is_null( $qname1 ) )
		{
			return false;
		}

		return XBRL_Equality::xequal( $value1, $value2, $type1, $type2, $types );
	}

	/**
	 * <period> elements are S-Equal, and <entity> elements are S-Equal, and <scenario> elements are S-Equal.
	 *
	 * @param string $c1
	 * @param string $c2
	 *
	 * return bool
	 */
	public static function context_equal( $c1, $c2 )
	{
		if ( is_null( $c1 ) && is_null( $c2 ) ) return true;
		if ( is_null( $c1 ) || is_null( $c2 ) ) return false;

		// Check the arrays have the same elements
		$result = XBRL_Equality::matchedKeyNames( $c1, $c2, array( 'entity', 'period', 'scenario' ) );

		// if ( $result === false || ! is_array( $result ) || ! count( $result ) ) return false;
		if ( $result === false || ! is_array( $result ) ) return false;
		if ( ! count( $result ) ) return true;

		foreach ( $result as $elementName )
		{
			switch ( $elementName )
			{
				case 'entity':

					if ( ! XBRL_Equality::entity_equal( $c1['entity'], $c2['entity'] ) )
					{
						return false;
					}

					break;

				case 'period':

					if ( ! XBRL_Equality::period_equal( $c1['period'], $c2['period'] ) )
					{
						return false;
					}

					break;

				case 'scenario':

					if ( ! XBRL_Equality::segment_equal( $c1['scenario'], $c2['scenario'] ) )
					{
						return false;
					}

					break;
			}
		}

		return true;
	}

	/**
	 * Make sure divide definitions are equal
	 *
	 * @param array $d1			An array of numerator and denominator elements
	 * @param array $d2			An array of numerator and denominator elements
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return false|array
	 */
	public static function divide_equal( $d1, $d2, &$types, &$namespaces )
	{
		if ( is_null( $d1 ) && is_null( $d2 ) ) return true;
		if ( is_null( $d1 ) || is_null( $d2 ) ) return false;

		// Check the arrays have the same elements
		$result = XBRL_Equality::matchedKeyNames( $d1, $d2, array( 'denominator', 'numerator' ) );

		// if ( $result === false || ! is_array( $result ) || ! count( $result ) ) return false;
		if ( $result === false || ! is_array( $result ) ) return false;
		if ( ! count( $result ) ) return true;

		foreach ( $result as $elementName )
		{
			switch ( $elementName )
			{
				case 'denominator':

					if ( ! XBRL_Equality::measures_equal( $d1['denominator'], $d2['denominator'], $types, $namespaces ) )
					{
						return false;
					}

					break;

				case 'numerator':

					if ( ! XBRL_Equality::measures_equal( $d1['numerator'], $d2['numerator'], $types, $namespaces ) )
					{
						return false;
					}

					break;
			}
		}

		return true;
	}

	/**
	 * <identifier> elements are S-Equal, and <segment> elements are S-Equal (with any missing segment
	 * treated as S-Equal to an empty <segment> element).
	 *
	 * @param string $e1
	 * @param string $e2
	 *
	 * return bool
	 */
	public static function entity_equal( $e1, $e2 )
	{
		if ( is_null( $e1 ) && is_null( $e2 ) ) return true;
		if ( is_null( $e1 ) || is_null( $e2 ) ) return false;

		// Check the arrays have the same elements
		$result = XBRL_Equality::matchedKeyNames( $e1, $e2, array( 'identifier', 'segment' ) );

		// if ( $result === false || ! is_array( $result ) || ! count( $result ) ) return false;
		if ( $result === false || ! is_array( $result ) ) return false;
		if ( ! count( $result ) ) return true;

		foreach ( $result as $elementName )
		{
			switch ( $elementName )
			{
				case 'identifier':

					if ( ! XBRL_Equality::identifier_equal( $e1['identifier'], $e2['identifier'] ) )
					{
						return false;
					}

					break;

				case 'segment':

					if ( ! XBRL_Equality::segment_equal( $e1['segment'], $e2['segment'] ) )
					{
						return false;
					}

					break;
			}
		}

		return true;
	}

	/**
	 * Identifiers should be S-Equal
	 *
	 * @param array $i1 A pair of elements holding the scheme and value respectively
	 * @param array $i2 A pair of elements holding the scheme and value respectively
	 *
	 * @return bool
	 */
	public static function identifier_equal( $i1, $i2 )
	{
		return XBRL_Equality::matchedElements( $i1, $i2, array( 'scheme', 'value' ), true );
	}

	/**
	 * Make sure measure definitions are equal but the order does not matter
	 * Why?  Because two measures represent value that commute (A*B == B*A)
	 *
	 * @param array $m1			An array of measure arrays
	 * @param array $m2			An array of measure arrays
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return false|array
	 */
	public static function measures_equal( $m1, $m2, &$types, &$namespaces )
	{
		if ( is_null( $m1 ) && is_null( $m2 ) ) return true;
		if ( is_null( $m1 ) || is_null( $m2 ) ) return false;

		// Must have the same number of items
		if ( count( $m1 ) != count( $m2 ) ) return false;

		$toQName = function( $measure ) use( $types, $namespaces ) {
			$qname = qname( $measure, $namespaces );
			return is_null( $qname )
			? $measure
			: $qname->clarkNotation();
		};

		// Need to match up QNames on one side with QNames on the other so convert to clark notation
		$m1qnames = array_map( $toQName, $m1 );
		$m2qnames = array_map( $toQName, $m2 );

		// Sort them so that if they are the same they will be in the same order
		sort( $m1qnames );
		sort( $m2qnames );

		// Keys should be identical on both sides (keys willl be numeric)
		if (
				array_diff( array_keys( $m1qnames ), array_keys( $m2qnames ) ) ||
				array_diff( array_keys( $m2qnames ), array_keys( $m1qnames ) )
		   )
		{
			return false;
		}

		// Check each measure
		foreach ( $m1qnames as $key => $measure )
		{
			if ( ! XBRL_Equality::attribute_equal( $measure, $m2qnames[ $key ], XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING, $namespaces, $types ) )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * <segment> elements are S-Equal (with any missing segment treated as S-Equal to an empty <segment> element).
	 * @param array $s1
	 * @param array $s2
	 * @param bool $matchOrdinalPosition
	 * @return bool
	 */
	public static function segment_equal( $s1, $s2, $matchOrdinalPosition = false )
	{
		if ( is_null( $s1 ) && is_null( $s2 ) ) return true;
		if ( is_null( $s1 ) || is_null( $s2 ) ) return false;

		// Check the arrays have the same elements
		$result = XBRL_Equality::matchedKeyNames( $s1, $s2, array( 'member', 'explicitMember', 'typedMember' ) );

		// if ( $result === false || ! is_array( $result ) || ! count( $result ) ) return false;
		if ( $result === false || ! is_array( $result ) ) return false;
		if ( ! count( $result ) ) return true;

		foreach ( $result as $elementName )
		{
			// Must have the same number of items
			if ( count( $s1[ $elementName ] ) != count( $s2[ $elementName ] ) ) return false;

			// Keys should be identical on both sides
			if (
				  array_diff( array_keys( $s1[ $elementName ] ) , array_keys( $s2[ $elementName ] ) ) ||
				  array_diff( array_keys( $s2[ $elementName ] ) , array_keys( $s1[ $elementName ] ) )
			   )
			{
				return false;
			}

			$validNames = array();

			switch ( $elementName )
			{
				case "member":

					$validNames[] = 'name';
					$validNames[] = 'member';
					$validNames[] = 'children';

					break;

				case "explicitMember":

					$validNames[] = 'dimension';
					$validNames[] = 'member';

					break;

				case "typedMember":

					$validNames[] = 'dimension';
					// BMS 2018-02-18 TODO
					// Should check 'member' but the xequal check doesn't support it yet
					break;
			}

			foreach ( $s1[ $elementName ] as $key => $details1 )
			{
				$details2 = $s2[ $elementName ][ $key ];

				if ( $matchOrdinalPosition )
				{
					if ( ( isset( $details1['ordinal'] ) && ! isset( $details2['ordinal'] ) ) ||
						 ( ! isset( $details1['ordinal'] ) && isset( $details2['ordinal'] ) ) ||
						 ( isset( $details1['ordinal'] ) && isset( $details2['ordinal'] ) &&
						   $details1['ordinal'] != $details2['ordinal']
						 )
					   )
					{
						return false;
					}

					// $validNames[] = "ordinal";
				}

				if ( ! XBRL_Equality::matchedElements( $details1, $details2, $validNames ) )
				{
					return false;
				}

				// If both do not have an attributes element then continue to the next
				if ( ! isset( $details1['attributes'] ) && ! isset( $details2['attributes'] ) )
				{
					continue;
				}

				// If either are missing if means there is a mismatch
				if ( ! isset( $details1['attributes'] ) || ! isset( $details2['attributes'] ) )
				{
					return false;
				}

				foreach ( $details1['attributes'] as $name => $attribute )
				{
					if ( ! XBRL_Equality::matchedElements( $attribute, $details2['attributes'][$name], array( 'name', 'value' ) ) )
					{
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Make sure the period elements are consistent
	 *
	 * @param array $p1
	 * @param array $p2
	 * @return false|array
	 */
	public static function period_equal( $p1, $p2 )
	{
		return XBRL_Equality::matchedElements( $p1, $p2, array( 'startDate', 'endDate', 'instant', 'forever' ), true );
	}

	/**
	 * Make sure unit definitions are equal
	 *
	 * @param array $u1
	 * @param array $u2
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return false|array
	 */
	public static function unit_equal( $u1, $u2, &$types, &$namespaces )
	{
		if ( is_null( $u1 ) && is_null( $u2 ) ) return true;
		if ( is_null( $u1 ) || is_null( $u2 ) ) return false;

		// If one value is an array (divide) they must *both* be array.
		// If one value is a string (measure) they MUST *both* be string.

		if ( is_string( $u1 ) && is_string( $u2 ) )
		{
			// It's a string so it's a measure
			return XBRL_Equality::attribute_equal( $u1, $u2, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING, $namespaces, $types );
		}

		if ( ! is_array( $u1 ) || ! is_array( $u2 ) )
		{
			return false;
		}

		// Check the arrays have the same elements
		$result = XBRL_Equality::matchedKeyNames( $u1, $u2, array( 'divide', 'measures' ) );

		if ( $result === false || ! is_array( $result ) ) return false;
		if ( ! count( $result ) ) return true;

		foreach ( $result as $elementName )
		{
			// There will be a divide or there will be measures
			switch ( $elementName )
			{
				case 'divide':

					if ( ! XBRL_Equality::divide_equal( $u1['divide'], $u2['divide'], $types, $namespaces ) )
					{
						return false;
					}

					break;

				case 'measures':

					if ( ! XBRL_Equality::measures_equal( $u1['measures'], $u2['measures'], $types, $namespaces ) )
					{
						return false;
					}

					break;
			}
		}

		return true;
	}

	/**
	 * An XML object A is X-Equal to an XML object B if the [XPath 1.0] expression A = B returns the value true
	 * (see http://www.w3.org/TR/xpath.html#booleans). In the case of element and attribute values, those whose
	 * type are xs:decimal, xs:float, or xs:double, or derived from one of these types MUST be treated as numbers
	 * for the purposes of interpretation of http://www.w3.org/TR/xpath.html#booleans. If a value has type xs:boolean
	 * (or a type derived from xs:boolean), then it MUST be converted to an [XPath 1.0] Boolean with '1' and 'true' being
	 * converted to true and '0' and 'false' being converted to false. Values with any other XML Schema type are treated as
	 * [XPath 1.0] strings.
	 *
	 * @param string $value1 A value to compare
	 * @param string $value2 A value to compare
	 * @param string $type1 The type of $value1 (default: XBRL_Equality::$EQUALITY_TYPE_STRING)
	 * @param string $type2 The type of $value2 (default: XBRL_Equality::$EQUALITY_TYPE_STRING)
	 * // BMS 2018-02-18 Added because the nodes should be equal in type so type information is going to be needed
	 * @param XBRL_Types $types
	 * @return bool
	 */
	public static function xequal( $value1, $value2, $type1 = 0 /* XBRL_Equality::$EQUALITY_TYPE_STRING */, $type2 = 0 /* XBRL_Equality::EQUALITY_TYPE_STRING */, $types = null )
	{
		// If both are empty then they are the same (not necessarily right but the same)
		if ( empty( $value1 ) && empty( $value2 ) ) return true;

		$isPositiveInf1 = false;
		$isNegativeInf1 = false;
		$isPositiveInf2 = false;
		$isNegativeInf2 = false;

		// Clean the valuess
		switch ( $type1 )
		{
			case XBRL_Equality::$EQUALITY_TYPE_BOOLEAN:

				if ( XBRL_Equality::isBooleanValue( $value1 ) )
				{
					$value1 = filter_var( $value1, FILTER_VALIDATE_BOOLEAN ) ? "true()" : "false()";
				}
				else if ( is_string( $value1 ) )
				{
					$value1 = empty( $value1 ) ? "false()" : "true()";
				}
				else
				{
					return false;
				}

				break;

			case XBRL_Equality::$EQUALITY_TYPE_NUMBER:

				if ( trim( $value1, '-+' ) == 'INF' )
				{
					$isPositiveInf1 = $value1[0] != '-';
					$isNegativeInf1 = $value1[0] == '-';
				}
				else
				{
					if ( ! is_numeric( $value1 ) ) return false;
					$value1 = trim( $value1, '+' );
				}

				break;

			default: // string

				if ( ! is_array( $value1 ) )
				{
					// XPath 1.0 has no character escaping (XPath 2.0 does) so
					// splitting on apostrophe and using the concat() function
					$parts = explode( "'", $value1 );
					$value1 = count( $parts ) > 1
						? "concat('" . join( "', \"'\", '", $parts ) . "')"
						: "'$value1'";
				}

				break;
		}

		switch ( $type2 )
		{
			case XBRL_Equality::$EQUALITY_TYPE_BOOLEAN:

				if ( XBRL_Equality::isBooleanValue( $value2 ) )
				{
					$value2 = filter_var( $value2, FILTER_VALIDATE_BOOLEAN ) ? "true()" : "false()";
				}
				else if ( is_string( $value2 ) )
				{
					$value2 = empty( $value2 ) ? "false()" : "true()";
				}
				else
				{
					return false;
				}

				break;

			case XBRL_Equality::$EQUALITY_TYPE_NUMBER:

				if ( trim( $value1, '-+' ) == 'INF' )
				{
					$isPositiveInf2 = $value2[0] != '-';
					$isNegativeInf2 = $value2[0] == '-';
				}
				else
				{
					if ( ! is_numeric( $value2 ) ) return false;
					$value2 = trim( $value2, '+' );
				}

				break;

			default: // String

				if ( ! is_array( $value2 ) )
				{
					// XPath 1.0 has not character escaping (XPath 2.0 does) so
					// splitting on apostrophe and using the concat() function
					$parts = explode( "'", $value2 );
					$value2 = count( $parts ) > 1
						? "concat('" . join( "', \"'\", '", $parts ) . "')"
						: "'$value2'";
				}

				break;
		}

		if ( is_array( $value1 ) )
		{
			return XBRL_Equality::compare_arrays_ordinal($value1, $value2);
		}
		else
		{
			if ( $isNegativeInf1 || $isNegativeInf2 || $isPositiveInf1 || $isPositiveInf2 )
			{
				return ( $isNegativeInf1 & $isNegativeInf2 ) || ( $isPositiveInf1 & $isPositiveInf2 );
			}

			// Create a singleton XML document instance that can be used to evaluate the XPath equality of two nodes
			if ( is_null( XBRL_Equality::$doc ) )
			{
				$xml = "<a></a>";
				XBRL_Equality::$doc = simplexml_load_string( $xml );
			}

			// Create an XPath query to evaluate the values
			$result = XBRL_Equality::$doc->xpath( "/a[$value1 = $value2]" );
			$result = count( $result ) > 0;
			return $result;
		}
	}

	/**
	 * Compare arrays ignoring the position of the elements
	 * @param array $value1
	 * @param array $value2
	 * @return bool
	 */
	public static function compare_arrays( $value1, $value2 )
	{
		if ( count( $value1 ) != count( $value2 ) ) return false;

		$types = XBRL_Types::getInstance();

		foreach ( $value1 as $key => $value )
		{
			if ( ! isset( $value2[ $key ] ) ) return false;

			if ( $key == 'id' )
			{
				$pattern = "/^" . SchemaTypes::$ncName . "$/u";
				if ( ! preg_match( $pattern, $value, $matches ) )
				{
					$this->log()->taxonomy_validation( "context", "id attribute is not a valid NCName", array( 'id' => $value ) );
				}
				if ( ! preg_match( $pattern, $value2[ $key ], $matches ) )
				{
					$this->log()->taxonomy_validation( "context", "id attribute is not a valid NCName", array( 'id' => $value[ $key ] ) );
				}
			}

			if ( is_array( $value ) )
			{
				if ( ! is_array( $value2[ $key ] ) ) return false;

				// These arrays will represent a collection of elements (the key will be 'children') so compare them in order
				if ( ! XBRL_Equality::compare_arrays_ordinal( $value, $value2[ $key ]) )
				{
					return false;
				}

				continue;
			}
			else if ( in_array( $key, array( 'id', 'name', 'prefix', 'type' ) ) )
			{
				if ( $value != $value2[ $key ] )
				{
					return false;
				}

				continue;
			}

			// These can be attribute comparisons so if not the element value look for an attribute and get the type
			$aType = $key == 'value'
				? XBRL_Equality::xequalElementType( $types, $value1['name'], isset( $value1['prefix'] ) ? $value1['prefix'] : null )
				: XBRL_Equality::xequalAttributeType( $types, $key, isset( $value1['prefix'] ) ? $value1['prefix'] : null );

			$bType = $key == 'value'
				? XBRL_Equality::xequalElementType( $types, $value2['name'], isset( $value2['prefix'] ) ? $value2['prefix'] : null )
				: XBRL_Equality::xequalAttributeType( $types, $key, isset( $value2['prefix'] ) ? $value2['prefix'] : null );

			if ( ! XBRL_Equality::xequal( $value, $value2[ $key ], $aType, $bType, $types ) )
			{
				return false;
			}

		}

		return true;
	}

	/**
	 * Get the xequal comparison type an attribute
	 * @param XBRL_Types $types
	 * @param string $localName
	 * @param string|null $prefix
	 * @return number
	 */
	public static function xequalAttributeType( $types, $localName, $prefix )
	{
		$attribute = $types->getAttribute( $localName, $prefix );
		if ( ! $attribute || ! isset( $attribute['types'] ) || ! count( $attribute['types'] ) ) return XBRL_Equality::$EQUALITY_TYPE_STRING;

		return XBRL_Equality::xEqualComparisonType( array( 'type' => $attribute['types'][0] ), $types);
	}

	/**
	 * Get the xequal comparison type an element
	 * @param XBRL_Types $types
	 * @param string $localName
	 * @param string|null $prefix
	 * @return number
	 */
	private static function xequalElementType( $types, $localName, $prefix )
	{
		$element = $types->getElement( $localName, $prefix );
		if ( ! $element || ! isset( $element['types'] ) || ! count( $element['types'] ) ) return XBRL_Equality::$EQUALITY_TYPE_STRING;

		return XBRL_Equality::xEqualComparisonType( array( 'type' => $element['types'][0] ), $types);
	}

	/**
	 * Compare arrays using the position of the elements
	 * @param array $array1
	 * @param array $array2
	 * @return bool
	 */
	public static function compare_arrays_ordinal( $array1, $array2 )
	{
		if ( count( $array1 ) != count( $array2 ) ) return false;
		if ( ! count( $array1 ) ) return true;

		$types = XBRL_Types::getInstance();

		for( $i = 0; $i < count( $array1 ); $i++ )
		{
			$key1 = key( $array1 );
			$key2 = key( $array2 );

			if (  $key1 != $key2 ) return false;

			$value1 = current( $array1 );
			$value2 = current( $array2 );

			next( $array1 );
			next( $array2 );

			if ( $key1 == 'id' )
			{
				$pattern = "/^" . SchemaTypes::$ncName . "$/u";
				if ( ! preg_match( $pattern, $value1, $matches ) )
				{
					$this->log()->taxonomy_validation( "context", "id attribute is not a valid NCName", array( 'id' => $value1 ) );
				}
				if ( ! preg_match( $pattern, $value2, $matches ) )
				{
					$this->log()->taxonomy_validation( "context", "id attribute is not a valid NCName", array( 'id' => $value2 ) );
				}
			}

			if ( is_array( $value1 ) )
			{
				if ( ! is_array( $value2 ) ) return false;

				// These arrays will represent elements so compare the contents by name
				if ( ! XBRL_Equality::compare_arrays( $value1, $value2 ) )
				{
					return false;
				}
			}
			else if ( in_array( $key1, array( 'id', 'name', 'prefix', 'type' ) ) )
			{
				if ( $value1 != $value2 )
				{
					return false;
				}
			}
			else
			{
				$value1Type = XBRL_Equality::xEqualComparisonType( $value1, $types );
				$value2Type = XBRL_Equality::xEqualComparisonType( $value2, $types );
				if ( ! XBRL_Equality::xequal( $value1, $value2, $value1Type, $value2Type, $types ) )
				{
					return false;
				}
			}
		}

		return true;
	}

	/** -------------------------------------------------------------------------------
	 *
	 *  Private utility functions
	 *
	 *  ------------------------------------------------------------------------------- */

	/**
	 * Test whether $value is really a boolean.  This function differs from the built in
	 * is_bool() function because it accommodates zero as false and any other number as true.
	 *
	 * @param mixed $value The value to be tested
	 * @return boolean
	 */
	private static function isBooleanValue( $value )
	{
		if ( is_bool( $value ) ) return true;

		$result = filter_var( $value, FILTER_VALIDATE_BOOLEAN, array( 'flags' => FILTER_NULL_ON_FAILURE ) );
		return $result !== null;
	}

	/**
	 * Display the results of a comparison
	 *
	 * @param bool		$result The comparison result
	 * @param string	$function The function executed to compare
	 * @param array		$source A list function parameters
	 */
	private static function display( $result, $function, $source )
	{
		echo ($result ? "Match   " : "Mismatch");
		echo " $function ";
		echo is_array( $source )
			? implode( ', ', $source )
			: " ($source)";
		echo "\n";
	}

	/**
	 * Test to make sure an element exists in both arrays
	 *
	 * @param array	$a An array holding content with element names to be matched
	 * @param array	$b An array holding content with element names to be matched
	 * @param bool	$elementName
	 * @return bool Returns true if both or neither array contains the element name
	 */
	private static function hasElement( $a, $b, $elementName )
	{
		return
			( isset( $a[ $elementName ] ) && isset( $b[ $elementName ] ) ) ||
			( ! isset( $a[ $elementName ] ) && ! isset( $b[ $elementName ] ) );
	}

	/**
	 * Check a pair of arrays to ensure they both have the same set of valid elements
	 *
	 * @param array $a			An array holding content with element names to be matched
	 * @param array $b			An array holding content with element names to be matched
	 * @param array $validNames	A list of names that are valid for these arrays
	 * @return bool|array		Returns false if the arrays contain a different number
	 * 							of arrays or an array of the common, valid names
	 */
	private static function matchedKeyNames( $a, $b, $validNames )
	{
		if ( ! is_array( $validNames ) || ! count( $validNames ) )
			throw new Exception( "XBRL_Equality::matchedKeyNames $validNames MUST be an array and cannot be empty" );

		if ( ! is_array( $a ) || ! is_array( $b ) )
			throw new Exception( "XBRL_Equality::matchedKeyNames parameters \$a and \$b MUST be arrays" );

		// Neither have content so they are the same (that's not necessarily correct be they are the same)
		if ( ! count( $a ) && ! count( $b ) ) return array();

		// Get a list of the different elements
		$aValidKeys	= array_intersect( array_keys( $a ), $validNames );
		$bValidKeys	= array_intersect( array_keys( $b ), $validNames );

		// If they have a different number of valid key they can't be the same
		if ( count( $aValidKeys ) != count( $bValidKeys ) ) return false;

		// If there are no valid keys that may be a problem but for the caller to decide
		if ( ! count( $aValidKeys ) ) return $aValidKeys;

		// Arrays may have the same number of keys but different ones
		$diffa = array_diff( $aValidKeys, $bValidKeys );
		$diffb = array_diff( $bValidKeys, $aValidKeys );

		// It's good there are no differences so return a valid array (it doesn't matter which because they are the same)
		// If there are differences return false
		return ! count( $diffa ) && ! count( $diffb )
			? $aValidKeys
			: false;
	}

	/**
	 * Check a pair of arrays to ensure they both have the same set of valid elements
	 *
	 * @param array $a					 An array holding content with element names to be matched
	 * @param array $b					 An array holding content with element names to be matched
	 * @param array $validNames			 A list of names that are valid for these arrays
	 * @param bool 	$emptyContentAllowed
	 * @return bool|array				 Returns false if the arrays contain a different number
	 * 									 of arrays or an array of the common, valid names
	 */
	private static function matchedElements( $a, $b, $validNames, $emptyContentAllowed = false )
	{
		if ( is_null( $a ) && is_null( $b ) ) return true;
		if ( is_null( $a ) || is_null( $b ) ) return false;

		if ( ! is_array( $validNames ) || ! count( $validNames ) )
		{
			throw new Exception( "The list of valid element names is not valid" );
		}

		// Check the arrays have the same elements
		$result = XBRL_Equality::matchedKeyNames( $a, $b, $validNames );

		if ( $result === false || ! is_array( $result ) ) return false;
		if ( ! $emptyContentAllowed && ! count( $result ) ) return false;

		$types = XBRL_Types::getInstance();

		foreach ( $validNames as $elementName )
		{
			// BMS 2018-02-18 Need to look at this because the types are necessary for the test to make sense.
			if ( $elementName == 'name' )
			{
				if ( $a[ $elementName ] != $b[ $elementName ] )
				{
					return false;
				}

				continue;
			}

			if ( ! isset( $a[ $elementName ] ) || ! isset( $b[ $elementName ] ) )
			{
				continue;
			}

			$aType = XBRL_Equality::xEqualComparisonType( $a, $types );
			$bType = XBRL_Equality::xEqualComparisonType( $b, $types );
			if ( ! XBRL_Equality::xequal( $a[ $elementName ], $b[ $elementName ], $aType, $bType, $types ) )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the XBRL_Equality type for $details
	 * @param array $details
	 * @param XBRL_Types $types
	 * @return number
	 */
	private static function xEqualComparisonType( $details, $types )
	{
		$type = isset( $details['type'] ) ? $details['type'] : false;

		if ( $type && $types->resolvesToBaseType( $type, array( 'xs:double', 'xsd:double', 'xs:float', 'xsd:float', 'xs:decimal', 'xsd:decimal' ) ) )
		{
			return XBRL_Equality::$EQUALITY_TYPE_NUMBER;
		}
		else if ( $type && $types->resolvesToBaseType( $type, array( 'xs:boolean', 'xsd:boolean' ) ) )
		{
			return XBRL_Equality::$EQUALITY_TYPE_BOOLEAN;
		}

		return XBRL_Equality::$EQUALITY_TYPE_STRING;
	}

	/** -------------------------------------------------------------------------------
	 *
	 *  Test functions
	 *
	 *  -------------------------------------------------------------------------------
	 */

	/**
	 * Test for the xEqual function
	 */
	public static function testXEquals()
	{
		$number = 0;
		$string = "";
		$boolean = false;

		XBRL_Equality::display( XBRL_Equality::xequal( $number, $number, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $number, $number, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $string, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $number, $string, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $boolean, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $number, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $number, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $string, $number, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $string, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $string, $string, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $boolean, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $string, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean, $number, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $boolean ? 'true' : 'false', $number, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $string, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $boolean ? 'true' : 'false', $string, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $boolean, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $boolean ? 'true' : 'false', $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		echo "\n";
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $number, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $number, $number, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $string, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $number, $string, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $boolean, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $number, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $number, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $string, $number, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $string, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $string, $string, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $boolean, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $string, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean, $number, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $boolean ? 'true' : 'false', $number, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $string, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $boolean ? 'true' : 'false', $string, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $boolean, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $boolean ? 'true' : 'false', $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		echo "\n";
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $number, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $number, $number, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $string, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $number, $string, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $boolean, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $number, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $number, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $string, $number, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $string, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $string, $string, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $boolean, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $string, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean, $number, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $boolean ? 'true' : 'false', $number, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $string, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $boolean ? 'true' : 'false', $string, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $boolean, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $boolean ? 'true' : 'false', $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		echo "\n";
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $number, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $number, $number, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $string, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $number, $string, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $boolean, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $number, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $number, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $string, $number, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $string, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $string, $string, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $boolean, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $string, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean, $number, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $boolean ? 'true' : 'false', $number, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $string, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $boolean ? 'true' : 'false', $string, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $boolean, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $boolean ? 'true' : 'false', $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		echo "\n";
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $number, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $number, $number, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $string, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $number, $string, 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $number, $boolean, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $number, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $number, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $string, $number, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $string, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $string, $string, 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $string, $boolean, XBRL_Equality::$EQUALITY_TYPE_NUMBER, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $string, $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean, $number, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_NUMBER ), "xequal", array( $boolean ? 'true' : 'false', $number, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_NUMBER' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $string, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING ), "xequal", array( $boolean ? 'true' : 'false', $string, 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_STRING' ) );
		XBRL_Equality::display( XBRL_Equality::xequal( $boolean ? 'true' : 'false', $boolean, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_BOOLEAN ), "xequal", array( $boolean ? 'true' : 'false', $boolean ? 'true' : 'false', 'XBRL_Equality::$EQUALITY_TYPE_STRING', 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN' ) );

	}

	/**
	 * Generate the xequal test functions
	 */
	public static function generateXEqualTestcases()
	{
		$types = array(
			'$number' => 'XBRL_Equality::$EQUALITY_TYPE_NUMBER',
			'$string' => 'XBRL_Equality::$EQUALITY_TYPE_STRING',
			'$boolean' => 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN',
		);

		// Generate the cases that the declared type is the same as the value type
		foreach ( $types as $var1 => $type1 )
		{
			foreach ( $types as $var2 => $type2 )
			{
				echo "XBRL_Equality::display( XBRL_Equality::xequal( $var1, $var2, $type1, $type2 ), \"xequal\", ";
				if ( $var1 == "\$boolean" ) $var1 = "\$boolean ? 'true' : 'false'";
				if ( $var2 == "\$boolean" ) $var2 = "\$boolean ? 'true' : 'false'";
				echo "array( $var1, $var2, '$type1', '$type2' ) );\n";
			}
		}

		echo "echo \"\\n\";\n";

		$types2 = array(
			'$number' => 'XBRL_Equality::$EQUALITY_TYPE_STRING',
			'$string' => 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN',
			'$boolean' => 'XBRL_Equality::$EQUALITY_TYPE_NUMBER',
		);

		// Generate the cases that the declared type of the second parameter is the not the same as the value type
		foreach ( $types2 as $var1 => $type1 )
		{
			foreach ( $types as $var2 => $type2 )
			{
				echo "XBRL_Equality::display( XBRL_Equality::xequal( $var1, $var2, $type1, $type2 ), \"xequal\", ";
				if ( $var1 == "\$boolean" ) $var1 = "\$boolean ? 'true' : 'false'";
				if ( $var2 == "\$boolean" ) $var2 = "\$boolean ? 'true' : 'false'";
				echo "array( $var1, $var2, '$type1', '$type2' ) );\n";
			}
		}

		echo "echo \"\\n\";\n";

		$types2 = array(
			'$number' => 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN',
			'$string' => 'XBRL_Equality::$EQUALITY_TYPE_NUMBER',
			'$boolean' => 'XBRL_Equality::$EQUALITY_TYPE_STRING',
		);

		// Generate the cases that the declared type of the second parameter is the not the same as the value type
		foreach ( $types as $var1 => $type1 )
		{
			foreach ( $types2 as $var2 => $type2 )
			{
				echo "XBRL_Equality::display( XBRL_Equality::xequal( $var1, $var2, $type1, $type2 ), \"xequal\", ";
				if ( $var1 == "\$boolean" ) $var1 = "\$boolean ? 'true' : 'false'";
				if ( $var2 == "\$boolean" ) $var2 = "\$boolean ? 'true' : 'false'";
				echo  "array( $var1, $var2, '$type1', '$type2' ) );\n";
			}
		}

		echo "echo \"\\n\";\n";

		$types2 = $types;

		$types = array(
			'$number' => 'XBRL_Equality::$EQUALITY_TYPE_STRING',
			'$string' => 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN',
			'$boolean' => 'XBRL_Equality::$EQUALITY_TYPE_NUMBER',
		);

		// Generate the cases that the declared type of the second parameter is the not the same as the value type
		foreach ( $types as $var1 => $type1 )
		{
			foreach ( $types2 as $var2 => $type2 )
			{
				echo "XBRL_Equality::display( XBRL_Equality::xequal( $var1, $var2, $type1, $type2 ), \"xequal\", ";
				if ( $var1 == "\$boolean" ) $var1 = "\$boolean ? 'true' : 'false'";
				if ( $var2 == "\$boolean" ) $var2 = "\$boolean ? 'true' : 'false'";
				echo "array( $var1, $var2, '$type1', '$type2' ) );\n";
			}
		}

		echo "echo \"\\n\";\n";

		$types = array(
			'$number' => 'XBRL_Equality::$EQUALITY_TYPE_BOOLEAN',
			'$string' => 'XBRL_Equality::$EQUALITY_TYPE_NUMBER',
			'$boolean' => 'XBRL_Equality::$EQUALITY_TYPE_STRING',
		);

		// Generate the cases that the declared type of the second parameter is the not the same as the value type
		foreach ( $types as $var1 => $type1 )
		{
			foreach ( $types2 as $var2 => $type2 )
			{
				echo "XBRL_Equality::display( XBRL_Equality::xequal( $var1, $var2, $type1, $type2 ), \"xequal\", ";
				if ( $var1 == "\$boolean" ) $var1 = "\$boolean ? 'true' : 'false'";
				if ( $var2 == "\$boolean" ) $var2 = "\$boolean ? 'true' : 'false'";
				echo "array( $var1, $var2, '$type1', '$type2' ) );\n";
			}
		}

	}

	/**
	 * Returns an array of tests for the context equality function
	 * @return array
	 */
	private static function contextTests()
	{
		$tests = array();

		// Create a cross product of the identifier and the segments test cases
		$entityTests = XBRL_Equality::entityTests();
		$periodTests = XBRL_Equality::periodTypeTests();
		$scenarioTests = XBRL_Equality::segmentTests(); // Scenarios are really the same as segments

		// Add a test which has no content
		$test = array(
			'expectation' => true,
			'comment' => "No content",
			'scenario' => array(
				array(),
				array(),
			)
		);

		$tests[] = $test;

		// Create tests by combining entities, period and scenario componentss
		$combinations = array(
			array( 00, 00, 00, 00, 00, 00 ),
			array( 00, 00, -1, 00, 00, -1 ),
			array( 00, -1, -1, 00, -1, -1 ),
			array( 01, 01, -1, 01, 01, -1 ),
			array( 01, 02, -1, 01, 02, -1 ),
			array( 01, 03, -1, 01, 03, -1 ),
		);

		foreach ( $combinations as $selection )
		{
			$comments = array();
			$context0 = array();
			if ( $selection[0] != -1 )
			{
				$context0['entity'] = $entityTests[ $selection[0] ]['scenario'];
				$comments[] = "Entity 0: {$entityTests[ $selection[0] ]['comment']}";
			}

			if ( $selection[1] != -1 )
			{
				$context0['period'] = $periodTests[ $selection[1] ]['scenario'];
				$comments[] = "Period 0: {$periodTests[ $selection[1] ]['comment']}";
			}

			if ( $selection[2] != -1 )
			{
				$context0['scenario'] = $scenarioTests[ $selection[2] ]['scenario'];
				$comments[] = "Scenario 0: {$scenarioTests[ $selection[2] ]['comment']}";
			}

			$context1 = array();
			if ( $selection[3] != -1 )
			{
				$context1['entity'] = $entityTests[ $selection[3] ]['scenario'];
				$comments[] = "Entity 1: {$entityTests[ $selection[3] ]['comment']}";
			}

			if ( $selection[4] != -1 )
			{
				$context1['period'] = $periodTests[ $selection[4] ]['scenario'];
				$comments[] = "Period 1: {$periodTests[ $selection[4] ]['comment']}";
			}

			if ( $selection[5] != -1 )
			{
				$context1['scenario'] = $scenarioTests[ $selection[5] ]['scenario'];
				$comments[] = "Scenario 1: {$scenarioTests[ $selection[5] ]['comment']}";
			}

			$test = array(
				'expectation' => true,
				'comment' => join( " ", $comments ),
				'scenario' => array(
					$context0,
					$context1,
				)
			);

			$tests[] = $test;

		}

		return $tests;
	}

	/**
	 * Test example context arrays
	 * @return array
	 */
	public static function  testContexts()
	{
		return XBRL_Equality::processTests( function( $a, $b ) {
			return XBRL_Equality::context_equal( $a, $b );
		}, XBRL_Equality::contextTests() );
	}

	/**
	 * Returns an array of tests for the divide equality function
	 *
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return array
	 */
	private static function divideTests( &$types, &$namespaces )
	{
		$measureTests = XBRL_Equality::measureTests( $types, $namespaces );

		$tests = array();

		// Add a test which has no content
		$tests[] = array(
			'expectation' => true,
			'comment' => "No content",
			'scenario' => array(
				array(),
				array(),
			)
		);

		$tests[] = array(
			'expectation' => true,
			'comment' => "Numerator: {$measureTests[1]['comment']} * {$measureTests[2]['comment']}, Denominator: {$measureTests[3]['comment']} * {$measureTests[4]['comment']}",
			'scenario' => array(
				array(
					'numerator' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
					'denominator' => array(
						$measureTests[3]['scenario'][1],
						$measureTests[4]['scenario'][1],
					),
				),
				array(
					'numerator' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
					'denominator' => array(
						$measureTests[3]['scenario'][1],
						$measureTests[4]['scenario'][1],
					),
				),
			)
		);

		$tests[] = array(
			'expectation' => false,
			'comment' => "Mismatched divide",
			'scenario' => array(
				array(
					'numerator' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
					'denominator' => array(
						$measureTests[3]['scenario'][1],
						$measureTests[4]['scenario'][1],
					),
				),
				array(),
			)
		);

		$tests[] = array(
			'expectation' => false,
			'comment' => "Mismatched measure vs divide",
			'scenario' => array(
				array(
					'numerator' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
					'denominator' => array(
						$measureTests[3]['scenario'][1],
						$measureTests[4]['scenario'][1],
					),
				),
				array(
					$measureTests[1]['scenario'][0],
				),
			)
		);

		return $tests;
	}

	/**
	 * Test example divide arrays
	 *
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return array
	 */
	public static function  testDivides( &$types, &$namespaces )
	{
		$tests = XBRL_Equality::divideTests( $types, $namespaces );

		return XBRL_Equality::processTests( function( $a, $b ) use( $types, $namespaces ) {
			return XBRL_Equality::divide_equal( $a, $b, $types, $namespaces );
		}, $tests );
	}

	/**
	 * Returns an array of tests for the entity equality function
	 * @return array
	 */
	private static function entityTests()
	{
		$tests = array();

		// Create a cross product of the identifier and the segments test cases
		$identifierTests = XBRL_Equality::identifierTests();
		$segmentTests = XBRL_Equality::segmentTests();

		$identifierTest = $identifierTests[0];
		$segmentTest = $segmentTests[0];

		// Add a test which only has an identifier
		$test = array(
			'expectation' => $identifierTest['expectation'],
			'comment' => "A test which only has an identifier",
			'scenario' => array(
				array(
					'identifier' => $identifierTest['scenario'][0],
				),
				array(
					'identifier' => $identifierTest['scenario'][1],
				),
			)
		);

		$tests[] = $test;

		// Add a test which only has an segment
		$test = array(
			'expectation' => $segmentTest['expectation'],
			'comment' => "A test which only has an segment",
			'scenario' => array(
				array(
					'segment' => $segmentTest['scenario'][0],
				),
				array(
					'segment' => $segmentTest['scenario'][1],
				),
			)
		);

		$tests[] = $test;

		// Add a test which has no content
		$test = array(
			'expectation' => true,
			'comment' => "No content",
			'scenario' => array(
				array(),
				array(),
			)
		);

		$tests[] = $test;

		foreach ( $identifierTests as $identifierTest )
		{

			foreach ( $segmentTests as $segmentTest )
			{

				$entity0 = array(
					'identifier' => $identifierTest['scenario'][0],
					'segment' => $segmentTest['scenario'][0],
				);

				$entity1 = array(
					'identifier' => $identifierTest['scenario'][1],
					'segment' => $segmentTest['scenario'][1],
				);

				$test = array(
					'expectation' => (bool) ( $identifierTest['expectation'] & $segmentTest['expectation'] ),
					'comment' => "identifier: {$identifierTest['comment']} -  segment: {$segmentTest['comment']}",
					'scenario' => array(
						$entity0,
						$entity1,
					)
				);

				$tests[] = $test;
			}

		}

		return $tests;
	}

	/**
	 * Test example entity arrays
	 * @return array
	 */
	public static function  testEntities()
	{
		return XBRL_Equality::processTests( function( $a, $b ) {
			return XBRL_Equality::entity_equal( $a, $b );
		}, XBRL_Equality::entityTests() );
	}

	/**
	 * Returns an array of tests for the identifier equality function
	 * @return array
	 */
	private static function identifierTests()
	{
		$tests = array(

			array(
				'expectation' => true,
				'comment' => 'Correct',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name' ),
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name' ),
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Extra elements (same)',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name', 'xxx' => '' ),
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name', 'xxx' => '' ),
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Extra elements (different)',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name', 'xxx' => '' ),
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name', 'yyy' => '' ),
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Missing elements (scheme)',
				'scenario' => array(
					array( 'value' => 'Company name', 'xxx' => '' ),
					array( 'value' => 'Company name', 'xxx' => '' ),
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Missing elements (value)',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'xxx' => '' ),
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'xxx' => '' ),
				),
			),
			array(
				'expectation' => false,
				'comment' => 'ismatched elements (value vs scheme)',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'xxx' => '' ),
					array( 'value' => 'Company name', 'xxx' => '' ),
				),
			),
			array(
				'expectation' => false,
				'comment' => 'Mismatched different schema',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/xxx", 'value' => 'Company name' ),
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name' ),
				),
			),
			array(
				'expectation' => false,
				'comment' => 'Mismatched different value',
				'scenario' => array(
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name' ),
					array( 'scheme' => "http://xxx.yyy.com/zzz", 'value' => 'Company name 2' ),
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Match no content',
				'scenario' => array(
					array(),
					array(),
				),
			),
		);

		return $tests;
	}

	/**
	 * Test example identifier arrays
	 * @return array
	 */
	public static function testIdentifiers()
	{
		return XBRL_Equality::processTests( function( $a, $b ) {
			return XBRL_Equality::identifier_equal( $a, $b );
		}, XBRL_Equality::identifierTests() );
	}

	/**
	 * Returns an array of tests for single measures function
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return array
	 */
	private static function measureTests( &$types, &$namespaces )
	{
		$tests = array();

		$namespaces['my'] = 'http://dont/really/care';
		$namespaces['x'] = 'http://dont/really/care/either';
		$namespaces['xbrli'] = 'http://www.xbrl.org/2003/instance';
		$namespaces['iso'] = 'http://www.xbrl.org/2003/iso4217';

		$tests = array(
			array(
				'expectation' => true,
				'comment' => 'Correct my:pure',
				'scenario' => array(
					'my:pure',
					'my:pure',
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Correct my:feet',
				'scenario' => array(
					'my:feet',
					'my:feet',
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Correct my:pond',
				'scenario' => array(
					'my:pond',
					'my:pond',
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Correct my:inch',
				'scenario' => array(
					'my:inch',
					'my:inch',
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Correct xbrli:shares',
				'scenario' => array(
					'xbrli:shares',
					'xbrli:shares',
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Correct x:USD',
				'scenario' => array(
					'x:USD',
					'x:USD',
				),
			),
			array(
				'expectation' => true,
				'comment' => 'Correct iso:NZD',
				'scenario' => array(
					'iso:NZD',
					'iso:NZD',
				),
			),
		);

		return $tests;
	}

	/**
	 * Test example identifier arrays
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return array
	 */
	public static function testMeasures( $types, $namespaces )
	{
		$tests = XBRL_Equality::measureTests( $types, $namespaces );

		return XBRL_Equality::processTests( function( $a, $b ) use( $types, $namespaces ) {
			return XBRL_Equality::attribute_equal( $a, $b, XBRL_Equality::$EQUALITY_TYPE_STRING, XBRL_Equality::$EQUALITY_TYPE_STRING, $namespaces, $types );
		}, $tests );
	}

	/**
	 * Returns an array of tests for the period type equality function
	 * @return array
	 */
	private static function periodTypeTests()
	{
		$tests = array(

			array(
				'expectation' => true,
				'comment' => 'Instant',
				'scenario' => array(
					array( 'startDate' => '2012-03-31', 'endDate' => '2012-03-31', 'type' => 'instant' ),
					array( 'startDate' => '2012-03-31', 'endDate' => '2012-03-31', 'type' => 'instant' ),
				),
			),

			array(
				'expectation' => true,
				'comment' => 'Forever',
				'scenario' => array(
					array( 'startDate' => '2012-03-31', 'endDate' => '2012-03-31', 'type' => 'forever' ),
					array( 'startDate' => '2012-03-31', 'endDate' => '2012-03-31', 'type' => 'forever' ),
				),
			),

			array(
				'expectation' => true,
				'comment' => 'No type',
				'scenario' => array(
					array( 'startDate' => '2012-03-31', 'endDate' => '2012-03-31' ),
					array( 'startDate' => '2012-03-31', 'endDate' => '2012-03-31' ),
				),
			),

			array(
				'expectation' => true,
				'comment' => 'No end date',
				'scenario' => array(
					array( 'startDate' => '2012-03-31', ),
					array( 'startDate' => '2012-03-31', ),
				),
			),

			array(
				'expectation' => true,
				'comment' => 'No start date',
				'scenario' => array(
					array( 'endDate' => '2012-03-31' ),
					array( 'endDate' => '2012-03-31' ),
				),
			),

			array(
				'expectation' => false,
				'comment' => 'Different elements',
				'scenario' => array(
					array( 'startDate' => '2012-03-31' ),
					array( 'endDate' => '2012-03-31' ),
				),
			),

			array(
				'expectation' => true,
				'comment' => 'No content',
				'scenario' => array(
					array(),
					array(),
				),
			),

		);

		return $tests;
	}

	/**
	 * Test example period type arrays
	 */
	public static function testPeriodTypes()
	{
		return XBRL_Equality::processTests( function( $a, $b ) {
			return XBRL_Equality::period_equal( $a, $b );
		}, XBRL_Equality::periodTypeTests() );
	}

	/**
	 * Returns an array of tests for the segment equality function
	 * @return array
	 */
	private static function segmentTests()
	{
		$tests = array(

			array(
				'expectation' => true,
				'comment' => 'Correct',
				'scenario' => array(
					array(
						'explicitMember' => array(
							array(
								'dimension' => "uk-bus:EntityOfficersDimension",
								'member' => "uk-bus:CompanySecretaryDirector",
							),
						),
					),
					array(
						'explicitMember' => array(
							array(
								'dimension' => "uk-bus:EntityOfficersDimension",
								'member' => "uk-bus:CompanySecretaryDirector",
							),
						),
					),
				),
			),

			array(
				'expectation' => false,
				'comment' => 'Missing segment details',
				'scenario' => array(
					array(
						'explicitMember' => array(
							array(
								'dimension' => "uk-bus:EntityOfficersDimension",
								'member' => "uk-bus:CompanySecretaryDirector",
							),
						),
					),
					array(),
				)
			),

			array(
				'expectation' => false,
				'comment' => 'Mismatch missing dimension member',
				'scenario' => array(
					array(
						'explicitMember' => array(
							array(
								'dimension' => "uk-bus:EntityOfficersDimension",
								'member' => "uk-bus:CompanySecretaryDirector",
							),
						),
					),
					array(
						'explicitMember' => array(
							array(
								'dimension' => "uk-bus:EntityOfficersDimension",
							),
						),
					),
				),
			),

			array(
				'expectation' => false,
				'comment' => 'Mismatch missing explicit member items',
				'scenario' => array(
					array(
						'explicitMember' => array(
							array(
								'dimension' => "uk-bus:EntityOfficersDimension",
								'member' => "uk-bus:CompanySecretaryDirector",
							),
						),
					),
					array(
						'explicitMember' => array(),
					),
				),
			),

			array(
				'expectation' => true,
				'comment' => 'Match non-dimensional',
				'scenario' => array(
					array(
						'member' => array(
							array(
								'name' => "my:stateProvince",
								'member' => "MI",
							),
						),
					),
					array(
						'member' => array(
							array(
								'name' => "my:stateProvince",
								'member' => "MI",
							),
						),
					),
				),
			),

			array(
				'expectation' => false,
				'comment' => 'Mismatch empty segment',
				'scenario' => array(
					array(
						'member' => array(
							array(
								'name' => "my:stateProvince",
								'member' => "MI",
							),
						),
					),
					array(),
				),
			),

			array(
				'expectation' => false,
				'comment' => 'Mismatch missing member value',
				'scenario' => array(
					array(
						'member' => array(
							array(
								'name' => "my:stateProvince",
								'member' => "MI",
							),
						),
					),
					array(
						'member' => array(
							array(
								'name' => "my:stateProvince",
							),
						),
					),
				),
			),
		);

		return $tests;
	}

	/**
	 * Process the segment tests
	 */
	public static function testSegments()
	{
		return XBRL_Equality::processTests( function( $a, $b ) {
			return XBRL_Equality::segment_equal( $a, $b );
		}, XBRL_Equality::segmentTests() );
	}

	/**
	 * Returns an array of tests for the unit equality function
	 *
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return array
	 */
	private static function unitTests( &$types, &$namespaces )
	{
		$measureTests = XBRL_Equality::measureTests( $types, $namespaces );
		$divideTests = XBRL_Equality::divideTests( $types, $namespaces );

		$tests = array();

		// Add a test which has no content
		$tests[] = array(
			'expectation' => true,
			'comment' => "No content",
			'scenario' => array(
				array(),
				array(),
			)
		);

		$tests[] = array(
			'expectation' => true,
			'comment' => "Divide valid",
			'scenario' => array(
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
				),
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
				),
			)
		);

		$tests[] = array(
			'expectation' => true,
			'comment' => "Divide valid but swapped the order of the measures",
			'scenario' => array(
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
				),
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[2]['scenario'][0],
							$measureTests[1]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[4]['scenario'][1],
							$measureTests[3]['scenario'][1],
						),
					),
				),
			)
		);

		$tests[] = array(
			'expectation' => false,
			'comment' => "Divide invalid because the second unit denominator has a different unit",
			'scenario' => array(
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
				),
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[2]['scenario'][0],
							$measureTests[1]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[4]['scenario'][1],
							$measureTests[5]['scenario'][1],
						),
					),
				),
			)
		);

		$tests[] = array(
			'expectation' => false,
			'comment' => "Mismatched divide",
			'scenario' => array(
				$measureTests[1]['scenario'][0],
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
				),
			)
		);

		$tests[] = array(
			'expectation' => true,
			'comment' => "Invalid measure *and* divide",
			'scenario' => array(
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
					$measureTests[1]['scenario'][0],
				),
				array(
					'divide' => array(
						'numerator' => array(
							$measureTests[1]['scenario'][0],
							$measureTests[2]['scenario'][0],
						),
						'denominator' => array(
							$measureTests[3]['scenario'][1],
							$measureTests[4]['scenario'][1],
						),
					),
					$measureTests[1]['scenario'][0],
				),
			)
		);

		$tests[] = array(
			'expectation' => true,
			'comment' => "Valid only measures",
			'scenario' => array(
				array(
					'measures' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
				),
				array(
					'measures' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
				),
			)
		);

		$tests[] = array(
			'expectation' => false,
			'comment' => "Only measures is invalid because the list is different",
			'scenario' => array(
				array(
					'measures' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[2]['scenario'][0],
					),
				),
				array(
					'measures' => array(
						$measureTests[1]['scenario'][0],
						$measureTests[3]['scenario'][0],
					),
				),
			)
		);

		return $tests;
	}

	/**
	 * Test example unit arrays
	 *
	 * @param array $types		A reference to the global XBRL_Types instance
	 * @param array $namespaces A list of the namespaces in the current document
	 * @return array
	 */
	public static function  testUnits( &$types, &$namespaces )
	{
		$tests = XBRL_Equality::unitTests( $types, $namespaces );

		return XBRL_Equality::processTests( function( $a, $b ) use( $types, $namespaces ) {
			return XBRL_Equality::unit_equal( $a, $b, $types, $namespaces );
		}, $tests );
	}

	/**
	 * Process a set of tests
	 *
	 * @param string $function 	A callback to process the test
	 * @param array $tests		The set of tests to process
	 * @return bool Returns an array of failed tests
	 */
	private static function processTests( $function, $tests )
	{
		$overall = array();

		foreach ( $tests as $key => $test )
		{
			$expectation = $test['expectation'] ? "True" : "False";
			$result = $function( $test['scenario'][0], $test['scenario'][1] );
			if ( ! $result ) $overall[] = $test;
			echo ( $result === $test['expectation'] ? "Correct   " : "Incorrect " ) . "{$test['comment']} (expectation: $expectation)\n";
		}

		return $overall;
	}

	/**
	 * Examples looking at XPath equality
	 */
	public static function testXPathEquality()
	{
		// $xml = "<x:a xmlns:x=\"xxx\"><x:b>1</x:b><x:c>1</x:c></x:a>";
		$xml = "<a></a>";
		$doc = simplexml_load_string( $xml );
		$c = "concat('that'," . "\"'\"," . "'s')";

		$value2 = "That's cool";
		$parts = explode( "'", $value2 );
		$value2 = count( $parts ) > 1
			? "concat('" . join( "', \"'\", '", $parts ) . "')"
			: "'$value2'";

		$result = $doc->xpath( "/a[$c = 'thats']" ); // Match
		$result = $doc->xpath( "/a[$value2 = $value2]" ); // Match
		$result = $doc->xpath( "/a['xxx' = 'yyy']" ); // False
		$result = $doc->xpath( "/a['' = '']" ); // Match
		$result = $doc->xpath( "/a[1 = 1]" ); // Match
		$result = $doc->xpath( "/a[1 = 2]" ); // False
		$result = $doc->xpath( "/a[1 = '']" ); // False
		$result = $doc->xpath( "/a[0 = '']" ); // False
		$result = $doc->xpath( "/a[1 = '1']" ); // Match
		$result = $doc->xpath( "/a[1 = '2']" ); // False
		$result = $doc->xpath( "/a[1 = 'x']" ); // False
		$result = $doc->xpath( "/a[true() = '0']" ); // Match (any string)
		$result = $doc->xpath( "/a[true() = true()]" ); // Match
		$result = $doc->xpath( "/a[false() = true()]" ); // False
		$result = $doc->xpath( "/a[false() = false()]" ); // Match
		$result = $doc->xpath( "/a[false() = '0']" ); // False (any string)
		$result = $doc->xpath( "/a[false() = '']" ); // True
		$result = $doc->xpath( "/a[1 = '0']" ); // False
		$result = $doc->xpath( "/a[1 = '1']" ); // True

		echo "";
		// echo $result[0];
	}
}