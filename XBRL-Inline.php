<?php

/**
 * XBRL Inline document loading and validatopm
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

use XBRL\Formulas\Resources\Filters\Boolean;

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

define( "IXBRL_ATTR_ARCROLE", "arcrole" );
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
	protected $xpath = null;

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
	 * Inline XBRL class constructor instantiating DOMDocument
	 * @throws \Exception When the document is not an IXBRL document
	 */
	public function __construct( $docUrl )
	{
		$this->document = new \DOMDocument();
		if ( ! $this->document->load( $docUrl ) )
		{
			throw new \IXBRLException('Failed to load the document');
		}

		$this->url = $docUrl;
		$this->root = $this->document->documentElement;
		$ns = $this->root->namespaceURI;
		$ln = $this->root->localName;

		$this->xpath = $xpath = new DOMXPath( $this->document );
		$xpath->registerNamespace( 'ix10', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL10] );
		$xpath->registerNamespace( 'ix11', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL11] );
		$xpath->registerNamespace( STANDARD_PREFIX_XBRLI, \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI] );
		if ( count( $xpath->query( '//ix11:*', $this->root ) ) && count( $xpath->query( '//ix10:*', $this->root ) ) )
		{
			throw new \IXBRLDocumentValidationException('ix:multipleIxNamespaces', 'The document uses more than one iXBRL namespace');
		}

		$iXBRLNamespaces = array_intersect( \XBRL_Constants::$ixbrlNamespaces, $this->getElementNamespaces() );

		$this->ixPrefix = array_flip( \XBRL_Constants::$ixbrlNamespaces )[ reset( $iXBRLNamespaces ) ];

		// Any document can be IXBRL
		$this->isIXBRL = count( $iXBRLNamespaces ) > 0;
		if ( ! $this->isIXBRL )
			throw new \IXBRLException('This is not an Inline XBRL document');

		$this->isXHTML = $ns == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML ] && ( $ln == IXBRL_ELEMENT_HTML || $ln == IXBRL_ELEMENT_XHTML );
	}

	/**
	 * Returns an array of namespaces in the document if it is valid
	 *
	 * @param [DOMElement] $element If provided the query operates on this element otherwise the root.
	 * @return string[]
	 */
	public function getElementNamespaces( $element = null)
	{
		return $this->getNodeValues('namespace::*', $element );
	}

	/**
	 * Returns an array of all namespaces in the document, including those of nested elements, if the document is valid
	 *
	 * @param [DOMElement] $element If provided the query operates on this element otherwise the root.
	 * @return string[]
	 */
	public function getAllNamespaces( $element = null )
	{
		return $this->getNodeValues('//namespace::*', $element );
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
			$xpath = new DOMXPath( $this->document );
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
	 * @param array? $targets
	 * @return \DOMElement[]
	 */
	private function getTargets( $targets = array() )
	{
		$targets[null] = $targets[null] ?? array();

		// Get all ix nodes that DO NOT have a 'target' attribute (these are for the default document)
		$nodes = $this->xpath->query( sprintf("//{$this->ixPrefix}:*[not(@%s)]", IXBRL_ATTR_TARGET ), $this->root );
		$targets[null] = array_merge( $targets[null], iterator_to_array( $nodes ) );

		// Get all ix nodes that have a 'target' attribute
		$nodes = $this->xpath->query( sprintf("//{$this->ixPrefix}:*[@%s]", IXBRL_ATTR_TARGET ), $this->root );
		$targets = array_reduce( iterator_to_array( $nodes ), function( $carry, $node )
		{
			/** @var \DOMElement $node */
			$target = $node->getAttribute( IXBRL_ATTR_TARGET );
			$carry[ $target ][] = $node;
			return $carry;
		},$targets );
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
	 * Validate the current document if there is one
	 *
	 * @throws \Exception If there is no valid document
	 * @throws IXBRLSchemaValidationException If there are schema violations in the structure of the document
	 */
	public function validateDocument()
	{
		if ( ! $this->document )
		{
			throw new \IXBRLException('There is no valid iXBRL document');
		}

		// Can only validate xhtml against the schema
		if ( ! $this->isXHTML ) return;
		$validator = new ValidateInlineDocument();
		$validator->validateDOMDocument( $this->document, $this->ixPrefix );

		// Getting here means the document validates against the iXBRL schema
	}

	/**
	 * A holder for the documents being created
	 *
	 * @var array
	 */
	private static $outputs = null;

	/**
	 * Create an instance document from in input iXBRL document set
	 *
	 * @param string[] $documentSet
	 * @param boolean? $validate
	 * @param callable? $fn This is a dummy parameter to get around the intelliphense type checking which insists that the arg to libxml_set_external_entity_loader cannot be null.
	 * @return void
	 */
	public static function createInstanceDocument( $documentSet, $validate = true, $fn = null )
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

		$targets = array();
		$idNodes = array();
		self::$outputs = array();

		try
		{
			$documents = [];
			foreach( $documentSet as $documentUrl )
			{
				// Use the entity loader to make sure libxml uses files from the local.
				// This is an order of magnitude faster.
				$context->setEntityLoader( dirname( $documentUrl ) );

				$document = new XBRL_Inline( $documentUrl );
				$documents[ $documentUrl ] = $document;
				$document->validateDocument();  // Schema validation

				$targets = $document->getTargets( $targets );
				$idNodes = $document->getIDs( $idNodes );

				$context->resetEntityLoader();
			}

			unset( $document );
			unset( $documentUrl );

			// There will be an output document for every target found
			$putputs = array_fill_keys( array_keys( $targets ), array() );
	
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

			// Get and validate the ix:header elements (8)
			foreach( $nodesByLocalNames as $localName => $nodes )
			{
				foreach( $nodes as $node )
				{
					/** @var \DOMElement $node */
					if ( $validate )
					{
						self::validateConstraints( $node, $localName, $nodesByLocalNames, $documents, $idNodes );
					}
				}
			}

			if ( $nodesByLocalNames[ IXBRL_ELEMENT_CONTINUATION ] )
			{
				// Make sure continuedAt attribute don't reference the same id
				// Get the nodes for continuation, footnote and nonNumeric elements
				$correspondents = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_CONTINUATION );
				$atIds = array();
				foreach( $correspondents as $localName )
				{
					foreach( $nodesByLocalNames[ $localName ] as $node )
					{
						if ( ! $node->hasAttribute( IXBRL_ATTR_CONTINUEDAT ) ) continue;
						$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
						if ( array_search( $continuedAt, $atIds ) !== false )
						{
							throw new IXBRLDocumentValidationException( 'ContinuationReuse', 'Section 4.1.1 A \'continuationAt\' attribute with value \'$continuedAt\' has been used more than once' );			
						}
						$atIds[] = $continuedAt;
					}
				}
			}
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
	 * Check the constraints
	 * @param \DOMElement $node
	 * @param string $localName
	 * @param \DOMElement[][] $nodesByLocalNames
	 * @param XBRL_Inline[] $documents
	 * @param \DOMElement[] $idNodes
	 * @return void
	 */
	public static function validateConstraints( &$node, $localName, &$nodesByLocalNames, &$documents, &$idNodes )
	{
		// Provides access to the raw document and initialized xpath instance
		$document = $documents[ $node->baseURI ];
		switch( strtolower( $localName ) )
		{
			case IXBRL_ELEMENT_CONTINUATION:
				// 4.1.1
				// MUST have an id attribute
				if ( ! $node->hasAttribute(IXBRL_ATTR_ID) )
				{
					throw new IXBRLDocumentValidationException( 'MissingId', 'Section 4.1.1 id does not exist' );
				}

				// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
				$id = $node->getAttribute( IXBRL_ATTR_ID );
				$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
				$correspondents = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_CONTINUATION );

				// Cannot be a descendant of <hidden>
				// Cannot be a descendant of
				$errorChildOfReferrer = false;
				$parentNode = $node->parentNode;
				while( $parentNode )
				{
					if ( $parentNode->localName == IXBRL_ELEMENT_HTML ) break;
					if ( $parentNode->localName == IXBRL_ELEMENT_HIDDEN )
					{
						throw new IXBRLDocumentValidationException( 'ContinuationDescendantOfHidden', 'Section 4.1.1 <continuation> is a descendant of <hidden>' );
					}
					if ( array_search( $parentNode->localName, $correspondents ) !== false && 
						$parentNode->hasAttribute( IXBRL_ATTR_CONTINUEDAT ) &&
						$parentNode->getAttribute( IXBRL_ATTR_CONTINUEDAT ) == $id )
					{
						throw new IXBRLDocumentValidationException( 'ContinuationInvalidNesting', 'Section 4.1.1 <continuation> is a descendant of the referring element: \'' . $node->getNodePath() . '\' -> \'' . $parentNode->getNodePath() . '\'' );
					}

					// Up another level
					$parentNode = $parentNode->parentNode;
				}

				$foundContinuedAt = false;
				// $foundContinuation = ! $continuedAt;
				foreach( $correspondents as $localName )
				{
					foreach( $nodesByLocalNames[ $localName ] ?? array() as $lnNode )
					{
						if ( spl_object_hash( $lnNode ) == spl_object_hash( $node ) ) continue;
						$cAt = $lnNode->getAttribute( IXBRL_ATTR_CONTINUEDAT );
						if ( $continuedAt && $cAt && $continuedAt == $cAt )
						{
							throw new IXBRLDocumentValidationException( 'ContinuedAtDuplicated', 'Section 4.1.1 continuedAt attribute value is duplicated: \'' . $node->getNodePath() . '\' -> \'' . $lnNode->getNodePath() . '\'' );
						}
						if ( ! $foundContinuedAt && $cAt && $id == $cAt )
						{
							$foundContinuedAt = true;
						}
					}
				}

				if ( ! $foundContinuedAt )
				{
					throw new IXBRLDocumentValidationException( 'UnreferencedContinuation', 'Section 4.1.1 continuedAt there is no id with \'continuedAt\' attribute value: $continuedAt' );
				}

				self::checkContinuationCycles( $node, $continuedAt, $idNodes );

				break;

			case IXBRL_ELEMENT_DENOMINATOR:
				break;

			case IXBRL_ELEMENT_EXCLUDE:
				break;

			case IXBRL_ELEMENT_FOOTNOTE:
				break;

			case IXBRL_ELEMENT_FRACTION:
				break;

			case IXBRL_ELEMENT_HEADER:
				// 8.1.1 
				// Must not be a child of html
				if ( strtolower( $node->parentNode->localName ) == IXBRL_ELEMENT_HTML )
				{
					throw new IXBRLDocumentValidationException( 'MisplacedIXElement', 'Section 8.1.1 <header> must not be a child of <html>' );
				}

				// Must have at most one <hidden> element
				$hiddenNodes = $document->xpath->query( sprintf( "./{$document->ixPrefix}:%s", IXBRL_ELEMENT_HIDDEN ), $node );
				if ( $hiddenNodes->count() > 1 )
				{
					throw new IXBRLDocumentValidationException( 'MoreThanOneElement', 'Section 8.1.1 <header> must have at most one <hidden> element' );
				}

				// Must have at most one <resources> element
				$resourceNodes = $document->xpath->query( sprintf( "./{$document->ixPrefix}:resources", IXBRL_ELEMENT_RESOURCES ), $node );
				if ( $resourceNodes->count() > 1 )
				{
					throw new IXBRLDocumentValidationException( 'MoreThanOneElement', 'Section 8.1.1 <header> must have at most one <resources> element' );
				}
				break;

			case IXBRL_ELEMENT_HIDDEN:

				// 9.1.1 Must a child of header
				if ( $node->parentNode->localName != IXBRL_ELEMENT_HEADER )
				{
					throw new IXBRLDocumentValidationException( 'MisplacedIXElement', 'Section 9.1.1 <hidden> must not be a child of <header>' );
				}

				// Children MUST be one of the allowed elements
				$chidElements = array( 'footnote', 'fraction', 'nonFraction', 'nonNumeric', 'tuple' );
				foreach( $node->childNodes as $localName => $childNode )
				{
					/** @var DOMNode $childNode */
					if ( $childNode->nodeType == XML_ELEMENT_NODE ) continue;
					if ( array_search( $childNode->localName, $chidElements ) === false )
					throw new IXBRLDocumentValidationException( 'MisplacedIXElement', 'Section 8.1.1 <header> must not be a child of HTML' );
				}

				break;

			case IXBRL_ELEMENT_NONFRACTION:
				break;

			case IXBRL_ELEMENT_NONNUMERIC:
				break;

			case IXBRL_ELEMENT_NUMERATOR:
				break;

			case IXBRL_ELEMENT_REFERENCES:
				break;

			case IXBRL_ELEMENT_RELATIONSHIP:
				break;

			case IXBRL_ELEMENT_RESOURCES:
				break;

			case IXBRL_ELEMENT_TUPLE:
				break;		

		}
	}

	/**
	 * Check for loops in continuation elements
	 *
	 * @param \DOMElement $node
	 * @param string $continuedAt
	 * @param \DOMElement[] $idNodes
	 * @return void
	 * @throws IXBRLDocumentValidationException
	 */
	private static function checkContinuationCycles( $node, $continuedAt, &$idNodes )
	{
		if ( ! $continuedAt ) return;

		if ( ! isset( $idNodes[ $continuedAt ] ) )
		{
			throw new IXBRLDocumentValidationException( 'DanglingContinuation', 'Section 4.1.1 continuedAt there is no id with \'continuedAt\' attribute value: $continuedAt' );
		}

		if ( $node->getAttribute( IXBRL_ATTR_ID ) == $continuedAt )
		{
			throw new IXBRLDocumentValidationException( 'UnreferencedContinuation', 'Section 4.1.1 The continuedAt and id attribute values are the same: \'' . $node->getNodePath() . '\'' );
		}

		// Look for circular references
		$visited = array( $node->getNodePath() );
		while( true )
		{
			$nextNode = $idNodes[ $continuedAt ];
			if ( ! $nextNode->hasAttribute('continuedAt') ) break;
			$path = $nextNode->getNodePath();
			$exists = array_search( $path, $visited ) !== false;
			$visited[] = $path;
			if ( $exists ) 
			{
				throw new IXBRLDocumentValidationException( 'DanglingContinuation', 'Section 4.1.1 continuedAt attribute value forms circular reference: \'' . join( "' -> '", $visited ) . '\'' );
			}
			$continuedAt = $nextNode->getAttribute( IXBRL_ATTR_CONTINUEDAT );
		}
	}

	/**
	 * Run all conformance tests
	 */
	public static function Test()
	{
		if ( ! function_exists('TestInlineXBRL') ) return;
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
			throw new \IXBRLException('The DOM document is not valid');

		$ixPrefix ?? STANDARD_PREFIX_IXBRL11;
		$schemaUrl = \XBRL_Constants::$standardNamespaceSchemaLocations[ $ixPrefix ];
		// $schemaFile = $this->getIXBRLSchemaFile( $schemaUrl );

		libxml_use_internal_errors( true );

		// if ( $document->schemaValidate( $schemaFile ) )
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
 * An iXBRL specific exception when validating the content of the document
 */
class IXBRLDocumentValidationException extends \IXBRLException
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
class IXBRLSchemaValidationException extends \IXBRLException
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

/**
 * Run all conformance tests
 *
 * @return void
 */
function TestInlineXBRL()
{
	$testCasesFolder = "D:/GitHub/xbrlquery/conformance/inlineXBRL-1.1-conformanceSuite-2020-04-08/";
	$mainDoc = new \DOMDocument();
	if ( ! $mainDoc->load( "$testCasesFolder/index.xml" ) )
	{
		throw new \IXBRLException('Failed to load the main test document');
	}

	$documentElement = $mainDoc->documentElement;

	error_log( "{$documentElement->localName}" );
	error_log( $documentElement->getAttribute('name') );

	$xpath = new DOMXPath( $mainDoc );

	foreach( $xpath->query( '//testcases', $documentElement ) as $testcases )
	{
		/** @var \DOMElement $testcases */
		error_log( $testcases->getAttribute('title') );

		foreach( $xpath->query( 'testcase', $testcases ) as $tag => $testcase )
		{
			/** @var \DOMElement $element */
			$testcaseDir = trailingslashit( "{$testCasesFolder}tests/" . dirname( $testcase->getAttribute('uri') ) );
			$testcaseFilename = basename( $testcase->getAttribute('uri') );
			testCase( $testcaseDir, $testcaseFilename );
		}
	}
}

/**
 * Execute a test case
 * @param string $basename
 * @param string $filename
 * @return boolean
 */
function testCase( $dirname, $filename )
{
	switch( $filename  )
	{
		#region ./baseURIs
		case "FAIL-baseURI-on-ix-header.xml":
		case "FAIL-baseURI-on-xhtml.xml":
		case "PASS-baseURI-on-ix-references-multiRefs.xml":
		#endregion
			return;

		#region ./continuation
		// case "FAIL-continuation-duplicate-id.xml": // Checked
		// case "FAIL-continuation-nonNumeric-circular.xml": // Checked Dangling
		// case "FAIL-continuation-nonNumeric-circular2.xml": // Checked ContinuationReuse
		// case "FAIL-continuation-nonNumeric-invalid-nesting-2.xml": // Checked ContinuationInvalidNesting
		// case "FAIL-continuation-nonNumeric-invalid-nesting.xml": // TODO: Check this in nonNumeric
		// case "FAIL-continuation-nonNumeric-self.xml": // TODO: Check this in nonNumeric Dangling
		// case "FAIL-continuation-orphaned-cycle.xml": // Checked UnreferencedContinuation
		// case "FAIL-continuation-used-twice.xml": // Checked ContinuationReuse
		// case "FAIL-footnote-continuation-invalid-nesting-2.xml": // Checked ContinuationInvalidNesting
		// case "FAIL-footnote-continuation-invalid-nesting.xml": // TODO: Check this in footnote
		// case "FAIL-nonNumeric-dangling-continuation-2.xml": // Checked DanglingContinuation
		// case "FAIL-nonNumeric-dangling-continuation.xml": // TODO: Check this in nonNumeric Dangling
		// case "FAIL-orphaned-continuation.xml": // UnreferencedContinuation
		// case "PASS-nonNumeric-continuation-multiple-documents.xml":
		// case "PASS-nonNumeric-continuation-other-descendants-escaped.xml":
		// case "PASS-nonNumeric-continuation-other-descendants.xml":
		// case "PASS-nonNumeric-continuation-out-of-order.xml":
		// case "PASS-nonNumeric-continuation-transform.xml":
		// case "PASS-nonNumeric-continuation.xml":
		#endregion
			return;

		#region ./exclude
		case "FAIL-exclude-nonFraction-parent.xml":
		case "FAIL-misplaced-exclude.xml":
		case "PASS-element-ix-exclude-complete.xml":
		case "PASS-exclude-nonNumeric-parent.xml":
		case "PASS-multiple-excludes-nonNumeric-parent.xml":
		#endregion
			return;

		#region ./footnotes
		case "FAIL-element-ix-footnote-04.xml":
		case "FAIL-footnote-any-attribute.xml":
		case "FAIL-footnote-dangling-continuation.xml":
		case "FAIL-footnote-dangling-fromRef.xml":
		case "FAIL-footnote-dangling-toRef.xml":
		case "FAIL-footnote-duplicate-footnoteIDs-different-input-docs.xml":
		case "FAIL-footnote-duplicate-footnoteIDs.xml":
		case "FAIL-footnote-invalid-element-content.xml":
		case "FAIL-footnote-missing-footnoteID.xml":
		case "PASS-element-ix-footnote-03.xml":
		case "PASS-element-link-footnote-02.xml":
		case "PASS-element-link-footnote-complete-role-defs.xml":
		case "PASS-element-link-footnote-complete.xml":
		case "PASS-element-link-footnote-footnoteArcrole-2.xml":
		case "PASS-element-link-footnote-footnoteArcrole.xml":
		case "PASS-element-link-footnote-footnoteLinkRole-2.xml":
		case "PASS-element-link-footnote-footnoteLinkRole.xml":
		case "PASS-element-link-footnote-footnoteRole-2.xml":
		case "PASS-element-link-footnote-footnoteRole.xml":
		case "PASS-element-link-footnote-nonNumeric-escaped.xml":
		case "PASS-element-link-footnote-nonNumeric-unescaped.xml":
		case "PASS-element-link-footnote-nothidden.xml":
		case "PASS-element-link-footnote-resolved-uris.xml":
		case "PASS-element-link-footnote-xhtml-content-exclude.xml":
		case "PASS-element-link-footnote-xhtml-content.xml":
		case "PASS-elements-footnote-and-nonNumeric-unresolvable-uris-in-exclude.xml":
		case "PASS-footnote-any-attribute.xml":
		case "PASS-footnote-continuation.xml":
		case "PASS-footnote-footnoteLinkRole-multiple-output.xml":
		case "PASS-footnote-footnoteRole-multiple-output.xml":
		case "PASS-footnote-ix-element-content.xml":
		case "PASS-footnote-ix-exclude-content.xml":
		case "PASS-footnote-nested-ix-element-content.xml":
		case "PASS-footnote-nested-xml-base-decls.xml":
		case "PASS-footnote-on-nonFraction.xml":
		case "PASS-footnote-order-attribute.xml":
		case "PASS-footnote-relative-uris-object-tag.xml":
		case "PASS-footnote-uris-with-spaces.xml":
		case "PASS-footnote-valid-element-content.xml":
		case "PASS-footnote-within-footnote.xml":
		case "PASS-footnote-xml-base-xhtml-base-no-interaction.xml":
		case "PASS-footnoteArcrole-multiple-output.xml":
		case "PASS-footnoteRef-on-fraction.xml":
		case "PASS-footnoteRef-on-nonNumeric.xml":
		case "PASS-footnoteRef-on-tuple.xml":
		case "PASS-many-to-one-footnote-complete.xml":
		case "PASS-many-to-one-footnote-different-arcroles.xml":
		case "PASS-many-to-one-footnotes-multiple-outputs.xml":
		case "PASS-multiple-outputs-check-dont-have-empty-footnoteLinks.xml":
		case "PASS-two-footnotes-multiple-output.xml":
		case "PASS-unused-footnote.xml":
		#endregion
			return;

		#region ./format
		case "FAIL-format-numdash-badContent.xml":
		case "FAIL-ix-format-undefined.xml":
		case "PASS-element-ix-nonFraction-ixt-num-nodecimals.xml":
		case "PASS-format-numdash.xml":
		#endregion
			return;

		#region ./fraction
		case "FAIL-fraction-denominator-empty.xml":
		case "FAIL-fraction-denominator-illegal-child-node.xml":
		case "FAIL-fraction-denominator-ix-format-expanded-name-mismatch.xml":
		case "FAIL-fraction-denominator-ix-format-invalid.xml":
		case "FAIL-fraction-denominator-ix-sign-invalid.xml":
		case "FAIL-fraction-illegal-content.xml":
		case "FAIL-fraction-illegal-nesting-unitRef.xml":
		case "FAIL-fraction-illegal-nesting-xsi-nil-2.xml":
		case "FAIL-fraction-illegal-nesting-xsi-nil.xml":
		case "FAIL-fraction-illegal-nesting.xml":
		case "FAIL-fraction-ix-any-attribute.xml":
		case "FAIL-fraction-ix-contextRef-unresolvable.xml":
		case "FAIL-fraction-ix-footnoteRef-unresolvable.xml":
		case "FAIL-fraction-ix-tupleRef-attr-tuple-missing.xml":
		case "FAIL-fraction-ix-unitRef-unresolvable.xml":
		case "FAIL-fraction-missing-contextRef.xml":
		case "FAIL-fraction-missing-denominator.xml":
		case "FAIL-fraction-missing-numerator-and-denominator.xml":
		case "FAIL-fraction-missing-numerator.xml":
		case "FAIL-fraction-missing-unitRef.xml":
		case "FAIL-fraction-multiple-denominators.xml":
		case "FAIL-fraction-multiple-numerators.xml":
		case "FAIL-fraction-no-format-negative-number.xml":
		case "FAIL-fraction-numerator-denominator-non-xsi-attributes.xml":
		case "FAIL-fraction-numerator-empty.xml":
		case "FAIL-fraction-numerator-illegal-child-node.xml":
		case "FAIL-fraction-numerator-ix-format-expanded-name-mismatch.xml":
		case "FAIL-fraction-numerator-ix-format-invalid.xml":
		case "FAIL-fraction-numerator-ix-sign-invalid.xml":
		case "FAIL-fraction-rule-no-other-ixDescendants.xml":
		case "FAIL-fraction-rule-no-xbrli-attributes.xml":
		case "PASS-attribute-ix-format-denominator-01.xml":
		case "PASS-attribute-ix-format-numerator-01.xml":
		case "PASS-attribute-ix-name-fraction-01.xml":
		case "PASS-attribute-ix-scale-denominator-01.xml":
		case "PASS-attribute-ix-scale-denominator-04.xml":
		case "PASS-attribute-ix-scale-numerator-01.xml":
		case "PASS-attribute-ix-scale-numerator-04.xml":
		case "PASS-attribute-ix-sign-denominator-01.xml":
		case "PASS-attribute-ix-sign-numerator-01.xml":
		case "PASS-fraction-denominator-ix-format-expanded-name-match.xml":
		case "PASS-fraction-denominator-ix-format.xml":
		case "PASS-fraction-denominator-ix-sign-scale-valid.xml":
		case "PASS-fraction-ix-order-attr.xml":
		case "PASS-fraction-ix-target-attr.xml":
		case "PASS-fraction-ix-tupleRef-attr.xml":
		case "PASS-fraction-nesting-2.xml":
		case "PASS-fraction-nesting-3.xml":
		case "PASS-fraction-nesting-4.xml":
		case "PASS-fraction-nesting.xml":
		case "PASS-fraction-non-ix-any-attribute.xml":
		case "PASS-fraction-numerator-denominator-xsi-attributes.xml":
		case "PASS-fraction-numerator-ix-format-expanded-name-match.xml":
		case "PASS-fraction-numerator-ix-format.xml":
		case "PASS-fraction-numerator-ix-sign-valid.xml":
		case "PASS-fraction-xsi-nil.xml":
		case "PASS-ix-denominator-01.xml":
		case "PASS-ix-denominator-02.xml":
		case "PASS-ix-denominator-03.xml":
		case "PASS-ix-denominator-04.xml":
		case "PASS-ix-numerator-04.xml":
		case "PASS-simple-fraction-with-html-children.xml":
		case "PASS-simple-fraction.xml":
		#endregion
			return;

		#region ./fullSizeTests
		case "PASS-full-size-unnested-tuples.xml":
		case "PASS-full-size-with-footnotes.xml":
		case "PASS-largeTestNoMarkup.xml":
		#endregion
			return;

		#region ./header
		// case "FAIL-ix-header-child-of-html-header.xml": // Checked
		// case "FAIL-misplaced-ix-element-in-context.xml": // TODO There are two errors which are ix elements in the context hierarchyies MisplacedIXElement,MisplacedIXElement
		// case "FAIL-missing-header.xml": // Checked HeaderAbsent, ReferencesAbsent, ResourcesAbsent
		case "PASS-header-content-split-over-input-docs.xml":
		case "PASS-header-empty.xml":
		case "PASS-single-ix-header-muli-input.xml":
		#endregion
			return;

		#region ./hidden
		case "FAIL-empty-hidden.xml": // xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_b 1871
		case "FAIL-hidden-empty-tuple-content.xml":
		case "FAIL-hidden-illegal-content.xml":
		case "FAIL-hidden-incorrect-order-in-header.xml":
		case "FAIL-hidden-not-header-descendant.xml":
		case "PASS-hidden-nonFraction-content.xml":
		case "PASS-hidden-tuple-content.xml":
		#endregion
			break;

		#region ./html
		case "FAIL-a-name-attribute.xml":
		case "FAIL-charset-on-meta.xml":
		case "FAIL-empty-class-attribute.xml":
		#endregion
			return;

		#region ./ids
		case "FAIL-id-triplication.xml":
		case "FAIL-non-unique-id-context.xml":
		case "FAIL-non-unique-id-footnote.xml":
		case "FAIL-non-unique-id-fraction.xml":
		case "FAIL-non-unique-id-nonFraction.xml":
		case "FAIL-non-unique-id-nonNumeric.xml":
		case "FAIL-non-unique-id-references.xml":
		case "FAIL-non-unique-id-tuple.xml":
		case "FAIL-non-unique-id-unit.xml":
		#endregion
			return;

		#region ./multiIO
		case "FAIL-multi-input-duplicate-context-ids.xml":
		case "FAIL-multi-input-duplicate-unit-ids.xml":
		case "FAIL-two-inputs-each-with-error.xml":
		case "FAIL-two-nonIXBRL-inputs.xml":
		case "PASS-double-input-single-output.xml":
		case "PASS-ix-references-06.xml":
		case "PASS-ix-references-07.xml":
		case "PASS-multiple-input-multiple-output.xml":
		case "PASS-single-input-double-output.xml":
		case "PASS-single-input.xml":
		#endregion
			return;

		#region ./nonFraction
		case "FAIL-nonFraction-IXBRLelement-content.xml":
		case "FAIL-nonFraction-any-ix-attribute.xml":
		case "FAIL-nonFraction-decimals-and-precision-attrs.xml":
		case "FAIL-nonFraction-double-nesting.xml":
		case "FAIL-nonFraction-empty-content.xml":
		case "FAIL-nonFraction-empty-without-xsi-nil.xml":
		case "FAIL-nonFraction-invalid-sign-attr.xml":
		case "FAIL-nonFraction-ix-format-expanded-name-mismatch.xml":
		case "FAIL-nonFraction-ix-format-invalid-minus-sign.xml":
		case "FAIL-nonFraction-ix-format-invalid.xml":
		case "FAIL-nonFraction-missing-context-attr.xml":
		case "FAIL-nonFraction-missing-name-attr.xml":
		case "FAIL-nonFraction-missing-unit-attr.xml":
		case "FAIL-nonFraction-mixed-nesting-2.xml":
		case "FAIL-nonFraction-mixed-nesting-3.xml":
		case "FAIL-nonFraction-mixed-nesting.xml":
		case "FAIL-nonFraction-neither-decimals-nor-precision-attrs.xml":
		case "FAIL-nonFraction-nesting-format-mismatch-2.xml":
		case "FAIL-nonFraction-nesting-format-mismatch.xml":
		case "FAIL-nonFraction-nesting-scale-mismatch-2.xml":
		case "FAIL-nonFraction-nesting-scale-mismatch.xml":
		case "FAIL-nonFraction-nesting-unitRef-mismatch.xml":
		case "FAIL-nonFraction-nesting-xsi-nil.xml":
		case "FAIL-nonFraction-nil-attr-false.xml":
		case "FAIL-nonFraction-nil-decimal-conflict.xml":
		case "FAIL-nonFraction-no-format-negative-number.xml":
		case "FAIL-nonFraction-unresolvable-ix-tupleRef-attr.xml":
		case "FAIL-nonfraction-rule-no-xbrli-attributes.xml":
		case "FAIL-unresolvable-contextRef.xml":
		case "FAIL-unresolvable-unitRef.xml":
		case "PASS-attribute-ix-format-nonFraction-01.xml":
		case "PASS-attribute-ix-name-nonFraction-01.xml":
		case "PASS-attribute-ix-scale-nonFraction-01.xml":
		case "PASS-attribute-ix-scale-nonFraction-04.xml":
		case "PASS-attribute-ix-sign-nonFraction-01.xml":
		case "PASS-element-ix-nonFraction-complete.xml":
		case "PASS-element-ix-nonFraction-ixt-numcomma.xml":
		case "PASS-element-ix-nonFraction-ixt-numcommadot.xml":
		case "PASS-element-ix-nonFraction-ixt-numdash.xml":
		case "PASS-element-ix-nonFraction-ixt-numdotcomma.xml":
		case "PASS-element-ix-nonFraction-ixt-numspacecomma.xml":
		case "PASS-element-ix-nonFraction-ixt-numspacedot.xml":
		case "PASS-nonFraction-any-attribute.xml":
		case "PASS-nonFraction-comments.xml":
		case "PASS-nonFraction-decimals-attr.xml":
		case "PASS-nonFraction-ix-format-expanded-name-match.xml":
		case "PASS-nonFraction-ix-format-valid.xml":
		case "PASS-nonFraction-ix-order-attr.xml":
		case "PASS-nonFraction-ix-target-attr.xml":
		case "PASS-nonFraction-ix-tupleRef-attr.xml":
		case "PASS-nonFraction-nesting-2.xml":
		case "PASS-nonFraction-nesting-formats-2.xml":
		case "PASS-nonFraction-nesting-formats.xml":
		case "PASS-nonFraction-nesting-scale.xml":
		case "PASS-nonFraction-nesting.xml":
		case "PASS-nonFraction-precision-attr.xml":
		case "PASS-nonFraction-processing-instructions.xml":
		case "PASS-nonFraction-valid-scale-attr.xml":
		case "PASS-nonFraction-valid-sign-attr.xml":
		case "PASS-nonFraction-valid-sign-format-attr.xml":
		case "PASS-nonFraction-xsi-nil-attr.xml":
		case "PASS-simple-nonFraction.xml":
		#endregion
			return;

		#region ./nonNumeric
		case "FAIL-element-ix-nonNumeric-escape-01.xml":
		case "FAIL-nonNumeric-any-ix-attribute.xml":
		case "FAIL-nonNumeric-empty-with-format.xml":
		case "FAIL-nonNumeric-illegal-null-namespace-attr.xml":
		case "FAIL-nonNumeric-invalid-ix-format-attr.xml":
		case "FAIL-nonNumeric-ix-format-attr-wrong-namespace-binding.xml":
		case "FAIL-nonNumeric-missing-contextRef-attr.xml":
		case "FAIL-nonNumeric-missing-name-attr.xml":
		case "FAIL-nonNumeric-no-xbrli-attributes.xml":
		case "FAIL-nonNumeric-unresolvable-ix-contextRef.xml":
		case "FAIL-nonNumeric-unresolvable-ix-tupleRef-attr.xml":
		case "PASS-attribute-ix-extension-illegalPlacement-01.xml":
		case "PASS-attribute-ix-name-nonNumeric-01.xml":
		case "PASS-element-ix-nonNumeric-complete.xml":
		case "PASS-element-ix-nonNumeric-escape-02.xml":
		case "PASS-element-ix-nonNumeric-escape-03.xml":
		case "PASS-element-ix-nonNumeric-escape-04.xml":
		case "PASS-element-ix-nonNumeric-escape-05.xml":
		case "PASS-element-ix-nonNumeric-escape-06.xml":
		case "PASS-element-ix-nonNumeric-escape-07.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedoteu-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedotus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedotus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedotus-03.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongdaymonthuk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongmonthdayus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongmonthyear-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelonguk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelonguk-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongyearmonth-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortdaymonthuk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortmonthdayus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortmonthyear-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortuk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortuk-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortyearmonth-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashdaymontheu-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslasheu-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslasheu-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslasheu-03.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashmonthdayus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-03.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-04.xml":
		case "PASS-element-ordering.xml":
		case "PASS-nonNumeric-any-attribute.xml":
		case "PASS-nonNumeric-empty-not-xsi-nil.xml":
		case "PASS-nonNumeric-escape-with-html-base.xml":
		case "PASS-nonNumeric-ix-format-attr-expanded-name-match.xml":
		case "PASS-nonNumeric-ix-format-attr.xml":
		case "PASS-nonNumeric-ix-order-attr.xml":
		case "PASS-nonNumeric-ix-target-attr.xml":
		case "PASS-nonNumeric-ix-tupleRef-attr.xml":
		case "PASS-nonNumeric-nesting-in-exclude.xml":
		case "PASS-nonNumeric-nesting-numerator.xml":
		case "PASS-nonNumeric-nesting-text-in-exclude.xml":
		case "PASS-nonNumeric-nesting.xml":
		case "PASS-nonNumeric-xsi-nil.xml":
		case "PASS-nonNumeric.xml":
		#endregion
			return;

		#region ./references
		case "FAIL-empty-references.xml":
		case "FAIL-ix-references-03.xml":
		case "FAIL-ix-references-08.xml":
		case "FAIL-ix-references-09.xml":
		case "FAIL-ix-references-namespace-bindings-01.xml":
		case "FAIL-ix-references-namespace-bindings-02.xml":
		case "FAIL-ix-references-namespace-bindings-03.xml":
		case "FAIL-ix-references-namespace-bindings-04.xml":
		case "FAIL-ix-references-rule-multiple-attributes-sameValue.xml":
		case "FAIL-ix-references-rule-multiple-id.xml":
		case "FAIL-missing-references-for-all-target-documents.xml":
		case "FAIL-missing-references.xml":
		case "FAIL-references-illegal-content.xml":
		case "FAIL-references-illegal-location.xml":
		case "FAIL-references-illegal-order-in-header.xml":
		case "PASS-element-ix-references-01.xml":
		case "PASS-ix-references-01.xml":
		case "PASS-ix-references-02.xml":
		case "PASS-ix-references-04.xml":
		case "PASS-ix-references-05.xml":
		case "PASS-ix-references-rule-multiple-matched-target.xml":
		case "PASS-ix-references-rule-multiple-xmlBase.xml":
		case "PASS-references-copy-non-ix-attrs.xml":
		case "PASS-references-ix-target-attr.xml":
		case "PASS-simple-linkbaseRef.xml":
		case "PASS-simple-schemaRef.xml":
		case "PASS-single-references-multi-input.xml":
		#endregion
			return;

		#region ./relationships
		case "FAIL-relationship-cross-duplication.xml":
		case "FAIL-relationship-mixes-footnote-with-explanatory-fact.xml":
		case "FAIL-relationship-with-no-namespace-attribute.xml":
		case "FAIL-relationship-with-xbrli-attribute.xml":
		case "FAIL-relationship-with-xlink-attribute.xml":
		case "PASS-explanatory-fact-copy-to-owner-target.xml":
		case "PASS-explanatory-fact-cycle.xml":
		case "PASS-explanatory-fact-not-hidden.xml":
		case "PASS-explanatory-fact.xml":
		case "PASS-relationship-to-multiple-explanatory-facts-multiple-outputs.xml":
		case "PASS-relationship-to-multiple-explanatory-facts.xml":
		case "PASS-relationship-with-xml-base.xml":
		case "PASS-tuple-footnotes.xml":
		#endregion
			return;

		#region ./resources
		case "FAIL-context-without-id.xml":
		case "FAIL-missing-resources.xml":
		case "FAIL-unit-without-id.xml":
		case "PASS-empty-resources.xml":
		case "PASS-simple-arcroleRef.xml":
		case "PASS-simple-roleRef.xml":
		#endregion
			return;

		#region ./specificationExamples
		case "PASS-section-10.3-example-1.xml":
		case "PASS-section-11.3-example-2.xml":
		case "PASS-section-15.1-example-3.xml":
		case "PASS-section-15.1-example-4.xml":
		#endregion
			return;

		#region ./transformations
		case "FAIL-invalid-long-month.xml":
		case "FAIL-invalid-short-month.xml":
		case "FAIL-unrecognised-schema-type.xml":
		case "PASS-sign-attribute-on-nonFraction-positive-input.xml":
		#endregion
			return;

		#region ./tuple
		case "FAIL-badly-formatted-order-attr.xml":
		case "FAIL-badly-nested-tuples.xml":
		case "FAIL-duplicate-order-and-value-but-not-attributes.xml":
		case "FAIL-duplicate-tuple-id-different-input-docs.xml":
		case "FAIL-duplicate-tuple-id.xml":
		case "FAIL-duplicate-tuple-order-different-values.xml":
		case "FAIL-illegal-element-nested.xml":
		case "FAIL-illegal-element.xml":
		case "FAIL-missing-descendants.xml":
		case "FAIL-nested-tuple-empty.xml":
		case "FAIL-order-attr-denominator.xml":
		case "FAIL-order-attr-inNonTuple.xml":
		case "FAIL-order-attr-numerator.xml":
		case "FAIL-ordering-order-duplicate-stringUnequal.xml":
		case "FAIL-ordering-order-duplicate.xml":
		case "FAIL-ordering-partially-missing.xml":
		case "FAIL-orphaned-tuple-content.xml":
		case "FAIL-tuple-any-ix-attribute.xml":
		case "FAIL-tuple-content-in-different-targets-tuple-not-in-default.xml":
		case "FAIL-tuple-content-in-different-targets.xml":
		case "FAIL-tuple-cycle-by-tupleRef.xml":
		case "FAIL-tuple-cycle-child.xml":
		case "FAIL-tuple-cycle-grandchildren.xml":
		case "FAIL-tuple-empty-no-ix-tupleID.xml":
		case "FAIL-tuple-empty.xml":
		case "FAIL-tuple-missing-name-attr.xml":
		case "FAIL-tuple-no-xbrli-attributes.xml":
		case "FAIL-tuple-unresolvable-footnoteRef-attr.xml":
		case "FAIL-tuple-xsi-nil-with-tuple-ref.xml":
		case "PASS-attribute-ix-name-tuple-01.xml":
		case "PASS-duplicate-order-same-ws-normalized-value-with-html.xml":
		case "PASS-duplicate-order-same-ws-normalized-value.xml":
		case "PASS-element-ix-tuple-complete.xml":
		case "PASS-element-tuple-reference-multiInput.xml":
		case "PASS-element-tuple-reference.xml":
		case "PASS-exotic-tuple-order.xml":
		case "PASS-nested-tuple-ix-order-no-tupleRef.xml":
		case "PASS-nested-tuple-nonEmpty.xml":
		case "PASS-nested-tuple.xml":
		case "PASS-nonFraction-nesting-reference-conflict.xml":
		case "PASS-ordering-references-nesting-order.xml":
		case "PASS-singleton-tuple.xml":
		case "PASS-tuple-all-content-nested-noTupleID.xml":
		case "PASS-tuple-any-attribute.xml":
		case "PASS-tuple-ix-target-attr.xml":
		case "PASS-tuple-nested-nonNumeric.xml":
		case "PASS-tuple-nesting-reference-conflict.xml":
		case "PASS-tuple-nonInteger-ordering-nested.xml":
		case "PASS-tuple-ordering-nested.xml":
		case "PASS-tuple-scope-inverted-siblings.xml":
		case "PASS-tuple-scope-inverted.xml":
		case "PASS-tuple-scope-nested-nonNumeric.xml":
		case "PASS-tuple-scope-nonNumeric.xml":
		case "PASS-tuple-xsi-nil.xml":
		#endregion
			return;

		#region ./xmllang
		case "FAIL-xml-lang-not-in-scope-for-footnote.xml":
		case "FAIL-xml-lang-on-ix-hidden-and-on-footnote.xml":
		case "FAIL-xml-lang-on-ix-hidden.xml":
		case "PASS-direct-xml-lang-not-overidden.xml":
		case "PASS-xml-lang-on-xhtml.xml":
		#endregion
				return;

		default:
			return;
	}

	$testDoc = new \DOMDocument();
	if ( ! $testDoc->load( "$dirname$filename" ) )
	{
		throw new \IXBRLException('Failed to load the test case document: $filename');
	}

	$documentElement = $testDoc->documentElement;
	$xpath = new \DOMXPath( $testDoc );
	$xpath->registerNamespace('tc', 'http://xbrl.org/2008/conformance' );

	/**
	 * Get the text for an element
	 * @param string $elementName
	 * @param string $node
	 * @return string
	 */
	$getElementText = function( $elementName, $node ) use( $xpath )
	{
		/** @var \DOMNodeList $elements */
		$elements = $xpath->query( $elementName, $node );
		return count( $elements )
			? $number = $elements[0]->textContent
			: '';
	};

	/**
	 * Get an array of text content for an element
	 * @param string $elementName
	 * @param string $node
	 * @return array
	 */
	$getTextArray = function( $elementName, $node ) use( $xpath )
	{
		$elements = array();
		foreach( $xpath->query( $elementName, $node ) as $element )
		{
			$elements[] = $element->textContent;
		}
		return $elements;
	};

	$number = $getElementText('tc:number', $documentElement );
	$name = $getElementText('tc:name', $documentElement );

	foreach( $xpath->query( 'tc:variation', $documentElement ) as $tag => $variation )
	{
		/** @var \DOMElement $variation */
		$id = $variation->getAttribute(IXBRL_ATTR_ID);
		$description = $getElementText('tc:description', $variation );

		$firstInstances = $getTextArray( 'tc:data/tc:instance[@readMeFirst="true"]', $variation );
		$otherInstances = array_diff( $getTextArray( 'tc:data/tc:instance', $variation ), $firstInstances );
		$result = $xpath->query( 'tc:result', $variation )[0];
		$expected = $result->getAttribute('expected');
		$standard = ! boolval( $result->getAttribute('nonStandardErrorCodes') );
		if ( $expected == 'valid' )
		{
			$resultInstances = $getTextArray( 'tc:instance', $result );
		}
		else
		{
			$errors = $getTextArray( 'tc:error', $result );
			$extras = array();
			foreach( $errors as $error )
			{
				switch( $error )
				{
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2':
						$extras[] = '1866';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_b':
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a':
						$extras[] = '1871';
						break;
				}
			}
	
			$errors = array_merge( $errors, $extras );
		}

		// For now ignore negative tests
		if ( ! $errors ) return;

		$message = "($id) $filename - $description ";
		$message .= " ($expected" . ( $errors ? ": " . join( ',', $errors ) : "" ) . ")";
		error_log( $message );
		echo( "$message\n" );

		// True if the test result agrees with the expected result
		$success = false;

		try
		{
			$documentSet = array_map( function( $document ) use( $dirname ) 
			{
				return \XBRL::resolve_path( $dirname, $document );
			}, array_merge( $firstInstances, $otherInstances ) );

			\XBRL_Inline::createInstanceDocument( $documentSet );
			if ( $expected == 'invalid' )
			{
				error_log( "The test result (valid) does not match the expected result (invalid)" );
				$error = join( ',', $errors );
				error_log( "The expected error is ($error)" );
				return;
			}

			$success = true;
		}
		catch( IXBRLSchemaValidationException $ex )
		{
			$validator = $ex->getValidator();
			if ( $expected == 'invalid' )
			{
				if ( $validator->hasErrorCode( $errors ) )
				{
					echo join( ', ', $errors ) . "\n";
					$success = true;
				}
			}

			if ( ! $success )
			{
				if ( $expected == 'valid' )
					error_log( "The test result (invalid) does not match the expected result (valid)" );
				else
				{
					$error = join( ',', $errors );
					error_log( "The test result error does not match the expected error ($error)" );
				}

				$validator->displayErrors();
			}

		}
		catch( IXBRLDocumentValidationException $ex )
		{
			if ( $expected == 'valid' )
			{
				error_log( "The test result (invalid) does not match the expected result (valid)" );
			}
			else if ( array_search( $ex->getErrorCode(), $errors ) === false )
			{
				error_log( "The test result error does not match the expected error ($error)" );
			}
			else
			{
				echo $ex->getErrorCode() . "\n";
				$success = true;
			}

			if ( ! $success )
			{
				echo $ex;
			}
		}
		catch( IXBRLException $ex ) 
		{
			echo $ex;
		}
		catch( Exception $ex )
		{
			echo $ex;
		}

	}

}

