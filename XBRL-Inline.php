<?php

/**
 * XBRL Inline document loading and validation
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2021 Lyquidity Solutions Limited
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

namespace lyquidity\ixbrl;

use DOMDocument;
use lyquidity\ixt\IXBRL_Transforms;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\TreeComparer;
use XBRL\Formulas\ContextComparer;

require __DIR__. '/IXBRL-Transforms.php';
require __DIR__. '/IXBRL-CreateInstance.php';

#region iXBRL Elements

define( "IXBRL_ELEMENT_CONTEXT", "context" );
define( "IXBRL_ELEMENT_CONTINUATION", "continuation" );
define( "IXBRL_ELEMENT_DENOMINATOR", "denominator" );
define( "IXBRL_ELEMENT_EXCLUDE", "exclude" );
define( "IXBRL_ELEMENT_FOOTNOTE", "footnote" );
define( "IXBRL_ELEMENT_FRACTION", "fraction" );
define( "IXBRL_ELEMENT_HEADER", 'header' );
define( "IXBRL_ELEMENT_HIDDEN", "hidden" );
define( "IXBRL_ELEMENT_NONFRACTION", "nonFraction" );
define( "IXBRL_ELEMENT_NONNUMERIC", "nonNumeric" );
define( "IXBRL_ELEMENT_NUMERATOR", "numerator" );
define( "IXBRL_ELEMENT_REFERENCES", "references" );
define( "IXBRL_ELEMENT_RELATIONSHIP", "relationship" );
define( "IXBRL_ELEMENT_RESOURCES", "resources" );
define( "IXBRL_ELEMENT_TUPLE", "tuple" );
define( "IXBRL_ELEMENT_HTML", "html" );
define( "IXBRL_ELEMENT_XHTML", "xhtml" );

#endregion

#region iXBRL Attributes

define( "IXBRL_ATTR_ARCROLE", "arcrole" );
define( "IXBRL_ATTR_BASE", "base" );
define( "IXBRL_ATTR_CONTEXTREF", "contextRef" );
define( "IXBRL_ATTR_CONTINUATIONFROM", "continuationFrom" );
define( "IXBRL_ATTR_CONTINUEDAT", "continuedAt" );
define( "IXBRL_ATTR_DECIMALS", "decimals" );
define( "IXBRL_ATTR_ESCAPE", "escape" );
define( "IXBRL_ATTR_FOOTNOTEROLE", "footnoteRole" );
define( "IXBRL_ATTR_FORMAT", "format" );
define( "IXBRL_ATTR_FROMREFS", "fromRefs" );
define( "IXBRL_ATTR_ID", "id" );
define( "IXBRL_ATTR_LINKROLE", "linkRole" );
define( "IXBRL_ATTR_NAME", "name" );
define( "IXBRL_ATTR_NIL", "xsi:nil" );
define( "IXBRL_ATTR_PRECISION", "precision" );
define( "IXBRL_ATTR_ORDER", "order" );
define( "IXBRL_ATTR_SCALE", "scale" );
define( "IXBRL_ATTR_SIGN", "sign" );
define( "IXBRL_ATTR_TARGET", "target" );
define( "IXBRL_ATTR_TITLE", "title" );
define( "IXBRL_ATTR_TOREFS", "toRefs" );
define( "IXBRL_ATTR_TUPLEID", "tupleID" );
define( "IXBRL_ATTR_TUPLEREF", "tupleRef" );
define( "IXBRL_ATTR_UNITREF", "unitRef" );
define( "IXBRL_ATTR_LANG", "xml:lang" );

#endregion

/**
 * A class to validate and load an inline XBRL document
 *
 */
class XBRL_Inline
{
	/**
	 * @var \XBRL_Global
	 */
	private static $context = null;

	/**
	 * A holder for the documents being created
	 * @var array
	 */
	private static $outputs = null;

	/**
	 * The set of source documents
	 * @var XBRL_Inline[] 
	 */
	public static $documents = [];

	/**
	 * @var \DOMDocument 
	 */
	public $document = null;

	/**
	 * Url to the source iXBRL document
	 *
	 * @var [type]
	 */
	public $url = null;

	/**
	 * The document root element
	 *
	 * @var \DOMElement
	 */
	private $root = null;

	/**
	 * An xpath instance for the document
	 *
	 * @var \DOMXPath
	 */
	public $xpath = null;

	/**
	 * True if the document is IXBRL
	 *
	 * @var boolean
	 */
	public $isIXBRL = false;

	/**
	 * The prefix of the namespace being used
	 *
	 * @var string
	 */
	public $ixPrefix = 'ix11';

	/**
	 * Any document can be IXBRL.  This flag is true if the document is HTML or 
	 *
	 * @var boolean
	 */
	public $isXHTML = false;

	/**
	 * Capture the format status
	 * @var boolean
	 */
	private static $formatOutput = true;

	/**
	 * The base url to be used.  Will be empty unless there is @base in <head>
	 * @var string
	 */
	private $base = '';

