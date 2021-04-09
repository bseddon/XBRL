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

use lyquidity\ixbrl\XBRL_Inline;

/**
 * Utility class used to create one or more XBRL instance documents from an iXBRL document set
 */
class IXBRL_CreateInstance
{
	/**
	 * The document being crested
	 * @var \DOMDocument
	 */
	private $document = null;

	/**
	 * An array of comment patterns to add the the header of a document
	 * @var array
	 */
	private $headerComments = array(
		"Location           : %s ",
		"Description        : %s",
		"Creation Date      : %s "
	);

	/**
	 * Creates an instance document for each output target
	 * @param string[] $targets
	 * @param string $name
	 * @param \DOMElement[][] $nodesByLocalName
	 * @param \DOMElement[][] $nodesById
	 * @param \DOMElement[][] $nodesByTarget
	 * @return \DOMDocument[]
	 */
	public static function createInstanceDocuments( $targets, $name, &$nodesByLocalName, &$nodesById, &$nodesByTarget )
	{
		// Rebuild $nodesByTarget to group nodes by ix element
		$targetNodesByLocalName = array_map( function( $targetNodes )
		{
			return array_reduce( $targetNodes, function( $carry, $node )
			{
				$carry[ $node->localName ][] = $node;
				return $carry;
			}, array() );
		}, $nodesByTarget );

		return array_map( function( $target ) use( $name, &$nodesByLocalName, &$nodesById, &$targetNodesByLocalName ) 
		{
			return (new IXBRL_CreateInstance())->createDocument( $target, $name, $nodesByLocalName, $nodesById, $targetNodesByLocalName );
		}, $targets );
	}

	/**
	 * Create an instance document for a named target
	 * @param string $target
	 * @param string $name
	 * @param \DOMElement[][] $nodesByLocalName
	 * @param \DOMElement[][] $nodesById
	 * @param \DOMElement[][][] $nodesByTarget
	 * @return \DOMDocument
	 */
	public function createDocument( $target, $name, &$nodesByLocalName, &$nodesById, &$nodesByTarget )
	{
		$this->document = new \DOMDocument();

		// Comments
		$this->addHeaderComments( "$name$target", "XBRL instance document created from an iXBRL source using the XBRL Query processor" );

		// Root node
		$node = $this->document->createElementNS( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI], 'xbrl' );
		$xbrl = $this->document->appendChild( $node );
		unset( $node );

		// Add namespaces for all input documents
		$namespaces = array();
		foreach( XBRL_Inline::$documents as $document )
		{
			/** @var \lyquidity\ixbrl\XBRL_Inline $document */
			foreach( $document->getElementNamespaces() as $namespace )
			{
				$prefix = $document->document->lookupPrefix( $namespace );
				$this->addPrefix( $prefix, $namespace );
				$namespaces[ $prefix ] = $namespace;
			}
		}

