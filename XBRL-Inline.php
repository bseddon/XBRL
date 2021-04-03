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

use lyquidity\ixt\IXBRL_Transforms;

require __DIR__. '/IXBRL-Transforms.php';
require __DIR__. '/IXBRL-Tests.php';

#region iXBRL Elements

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
define( "IXBRL_ATTR_NIL", "nil" );
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
			/** @var \XBRL_Inline[] */
			$documents = [];
			foreach( $documentSet as $documentUrl )
			{
				// Use the entity loader to make sure libxml uses files from the local.
				// This is an order of magnitude faster.
				$context->setEntityLoader( dirname( $documentUrl ) );

				$document = new XBRL_Inline( $documentUrl );
				$documents[ $documentUrl ] = $document;
				$document->validateDocument();  // Schema validation
				// Special case: test case FAIL-empty-class-attribute.html does not fail as expected
				if ( ( $document->xpath->query('//*[@*=""]') )->length )
				{
					throw new IXBRLDocumentValidationException( 'xbrl.core.xml.SchemaValidationError.cvc-minLength-valid', 'Failed using schemaValidate' );				
				}

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

			// Get and validate the ix:header and other elements
			if ( $validate )
			{
				foreach( $nodesByLocalNames as $localName => $nodes )
				{
					foreach( $nodes as $node )
					{
						/** @var \DOMElement $node */
						self::validateConstraints( $node, $localName, $nodesByLocalNames, $documents, $idNodes );
					}
				}
			}

			// Check cross-element contination validation rules
			// Make sure continuedAt attributes don't reference the same id
			$correspondents = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_CONTINUATION );
			$atIds = array();
			// Get the nodes for continuation, footnote and nonNumeric elements
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

			// Check cross-references validation rules (12.1.2)
			if (  $nodesByLocalNames[ IXBRL_ELEMENT_REFERENCES ] ?? false )
			{
				// Each attribute value should be unique across all <references> for attribute not in the IX namespace
				$attributes = array();
				$ixNamespaces = array( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL10], \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL11] );
				foreach( $nodesByLocalNames[ IXBRL_ELEMENT_REFERENCES ] as $node )
				{
					foreach( $node->attributes as $name => $attr )
					{
						/** @var \DOMAttr $attr */
						if ( array_search( $attr->namespaceURI, $ixNamespaces ) !== false ) continue;
						if ( isset( $attributes[ $name ] ) && $attr->nodeValue )
						{
							throw new IXBRLDocumentValidationException( $name == 'id' ? 'RepeatedIdAttribute' : 'RepeatedOtherAttributes', "Section 12.1.2 A <references> attribute/value pair has been used more than once ($name/{$attr->nodeValue}" );
						}
						$attributes[ $name ] = $attr->nodeValue;
					}
				}

				// Each target must have a <references>
				foreach( $targets as $target => $nodes )
				{
					// Only one can have an id attribute
					$idFound = array();
					$referencesFound = false;
					$defaultNamespace = null;
					foreach( $nodes as $targetNode )
					{
						/** @var \DOMElement $targetNode */
						if ( $targetNode->localName != IXBRL_ELEMENT_REFERENCES ) continue;
						$referencesFound - true;
						if ( $targetNode->hasAttribute(IXBRL_ATTR_ID) )
						{
							if ( $idFound )
								throw new IXBRLDocumentValidationException( 'MoreThanOneID', "Section 12.1.2 Only one <references> element for a target can include an id attribute.  Found '" . join( "','", $idFound ) . "'" );
							$idFound[] = $targetNode->getAttribute(IXBRL_ATTR_ID);
						}
						$document = $documents[ $targetNode->baseURI ];
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
						else if ( $defaultNamespace )
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
		return array_reduce(
			iterator_to_array( $node->childNodes ),
			function ( $carry, \DOMNode $child )
			{
				return $carry.$child->ownerDocument->saveHTML( $child );
			}
		 );
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
	public static function validateFormat( &$node, $localName, &$nodesByLocalNames, &$documents, &$idNodes )
	{
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
	private static function checkParentNodes( \DOMElement $node, \Closure $test )
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
	private static function checkAttributeConsistency( \DOMElement $node, string $attrName, string $attrValue, string $section, string $localName, $errorCode = 'NonFractionNestedAttributeMismatch' ) 
	{
		return self::checkParentNodes( $node, function( \DOMElement $parentNode ) use( $attrName, $attrValue, $errorCode, $localName, $section )
		{
			if ( $parentNode->localName != $localName ) return false;
			$parentAttrValue = $parentNode->getAttribute( $attrName );
			if ( $parentAttrValue == $attrValue ) return false;
			{
				throw new IXBRLDocumentValidationException( $errorCode, "Section $section Attribute @'$attrName' should be consistent in nested <$localName>" );
			}
			return true; // End here.  Any other parents will be checked when the parent is validated.
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
		$unitRef = $node->getAttribute( IXBRL_ATTR_UNITREF );
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
	 * @return void
	 */
	private static function checkTupleRef( $node, $section, $localName )
	{
		$tupleRef = $node->getAttribute( IXBRL_ATTR_TUPLEREF );
		if ( $tupleRef )
		{
			$tupleNode = $idNodes[ $tupleRef ] ?? null;
			if ( ! $tupleNode )
			{
				throw new IXBRLDocumentValidationException( 'UnknownTuple', "Section $section The tuple with id '$tupleRef' used in <$localName> does not exist " );
			}
		}
	}

	/**
	 * Check the content and format is valid
	 * @param \DOMElement $node
	 * @param string $section
	 * @param string $localName
	 * @param XBRL_Inline $document
	 * @return void
	 */
	private static function checkFormat( $node, $section, $localName, $document )
	{
		$nil = $node->getAttribute( 'xsi:' . IXBRL_ATTR_NIL );				
		$escape = $node->getAttribute( IXBRL_ATTR_ESCAPE );
		$format = $node->getAttribute( IXBRL_ATTR_FORMAT );
		self::checkAttributeConsistency( $node, IXBRL_ATTR_FORMAT, $format, $section, $localName );

		$content = $escape 
			? self::innerHTML( $node )
			: $node->textContent;

		// The content my be empty but there may be a nested node
		if ( ! $content && ! ( $node->childNodes->length || filter_var( $nil, FILTER_VALIDATE_BOOLEAN ) ) )
		{
			throw new IXBRLDocumentValidationException( 'FormatUndefined', 'Content empty in <$localName>' );
		}

		if ( $format )
		{
			try
			{
				$result = $document->format( $format, $content, $node, true );
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
			if ( ! is_numeric( $content ) || floatval( $content ) < 0 )
			{
				throw new IXBRLDocumentValidationException( 'FormatAbsentNegativeNumber', '@format is missing in <$localName> so the content must be a positive number' );
			}
		}
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
		$nil = $node->getAttribute( 'xsi:' . IXBRL_ATTR_NIL );
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
			if ( ( ! $decimals && ! $precision ) )
			{
				throw new IXBRLDocumentValidationException( 'PrecisionAndDecimalsAbsent', "Section $section Decimals or precision must present on <$localName> but neither exist." );
			}

			if ( ( $decimals && $precision  ) )
			{
				throw new IXBRLDocumentValidationException( 'PrecisionAndDecimalsPresent', "Section $section Decimals or precision must present on <$localName> but both exist." );
			}

			if ( $node->childNodes->length == 0 )
			{
				throw new IXBRLDocumentValidationException( 'NonFractionIncompleteContent', "Section $section <$localName> facts that are not nil should have exactly one child" );
			}

			if ( $node->childNodes->length > 1 )
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
	 * @param string $continuedAt
	 * @param \DOMElement[] $idNodes
	 * @return void
	 * @throws IXBRLDocumentValidationException
	 */
	private static function checkContinuationCycles( $node, $section, $continuedAt, &$idNodes )
	{
		if ( ! $continuedAt ) return;


		if ( ! isset( $idNodes[ $continuedAt ] ) )
		{
			throw new IXBRLDocumentValidationException( 'DanglingContinuation', "Section $section continuedAt there is no id with 'continuedAt' attribute value: $continuedAt" );
		}

		if ( $node->getAttribute( IXBRL_ATTR_ID ) == $continuedAt )
		{
			throw new IXBRLDocumentValidationException( 'UnreferencedContinuation', "Section $section The continuedAt and id attribute values are the same: '" . $node->getNodePath() . "'" );
		}

		// Look for circular references
		$visited = array( $node->getNodePath() );
		while( true )
		{
			$nextNode = $idNodes[ $continuedAt ];
			if ( ! $nextNode->hasAttribute(IXBRL_ATTR_CONTINUEDAT) ) break;
			$path = $nextNode->getNodePath();
			$exists = array_search( $path, $visited ) !== false;
			$visited[] = $path;
			if ( $exists ) 
			{
				throw new IXBRLDocumentValidationException( 'DanglingContinuation', 'Section $section continuedAt attribute value forms circular reference: \'' . join( "' -> '", $visited ) . '\'' );
			}
			$continuedAt = $nextNode->getAttribute( IXBRL_ATTR_CONTINUEDAT );
		}
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
			$exists = array_search( $path, $visited ) !== false;
			$visited[] = $path;
			if ( $exists ) 
			{
				throw new IXBRLDocumentValidationException( 'TupleCycle', 'Section $section @tupleRef attribute value forms circular reference: \'' . join( "' -> '", $visited ) . '\'' );
			}
			$tupleRef = $nextNode->getAttribute( IXBRL_ATTR_TUPLEREF );
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
		$correspondents = array( IXBRL_ELEMENT_FOOTNOTE, IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_CONTINUATION );
		$section = '';
		$checkUnitRef = true;
		$checkContextRef = true;

		switch( $localName )
		{
			case IXBRL_ELEMENT_CONTINUATION:
				$section = '4.1.1';

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

				if ( ! $foundContinuedAt )
				{
					throw new IXBRLDocumentValidationException( 'UnreferencedContinuation', "Section $section continuedAt there is no id with 'continuedAt' attribute value: $continuedAt" );
				}

				self::checkContinuationCycles( $node, $section, $continuedAt, $idNodes );

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
					self::checkContinuationCycles( $node, $section, $continuedAt, $idNodes );
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
				self::checkFormat( $node, $section, $localName, $document );
				break;

			case IXBRL_ELEMENT_NUMERATOR:
				$section = '7.1.1';
				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkFormat( $node, $section, $localName, $document );
				break;

			case IXBRL_ELEMENT_FRACTION:
				$section = '7.1.1';

				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkUnitRef( $node, $idNodes, $section, $localName, 'FractionNestedAttributeMismatch' );
				self::checkContextRef( $node, $idNodes, $section, $localName );
				self::checkTupleCycles( $node,$section, $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] );

				$nil = $node->getAttribute( 'xsi:' . IXBRL_ATTR_NIL );
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

				self::checkUnitRef( $node, $idNodes, $section, $localName );
				self::checkSign( $node, $section, $localName );
				self::checkScale( $node, $section, $localName );

				if ( self::checkDecimalsPrecision( $node, $section, $localName ) )
				{
					/** @var \DOMNode */
					$childNode = $node->childNodes[0];
					if ( $childNode->nodeType == XML_TEXT_NODE )
					{
						if ( ! $childNode->textContent )
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
				self::checkTupleRef( $node, $section, $localName );
				self::checkFormat( $node, $section, $localName, $document );

				// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
				$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
				self::checkContinuationCycles( $node, $section, $continuedAt, $idNodes );

				break;

			case IXBRL_ELEMENT_NONNUMERIC:

				$section = '11.1.1';

				// No ix attributes should appear
				self::checkIXbrlAttribute( $node, $section, $localName );
				self::checkContextRef( $node, $idNodes, $section, $localName );
				self::checkTupleRef( $node, $section, $localName );
				self::checkFormat( $node, $section, $localName, $document );

				// id must be a reference to a 'continuedAt' attribute value in 'footnote', 'nonNumeric' or 'continuation'
				$continuedAt = $node->getAttribute( IXBRL_ATTR_CONTINUEDAT );
				self::checkContinuationCycles( $node, $section, $continuedAt, $idNodes );

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
				self::checkTupleCycles( $node, $section, $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] );
				self::checkTupleOrders( $node, $section, $nodesByLocalNames );

				break;
		}
	}

	/**
	 * Check the order numbers used in <tuple> are unique
	 *
	 * @param \DOMElement $node
	 * @param string $section
	 * @param \DOMElement[][] $nodesByLocalNames
	 * @return void
	 */
	private static function checkTupleOrders( $node, $section, &$nodesByLocalNames )
	{
		$elements = array( IXBRL_ELEMENT_NONNUMERIC, IXBRL_ELEMENT_NONFRACTION, IXBRL_ELEMENT_FRACTION, IXBRL_ELEMENT_TUPLE );
		// Get the orders for each tuple.  Begin with those of any nested elements
		// $tupleIds = self::getTupleIds( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ], $section );
		$orderNodes = array();
		$childNodes = array();
		foreach( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] as $tupleRef => $tupleNode )
		{
			if ( $tupleNode->hasAttribute( IXBRL_ATTR_TUPLEREF ) )
			{
				$tupleRef = $tupleNode->getAttribute( IXBRL_ATTR_TUPLEREF );
			}
			$nodePath = $tupleNode->getNodePath();
			$depth = substr_count( $nodePath, IXBRL_ELEMENT_TUPLE );
			// Only need to consider ix element as html elements will be valid parts of the layout
			foreach( $nodesByLocalNames as $ixLocalName => $ixNodes )
			{
				foreach( $ixNodes as $ixNode )
				{
					$ixNodePath = $ixNode->getNodePath();
					// ixNode will be a child if it begins with $nodePath (contains is good enough) 
					// but only want immediate children (the children will be processed on their own)
					if ( $ixNodePath == $nodePath || strpos( $ixNodePath, $nodePath ) === false  ) continue;
					if ( substr_count( $ixNodePath, IXBRL_ELEMENT_TUPLE ) != $depth + ( $ixLocalName == IXBRL_ELEMENT_TUPLE ? 1 : 0 ) ) continue;
					if ( array_search( $ixLocalName, $elements ) === false )
					{
						throw new IXBRLDocumentValidationException( 'InvalidTupleChild', "Section $section The <$ixLocalName> is not valid within a tuple: $ixNodePath" );
					}
					$order = $ixNode->getAttribute( IXBRL_ATTR_ORDER );
					if ( isset( $orderNodes[ $tupleRef ][ $order ] ) )
					{
						throw new IXBRLDocumentValidationException( 'OrderDuplicate', "Section $section The order of an element within a tuple must be unique: $ixNodePath" );
					}
					$orderNodes[ $tupleRef ][ $order ] = $ixNode;
					$childNodes[ $tupleRef ][ $ixLocalName ][] = $ixNode;
				}
			}
		}

		foreach( $elements as $ixLocalName )
		{
			foreach( $nodesByLocalNames[ $ixLocalName ] as $ixNode )
			{
				$ixTupleRef = $ixNode->getAttribute( IXBRL_ATTR_TUPLEREF );
				if ( ! $ixTupleRef ) continue;
				$order = $ixNode->getAttribute( IXBRL_ATTR_ORDER );
				if ( isset( $orderNodes[ $tupleRef ][ $order ] ) )
				{
					throw new IXBRLDocumentValidationException( 'OrderDuplicate', "Section $section The order of an element within a tuple must be unique: {$ixNode->getNodePath()}" );
				}
				$orderNodes[ $tupleRef ][ $order ] = $ixNode;
				$childNodes[ $tupleRef ][ $ixLocalName ][] = $ixNode;
			}
		}

		if ( count( $childNodes ) != count( $nodesByLocalNames[ IXBRL_ELEMENT_TUPLE ] ) )
		{
			throw new IXBRLDocumentValidationException( 'TupleNonEmptyValidation', "Section $section A tuple must have child elements" );
		}
	}

	/**
	 * Run all conformance tests
	 */
	public static function Test()
	{
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
