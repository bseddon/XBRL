<?php

/**
 * QName class and factory functions.  This is ported from Arelle.
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

use lyquidity\xml\QName;

// Now this is implemeted in /xml/QName so the only purpose of this file is to make sure
// the QName class in loaded

if ( ! class_exists( '\\lyquidity\\xml\\schema\\SchemaTypes', true ) )
{
	$xmlSchemaPath = isset( $_ENV['XML_LIBRARY_PATH'] )
		? $_ENV['XML_LIBRARY_PATH']
		: ( defined( 'XML_LIBRARY_PATH' ) ? XML_LIBRARY_PATH : __DIR__ . "/../xml/" );

	require_once $xmlSchemaPath . '/bootstrap.php';
}

/**
 * Generate a QName instance.
 *
 * @param array|string $value	can be a QName, an array or a string.  If a string it will be the
 * 								namespace of the value of $name or a Clark notation.
 * 								If an array then it will be an array representation of the QName.
 * @param array|string $name	Can be an array of prefix/namespace key/value pairs or it can be
 * 								the prefixed local name
 * @param bool $noPrefixIsNoNamespace  If no prefix is found then there will be no namespace if this is true
 * @param Exception $castException		In case there is a cast exception
 * @param Exception $prefixException	In case there is a prefix exception
 * @throws Exception
 * @return \lyquidity\xml\QName
 */
function qname( $value, $name = null, $noPrefixIsNoNamespace = false, $castException = null, $prefixException = null )
{
	return \lyquidity\xml\qname( $value, $name, $noPrefixIsNoNamespace, $castException, $prefixException );
}

return;

/**
 * Convert a namespace/local name pair into a QName instance
 * Does not handle localNames with prefix
 *
 * @param string $namespaceURI
 * @param string $localName
 * @return null|QName
 */
function qnameNsLocalName( $namespaceURI, $localName )
{
	return new QName( null, $namespaceURI ? $namespaceURI : null, $localName );
}

/**
 * Converts a string in the clark notation format to a QName instance
 * Does not handle clark names with prefix
 *
 * @param string $clarkname
 * @return null|QName
 */
function qnameClarkName( $clarkname )
{
	// clark notation (with optional prefix)
	if ( $clarkname && $clarkname[0] == '{' )
	{
		// namespaceURI,sep,prefixedLocalName = value[1:].rpartition('}')
		$matches = null;
		if ( ! preg_match( "/({(?<namespaceURI>.*)})?(?<prefixedLocalName>.*)/", $clarkname, $matches ) )
		{
			return null;
		}
		$namespaceURI = $matches['namespaceURI'] ? $matches['namespaceURI'] : null;
		$prefixedLocalName = $matches['prefixedLocalName'];

		// prefix,sep,localName = $prefixedLocalName.rpartition(':')
		$matches = null;
		if ( ! preg_match( "/((?<prefix>.*):)?(?<localName>.*)/", $prefixedLocalName, $matches ) )
		{
			return null;
		}
		$prefix = $matches['prefix'] ? $matches['prefix'] : null;
		$localName = $matches['localName'] ? $matches['localName'] : null;

		return new QName( $prefix, $namespaceURI, $localName );
	}
	else
	{
		return new QName( null, null, $clarkname );
	}
}

/**
 * Create a QName from a prefix:name pair.  Use the namespace associated
 * with $element to resolve the prefix (if there is one)
 *
 * @param SimpleXMLElement $element
 * @param string $prefixedName
 * @param Exception $prefixException
 * @throws \Exception
 * @return NULL|QName
 */
function qnameEltPfxName( $element, $prefixedName, $prefixException = null )
{
	return \lyquidity\xml\qnameEltPfxName( $element, $prefixedName, $prefixException );
}

/**
 * @deprecated
 * Represents a namespace, prefix and localname
 */
class QNamex
{
	/**
	 * The QName prefix
	 * @var string
	 */
	public $prefix;

	/**
	 * The QName namespace
	 * @var string
	 */
	public $namespaceURI;

	/**
	 * The QName local name
	 * @var string
	 */
	public $localName;

	/**
	 * A hash of the QName
	 * @var string
	 */
	private $qnameValueHash;