	/**
	 * Inline XBRL class constructor instantiating DOMDocument
	 * @throws \Exception When the document is not an IXBRL document
	 */
	public function __construct( $docUrl )
	{
		if ( ! $docUrl ) return;

		$this->document = new \DOMDocument();
		if ( ! $this->document->load( $docUrl ) )
		{
			throw new IXBRLException('Failed to load the document');
		}

		$this->url = $docUrl;
		$this->root = $this->document->documentElement;
		$ns = $this->root->namespaceURI;
		$ln = $this->root->localName;

		$this->xpath = $xpath = new \DOMXPath( $this->document );
		$xpath->registerNamespace( 'ix10', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL10] );
		$xpath->registerNamespace( 'ix11', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL11] );
		$xpath->registerNamespace( STANDARD_PREFIX_XBRLI, \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI] );
		$xpath->registerNamespace( STANDARD_PREFIX_SCHEMA_XHTML, \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML] );
		if ( count( $xpath->query( '//ix11:*', $this->root ) ) && count( $xpath->query( '//ix10:*', $this->root ) ) )
		{
			throw new IXBRLDocumentValidationException('ix:multipleIxNamespaces', 'The document uses more than one iXBRL namespace');
		}

		$iXBRLNamespaces = array_intersect( \XBRL_Constants::$ixbrlNamespaces, $this->getElementNamespaces() );
		$this->ixPrefix = array_flip( \XBRL_Constants::$ixbrlNamespaces )[ reset( $iXBRLNamespaces ) ];

		// These are two test functions
		// $x = $this->getTRR('ixt');
		// $result = $this->format( 'ixt:numdash', '-' );

		// Any document can be IXBRL
		$this->isIXBRL = count( $iXBRLNamespaces ) > 0;
		if ( ! $this->isIXBRL )
			throw new IXBRLException('This is not an Inline XBRL document');

		$this->isXHTML = $ns == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML ] && ( $ln == IXBRL_ELEMENT_HTML || $ln == IXBRL_ELEMENT_XHTML );

		$base = $xpath->query('//xhtml:html/xhtml:head/xhtml:base[@href]');
		if ( $base->length )
		{
			$this->base = $base[0]->getAttribute('href');
		}
	}

	private $elementNamespaces = array();

	/**
	 * Returns an array of namespaces in the document if it is valid
	 *
	 * @param [DOMElement] $element If provided the query operates on this element otherwise the root.
	 * @return string[]
	 */
	public function getElementNamespaces( $element = null)
	{
		$path = $element ? $element->getNodePath() : '';
		if ( ! isset( $this->elementNamespaces[ $path ] ) )
			$this->elementNamespaces[ $path ] = $this->getNodeValues('namespace::*', $element );
		return $this->elementNamespaces[ $path ];
	}

	/**
	 * Returns an array of all namespaces in the document, including those of nested elements, if the document is valid
	 *
	 * @param [DOMElement] $element If provided the query operates on this element otherwise the root.
	 * @return string[]
	 */
	public function getAllNamespaces( $element = null )
	{
		$path = $element ? $element->getNodePath() : '';
		if ( ! isset( $this->elementNamespaces[ $path ] ) )
			$this->elementNamespaces[ $path ] = $this->getNodeValues('//namespace::*', $element );
		return $this->elementNamespaces[ $path ];
	}

	/**
	 * Core function to return the node values of a query as an array
	 *
	 * @param string $query The xpath query to execute
	 * @param [DOMElement] $element If provided the query operates on this element otherwise the root.
	 * @return string[]
	 */
	private function getNodeValues( $query, $element = null )
	{
		$result = array();
		if ( $this->root )
		{
			$xpath = new \DOMXPath( $this->document );
			foreach( $xpath->query( $query, $element ? $element : $this->root ) as $node )
			{
				$result[] = $node->nodeValue;
			}
			$result = array_unique( $result );
		}
		return $result;
	}

	/**
	 * Returns a list of all the targets defined in the document
	 * The list is indexed by target name and the elements are nodes that declare that target
	 * @param \DOMElement[][] $targets
	 * @return \DOMElement[][]
	 */
	private function getTargets( $targets = array() )
	{
		/** @var \DOMElement[][] */
		$targets[null] = $targets[null] ?? array();

		// Get all ix nodes that DO NOT have a 'target' attribute (these are for the default document)
		$nodes = $this->xpath->query( sprintf("//{$this->ixPrefix}:*[not(@%s)]", IXBRL_ATTR_TARGET ), $this->root );

		/** @var \DOMElement[][] */
		$targets[null] = array_merge( $targets[null], iterator_to_array( $nodes ) );

		// Get all ix nodes that have a 'target' attribute
		$nodes = $this->xpath->query( sprintf("//{$this->ixPrefix}:*[@%s]", IXBRL_ATTR_TARGET ), $this->root );

		/** @var \DOMElement[][] */
		$targets = array_reduce( iterator_to_array( $nodes ), function( $carry, $node )
		{
			/** @var \DOMElement $node */
			$target = $node->getAttribute( IXBRL_ATTR_TARGET );
			$carry[ $target ][] = $node;
			return $carry;
		}, $targets );
		/** @var \DOMElement[][] $targets */
		return $targets;
	}

	/**
	 * Returns a list of all the targets defined in the document
	 * The list is indexed by target name and the elements are nodes that declare that target
	 * @param \DOMElement[]? $idNodes
	 * @return \DOMElement[]
	 */
	private function getIDs( $idNodes = array() )
	{
		// Get all ix nodes that have an IXBRL_ATTR_ID attribute
		$nodes = iterator_to_array( $this->xpath->query( sprintf("//{$this->ixPrefix}:*[@%s]", IXBRL_ATTR_ID ), $this->root ) );
		$idNodes = array_merge( $idNodes, $nodes );

		// Get all xbrli nodes that have a IXBRL_ATTR_ID attribute
		$nodes = iterator_to_array( $this->xpath->query( sprintf("//xbrli:*[@%s]", IXBRL_ATTR_ID ), $this->root ) );
		$idNodes = array_merge( $idNodes, $nodes );

		return $idNodes;
	}

	/**
	 * Get the TRR for a prefix
	 * @param string $namespace
	 * @return IXBRL_Transforms
	 */
	private function getTRR( $namespace )
	{
		if ( ! $namespace )
			throw new IXBRLInvalidNamespaceException("A namespace has not been provided");

		if ( array_search( $namespace, \XBRL_Constants::$ixtNamespaces ) === false )
			throw new IXBRLInvalidNamespaceException("'$namespace' is not a TRR namespace");

		/**
		 * @var IXBRL_Transforms
		 */
		$transformInstance = IXBRL_Transforms::getInstance();
		$transformInstance->setTransformVersion( $namespace );
		return $transformInstance;
	}

	/**
	 * Format a value using the requested format
	 * @param string $formatQname The qname of the format to use
	 * @param string $value Thev alue to be formatted
	 * @param \DOMElement $node
	 * @param boolean [$validate] (default: false)
	 * @return string
	 */
	private function format( $formatQname, $value, $node, $validate = false )
	{
		list( $prefix, $localName ) = explode( ':', $formatQname );
		try
		{
			$namespace = $node->lookupNamespaceURI( $prefix );

			$instance = $this->getTRR( $namespace, $node );
			$result = $instance->transform( $localName, $value );
			return $result;
		}
		catch( \Exception $ex )
		{
			if ( $validate ) throw $ex;
		}

		return $value;
	}

	/**
	 * Validate the current document if there is one
	 * @throws \Exception If there is no valid document
	 * @throws IXBRLSchemaValidationException If there are schema violations in the structure of the document
	 */
	public function validateDocument()
	{
		if ( ! $this->document )
		{
			throw new IXBRLException('There is no valid iXBRL document');
		}

		// Can only validate xhtml against the schema
		if ( ! $this->isXHTML ) return;
		$validator = new ValidateInlineDocument();
		$validator->validateDOMDocument( $this->document, $this->ixPrefix );

		// Getting here means the document validates against the iXBRL schema
	}

	/**
	 * Create an instance document from in input iXBRL document set
	 * @param string $name
	 * @param string[] $documentSet
	 * @param boolean? $validate
	 * @param callable? $fn This is a dummy parameter to get around the intelliphense type checking which insists that the arg to libxml_set_external_entity_loader cannot be null.
	 * @return \DOMDocument[] An array of the generated documents
	 */
	public static function createInstanceDocument( $name, $documentSet,$validate = true, $fn = null )
	{
		if ( ! self::$context )
		{
			// Only do this once
			$cacheLocation = \lyquidity\xbrl_validate\get_taxonomy_cache_location();

			\XBRL_Global::reset();
			self::$context = $context = \XBRL_Global::getInstance();
			$context->useCache = true;
			$context->cacheLocation = $cacheLocation;
			$context->initializeCache();
		}

		/**
		 * @var \DOMElement[][]
		 */
		$targets = array();
		$idNodes = array();
		self::$outputs = array();
		self::$tupleIDs = null;

		try
		{
			foreach( $documentSet as $documentUrl )
			{
				// Use the entity loader to make sure libxml uses files from the local.
				// This is an order of magnitude faster.
				$context->setEntityLoader( dirname( $documentUrl ) );

				$document = new XBRL_Inline( $documentUrl );
				self::$documents[ $documentUrl ] = $document;
				if ( $document->document->documentElement->localName != IXBRL_ELEMENT_HTML || $document->document->documentElement->namespaceURI != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML ])
				{
					throw new IXBRLDocumentValidationException( 'UnsupportedDocumentType', "The url does not reference a valid iXBRL document: $documentUrl" );
				}
				$document->validateDocument();  // Schema validation
				// Special case: test case FAIL-empty-class-attribute.html does not fail as expected
				if ( ( $document->xpath->query('//*[@class=""]') )->length )
				{
					throw new IXBRLDocumentValidationException( 'xbrl.core.xml.SchemaValidationError.cvc-minLength-valid', 'Failed using schemaValidate' );				
				}

				/** @var \DOMElement[][] */
				$targets = $document->getTargets( $targets );
				$idNodes = $document->getIDs( $idNodes );

				$context->resetEntityLoader();
			}

			unset( $document );
			unset( $documentUrl );

			// There will be an output document for every target found
			$outputNodes = array_filter( 
				$targets, 
				function( $nodes ) 
				{
					return array_filter( 
						$nodes, 
						function( $node ) { /** @var \DOMElement $node */ return array_search( $node->localName, array( IXBRL_ELEMENT_HEADER, IXBRL_ELEMENT_RESOURCES ) ) === false; }
					);
				}
			);
			self::$outputs = array_fill_keys( array_keys( $outputNodes ), array() );
	
			// Create list of the idNodes indexed by the id string.  The element value is the index into 
			$ids = \XBRL::array_reduce_key( $idNodes, function( $carry, $node, $index )
			{
				/** @var \DOMElement $node */
				$id = $node->getAttribute(IXBRL_ATTR_ID);
				$carry[ $id ][] = $index;
				return $carry;
			}, [] );

			$duplicateIds = array_filter( $ids, function( $indexes ) { return count( $indexes ) > 1; } );

			if ( $duplicateIds )
			{
				// Create a string of node qnames to report the error
				$error = [];
				foreach( $duplicateIds as $id => $indexes )
				{
					/** @var int[] $indexes */
					$error[] = "$id (" . join(', ', array_map( function( $index ) use( &$idNodes )
					{
						return $idNodes[ $index ]->tagName;
					}, $indexes ) ) . ")";
				}
				throw new IXBRLDocumentValidationException('DuplicateId', join( ' and ', $error ) );
			}

			// Now there will be a one-to-one correspondence between an id and index so recreate idNodes
			$idNodes = \XBRL::array_reduce_key( $ids, function( $carry, $indexes, $id ) use ( &$idNodes )
			{
				$carry[ $id ] = $node = $idNodes[ $indexes[0] ];
				return $carry;
			}, array() );

			unset( $ids );
			unset( $duplicateIds );

			// Re-index ix elements to index by localname
			/** @var \DOMElement[][] */
			$nodesByLocalNames = array_reduce( $targets, function( $carry, $nodes )
			{
				/** @var \DOMElement[] $nodes */
				foreach( $nodes as $node )
				{
					if ( $node->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) continue;
					$carry[ $node->localName ][] = $node;
				}
				return $carry;
			}, array() );

			if ( ! isset( $nodesByLocalNames[ IXBRL_ELEMENT_HEADER ] ) )
			{
				throw new IXBRLDocumentValidationException( 'HeaderAbsent', 'Section 8.1.1 A <header> element cannot be found' );			
			}

			if ( ! isset( $nodesByLocalNames[ IXBRL_ELEMENT_REFERENCES ] ) )
			{
				throw new IXBRLDocumentValidationException( 'ReferencesAbsent', 'Section 12.1.1 A <references> element cannot be found' );			
			}

			if ( ! isset( $nodesByLocalNames[ IXBRL_ELEMENT_RESOURCES ] ) )
			{
				throw new IXBRLDocumentValidationException( 'ResourcesAbsent', 'Section 14.1.1 A <resources> element cannot be found' );			
			}

			// Get and validate the ix:header and other elements
			if ( $validate )
			{
				foreach( $nodesByLocalNames as $localName => $nodes )
				{
					foreach( $nodes as $node )
					{
						/** @var \DOMElement $node */
						self::validateConstraints( $node, $localName, $nodesByLocalNames, $idNodes );
					}
				}
				self::checkTupleOrders( $node, "15.1.1", $nodesByLocalNames );
			}

			// Check cross-element continuation validation rules
			self::checkCrossElementContinuations( $nodesByLocalNames );

			// Check cross-references validation rules (12.1.2)
			self::checkCrossReferencesRules( $nodesByLocalNames, $targets );

			$documents = IXBRL_CreateInstance::createInstanceDocuments( array_keys( self::$outputs ), $name, $nodesByLocalNames, $idNodes, $targets );

			return $documents;

		}
		catch( \Exception $ex )
		{
			throw $ex;
		}
		finally
		{
			$context->resetEntityLoader();
			$context->reset();
			self::$context = null;	
		}

	}

	/**
	 * Get the inner HTML for a node
	 * @param \DOMNode $node
	 * @return void
	 */
	private static function innerHTML( $node )
	{
		if ( ! ( $node instanceof \DOMNode ) ) return '';
		$document = self::$documents[ $node->ownerDocument->documentElement->baseURI ];
		$updateChildren = function( \DOMNodeList $nodes, \DOMNode $parentNode = null ) use( &$updateChildren, $document )
		{
			$result = '';

			foreach( $nodes as $child )
			{
				/** @var \DOMNode $child */
				if ( $child instanceof \DOMElement )
				{
					if ( array_search( $child->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) )
					{
						$result .= $updateChildren( $child->childNodes, $parentNode );
						continue;
					}
	
					$result .= "<{$child->tagName}";

					if ( $document->base )
					{
						$base = \XBRL::endswith( $document->base, '/' ) || pathinfo( $document->base, PATHINFO_EXTENSION ) ? $document->base : $document->base . '/../';
						if ( $href = $child->getAttribute('href') )
						{
							@list( $url, $fragment ) = explode( '#', $href );
							$child->setAttribute( 'href', ( $url ? \XBRL::resolve_path( $base, $url ) : $base ) . ( $fragment ? "#$fragment" : '' ) );
						}

						if ( $src = $child->getAttribute('src') )
						{
							@list( $url, $fragment ) = explode( '#', $src );
							$child->setAttribute( 'src', ( $url ? \XBRL::resolve_path( $base, $url ) : $base ) . ( $fragment ? "#$fragment" : '' ) );						
						}
					}

					// Add xmlns:xhtml to any new child nodes that are not an xbrli node
					if ( ! $parentNode && array_search( $child->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) === false && ! $child->hasAttributeNS( 'http://www.w3.org/2000/xmlns/', "xmlns" ) )
					{
						$result .= " xmlns=\"" . \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML] . "\"";
					}
	
					foreach( $child->attributes as $attr )
					{
						/** @var \DOMAttr $attr */
						$result .= " " . $attr->nodeName . '="' . $attr->nodeValue . '"';
					}

					if ( $child->nodeValue || $child->hasChildNodes() )
					{
						$result .= ">";

						if ( $child->hasChildNodes() )
						{
							$result .= $updateChildren( $child->childNodes, $child );
						}

						$result .= "</{$child->tagName}>";
					}
					else
					{
						$result .= "/>";
					}

				}
				else
				{
					$result .= htmlentities( $child->nodeValue, ENT_NOQUOTES );
					// $result .= preg_replace( '/\s+/', ' ',  htmlentities( $child->nodeValue, ENT_NOQUOTES ) );
				}
			}

			return $result;
		};

		$result = $node->hasChildNodes()
			? $updateChildren( $node->childNodes )
			: $node->textContent;

		return $result;
	}

	/**
	 * Outputs the XML for the generated document
	 * @param \DOMNode $node
	 * @param mixed $options
	 * @param \DOMDocument $document
	 * @return string
	 */
	public static function saveXML( $document, $formatOutput, $options = null )
	{
		$document->formatOutput = $formatOutput;

		if ( ! $formatOutput ) return $document->saveXML( null, $options );

		// Expand all xmlns declarations so they appear on separate lines
		$xml = $document->saveXML( null, $options );
		// $xml = str_replace( array('xmlns', 'xsi:schemaLocation'), array("\n\txmlns", "\n\txsi:schemaLocation"), $xml );
		// Expand the schema location entries so that appear on separate lines
		$xml = preg_replace_callback( '/(xsi:schemaLocation=")(.*)(")/', function( $matches )
		{
			return $matches[1] . "\n\t\t" . str_replace( '.xsd', ".xsd\n\t\t", $matches[2] ) . $matches[3];
		}, $xml );
		// Find elements with attributes and put the second and subsequent attributes on separate lines
		// $xml = preg_replace_callback( '|\s*<[a-z]+:.*? .*?" .*|', function( $matches )
		// {
		// 	return str_replace('" ', "\"\n\t\t", $matches[0] );
		// }, $xml );

		return $xml;
	}

	/**
	 * Check cross-element continuation validation rules
	 * @param [type] $nodesByLocalNames
	 * @return void
	 */
	private static function checkCrossElementContinuations( &$nodesByLocalNames )
	{
		// Make sure continuedAt attributes don't reference the same id
		$correspondents = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_CONTINUATION );
		$atIds = array();
		// Get the nodes for continuation, footnote and nonNumeric elements
		foreach( $correspondents as $localName )
		{
			foreach( $nodesByLocalNames[ $localName ] ?? array() as $node )
			{
				if ( ! $node->hasAttribute( IXBRL_ATTR_CONTINUEDAT ) ) continue;
				$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
				if ( array_search( $continuedAt, $atIds ) !== false )
				{
					throw new IXBRLDocumentValidationException( 'ContinuationReuse', 'Section 4.1.1 A \'continuationAt\' attribute with value \'$continuedAt\' has been used more than once' );			
				}

				$parentNode = self::checkParentNodes( $node, function( $parentNode ) { return $parentNode->localName== IXBRL_ELEMENT_CONTINUATION; } );
				if ( $parentNode && $parentNode->getAttribute( IXBRL_ATTR_ID ) == $continuedAt )
				{
					throw new IXBRLDocumentValidationException( 'ContinuationInvalidNesting', 'Section 4.1.1 A \'continuationAt\' attribute with value \'$continuedAt\' has @id in it parenmt' );			
				}

				$atIds[] = $continuedAt;
			}
		}
	}

	/**
	 * Check cross-references validation rules (12.1.2)
	 * @param \DOMElement[][] $nodesByLocalNames
	 * @param \DOMElement[][] $targets
	 * @return void
	 */
	private static function checkCrossReferencesRules( &$nodesByLocalNames, &$targets )
	{
		if ( ( $nodesByLocalNames[ IXBRL_ELEMENT_REFERENCES ] ?? false ) ) return;

		// Each attribute value should be unique across all <references> for attribute not in the IX namespace
		$attributes = array();
		$ixNamespaces = array( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL10], \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL11] );
		foreach( $nodesByLocalNames[ IXBRL_ELEMENT_REFERENCES ] ?? array() as $node )
		{
			$target = $node->hasAttribute( IXBRL_ATTR_TARGET )
				? $node->getAttribute( IXBRL_ATTR_TARGET )
				: '';

			foreach( $node->attributes as $name => $attr )
			{
				if ( $name == IXBRL_ATTR_TARGET ) continue;
				if ( $name == IXBRL_ATTR_BASE ) continue;

				/** @var \DOMAttr $attr */
				if ( array_search( $attr->namespaceURI, $ixNamespaces ) !== false ) continue;
				if ( isset( $attributes[ $target ][ $name ] ) && $attr->nodeValue )
				{
					throw new IXBRLDocumentValidationException( $name == 'id' ? 'RepeatedIdAttribute' : 'RepeatedOtherAttributes', "Section 12.1.2 A <references> attribute/value pair has been used more than once ($name/{$attr->nodeValue}" );
				}
				$attributes[ $target ][ $name ] = $attr->nodeValue;
			}
		}

		// Each target must have a <references>
		// Test PASS-ix-references-rule-multiple-matched-target.html shows that a target can be valid without references if its the default
		// Filter nodes to remove ix elements that do not have a target attribute
		foreach( array_intersect_key( $targets, self::$outputs ) as $target => $nodes )
		{
			// Only one can have an id attribute
			$idFound = array();
			$referencesFound = false;
			$defaultNamespace = null;
			foreach( $nodes as $targetNode )
			{
				/** @var \DOMElement $targetNode */
				if ( $targetNode->localName != IXBRL_ELEMENT_REFERENCES ) continue;
				$referencesFound = true;
				if ( $targetNode->hasAttribute( IXBRL_ATTR_ID ) )
				{
					if ( $idFound )
						throw new IXBRLDocumentValidationException( 'MoreThanOneID', "Section 12.1.2 Only one <references> element for a target can include an id attribute.  Found '" . join( "','", $idFound ) . "'" );
					$idFound[] = $targetNode->getAttribute( IXBRL_ATTR_ID );
				}
				// Go to the document and then to the root element so the base uri is not affected by local xml:base definitions
				$document = self::$documents[ $targetNode->ownerDocument->documentElement->baseURI ];
				$namespaces = array_diff( $document->getElementNamespaces( $targetNode ), $document->getElementNamespaces() );
				if ( $namespaces )
				{
					$namespace = reset( $namespaces );
					if ( $defaultNamespace && $defaultNamespace != $namespace )
					{
						throw new IXBRLDocumentValidationException( 'ReferencesNamespaceClash', "Section 12.1.2 Only one default namespace can be used across all <references>. Two or more detected: '$defaultNamespace' and '{$namespace}'" );
					}
					else
					{
						$defaultNamespace = $namespace;
					}
				}
				else if ( $defaultNamespace && $defaultNamespace != 'default' )
				{
					throw new IXBRLDocumentValidationException( 'ReferencesNamespaceClash', "Section 12.1.2 Only one default namespace can be used across all <references>. Two or more detected: '$defaultNamespace' and 'default'" );
				}
				else
				{
					$defaultNamespace = 'default';
				}
			}

			if( ! $referencesFound )
				throw new IXBRLDocumentValidationException( 'ReferencesAbsent', "Section 12.1.2 A <references> must exist for all targets.  Missing element for target '$target'" );
		}
	}

	/**
	 * Check that a node does not use ix attributes
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @return void
	 */
	private static function checkIXbrlAttribute( $node, $section, $localName )
	{
		$xbrliNS = \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ];
		foreach( $node->attributes as $attr )
		{
			/** @var \DOMAttr $attr */
			if ( $attr->namespaceURI == $xbrliNS )
			{
				throw new IXBRLDocumentValidationException( 'InvalidAttributeContent', "Section $section <$localName> elements must not include attributes in the xbrli namespace" );
			}
		}
	}

	/**
	 * Check parent nodes for some criteria
	 * @param \DOMElement $node
	 * @param string $section
	 * @return \DOMElement
	 */
	public static function checkParentNodes( \DOMElement $node, \Closure $test )
	{
		$parentNode = $node->parentNode;
		while( $parentNode && $parentNode instanceof \DOMElement )
		{
			if ( $test( $parentNode ) ) return $parentNode;
			// Up another level
			$parentNode = $parentNode->parentNode;
		}
		return null;
	}

	/**
	 * Use to check the equivalence of a <nonFraction> attribute with that of any parent
	 * @param \DOMElement $node
	 * @param string $attrName
	 * @param string $attrValue
	 * @param string $section
	 * @param string $localName
	 * @param string $errorCode
	 * @return \DOMElement
	 */
	private static function checkAttributeConsistency( \DOMElement $node, string $attrName, string $attrValue, string $section, string $localName, $errorCode = null ) 
	{
		if ( strpos( $attrValue, ':') !== false )
		{
			list( $prefix, $name ) = explode( ':', $attrValue );
			$attrValue = '{' . $node->lookupNamespaceURI( $prefix ) . '}' . ":$name";
		}

		if ( is_null( $errorCode ) ) $errorCode = 'NonFractionNestedAttributeMismatch';
		return self::checkParentNodes( $node, function( \DOMElement $parentNode ) use( $attrName, $attrValue, $errorCode, $localName, $section )
		{
			if ( $parentNode->localName != $localName ) return false;
			$parentAttr = $parentNode->getAttributeNode( $attrName );
			$parentAttrValue = $parentAttr ? trim( $parentAttr->nodeValue ) : '';

			if ( strpos( $parentAttrValue, ':') !== false )
			{
				list( $prefix, $name ) = explode( ':', $parentAttrValue );
				$parentAttrValue = '{' . $parentNode->lookupNamespaceURI( $prefix ) . '}' . ":$name";
			}

			if ( $parentAttrValue == $attrValue ) return false;
			throw new IXBRLDocumentValidationException( $errorCode, "Section $section Attribute @'$attrName' should be consistent in nested <$localName>" );

			// return true; // End here.  Any other parents will be checked when the parent is validated.
		} );	
	}

	/**
	 * Check the contextRef is valid
	 * @param \DOMElement $node
	 * @param [type] $idNodes
	 * @param string $section
	 * @param string $localName
	 * @return void
	 */
	private static function checkContextRef( $node, &$idNodes, $section, $localName )
	{
		$contextRef = $node->getAttribute('contextRef');
		$contextNode = $idNodes[ $contextRef ] ?? null;
		if ( ! $contextNode )
		{
			throw new IXBRLDocumentValidationException( 'UnknownContext', "Section $section The c@ontextRef in <$localName> with id '$contextRef' does not exist" );
		}
}

	/**
	 * Check the unitRef is valid
	 * @param \DOMElement $node
	 * @param [type] $idNodes
	 * @param string $section
	 * @param string $localName
	 * @return void
	 */
	private static function checkUnitRef( $node, &$idNodes, $section, $localName, $errorCode = null )
	{
		$unitRef = trim( $node->getAttribute( IXBRL_ATTR_UNITREF ) );
		$unit = $idNodes[ $unitRef ] ?? null;				
		if ( ! $unit )
		{
			throw new IXBRLDocumentValidationException( 'UnknownUnit', "Section $section The unit with id '$unitRef' does not exist" );
		}
		self::checkAttributeConsistency( $node, IXBRL_ATTR_UNITREF, $unitRef, $section, $localName, $errorCode );
	}

	/**
	 * Check the sign is valid
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @return void
	 */
	private static function checkSign( $node, $section, $localName )
	{
		$sign = $node->getAttribute( IXBRL_ATTR_SIGN );
		if ( $sign && $sign != '-' )
		{
			throw new IXBRLDocumentValidationException( 'NonFractionInvalidSign', "Section $section @sign in <$localName> must have a value of '-'" );
		}
	}

	/**
	 * Check the scale is valid
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @return void
	 */
	private static function checkScale( $node, $section, $localName )
	{
		$scale = $node->getAttribute( IXBRL_ATTR_SCALE );
		self::checkAttributeConsistency( $node, IXBRL_ATTR_SCALE, $scale, $section, $localName );
	}

	/**
	 * Check the tupleRef is valid
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @param \DOMElement $tupleNodes
	 * @return void
	 */
	private static function checkTupleRef( $node, $section, $localName, &$tupleNodes )
	{
		$tupleRef = $node->getAttribute( IXBRL_ATTR_TUPLEREF );
		if ( ! $tupleRef ) return;

		$tupleIDs = self::getTupleIds( $tupleNodes, $section );
		$tupleNode = $tupleIDs[ $tupleRef ] ?? null;
		if ( $tupleNode ) 
		{
			// Make sure the tuple is valid
			$nil = filter_var( $tupleNode->getAttribute( IXBRL_ATTR_NIL ), FILTER_VALIDATE_BOOLEAN );
			if ( $nil )
			{
				throw new IXBRLDocumentValidationException( 'NilTupleWithChild', "Section $section The tuple with id '$tupleRef' is referenced <$localName> but <tuple> is null" );
			}
			return;
		}

		throw new IXBRLDocumentValidationException( 'UnknownTuple', "Section $section The tuple with id '$tupleRef' used in <$localName> does not exist " );
	}

	/**
	 * Check the content and format is valid
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @param XBRL_Inline $document
	 * @param \DOMElement[] $idNodes
	 * @return void
	 */
	public static function checkFormat( $node, $section, $localName, $document, $idNodes )
	{
		$nil = filter_var( $node->getAttribute( IXBRL_ATTR_NIL ), FILTER_VALIDATE_BOOLEAN );				
		$escape = filter_var( $node->getAttribute( IXBRL_ATTR_ESCAPE ), FILTER_VALIDATE_BOOLEAN );
		$format = $node->getAttribute( IXBRL_ATTR_FORMAT );
		self::checkAttributeConsistency( $node, IXBRL_ATTR_FORMAT, $format, $section, $localName );

		// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
		$cloneNode = $node->cloneNode( true );
		$document = self::$documents[ $node->ownerDocument->documentElement->baseURI ];
		self::removeNestedExcludes( $cloneNode, $document->xpath );
		$nodes = self::checkContinuationCycles( $cloneNode, $section, $idNodes );

		$content = $escape 
			? join( ' ', array_map( function( $node ) { return self::innerHTML( $node ); }, $nodes ) )
			: join( ' ', array_map( function( $node ) { return trim( $node->textContent ); }, $nodes ) );

		// The content my be empty but there may be a nested node
		if ( ! $content && ! ( $node->childNodes->length || $nil ) )
		{
			// Doesn't seem to be used
			// throw new IXBRLDocumentValidationException( 'FormatUndefined', 'Content empty in <$localName>' );
		}

		if ( $format )
		{
			try
			{
				$content = $document->format( $format, $content, $node, true );
			}
			catch( IXBRLInvalidNamespaceException $ex )
			{
				throw new IXBRLDocumentValidationException( 'FormatUndefined', $ex->getMessage(), 0, $ex );
			}
			catch( \Exception $ex )
			{
				throw new IXBRLDocumentValidationException( 'InvalidDataType', $ex->getMessage(), 0, $ex );
			}
		}
		else if ( $content )
		{
			if ( $localName != IXBRL_ELEMENT_NONNUMERIC && ( ! is_numeric( trim( $content ) ) || floatval( $content ) < 0 ) )
			{
				throw new IXBRLDocumentValidationException( 'FormatAbsentNegativeNumber', '@format is missing in <$localName> so the content must be a positive number' );
			}
		}

		if ( is_numeric( $content ) )
		{
			$scale = $node->getAttribute( IXBRL_ATTR_SCALE );
			if (  $scale )
				$content = $content * pow( 10, $scale );
			$sign = $node->getAttribute( IXBRL_ATTR_SIGN );
			if (  $sign )
				$content = "-$content";
		}

		return $content;
	}

	/**
	 * Check the decimals and precision is valid
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @return bool
	 */
	private static function checkDecimalsPrecision( $node, $section, $localName )
	{
		$decimals = $node->getAttribute( IXBRL_ATTR_DECIMALS );
		$precision = $node->getAttribute( IXBRL_ATTR_PRECISION );
		$nil = $node->getAttribute( IXBRL_ATTR_NIL );
		self::checkAttributeConsistency( $node, IXBRL_ATTR_NIL, $nil, $section, $localName, 'NonFractionNestedNilMismatch' );
		if ( $nil && filter_var( $nil, FILTER_VALIDATE_BOOLEAN ) )
		{
			if ( $decimals || $precision )
			{
				throw new IXBRLDocumentValidationException( 'PrecisionAndDecimalsPresent', "Section $section Nil facts should not @decimals or @precision" );
			}
			if ( $node->hasChildNodes() )
			{
				throw new IXBRLDocumentValidationException( 'InvalidNilContent', "Section $section Nil facts should not have child nodes" );
			}

			return false;
		}
		else
		{
			if ( ( ! $decimals && strlen( $decimals ) == 0 && ! $precision && strlen( $precision ) == 0 ) )
			{
				throw new IXBRLDocumentValidationException( 'PrecisionAndDecimalsAbsent', "Section $section Decimals or precision must present on <$localName> but neither exist." );
			}

			if ( ( $decimals && $precision  ) )
			{
				throw new IXBRLDocumentValidationException( 'PrecisionAndDecimalsPresent', "Section $section Decimals or precision must present on <$localName> but both exist." );
			}

			// Exclude comments and processing instructions
			$childNodes = array_filter( iterator_to_array( $node->childNodes ), function( $node ) { return $node->nodeType != XML_COMMENT_NODE && $node->nodeType != XML_PI_NODE; } );
			if ( ! $childNodes )
			{
				throw new IXBRLDocumentValidationException( 'NonFractionIncompleteContent', "Section $section <$localName> facts that are not nil should have exactly one child" );
			}

			if ( count( $childNodes ) > 1 )
			{
				throw new IXBRLDocumentValidationException( 'NonFractionChildElementMixed', "Section $section <$localName> facts should have only one child even if some of the children are whitespace." );
			}

			return true;
		}
	}

	/**
	 * Check for loops in continuation elements
	 *
	 * @param \DOMElement $node
	 * @param string $section
	 * @param \DOMElement[] $idNodes
	 * @return \DOMElement[]
	 * @throws IXBRLDocumentValidationException
	 */
	private static function checkContinuationCycles( $node, $section, &$idNodes )
	{
		$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
		$result = array( $node );
		if ( ! $continuedAt ) return $result;

		if ( $node->getAttribute( IXBRL_ATTR_ID ) == $continuedAt )
		{
			throw new IXBRLDocumentValidationException( 'DanglingContinuation', "Section $section The continuedAt and id attribute values are the same: '" . $node->getNodePath() . "'" );
		}

		// Look for circular references
		$visited = array( $node->getNodePath() );
		while( true )
		{
			if ( ! isset( $idNodes[ $continuedAt ] ) )
			{
				throw new IXBRLDocumentValidationException( 'DanglingContinuation', "Section $section continuedAt there is no id with 'continuedAt' attribute value: $continuedAt" );
			}
	
			$nextNode = $idNodes[ $continuedAt ] ?? false;
			if ( ! $nextNode ) break;
			$result[] = $nextNode;
			if ( ! $nextNode->hasAttribute( IXBRL_ATTR_CONTINUEDAT ) ) break;
			$path = $nextNode->getNodePath();
			$exists = array_search( $path, $visited ) !== false;
			$visited[] = $path;
			if ( $exists ) 
			{
				throw new IXBRLDocumentValidationException( 'DanglingContinuation', 'Section $section continuedAt attribute value forms circular reference: \'' . join( "' -> '", $visited ) . '\'' );
			}
			$continuedAt = $nextNode->getAttribute( IXBRL_ATTR_CONTINUEDAT );
		}

		return $result;
	}

	/**
	 * Returns true if the parents include @context
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @return void
	 */
	private static function checkParentIsNotElement( $node, $section, $localName )
	{
		if ( ! self::checkParentNodes( $node, function( $parentNode ) use( $localName )
		{
			/** @var \DOMElement $parentNode */
			return $parentNode->localName == $localName;
		} ) ) return;

		throw new IXBRLDocumentValidationException( 'MisplacedIXElement', "Section $section The element <{$node->localName}> is not valid with <$localName>" );
	}

	/**
	 * A list of nodes indexed by @tupleID
	 * @var [type]
	 */
	private static $tupleIDs = null;

	/**
	 * Creates a list of nodes indexed by @tupleID
	 * @param \DOMElement[] $tupleNodes
	 * @return  \DOMElement[]
	 */
	private static function getTupleIds( &$tupleNodes, $section )
	{
		if ( is_null( self::$tupleIDs ) )
		{
			// Create a list of node indexed by @tupleID
			self::$tupleIDs = array_reduce( $tupleNodes, function( $carry, $node ) use( $section )
			{
				$tupleId = $node->getAttribute( IXBRL_ATTR_TUPLEID );
				if ( $tupleId )
				{
					if ( isset( $carry[ $tupleId ] ) )
					{
						throw new IXBRLDocumentValidationException( 'DuplicateTupleId', "Section $section duplicate @tupleID value: {$node->getNodePath()}" );
					}
					$carry[ $tupleId ] = $node;
				}
				return $carry;
			}, array() );
		}

		return self::$tupleIDs;
	}

	/**
	 * Check for loops in continuation elements
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $tupleRef
	 * @param \DOMElement[] $tupleNodes
	 * @return void
	 * @throws IXBRLDocumentValidationException
	 */
	private static function checkTupleCycles( $node, $section, &$tupleNodes )
	{
		$tupleRef = $node->getAttribute( IXBRL_ATTR_TUPLEREF );
		if ( ! $tupleRef ) return;

		$tupleRef = $node->getAttribute( IXBRL_ATTR_TUPLEREF );

		$tupleIds = self::getTupleIds( $tupleNodes, $section );

		if ( ! isset( $tupleIds[ $tupleRef ] ) )
		{
			throw new IXBRLDocumentValidationException( 'UnknownTuple', "Section $section There is no <tuple> with @tupleID $tupleRef: {$node->getNodePath()}" );
		}

		// Look for circular references
		$visited = array( $node->getNodePath() );
		while( true )
		{
			$nextNode = $tupleIds[ $tupleRef ];
			if ( ! $nextNode->hasAttribute( IXBRL_ATTR_TUPLEREF ) ) break;
			$path = $nextNode->getNodePath();
			$exists = array_search( $path, $visited ) !== false || spl_object_id( $nextNode ) == spl_object_id( $node );
			$visited[] = $path;
			if ( $exists ) 
			{
				throw new IXBRLDocumentValidationException( 'TupleCycle', 'Section $section @tupleRef attribute value forms circular reference: \'' . join( "' -> '", $visited ) . '\'' );
			}
			$tupleRef = $nextNode->getAttribute( IXBRL_ATTR_TUPLEREF );
			$node = $nextNode;
		}
	}

	/**
	 * Check to determine if the node has a <tuple> parent
	 * @param \DOMElement $node
	 * @param boolean $immediate (optional: false)
	 * @return \DOMElement
	 */
	public static function checkTupleParent( $node, $immediate = false )
	{
		$parentNode = self::checkParentNodes( $node, function( $parentNode ) use( $immediate )
		{
			if ( $parentNode->localName == IXBRL_ELEMENT_TUPLE ) return true;
			if ( ! $immediate ) return false;
			return array_search( $parentNode->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) !== false;
		} );

		return  $parentNode && $parentNode->localName == IXBRL_ELEMENT_TUPLE
			? $parentNode
			: null;
	}

	/**
	 * Check the order of the node is appropriate (if it exists)
	 * @param \DOMElement $node
	 * @param string $section
	 * @return void
	 */
	private static function checkOrderIsAppropriate( $node, $section )
	{
		// If the element includes @order then either there must be @tupleRef or the element must be a descendant of <tuple>
		if ( $node->hasAttribute( IXBRL_ATTR_ORDER ) ) 
		{
			if ( $node->hasAttribute( IXBRL_ATTR_TUPLEREF ) ) return;
			if ( self::checkTupleParent( $node ) ) return;
			throw new IXBRLDocumentValidationException( 'OrderOnNonTupleChild', "Section $section @order must be used on valid descendants of <tuple>" );
		}
		else
		{
			if ( $node->hasAttribute( IXBRL_ATTR_TUPLEREF ) || ! self::checkTupleParent( $node, true ) ) return;
			throw new IXBRLDocumentValidationException( 'OrderAbsent', "Section $section @order must be used on valid descendants of <tuple>" );
		}
	}

	/**
	 * Check the order numbers used in <tuple> are unique
	 * @param \DOMElement $node
	 * @param string $section
	 * @param \DOMElement[][] $nodesByLocalNames
	 * @return void
	 */
	private static function checkTupleOrders( $node, $section, &$nodesByLocalNames )
	{
		$elements = array( IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_NONFRACTION, IXBRL_ELEMENT_FRACTION );
		$elementsWithuple = array_merge( $elements, array( IXBRL_ELEMENT_TUPLE ) );
		$orderNodes = array();
		$childNodes = array();
		foreach( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ?? array() as $tupleNode )
		{
			$target = $tupleNode->getAttribute( IXBRL_ATTR_TARGET );
			$tupleRef = $tupleNode->hasAttribute( IXBRL_ATTR_TUPLEID ) 
				? $tupleNode->getAttribute( IXBRL_ATTR_TUPLEID ) 
				: 'tuple-' . spl_object_id( $tupleNode );
			$nodePath = $tupleNode->getNodePath();
			$depth = substr_count( $nodePath, IXBRL_ELEMENT_TUPLE );
			// Only need to consider ix elements as html elements will be valid parts of the layout
			foreach( $nodesByLocalNames as $ixLocalName => $ixNodes )
			{
				if ( array_search( $ixLocalName, array( IXBRL_ELEMENT_DENOMINATOR, IXBRL_ELEMENT_NUMERATOR, IXBRL_ELEMENT_EXCLUDE ) ) !== false ) continue;
				foreach( $ixNodes as $ixNode )
				{
					$ixNodePath = $ixNode->getNodePath();
					$ixTupleRef = $tupleRef;

					// ixNode will be a child if it begins with $nodePath (contains is good enough) 
					// but only want immediate children (the children will be processed on their own)
					if ( strpos( $ixNodePath, $nodePath ) === false  ) continue;
					if ( $ixNodePath == $nodePath )
					{
						if ( ! $tupleNode->hasAttribute( IXBRL_ATTR_TUPLEREF ) ) continue;
						$childNodes[ $ixTupleRef ][ $ixLocalName ][] = $ixNode;
					}
					else if ( substr_count( $ixNodePath, IXBRL_ELEMENT_TUPLE ) != $depth + ( $ixLocalName == IXBRL_ELEMENT_TUPLE ? 1 : 0 ) ) continue;
					$ixTarget = $ixNode->getAttribute( IXBRL_ATTR_TARGET );
					if ( $ixTarget != $target )
					{
						throw new IXBRLDocumentValidationException( 'InconsistentTargets', "Section $section The target  of <$ixLocalName> is not the same as the parent <tuple>: $ixNodePath" );
					}
					if ( array_search( $ixLocalName, $elementsWithuple ) === false )
					{
						throw new IXBRLDocumentValidationException( 'InvalidTupleChild', "Section $section The <$ixLocalName> is not valid within a tuple: $ixNodePath" );
					}
					$order = floatval( $ixNode->getAttribute( IXBRL_ATTR_ORDER ) );
					if ( $ixNode->hasAttribute( IXBRL_ATTR_TUPLEREF ) )
					{
						$ixTupleRef = $ixNode->getAttribute( IXBRL_ATTR_TUPLEREF );
					}
	
					if ( isset( $orderNodes[ $ixTupleRef ][ "~$order" ] ) )
					{
						// 15.1.2 Each element in the {tuple content} property with the same {tuple order} property MUST have the same Whitespace Normalized Value.
						$text = trim( preg_replace('/\s+/', ' ', $orderNodes[ $ixTupleRef ][ "~$order" ]->textContent ) );
						$ixText = trim( preg_replace('/\s+/', ' ', $ixNode->textContent ) );
						if ( $text == $ixText ) continue;

						throw new IXBRLDocumentValidationException( 'OrderDuplicate', "Section $section The order of an element within a tuple must be unique: $ixNodePath" );
					}
					$orderNodes[ $ixTupleRef ][ "~$order" ] = $ixNode;
					$childNodes[ $ixTupleRef ][ $ixLocalName ][] = $ixNode;
				}
			}
		}

		// Process ix elements that are not part of a <tuple>
		foreach( $elements as $ixLocalName )
		{
			foreach( $nodesByLocalNames[ $ixLocalName ] ?? array() as $ixNode )
			{
				// If the node is a tuple child it will have been handled above
				if ( self::checkTupleParent( $ixNode ) ) continue;

				$ixTupleRef = $ixNode->getAttribute( IXBRL_ATTR_TUPLEREF );
				if ( ! $ixTupleRef ) continue;
				$order = null;
				if ( $orderNodes[ $ixTupleRef ] ?? false )
				{
					$order = floatval( $ixNode->getAttribute( IXBRL_ATTR_ORDER ) );
					if ( isset( $orderNodes[ $ixTupleRef ][ "~$order" ] ) )
					{
						throw new IXBRLDocumentValidationException( 'OrderDuplicate', "Section $section The order of an element within a tuple must be unique: {$ixNode->getNodePath()}" );
					}
				}
				else
				{
					$tupleIds = self::getTupleIds( $tupleNodes, $section );
					if ( ! isset( $tupleIds[ $tupleRef ] ) )
						throw new IXBRLDocumentValidationException( 'UnknownTuple', "Section $section There is no <tuple> with @tupleID $tupleRef: {$ixNode->getNodePath()}" );
				}
				if ( $order )
				{
					$orderNodes[ $tupleRef ][ "~$order" ] = $ixNode;
				}
				$childNodes[ $tupleRef ][ $ixLocalName ][] = $ixNode;
			}
		}

		$nonNilTuples = array_filter( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ?? array(), function( $node ) { return ! filter_var( $node->getAttribute( IXBRL_ATTR_NIL ), FILTER_VALIDATE_BOOLEAN ); } );
		if ( count( $childNodes ) != count( $nonNilTuples ) )
		{
			$diff = count( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ?? array() ) - count( $childNodes );
			throw new IXBRLDocumentValidationException( 'TupleNonEmptyValidation', "Section $section $diff empty <tuple> elements" );
		}
	}

	/**
	 * Check the constraints
	 * @param \DOMElement $node
	 * @param string $localName
	 * @param \DOMElement[][] $nodesByLocalNames
	 * @param \DOMElement[] $idNodes
	 * @return void
	 */
	public static function validateConstraints( &$node, $localName, &$nodesByLocalNames, &$idNodes )
	{
		// Provides access to the raw document and initialized xpath instance
		// Go to the document and then to the root element so the base uri is not affected by local xml:base definitions
		$document = self::$documents[ $node->ownerDocument->documentElement->baseURI ];
		$correspondents = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_CONTINUATION );
		$section = '';

		if ( ! isset( $nodesByLocalNames[ $localName ] ) ) return;

		switch( $localName )
		{
			case IXBRL_ELEMENT_CONTINUATION:
				$section = '4.1.1';

				// self::checkParentIsNotElement( $node, $section, IXBRL_ELEMENT_CONTEXT );

				// MUST have an id attribute
				if ( ! $node->hasAttribute(IXBRL_ATTR_ID) )
				{
					throw new IXBRLDocumentValidationException( 'MissingId', "Section $section id does not exist" );
				}

				// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
				$id = $node->getAttribute( IXBRL_ATTR_ID );
				$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );

				self::checkParentNodes( $node, function( $parentNode ) use( &$correspondents, $id, $node, $section )
				{
					if ( $parentNode->localName == IXBRL_ELEMENT_HTML ) return true;
					if ( $parentNode->localName == IXBRL_ELEMENT_HIDDEN )
					{
						throw new IXBRLDocumentValidationException( 'ContinuationDescendantOfHidden', "Section $section <continuation> is a descendant of <hidden>" );
					}
					if ( array_search( $parentNode->localName, $correspondents ) !== false && 
						$parentNode->hasAttribute( IXBRL_ATTR_CONTINUEDAT ) &&
						$parentNode->getAttribute( IXBRL_ATTR_CONTINUEDAT ) == $id )
					{
						throw new IXBRLDocumentValidationException( 'ContinuationInvalidNesting', "Section $section <continuation> is a descendant of the referring element: " . $node->getNodePath() . " -> '" . $parentNode->getNodePath() . "'" );
					}
					return false;
				} );

				$foundContinuedAt = false;
				foreach( $correspondents as $localName )
				{
					foreach( $nodesByLocalNames[ $localName ] ?? array() as $lnNode )
					{
						if ( spl_object_hash( $lnNode ) == spl_object_hash( $node ) ) continue;
						$cAt = $lnNode->getAttribute( IXBRL_ATTR_CONTINUEDAT );
						if ( $continuedAt && $cAt && $continuedAt == $cAt )
						{
							throw new IXBRLDocumentValidationException( 'ContinuedAtDuplicated', "Section $section continuedAt attribute value is duplicated: " . $node->getNodePath() . " -> " . $lnNode->getNodePath() . "'" );
						}
						if ( ! $foundContinuedAt && $cAt && $id == $cAt )
						{
							$foundContinuedAt = true;
						}
					}
				}

				// In test (V-1705) FAIL-misplaced-ix-element-in-context.xml there is a case where the continuation @id exists but has no @continuedAt
				if (  $continuedAt && ! $foundContinuedAt )
				{
					throw new IXBRLDocumentValidationException( 'UnreferencedContinuation', "Section $section continuedAt there is no @id with 'continuedAt' attribute value: $continuedAt" );
				}

				self::checkContinuationCycles( $node, $section, $idNodes );

				break;

			case IXBRL_ELEMENT_EXCLUDE:
				$soure = '5.1.1';
				// The node must a descendant of 'footnote', 'nonNumeric' or 'continuation'
				$parentNode = self::checkParentNodes( $node, function( $parentNode ) use( $correspondents )
				{
					// Check for an allowed ancestor
					if ( array_search( $parentNode->localName, $correspondents ) !== false ) return true;
				} );

				if ( ! $parentNode )
					throw new IXBRLDocumentValidationException( 'MisplacedExclude', "Section $section <exclude> is not a descendant of <footnote>, <nonNumeric> or <continuation>" );

				break;

			case IXBRL_ELEMENT_FOOTNOTE:

				$section = '6.1.1';

				// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
				$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
				if ( $continuedAt )
				{
					if ( ! isset($idNodes[ $continuedAt] ) )
					{
						throw new IXBRLDocumentValidationException( 'UnreferencedContinuation', "Section $section continuedAt there is no id with \'continuedAt\' attribute value: $continuedAt" );
					}
					self::checkContinuationCycles( $node, $section, $idNodes );
				}

				// Make sure there is a @xml:lang in scope
				if ( ! $node->hasAttribute( IXBRL_ATTR_LANG ) &&
					 ! self::checkParentNodes( $node, function( $parentNode ) 
					{
						return $parentNode->hasAttribute( IXBRL_ATTR_LANG );
					} ) )
				{
					throw new IXBRLDocumentValidationException( 'FootnoteWithoutXmlLangInScope', "Section $section A footnote must has a @xml:lang in scopes" );
				}

				break;

			case IXBRL_ELEMENT_HEADER:
				$section = '8.1.1'; 
				// Must not be a child of html
				if ( strtolower( $node->parentNode->localName ) == IXBRL_ELEMENT_HTML )
				{
					throw new IXBRLDocumentValidationException( 'MisplacedIXElement', "Section $section <header> must not be a child of <html>" );
				}

				// Must have at most one <hidden> element
				$hiddenNodes = $document->xpath->query( sprintf( "./{$document->ixPrefix}:%s", IXBRL_ELEMENT_HIDDEN ), $node );
				if ( $hiddenNodes->count() > 1 )
				{
					throw new IXBRLDocumentValidationException( 'MoreThanOneElement', "Section $section <header> must have at most one <hidden> element" );
				}

				// Must have at most one <resources> element
				$resourceNodes = $document->xpath->query( sprintf( "./{$document->ixPrefix}:resources", IXBRL_ELEMENT_RESOURCES ), $node );
				if ( $resourceNodes->count() > 1 )
				{
					throw new IXBRLDocumentValidationException( 'MoreThanOneElement', "Section $section <header> must have at most one <resources> element" );
				}

				break;

			case IXBRL_ELEMENT_HIDDEN:

				$section = '9.1.1'; // Must a child of header
				if ( $node->parentNode->localName != IXBRL_ELEMENT_HEADER )
				{
					throw new IXBRLDocumentValidationException( 'MisplacedIXElement', "Section $section <hidden> must not be a child of <header>" );
				}

				// Children MUST be one of the allowed elements
				$chidElements = array( 'footnote', 'fraction', 'nonFraction', 'nonNumeric', 'tuple' );
				foreach( $node->childNodes as $localName => $childNode )
				{
					/** @var DOMNode $childNode */
					if ( $childNode->nodeType != XML_ELEMENT_NODE ) continue;
					if ( array_search( $childNode->localName, $chidElements ) === false )
						throw new IXBRLDocumentValidationException( 'MisplacedIXElement', "Section $section <header> must not be a child of HTML" );
				}

				break;

			case IXBRL_ELEMENT_DENOMINATOR:
				$section = '7.1.1';
				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkFormat( $node, $section, $localName, $document, $idNodes );
				break;

			case IXBRL_ELEMENT_NUMERATOR:
				$section = '7.1.1';
				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkFormat( $node, $section, $localName, $document, $idNodes );
				break;

			case IXBRL_ELEMENT_FRACTION:
				$section = '7.1.1';

				self::checkParentIsNotElement( $node, $section, IXBRL_ELEMENT_CONTEXT );
				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkUnitRef( $node, $idNodes, $section, $localName, 'FractionNestedAttributeMismatch' );
				self::checkContextRef( $node, $idNodes, $section, $localName );
				$tupleNodes = $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ?? array();
				self::checkTupleCycles( $node,$section, $tupleNodes  );
				self::checkOrderIsAppropriate( $node, $section );

				$nil = $node->getAttribute( IXBRL_ATTR_NIL );
				self::checkAttributeConsistency( $node, IXBRL_ATTR_NIL, $nil, $section, $localName, 'FractionNestedNilMismatch' );

				if ( $nil && filter_var( $nil, FILTER_VALIDATE_BOOLEAN ) )
				{
					if ( $node->childNodes->length )
					{
						throw new IXBRLDocumentValidationException( 'InvalidChildren', "Section $section: When @nil of <$localName> is true there must be no child nodes." );
					}
					break;
				}

				// Only <fraction> <numerator> and <denominator> ix children allowed
				$allowedChildren = array( IXBRL_ELEMENT_DENOMINATOR, IXBRL_ELEMENT_NUMERATOR, IXBRL_ELEMENT_FRACTION );
				$childNodes = array_fill_keys( $allowedChildren, 0 );
				$nodePath = $node->getNodePath();
				// Only need to consider ix element as html elements will be valid parts of the layout
				foreach( $nodesByLocalNames as $ixLocalName => $ixNodes )
				{
					foreach( $ixNodes as $ixNode )
					{
						$ixNodePath = $ixNode->getNodePath();
						// ixNode will be a child if it begins with $nodePath (contains is good enough) 
						// but only want immediate children (the children will be processed on their own)
						if ( $ixNodePath == $nodePath || strpos( $ixNodePath, $nodePath ) === false  ) continue;
						if ( isset( $childNodes[ $ixLocalName ] ) )
						{
							$childNodes[ $ixLocalName ]++;
							continue;
						}
						throw new IXBRLDocumentValidationException( 'UnknownFractionChild', "Section $section: Only one <numerator> and one <denominator> allowed as children of <$localName>" );	
					}
				}

				// If there is a <fraction> descendant then there can be zero numerartors/denominators
				if ( ( ! $childNodes[ IXBRL_ELEMENT_DENOMINATOR ] ) || 
					 ( ! $childNodes[ IXBRL_ELEMENT_NUMERATOR ] ) )
					throw new IXBRLDocumentValidationException( 'IncompleteFraction', "Section $section: There must be one <numerator> and one <denominator> descendants of <$localName>. {$childNodes[ IXBRL_ELEMENT_NUMERATOR ]} found." );

				if ( $childNodes[ IXBRL_ELEMENT_NUMERATOR ] != 1 )
					throw new IXBRLDocumentValidationException( 'MultipleNumeratorDenominator', "Section $section: There must be one <numerator> descendant of <$localName>. {$childNodes[ IXBRL_ELEMENT_NUMERATOR ]} found." );

				if ( $childNodes[ IXBRL_ELEMENT_DENOMINATOR ] != 1 )
					throw new IXBRLDocumentValidationException( 'MultipleNumeratorDenominator', "Section $section: There must be one <denominator> descendant of <$localName>. {$childNodes[ IXBRL_ELEMENT_DENOMINATOR ]} found." );
					
				break;

			case IXBRL_ELEMENT_NONFRACTION:
				$section = $section ?? '10.1.1';

				self::checkParentIsNotElement( $node, $section, IXBRL_ELEMENT_CONTEXT );
				self::checkUnitRef( $node, $idNodes, $section, $localName );
				self::checkSign( $node, $section, $localName );
				self::checkScale( $node, $section, $localName );
				self::checkOrderIsAppropriate( $node, $section );

				if ( self::checkDecimalsPrecision( $node, $section, $localName ) )
				{
					// Exclude comments
					$childNodes = array_filter( iterator_to_array( $node->childNodes ), function( $node ) { return $node->nodeType != XML_COMMENT_NODE && $node->nodeType != XML_PI_NODE; } );
					/** @var \DOMNode */
					$childNode = reset( $childNodes );
					if ( $childNode->nodeType == XML_TEXT_NODE )
					{
						if ( strlen( $childNode->textContent ) == 0 )
						{
							throw new IXBRLDocumentValidationException( 'NonFractionChildElementMixed', "Section $section A text node in <$localName> must not be empty" );
						}
					}
					else if ( $childNode->nodeType != XML_ELEMENT_NODE || $childNode->localName != IXBRL_ELEMENT_NONFRACTION )
					{
						throw new IXBRLDocumentValidationException( 'NonFractionChildElementMixed', "Section $section <$localName> child node must be a text node or <nonFraction>" );
					}
				}

				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkContextRef( $node, $idNodes, $section, $localName );
				$tupleNodes =  $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ?? array();
				self::checkTupleRef( $node, $section, $localName, $tupleNodes );

				// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
				// $continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
				// self::checkContinuationCycles( $node, $section, $idNodes );
				self::checkFormat( $node, $section, $localName, $document, $idNodes );

				break;

			case IXBRL_ELEMENT_NONNUMERIC:

				$section = '11.1.1';

				// No ix attributes should appear
				self::checkParentIsNotElement( $node, $section, IXBRL_ELEMENT_CONTEXT );
				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkContextRef( $node, $idNodes, $section, $localName );
				$tupleNodes =  $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ?? array();
				self::checkTupleRef( $node, $section, $localName, $tupleNodes );
				self::checkOrderIsAppropriate( $node, $section );

				$formattedValue = self::checkFormat( $node, $section, $localName, $document, $idNodes );

				break;

			case IXBRL_ELEMENT_REFERENCES:
				$section = '12.1.1';
				if ( $node->parentNode->localName != IXBRL_ELEMENT_HEADER )
				{
					throw new IXBRLDocumentValidationException( 'MisplacedIXElement', "Section $section <references> must not be a child of <header>" );
				}

				self::checkIXbrlAttribute( $node, $section, $localName );
				break;

			case IXBRL_ELEMENT_RELATIONSHIP:

				$section = '13.1.1';
				$references = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_FRACTION, IXBRL_ELEMENT_NONFRACTION, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_TUPLE );
	
				// No attributes should use the xbrli namespace
				$attributes = array();
				$ixNamespaces = array( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL10], \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL11] );
				foreach( $node->attributes as $name => $attr )
				{
					/** @var \DOMAttr $attr */
					if ( array_search( $attr->namespaceURI, $ixNamespaces ) !== false )
					{
						throw new IXBRLDocumentValidationException( 'RepeatedOtherAttributes', "Section $section A <relationship> uses an attribute from the XBRLI namespace." );
					}
					$attributes[ $name ] = $attr->nodeValue;
				}

				// 'fromRefs' and 'toRefs' should not overlap
				$fromRefs = explode( ' ', $node->getAttribute( IXBRL_ATTR_FROMREFS ) );
				$toRefs = explode( ' ', $node->getAttribute( IXBRL_ATTR_TOREFS ) );
				if ( array_intersect( $fromRefs, $toRefs ) )
				{
					throw new IXBRLDocumentValidationException( 'RelationshipCrossDuplication', "Section $section A <relationship> attributes 'fronRefs' and 'toRefs' values overlap." );
				}

				$pos = array_search( IXBRL_ELEMENT_FOOTNOTE, $references );
				unset( $references[ $pos ] );

				// Check fromRefs and toRefs are valid
				$validateRefs = function( $refs, $refType ) use( &$idNodes, &$references )
				{
					$section = '13.1.2';
					$errorMessage = "Section $section A <relationship> attributes '$refType' values overlap.";
					$errorCode = $refType == IXBRL_ATTR_FROMREFS ? 'DanglingRelationshipFromRef' : 'DanglingRelationshipToRef';

					$localNames = array();
					foreach( $refs as $ref )
					{
						$idNode = $idNodes[ $ref ] ?? false;
						if ( $idNode )
						{
							$localName = $idNode->localName;

							// The node local name must be one of the references
							if ( $localName != IXBRL_ELEMENT_FOOTNOTE && array_search( $localName, $references ) === false )
							{
								throw new IXBRLDocumentValidationException( $errorCode, $errorMessage );
							}

							$localNames[] = $localName;
						}
						else
							throw new IXBRLDocumentValidationException( $errorCode, $errorMessage );
					}

					if ( array_search( IXBRL_ELEMENT_FOOTNOTE, $localNames ) !== false && array_intersect( $references, $localNames ) )
					{
						throw new IXBRLDocumentValidationException( 'RelationshipMixedToRefs', "Section $section A <relationship> to ref is to a footnote and another iXBRL element." );
					}
				};

				$validateRefs( $fromRefs, IXBRL_ATTR_FROMREFS );
				$validateRefs( $toRefs, IXBRL_ATTR_TOREFS );

				break;

			case IXBRL_ELEMENT_RESOURCES:
				break;

			case IXBRL_ELEMENT_TUPLE:
				$section = '15.1.1';
				self::checkIXbrlAttribute( $node, $section, $localName );
				// Checks for unique ids
				self::getTupleIds( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ], $section );
				self::checkTupleCycles( $node, $section, $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] );
				// self::checkTupleOrders( $node, $section, $nodesByLocalNames );

				break;
		}
	}

	/**
	 * Removes any excludes that are descendants of $node
	 * @param \DOMElement $node
	 * @param \DOMXPath $xpath
	 * @return void
	 */
	private static function removeNestedExcludes( $node, $xpath ) 
	{
		foreach( $xpath->query('./ix:exclude', $node ) as $excludeNode )
		{
			$excludeNode->parentNode->removeChild( $excludeNode );
		}
	}

	/**
	 * Run all conformance tests
	 */
	public static function Test()
	{
		require_once __DIR__. '/IXBRL-Tests.php';

		if ( ! function_exists('lyquidity\ixbrl\TestInlineXBRL') ) return;
		TestInlineXBRL();
	}
}