		$prefixes = array_flip( $namespaces );
		$xhtmlPrefix = $prefixes[ \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML ] ];
		unset( $namespaces[ $xhtmlPrefix ] );

		// Make sure the standard namespaces are added
		$this->addPrefix( $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XBRLDI] ], \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XBRLDI] );
		$this->addPrefix( $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_LINK] ], \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_LINK] );
		$this->addPrefix( $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XLINK] ], \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XLINK] );

		// Add references
		foreach( $nodesByTarget[ $target ][ IXBRL_ELEMENT_REFERENCES ] ?? array() as $reference )
		{
			/** @var \DOMElement $reference */
			foreach( $reference->childNodes as $node )
			{
				/** @var \DOMElement $node */
				if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
				// Look for a base uri
				$baseURI = substr( $node->baseURI, -1 ) =='/' ? $node->baseURI : '';
				switch( $node->localName )
				{
					case 'schemaRef':
						$link = $this->addElement( 'schemaRef', $xbrl, $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XLINK] ] );
						break;

					case 'linkbaseRef':
						$link = $this->addElement( 'linkbaseRef', $xbrl, $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XLINK] ] );
						break;
				}

				// $document = XBRL_Inline::$documents[ $node->ownerDocument->documentElement->baseURI ];
				foreach( $node->attributes as $index => $attr )
				{
					/** @var \DOMAttr $attr */
					// Don't copy any ix attributes
					if ( array_search( $attr->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) !== false ) continue;

					// Copy the attribute.  If it'a href merge with the baseuri if appropriate
					$value = $attr->name == 'href'&& $baseURI
						? \XBRL::resolve_path( $attr->nodeValue, $baseURI )
						: $attr->nodeValue;
					$this->addAttr( $attr->name, $value, $link, $prefixes[ $attr->namespaceURI ] ?? null );
				}
			}
		}

		// Add contexts
		// First need to remove contexts and units that are not used
		$usedRefs = array_reduce( array( IXBRL_ELEMENT_FRACTION, IXBRL_ELEMENT_NONFRACTION, IXBRL_ELEMENT_NONNUMERIC ), function( $carry, $ixElement ) use( &$nodesByTarget, $target )
		{
			foreach( $nodesByTarget[ $target ][ $ixElement ] as $node )
			{
				/** @var \DOMElement $node */
				if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
				if ( ( $value = $node->getAttribute('contextRef') ) )
				{
					$carry['contexts'][] = $value;
				}
				if ( ( $value = $node->getAttribute('unitRef') ) )
				{
					$carry['units'][] = $value;
				}
			}
			return $carry;
		}, array('contexts' => array(), 'units' => array() ) );
		$this->copyNodes( $nodesByTarget[ $target ][ IXBRL_ELEMENT_RESOURCES ] ?? array(), $xbrl, 
			function( $node ) use( $usedRefs )
			{
				if ( $node->localName == 'context' && array_search( $node->getAttribute('id'), $usedRefs['contexts'] ) !== false ) return true;
				if ( $node->localName == 'unit' && array_search( $node->getAttribute('id'), $usedRefs['units'] ) !== false ) return true;
				return false; 
			} );

		return $this->document;
	}

	/**
	 * Copy nodes from source to target
	 * @param \DOMElement $source
	 * @param \DOMElement $target
	 * @param boolean $trim
	 * @return void
	 * @throws IXBRLException
	 */
	private function copyNodes( $source, $target, $callback = null, $trim = true )
	{
		foreach( $source as $elements )
			foreach( $elements->childNodes as $from )
			{
				/** @var \DOMElement $from */
				if ( $from->nodeType != XML_ELEMENT_NODE ) 
				{
					$trimmed = $trim ? trim( $from->textContent ) : $from->textContent;
					if ( $trimmed ) $this->addContent( $from->textContent, $target );
					continue;
				}

				if ( $callback && ! $callback( $from ) ) continue;

				$element = $this->addElement( $from->localName, $target, $from->namespaceURI == $target->namespaceURI ? null : $from->prefix, $from->namespaceURI == $target->namespaceURI ? $from->namespaceURI : $from->namespaceURI );
				foreach( $from->attributes as $attr )
				{
					/** @var \DOMAttr $attr */
					// Don't copy any ix attributes
					if ( array_search( $attr->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) !== false ) continue;
					$this->addAttr( $attr->name, $attr->nodeValue, $element, $attr->namespaceURI == $target->namespaceURI ? null : $attr->prefix, $attr->namespaceURI == $target->namespaceURI ? null : $attr->namespaceURI );
				}

				$this->copyNodes( array( $from ), $element );
			}
	}

	/**
	 * Get the base URI in scope for an element by working up the node hierarchy until an absolute uri is found or one is not found
	 * @param \DOMElement $node
	 * @return string
	 */
	private function getBaseURI( $node )
	{
		$baseURI = '';
		while( $node )
		{

		}

		return $baseURI;
	}

	/**
	 * Returns the root element of the document
	 * @return \DOMNode
	 */
	private function getRoot()
	{
		if ( ! $this->document ) return;

		if ( ! $this->document->documentElement )
		{
			throw new IXBRLException('A root node has not been defined');
		}

		return $this->document->documentElement;
	}

	/**
	 * Adds an attribute to $parentNode.  If parent node is null the the root element is assumed.
	 * @param string $name
	 * @param string $value
	 * @param \DOMNode $parentNode
	 * @param string $prefix
	 * @param string $namespace
	 * @throws TaxonomyException
	 */
	function addAttr( $name, $value, $parentNode = null, $prefix = null, $namespace = null )
	{
		if ( ! $this->document ) return;

		if ( ! $parentNode )
		{
			$parentNode = $this->getRoot();
		}

		$this->checkParentNode( $parentNode );

		if ( $prefix && $namespace )
		{
			$this->addPrefix( $prefix, $namespace );
		}

		$attr = $this->document->createAttribute( $prefix ? "$prefix:$name" : $name );

		$attr->value = $value;
		$parentNode->appendChild( $attr );
	}

	/**
	 * Adds a comment node to the parent node or the document node
	 * @param string $comment
	 * @param \DOMNode $parentNode
	 */
	function addComment( $comment, $parentNode = null )
	{
		$node = $this->document->createComment( $comment );
		if ( ! $parentNode )
		{
			$parentNode = $this->getRoot();
		}

		$parentNode->appendChild( $node );

		return $node;
	}

	/**
	 * Adds an element node to $parentNode.  If parent node is null the the root element is assumed.
	 * @param string $name
	 * @param \DOMNode $parentNode
	 * @param string $prefix
	 * @param string $namespace
	 * @throws TaxonomyException
	 * @return \DOMNode
	 */
	function addElement( $name, $parentNode = null, $prefix = null, $namespace = null )
	{
		if ( ! $this->document ) return null;

		if ( ! $parentNode )
		{
			$parentNode = $this->getRoot();
		}

		$this->checkParentNode( $parentNode );

		if ( $prefix && $namespace )
		{
			$this->addPrefix( $prefix, $namespace );
		}

		$node = $namespace
			? $this->document->createElementNS( $namespace, $prefix ? "$prefix:$name" : $name )
			:  $this->document->createElement( $prefix ? "$prefix:$name" : $name );

		return $parentNode->appendChild( $node );
	}

	/**
	 * Adds text conent to $parentNode.  If parent node is null the the root element is assumed.
	 * @param string $value
	 * @param \DOMNode $parentNode
	 * @throws TaxonomyException
	 * @return \DOMNode
	 */
	function addContent( $content, $parentNode )
	{
		if ( ! $this->document ) return;

		if ( ! $parentNode )
		{
			$parentNode = $this->getRoot();
		}

		$this->checkParentNode( $parentNode );

		$textNode = $this->document->createTextNode( $content );

		$parentNode->appendChild( $textNode );

		return $parentNode;
	}

	/**
	 * Add comments to the header of the document
	 * @param string $documentName
	 * @param string $description
	 * @param string $extraComment
	 */
	function addHeaderComments( $documentName, $description, $extraComment = null )
	{
		if ( $extraComment )
		{
			$node = $this->document->createComment( $extraComment );
			$this->document->appendChild( $node );
		}

		foreach ( $this->headerComments as $index => $comment )
		{
			switch ( $index )
			{
				case 0:
					$comment = sprintf( $comment, $documentName );
					break;

				case 1:
					$comment = sprintf( $comment, $description );
					break;

				case 2:
					$comment = sprintf( $comment, date('Y-m-d H:i:s ') );
					break;
			}

			$node = $this->document->createComment( $comment );
			$this->document->appendChild( $node );
		}
	}

	/**
	 * Adds a prefix/namespace to the root node
	 * @param string $prefix
	 * @param string $namespace
	 */
	function addPrefix( $prefix, $namespace )
	{
		if ( ! $this->document ) return;

		$attr = $this->document->createAttribute( "xmlns:$prefix" );
		$attr->value = $namespace;
		$schema = $this->document->documentElement;
		$schema->appendChild( $attr );
	}

	/**
	 * Throws an exception if $parentNode is not an instance of DOMNode
	 * @param \DOMNode $parentNode
	 * @throws TaxonomyException
	 */
	private function checkParentNode( $parentNode )
	{
		if ( ! ( $parentNode instanceof \DOMNode ) )
		{
			throw new IXBRLException('The parent node of an attribute MUST be a DOMNode ($name=$value)');
		}
	}
}