	/**
	 * Default constructor
	 *
	 * @param string $prefix
	 * @param string $namespaceURI
	 * @param string $localName
	 */
	public function __construct( $prefix, $namespaceURI, $localName )
	{
		$this->prefix = $prefix;
		$this->namespaceURI = $namespaceURI;
		$this->localName = $localName;
		$this->qnameValueHash = hash( 'sha256', serialize( array( $this->namespaceURI, $this->localName ) ) );
	}

	/**
	 * Return the hash of the QName
	 *
	 * @return string
	 */
	public function getHash()
	{
		return $this->qnameValueHash;
	}

	/**
	 * Return a representation of the QName using a clark notation {namespace}prefix:name
	 *
	 * @return string
	 */
	public function clarkNotation()
	{
		if ( $this->namespaceURI )
		{
			return sprintf( '{%s}%s', $this->namespaceURI, $this->localName );
		}
		else
		{
			return $this->localName;
		}
	}

	/**
	 * Create a string representation
	 *
	 * @return number|string
	 */
	public function __toString()
	{
		$namespaceURI = empty( $this->namespaceURI )
			? ""
			: "{{$this->namespaceURI}}";

		return $namespaceURI . $this->localName;
	}

	/**
	 * Test whether one QName equals another
	 *
	 * @param QName $other
	 * @return boolean
	 */
	public function equals( $other )
	{
		try
		{
			return $this->qnameValueHash == $other->qnameValueHash ||
				( $this->localName == $other->localName && $this->namespaceURI == $other->namespaceURI );
		}
		catch( \Exception $ex )
		{
			return false;
		}
	}

	/**
	 * Test whether one QName is less than another
	 *
	 * @param QName $other
	 * @return boolean
	 */
	public function lessThan( $other )
	{
		return $this->namespaceURI == null && $other->namespaceURI ||
		$this->namespaceURI && $other->namespaceURI && $this->namespaceURI < $other->namespaceURI ||
		$this->namespaceURI == $other->namespaceURI && $this->localName < $other->localName;
	}

	/**
	 * Test whether one QName is less than or equal to another
	 *
	 * @param QName $other
	 * @return boolean
	 */
	public function lessThanOrEqual( $other )
	{
		return $this->namespaceURI == null && $other->namespaceURI ||
		$this->namespaceURI && $other->namespaceURI && $this->namespaceURI < $other->namespaceURI ||
		$this->namespaceURI == $other->namespaceURI && $this->localName <= $other->localName;
	}

	/**
	 * Test whether one QName is greater than another
	 *
	 * @param QName $other
	 * @return boolean
	 */
	public function greaterThan( $other )
	{
		return $this->namespaceURI && $other->namespaceURI == null ||
		$this->namespaceURI && $other->namespaceURI && $this->namespaceURI > $other->namespaceURI ||
		$this->namespaceURI == $other->namespaceURI && $this->localName > $other->localName;
	}

	/**
	 * Test whether one QName is greater or equal to another
	 *
	 * @param QName $other
	 * @return boolean
	 */
	public function greaterThanOrEqual( $other )
	{
		return $this->namespaceURI && $other->namespaceURI == null ||
		$this->namespaceURI && $other->namespaceURI && $this->namespaceURI > $other->namespaceURI ||
		$this->namespaceURI == $other->namespaceURI && $this->localName >= $other->localName;
	}

	/**
	 * Returns true if the QName is valid
	 *
	 * @return bool
	 */
	public function isValid()
	{
		// QName object bool is false if there is no local name (even if there is a namespace URI).
		return (bool) $this->localName;
	}

	/**
	 * Returns true is both the local name and namespace are empty
	 * @return boolean
	 */
	public function isEmpty()
	{
		return empty( $this->localName ) && empty( $this->namespaceURI );
	}

	/**
	 * Convert the QName to an array
	 */
	public function toArray()
	{
		$result = array( 'localname' => $this->localName );
		if ( isset( $this->prefix ) ) array( 'prefix' => $this->prefix );
		if ( isset( $this->namespaceURI ) ) array( 'namespace' => $this->namespaceURI );
		return $result;
	}
}