/**
 * Validate an inline XBRL document against the IX schema
 */
class ValidateInlineDocument
{
	const schemaUrl = 'http://www.xbrl.org/2013/inlineXBRL/xhtml-inlinexbrl-1_1.xsd';

	/**
	 * An array of libxml errors or null
	 * @var array
	 */
	public $errors = null;

	/**
	 * Validation Class constructor Instantiating DOMDocument
	 */
	public function __construct()
	{
	}

	/**
	 * @param \libXMLError object $error
	 *
	 * @return string
	 */
	public static function formatError( $error )
	{
		$errorString = "Error {$error->code} in {$error->file} (Line:{$error->line})\n";
		$errorString .= trim($error->message);
		return $errorString;
	}

	/**
	 * Displays any validation errors
	 *
	 * @return void
	 */
	public function displayErrors()
	{
		if ( ! $this->errors ) 
		{
			error_log( "No errors" );
			return;
		}

		foreach( $this->errors as $error )
		{
			error_log( self::formatError( $error ) );
		}
	}

	/**
	 * Displays any validation errors
	 * @param string[] $codes
	 * @return void
	 */
	public function hasErrorCode(  $codes)
	{
		if ( ! $this->errors ) 
		{
			return false;
		}

		foreach( $this->errors as $error )
		{
			if ( array_search( $error->code, $codes ) !== false ) return true;
		}

		return false;
	}

