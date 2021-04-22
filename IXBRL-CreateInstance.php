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

define( 'INSTANCE_ATTR_ID', 'id' );
define( 'INSTANCE_ATTR_CONTEXTREF', 'contextRef' );
define( 'INSTANCE_ATTR_UNITREF', 'unitRef' );
define( 'INSTANCE_ATTR_NAME', 'name' );
define( 'INSTANCE_ATTR_FORMAT', 'format' );
define( 'INSTANCE_ATTR_SCALE', 'scale' );

define( 'INSTANCE_ELEMENT_CONTEXT', 'context' );
define( 'INSTANCE_ELEMENT_UNIT', 'unit' );
define( 'INSTANCE_ELEMENT_ROLEREF', 'roleRef' );
define( 'INSTANCE_ELEMENT_ARCROLEREF', 'arcroleRef' );

define( 'ARRAY_CONTEXTS', 'contexts' );
define( 'ARRAY_UNITS', 'units' );

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

		return array_reduce( $targets, function( $carry, $target ) use( $name, &$nodesByLocalName, &$nodesById, &$targetNodesByLocalName ) 
		{
			$carry[ $target ] = (new IXBRL_CreateInstance())->createDocument( $target, $name, $nodesByLocalName, $nodesById, $targetNodesByLocalName );
			return $carry;
		}, array() );
	}

	/**
	 * Create an instance document for a named target
	 * @param string $target
	 * @param string $name
	 * @param \DOMElement[][] $nodesByLocalName
	 * @param \DOMElement[] $nodesById
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
		// Valid attributes on the <references> are added to <xbrl> in the target document
		foreach( $nodesByTarget[ $target ][ IXBRL_ELEMENT_REFERENCES ] ?? array() as $reference )
			$this->copyAttributes( $reference, $node );
		unset( $node );

		// Add namespaces for all input documents
		$namespaces = array();
		foreach( XBRL_Inline::$documents as $document )
		{
			/** @var \lyquidity\ixbrl\XBRL_Inline $document */
			foreach( $document->getElementNamespaces() as $namespace )
			{
				switch( $namespace )
				{
					case \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML ]:
					case \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL10 ]:
					case \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_IXBRL11 ]:
						continue 2;
				}
				$prefix = $document->document->lookupPrefix( $namespace );
				$this->addPrefix( $prefix, $namespace );
				$namespaces[ $prefix ] = $namespace;
			}
		}

		// Make sure the standard namespaces are added
		$prefixes = array_flip( $namespaces );
		if ( ( $prefix = $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XBRLDI] ] ?? false ) )
			$this->addPrefix( $prefix, \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XBRLDI] );
		if ( ( $prefix = $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_LINK] ] ?? false ) )
			$this->addPrefix( $prefix, \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_LINK] );
		if ( ( $prefix = $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XLINK] ] ?? false ) )
			$this->addPrefix( $prefix, \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_XLINK] );

		// Set up the xbrli prefix
		$xbrliPrefix = STANDARD_PREFIX_XBRLI;

		if ( isset( $prefixes[ \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ] ) )
		{
			$xbrliPrefix = $prefixes[ \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ];
		}
		else
		{
			$namespaces[ STANDARD_PREFIX_XBRLI ] = \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ];
			$prefixes[ \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ] = STANDARD_PREFIX_XBRLI;
		}

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
						$link = $this->addElement( 'schemaRef', $xbrl, $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_LINK] ] );
						break;

					case 'linkbaseRef':
						$link = $this->addElement( 'linkbaseRef', $xbrl, $prefixes[ \XBRL_Constants::$standardPrefixes[STANDARD_PREFIX_LINK] ] );
						break;
				}

				// $document = XBRL_Inline::$documents[ $node->ownerDocument->documentElement->baseURI ];
				foreach( $node->attributes as $index => $attr )
				{
					/** @var \DOMAttr $attr */
					// Don't copy any ix attributes
					if ( array_search( $attr->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) !== false ) continue;
					if ( $attr->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML] && $attr->name == IXBRL_ATTR_BASE ) continue;
					// Copy the attribute.  If it'a href merge with the baseuri if appropriate
					$value = $attr->name == 'href'&& $baseURI
						? \XBRL::resolve_path( $baseURI, $attr->nodeValue )
						: $attr->nodeValue;
					$this->addAttr( $attr->name, $value, $link, $prefixes[ $attr->namespaceURI ] ?? null );
				}
			}
		}

		$ixFactElements = array( IXBRL_ELEMENT_FRACTION, IXBRL_ELEMENT_NONFRACTION, IXBRL_ELEMENT_NONNUMERIC );

		// Add contexts
		// First need to remove contexts and units that are not used
		$usedRefs = array_reduce( $ixFactElements, function( $carry, $ixElement ) use( &$nodesByTarget, $target )
		{
			foreach( $nodesByTarget[ $target ][ $ixElement ] ?? array() as $node )
			{
				/** @var \DOMElement $node */
				if ( $node->nodeType != XML_ELEMENT_NODE ) continue;
				if ( ( $value = $node->getAttribute(INSTANCE_ATTR_CONTEXTREF) ) )
				{
					$carry[ARRAY_CONTEXTS][] = $value;
				}
				if ( ( $value = trim( $node->getAttribute(INSTANCE_ATTR_UNITREF) ) ) )
				{
					$carry[ARRAY_UNITS][] = $value;
				}
			}
			return $carry;
		}, array(ARRAY_CONTEXTS => array(), ARRAY_UNITS => array() ) );

		// There's only one set of resources and they will be in the default target collection
		$this->copyNodes( $nodesByTarget[''][ IXBRL_ELEMENT_RESOURCES ] ?? array(), $xbrl, 
			function( $node ) use( $usedRefs, $nodesById )
			{
				$id = $node->getAttribute(INSTANCE_ATTR_ID);
				switch( $node->localName )
				{
					case INSTANCE_ELEMENT_CONTEXT: return array_search( $id, $usedRefs[ARRAY_CONTEXTS] ) !== false;
					case INSTANCE_ELEMENT_UNIT: return array_search( $id, $usedRefs[ARRAY_UNITS] ) !== false;
					case INSTANCE_ELEMENT_ROLEREF:
					case INSTANCE_ELEMENT_ARCROLEREF:
						return true;
				}
				return false; 
			} );

		// Remove excluded elememts
		foreach( $nodesByTarget[ $target ][ IXBRL_ELEMENT_EXCLUDE ] ?? array() as $index => $node  )
		{
			/** @var \DOMElement $node */
			$parentNode = $node->parentNode;
			$parentNode->removeChild( $node );
		}

		// Time to add non-tuple facts.  If a fact has a tuple reference, it will be recorded to be added to the correct tuple later.
		$tupleRefs = array(); // The index is the id of the parent tuple
		$nodePaths = array(); // Nodes indexed by their tuple parent path
		$tupleParentChild = array(); // Node paths indexed by their parent path
		foreach( $ixFactElements as $ixElement )
		{
			foreach( $nodesByTarget[ $target ][ $ixElement ] ?? array() as $node  )
			{
				/** @var \DOMElement $node */
				$nodePath = $node->getNodePath();
				$nodePaths[ $nodePath ] = $node;

				if ( $tupleRef = $node->getAttribute( IXBRL_ATTR_TUPLEREF ) )
				{
					$tupleRefs[ $tupleRef ][] = $nodePath;
					continue;
				}

				// Elements within a tuple will be handled later
				if ( $tuple = XBRL_Inline::checkTupleParent( $node, true ) )
				{
					$tupleParentChild[ $tuple->getNodePath() ][] = $nodePath;
					continue;
				}

				$this->addIXElement( $node, $xbrl, $nodesByTarget, $xbrliPrefix, $nodesById );
			}
		}

		// Now process tuples
		// Create a list indexed by path and a hierachy of paths
		$tupleIds = array();
		foreach( $nodesByTarget[ $target ][ IXBRL_ELEMENT_TUPLE ] ?? array() as $node  )
		{
			/** @var \DOMElement $node */
			$path = $node->getNodePath();
			$nodePaths[ $path ] = $node;

			if ( $tupleId = $node->getAttribute( IXBRL_ATTR_TUPLEID ) )
			{
				$tupleIds[ $tupleId ] = $node->getNodePath();
			}

			if ( $tupleRef = $node->getAttribute( IXBRL_ATTR_TUPLEREF ))
			{
				$tupleRefs[ $tupleRef ][] = $path;
				continue;
			}

			$parentPath = null;
			if ( $parentNode = XBRL_Inline::checkTupleParent( $node, true )) 
			{
				$parentPath = $parentNode->getNodePath();
			}

			$tupleParentChild[ $parentPath ][] = $path;
		}

		// Now resolve tuple refs
		foreach( $tupleRefs as $tupleId => $paths )
		{
			$tupleParentChild[ $tupleIds[ $tupleId ] ] = array_merge( $tupleParentChild[ $tupleIds[ $tupleId ] ] ?? array(), $paths );
		}
		unset( $tupleId );
		unset( $tupleIds );
		unset( $tupleRefs );

		$processHierarchy = function( $paths, $parent ) use( &$processHierarchy, &$tupleParentChild, &$nodePaths, &$nodesByTarget, $xbrliPrefix, &$nodesById )
		{
			// First sort the nodes in the designated order
			$nodes = array_reduce( $paths, function( $carry, $path ) use( &$nodePaths ) { $carry[ $path ] = $nodePaths[ $path ]; return $carry; }, array() );
			uasort( $nodes, function( $a, $b )
			{
				$aOrder = $a->getAttribute(IXBRL_ATTR_ORDER);
				$bOrder = $b->getAttribute(IXBRL_ATTR_ORDER);
				$diff = floatval( $aOrder ?? 0 ) - floatval( $bOrder ?? 0 );
				return $diff == 0
					? 0
					: ( $diff > 0 ? 1 : -1 );
			} );

			// Drop duplicated orders
			$orderContents = array();
			$nodes = array_filter( $nodes, function( $child ) use( &$orderContents )
			{
				$nil = filter_var( $child->getAttribute(IXBRL_ATTR_NIL), FILTER_VALIDATE_BOOLEAN );
				if ( $nil ) return true;

				$order = $child->getAttribute(IXBRL_ATTR_ORDER);
				$value = trim( preg_replace( '/\s+/', ' ', $child->textContent ) );
				if ( isset( $orderContents[ $order ] ) && $orderContents[ $order ] == $value ) return false;
				$orderContents[ $order ] = $value;
				return true;
			} );
			unset( $orderContents );

			foreach( $nodes as $path => $node )
			{
				/** @var \DOMElement $node */

				$element = $this->addIXElement( $node, $parent, $nodesByTarget, $xbrliPrefix, $nodesById );

				if ( isset( $tupleParentChild[ $path ] ) )
				{
					$processHierarchy( $tupleParentChild[ $path ], $element );
				}
			}
		};

		if ( isset( $tupleParentChild[null] ) )
			$processHierarchy( $tupleParentChild[null], $xbrl );

		// Now references and footnotes
		// If there are any footnotes add a <link>
		// $footnotes = $nodesByLocalName[ IXBRL_ELEMENT_FOOTNOTE ] ?? array();
		// if ( $footnotes )
		$relationships = $nodesByLocalName[ IXBRL_ELEMENT_RELATIONSHIP ] ?? array();
		if ( $relationships )
		{
			// Group target relationships by link role and arcrole
			 /** @var \DOMElement[][][] */ 
			$relationshipGroups = array_reduce( $relationships, function( $carry, $relationship )
			{
				$linkRole = $relationship->getAttribute( IXBRL_ATTR_LINKROLE );
				if ( ! $linkRole ) $linkRole =  \XBRL_Constants::$defaultLinkRole;
				$arcRole = $relationship->getAttribute( IXBRL_ATTR_ARCROLE );
				if ( ! $arcRole ) $arcRole = \XBRL_Constants::$arcRoleFactFootnote;
			$carry[ $linkRole ][ $arcRole ][] = $relationship;
				return $carry;
			}, array() );

			foreach( $relationshipGroups as $linkRole => $relationshipGroup )
			{
				$footnotes = array();

				foreach( $relationshipGroup as $arcRole => $relationships )
				{
					foreach( $relationships as $relationship )
					{
						// Add a <loc> for every element in the 'fromRefs' attribute
						$fromRefs = preg_replace( '/\s+/', ' ', $relationship->getAttribute( IXBRL_ATTR_FROMREFS ) );
						if ( ! $fromRefs ) continue;

						// Add a <footnote> for every element in the 'toRefs' attribute
						$toRefs = preg_replace( '/\s+/', ' ', $relationship->getAttribute( IXBRL_ATTR_TOREFS ) );
						if ( ! $toRefs ) continue;

						// There's going to be an extended link for every footnote (toRef)
						$toRefs = explode( ' ', $toRefs );
						foreach( $toRefs as $toRef )
						{
							if ( ! isset( $footnotes[ $toRef ][ $arcRole ] ) )
								$footnotes[ $toRef ][ $arcRole ] = array( 
									'order' => $relationship->hasAttribute( IXBRL_ATTR_ORDER ) ? $relationship->getAttribute( IXBRL_ATTR_ORDER ) : null,
									'fromRefs' => array()
								);

							$footnotes[ $toRef ][ $arcRole ]['fromRefs'] = array_merge( 
								$footnotes[ $toRef ][ $arcRole ]['fromRefs'],
								array_filter(
									explode( ' ', $fromRefs ),
									// Check the validity of the fromRef
									function( $fromRef ) use( &$nodesById, $target )
									{
										// The ix element with @id value $fromRef must have target $target
										$ixElement = $nodesById[ $fromRef ];
										return $ixElement->getAttribute( IXBRL_ATTR_TARGET ) == $target;
									}
								)
							);
						}
					}
				}

				// There's going to be an extended link for every footnote (toRef)
				foreach( $footnotes as $toRef => $arcs )
				{
					// Look ahead to see if there are fromRefs
					$count = array_reduce( $arcs,function( $carry, $details ) 
					{
						return $carry + count( $details['fromRefs']);
					}, 0 );

					if ( ! $count ) continue;

					// Each footnote can have its own extended link
					$footnoteLinkElement = $this->addElement('footnoteLink', $xbrl, STANDARD_PREFIX_LINK, \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
						$this->addAttr( 'role', $linkRole, $footnoteLinkElement, STANDARD_PREFIX_XLINK, \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
						$this->addAttr( 'type','extended', $footnoteLinkElement, STANDARD_PREFIX_XLINK );

					$ixFootnote = $nodesById[ $toRef ];
					if ( ! $ixFootnote ) continue;

					// If the 'footnote' is another element then copy it regardless of its target
					if ( $ixFootnote->localName != IXBRL_ELEMENT_FOOTNOTE )
					{
						// If the 'footnote' element is in the same target then it already exists
						$ixTarget = $ixFootnote->getAttribute( IXBRL_ATTR_TARGET );
						if ( $ixTarget != $target )
						{
							$this->addIXElement( $ixFootnote, $xbrl, $nodesByTarget, $xbrliPrefix, $nodesById );
						}
						$ixId = $ixFootnote->getAttribute( IXBRL_ATTR_ID );
						// Create the locator.
						$locElement = $this->addElement( 'loc', $footnoteLinkElement, 'link' );
							$this->addAttr( 'href', "#$ixId", $locElement, 'xlink' );
							$this->addAttr( 'label', 'footnote', $locElement, 'xlink' );
							$this->addAttr( 'type', 'locator', $locElement, 'xlink' );	
					}
					else
					{
						$footnoteRole = $ixFootnote->getAttribute( IXBRL_ATTR_FOOTNOTEROLE );

						// Add the footnote resource implied by the <ix:footnote>
						/** @var \DOMElement */
						$footnoteElement = $this->addElement( 'footnote', $footnoteLinkElement, STANDARD_PREFIX_LINK );
							$this->copyAttributes( $ixFootnote, $footnoteElement );

							// Add the xlink attributes
							$this->addAttr( 'role', $footnoteRole ? $footnoteRole : \XBRL_Constants::$footnote, $footnoteElement, STANDARD_PREFIX_XLINK );
							$this->addAttr( 'label', 'footnote', $footnoteElement, STANDARD_PREFIX_XLINK );
							$this->addAttr( 'type', 'resource', $footnoteElement, STANDARD_PREFIX_XLINK );
							// @title will be copied across but it should be in the xlink namespace
							if ( $footnoteElement->hasAttribute( IXBRL_ATTR_TITLE ) )
								$this->addAttr( IXBRL_ATTR_TITLE, $footnoteElement->getAttribute( IXBRL_ATTR_TITLE ), $footnoteElement, STANDARD_PREFIX_XLINK );
							$footnoteElement->removeAttribute( IXBRL_ATTR_TITLE );
							// $footnoteElement->removeAttribute( IXBRL_ATTR_FOOTNOTEROLE );

							// getFormattedValue() returns a fragment that can be appended to the footnote element
							$hasElements = false; // This is used as reference variable passed to checkFformat
							$fragment = $this->document->createDocumentFragment();
							$fragment->appendXml( $this->getFormattedValue( $ixFootnote, $nodesById, $hasElements ) );
							$added = $footnoteElement->appendChild( $fragment );
							if ( $hasElements )
								$this->addAttr( 'xmlns', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_XHTML], $footnoteElement, STANDARD_PREFIX_XMLNS );
							$xmlNS = \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ];
							if ( ! $footnoteElement->hasAttributeNS( $xmlNS, IXBRL_ATTR_LANG ) )
							{
								// Find one further up the DOM
								XBRL_Inline::checkParentNodes( $ixFootnote, function( $parentNode ) use ( $xmlNS, $footnoteElement )
								{
									/** @var \DOMElement $parentNode */
									if ( ! $parentNode->hasAttributeNS( $xmlNS, IXBRL_ATTR_LANG ) ) return false;
									$this->addAttr( 'lang', $parentNode->getAttributeNodeNS( $xmlNS, IXBRL_ATTR_LANG )->value, $footnoteElement, STANDARD_PREFIX_XML );
									return true;
								} );
							}
					}

					$fromLabel = 'fact';
					$index = 1;

					foreach( $arcs as $arcRole => $details )
					{
						if ( ! $details['fromRefs'] ) continue;

						if ( count( $arcs ) > 1 )
						{
							$fromLabel = 'fact' . $index++;
						}

						foreach( $details['fromRefs'] as $fromRef )
						{
							// Create the locator.
							$locElement = $this->addElement( 'loc', $footnoteLinkElement, 'link' );
								$this->addAttr( 'href', "#$fromRef", $locElement, 'xlink' );
								$this->addAttr( 'label', $fromLabel, $locElement, 'xlink' );
								$this->addAttr( 'type', 'locator', $locElement, 'xlink' );	
						}

						// Add the footnote arc from the fact to the <ix:footnote>
						$footnoteArcElement = $this->addElement( 'footnoteArc', $footnoteLinkElement, 'link' );
							$this->addAttr( 'arcrole', $arcRole, $footnoteArcElement, 'xlink' );
							$this->addAttr( 'from', $fromLabel, $footnoteArcElement, 'xlink' );
							$this->addAttr( 'to', 'footnote', $footnoteArcElement, 'xlink' );
							$this->addAttr( 'type','arc', $footnoteArcElement, 'xlink' );
						// If @order exists, copy it
						if ( $details['order'] )
							$this->addAttr( IXBRL_ATTR_ORDER, $details['order'], $footnoteArcElement );
					}
				}
			}
		}
		return $this->document;
	}

	/**
	 * Add an IX element
	 * @param \DOMElement $node
	 * @param \DOMElement $parent
	 * @param \DOMElement[][] $nodesByTarget
	 * @param string $xbrliPrefix
	 * @param \DOMElement[][] $nodesById
	 * @return void
	 */
	private function addIXElement( $node, $parent, $nodesByTarget, $xbrliPrefix, &$nodesById )
	{
		// Create the element and core attributes
		$hasElements = false; // This is used as reference variable passed to checkFformat
		$name = $node->getAttribute(INSTANCE_ATTR_NAME); // Should always be a name
		$element = $this->addElement( $name, $parent );
			if ( ( $value = $node->getAttribute(INSTANCE_ATTR_CONTEXTREF) ) )
				$this->addAttr( INSTANCE_ATTR_CONTEXTREF, trim( $value ), $element );
			if ( ( $value = $node->getAttribute(INSTANCE_ATTR_UNITREF) ) )
				$this->addAttr( INSTANCE_ATTR_UNITREF, trim( $value ), $element );

		// Handle the value
		switch ( $node->localName )
		{
				case IXBRL_ELEMENT_NONNUMERIC:
				case IXBRL_ELEMENT_NONFRACTION:
				$this->addContent( $this->getFormattedValue( $node, $nodesById, $hasElements ), $element );
				break;

			case IXBRL_ELEMENT_FRACTION:
				// Need to find and add the numerator and denomninator.  These are always in the default target.
				$this->addFraction( $node, $element, $nodesById, $nodesByTarget[''], $xbrliPrefix );
				break;
		}

		// Copy other attributes
		$this->copyAttributes( $node, $element );

		return $element;
	}

	/**
	 * Adds the fraction components for a node
	 * @param \DOMElement $node
	 * @param \DOMElement $parent
	 * @param \DOMElement[][] $nodesById
	 * @param \DOMElement[] $nodesByTarget
	 * @param string $xbrliPrefix
	 * @return void
	 */
	private function addFraction( $node, $parent, &$nodesById, &$nodesByTarget, $xbrliPrefix )
	{
		$hasElements = false; // This is used as reference variable passed to checkFformat
		$numerators = $nodesByTarget[ IXBRL_ELEMENT_NUMERATOR ] ?? array();
		$numerator = $this->findElement( $node, $numerators );
		if ( ! $numerator ) return null;

		$denominators = $nodesByTarget[ IXBRL_ELEMENT_DENOMINATOR ] ?? array();
		$denominator = $this->findElement( $node, $denominators );
		if ( ! $denominator ) return null;

		$element = $this->addElement( IXBRL_ELEMENT_NUMERATOR, $parent, $xbrliPrefix );
			$content = $this->getFormattedValue( $numerator, $nodesById, $hasElements );
			$this->addContent( $content, $element );
			$this->copyAttributes( $numerator, $element );

		$element = $this->addElement( IXBRL_ELEMENT_DENOMINATOR, $parent, $xbrliPrefix );
			$content = $this->getFormattedValue( $denominator, $nodesById, $hasElements );
			$this->addContent( $content, $element );
			$this->copyAttributes( $denominator, $element );
	}

	/**
	 * Find a candidate child node - the nearest one
	 *
	 * @param [type] $node
	 * @param [type] $candidates
	 * @return void
	 */
	private function findElement( $node, &$candidates )
	{
		foreach( $candidates as $candidate )
		{
			$found = 0;
			$parentNode = XBRL_Inline::checkParentNodes( $candidate, function( $parentNode ) use( $node, &$found )
			{
				if ( $parentNode->localName != IXBRL_ELEMENT_FRACTION ) return false;
				$found++;
				return spl_object_id( $node ) == spl_object_id( $parentNode );
			} );

			if ( ! $parentNode ) continue;

			// Got a valid parent so numerator is a good'un
			return $candidate;
		}

		return null;
	}

	/**
	 * Returns the content for a node
	 * @param \DOMElement $node
	 * @param \DOMElement[][] $nodesById
	 * @param bool $hasElements Will be true if any of the content are elements such as <i> <b> etc.
	 * @return string
	 */
	private function getFormattedValue( $node, &$nodesById, &$hasElements )
	{
		$document = XBRL_Inline::$documents[ $node->ownerDocument->documentElement->baseURI ];
		return XBRL_Inline::checkFormat( $node, 'instance generation', $node->localName, $document, $nodesById, $hasElements );
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
				switch( $from->nodeType ) 
				{
					case \XML_ELEMENT_NODE:
						break;
					case \XML_COMMENT_NODE:
						continue 2;
					default:
						$trimmed = $trim ? trim( $from->textContent ) : $from->textContent;
						if ( $trimmed ) $this->addContent( $from->textContent, $target );
						continue 2;
				}

				if ( $callback && ! $callback( $from ) ) continue;

				$element = $this->addElement( $from->localName, $target, $from->namespaceURI == $target->namespaceURI ? $from->prefix : $from->prefix, $from->namespaceURI == $target->namespaceURI ? $from->namespaceURI : $from->namespaceURI );
				$this->copyAttributes( $from, $element );

				$this->copyNodes( array( $from ), $element );
			}
	}

	private $attrsToExclude = array( 
		INSTANCE_ATTR_CONTEXTREF, INSTANCE_ATTR_NAME, INSTANCE_ATTR_UNITREF, IXBRL_ATTR_ESCAPE, INSTANCE_ATTR_FORMAT, INSTANCE_ATTR_SCALE, 'xml:base',
		IXBRL_ATTR_SIGN, IXBRL_ATTR_TARGET, IXBRL_ATTR_ORDER, IXBRL_ATTR_TUPLEID, IXBRL_ATTR_TUPLEREF, IXBRL_ATTR_CONTINUEDAT, IXBRL_ATTR_FOOTNOTEROLE );

	/**
	 * Copy the attributes of source to target exluding ix attributes
	 * @param \DOMElement $source
	 * @param \DOMElement $target
	 * @return void
	 */
	private function copyAttributes( $source, $target )
	{		 
		foreach( $source->attributes as $attr )
		{
			/** @var \DOMAttr $attr */
			// Don't copy any ix attributes
			if ( array_search( $attr->namespaceURI, \XBRL_Constants::$ixbrlNamespaces ) !== false ) continue;
			if ( array_search( $attr->name, $this->attrsToExclude ) !== false ) continue;
			if ( array_search( "{$attr->prefix}:{$attr->name}", $this->attrsToExclude ) !== false ) continue;
			$this->addAttr( $attr->name, $attr->nodeValue, $target, $attr->namespaceURI == $target->namespaceURI ? null : $attr->prefix, $attr->namespaceURI == $target->namespaceURI ? null : $attr->namespaceURI );
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

		if ( ! $namespace && $prefix ) $namespace = $parentNode->lookupNamespaceURI( $prefix );

		$attr = $namespace
			? $this->document->createAttributeNS( $namespace, $prefix ? "$prefix:$name" : $name )
			: $this->document->createAttribute( $name );

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
	 * @return \DOMElement
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

		if ( ! $namespace && $prefix ) $namespace = $parentNode->lookupNamespaceURI( $prefix );
		if ( $namespace && ! $prefix ) $prefix = $parentNode->lookupPrefix( $namespace );

		// $node = $namespace || false
		// 	? $this->document->createElementNS( $namespace, $prefix ? "$prefix:$name" : $name )
		// 	:  $this->document->createElement( $prefix ? "$prefix:$name" : $name );
		$node = $this->document->createElement( $prefix ? "$prefix:$name" : $name );

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

			$node = $this->document->createComment( ' ' . $comment );
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
		$this->document->documentElement->setAttributeNS( 'http://www.w3.org/2000/xmlns/', "xmlns:$prefix", $namespace );
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