	/**
	 * Validate Inline XBRL DOM document against the inline 1.1 schema
	 *
	 * @param \DOMDocument $document
	 * @return void
	 * @throws \Exception If there is no valid document
	 * @throws IXBRLSchemaValidationException If there are schema violations in the structure of the document
	 */
	public function validateDOMDocument( $document, $ixPrefix = null)
	{
		if ( ! $document )
			throw new IXBRLException('The DOM document is not valid');

		$ixPrefix ?? STANDARD_PREFIX_IXBRL11;
		$schemaUrl = \XBRL_Constants::$standardNamespaceSchemaLocations[ $ixPrefix ];

		libxml_use_internal_errors( true );

		if ( $document->schemaValidate( $schemaUrl ) )
			return $this;

		$this->errors = libxml_get_errors();
		libxml_clear_errors();

		throw new IXBRLSchemaValidationException( $this, 'Failed using schemaValidate' );
		return $this;
	}

}

/**
 * An iXBRL specific exception
 */
class IXBRLException extends \Exception
{
}

/**
 * An iXBRL specific exception
 */
class IXBRLInvalidNamespaceException extends IXBRLException
{
}

/**
 * An iXBRL specific exception when validating the content of the document
 */
class IXBRLDocumentValidationException extends IXBRLException
{
	/**
	 * The code of a specific validation error
	 *
	 * @var string
	 */
	private $errorCode = null;

	/**
	 * Defines a new constructor
	 *
	 * @param string $message
	 * @param integer $code
	 * @param [type] $previous
	 */
	function __construct( $errorCode, $message = '', $code = 0, $previous = null )
	{
		$this->errorCode = $errorCode;
		parent::__construct( $message, $code, $previous );

		$this->message .= " ($this->errorCode)";
	}

	/**
	 * Get the code of a specific validation error
	 *
	 * @var string
	 */
	function getErrorCode()
	{
		return $this->errorCode;
	}
}

/**
 * An iXBRL specific exception when validating against the schema
 */
class IXBRLSchemaValidationException extends IXBRLException
{
	/**
	 * The id of a specific validation error
	 *
	 * @var ValidateInlineDocument
	 */
	private $validator = null;

	/**
	 * Defines a new constructor
	 *
	 * @param string $message
	 * @param integer $code
	 * @param [type] $previous
	 */
	function __construct( $validator, $message = '', $code = 0, $previous = null )
	{
		$this->validator = $validator;
		parent::__construct( $message, $code, $previous );

		$this->message .= "\n" . join('\n', array_map( function( $error ) 
		{
			return ValidateInlineDocument::formatError( $error ); 
		}, $validator->errors ) );
	}

	/**
	 * Returns the current validator instance
	 *
	 * @return ValidateInlineDocument
	 */
	function getValidator()
	{
		return $this->validator;
	}
}
