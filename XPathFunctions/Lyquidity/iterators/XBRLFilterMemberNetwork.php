<?php
/**
 * XPath 2.0 for PHP
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
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

namespace XBRL\functions\lyquidity\iterators;

require_once __DIR__ . '/XBRLIterator.php';

// use lyquidity\XPath2\Iterator\XBRLIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\ContextProvider;
use lyquidity\XPath2\XPath2Exception;

/**
 * XBRLIterator (public)
 * Implements a base iterator for XBRL iterators so they can share common functions where they exist
 */
class XBRLFilterMemberNetwork extends XBRLIterator implements \Iterator
{
	const CLASSNAME = "lyquidity\XPath2\XBRLFilterMemberNetwork";

	/**
	 * The QName of the dimension.
	 * @var QNameValue $dimension
	 */
	private $dimension;

	/**
	 * The QName of the dimension member that the selection criteria specified
	 * by the axis parameter are going to be applied relative to.
	 * @var QNameValue $member
	 */
	private $member;

	/**
	 * The linkrole value that specifies the network of effective relationships to determine
	 * the selected members on the specified axis from the member used as the origin.
	 * @var string $linkRole
	 */
	private $linkRole;

	/**
	 * The arcrole value that specifies the network of effective relationships as above.
	 * @var string $arcRole
	 */
	private $arcRole;

	/**
	 * The axis value MUST be one of: descendant-or-self child-or-self descendant child
	 * @var string $axis
	 */
	private $axis;

	/**
	 * Constructs a filter
	 * @param ContextProvider	$context	Provides access to
	 * @param QNameValue		$dimension	The QName of the dimension.
	 * @param QNameValue		$member		The QName of the dimension member that the selection criteria specified by the axis
	 * 										parameter are going to be applied relative to.
	 * @param string			$linkRole	The linkrole value that specifies the network of effective relationships to determine
	 * 										the selected members on the specified axis from the member used as the origin.
	 * @param string			$arcRole	The arcrole value that specifies the network of effective relationships as above.
	 * @param string			$axis		The axis value MUST be one of: descendant-or-self child-or-self descendant child
	 */
	public function __construct( $context, $dimension, $member, $linkRole, $arcRole, $axis )
	{
		parent::__construct( $context );
		$this->dimension = $dimension;
		$this->member = $member;
		$this->linkRole = $linkRole;
		$this->arcRole = $arcRole;
		$this->axis = $axis;
	}

	/**
	 * Creates a copy of this iterator that is independent of the original
	 */
	public function CloneInstance()
	{
		$result = new XBRLFilterMemberNetwork( $this->context, $this->dimension, $this->member, $this->linkRole, $this->arcRole, $this->axis );
		if ( ! is_null( $this->index ) ) $result->index = $this->index;
		if ( ! is_null( $this->memberPath ) ) $result->memberPath = $this->memberPath;
		if ( ! is_null( $this->candidates ) ) $result->candidates = $this->candidates;
		return $result;
	}

	/**
	 * The path to the current node in $dimensions['memberpath']
	 * @var $current
	 */
	private $index = null;

	/**
	 * The path to the requested member in the DRS.  Also used a flag to indicate whether the Init() function can been called.
	 * @var string $memberPaths
	 */
	private $memberPath = null;

	/**
	 * A list of the candidates found in the DRS
	 * @var array $candidates
	 */
	private $candidates = null;

	/**
	 * Initialize the iterator using either the dimensional or the
	 * non-dimensional processor based on the arcrole supplied
	 * {@inheritDoc}
	 * @see XPath2NodeIterator::Init()
	 * @return void
	 */
	public function Init()
	{
		// If the $memberNodes is null then it is the first time and the member needs to be located
		if ( is_null( $this->memberPath ) )
		{
			/**
			 * @var \XBRL $taxonomy
			 */
			$taxonomy = $this->context->xbrlTaxonomy;

			$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $this->dimension->NamespaceUri );
			if ( ! $dimTaxonomy )
			{
				throw XPath2Exception::withErrorCode( "xfie:invalidDimensionQName", "The dimension must exist in the member network" );
			}

			// The dimension reference MUST be valid
			$dimElement = $dimTaxonomy->getElementByName( $this->dimension->LocalName );
			if ( ! $dimElement || $dimElement['substitutionGroup'] != "xbrldt:dimensionItem" )
			{
				throw XPath2Exception::withErrorCode( "xfie:invalidDimensionQName", "The dimension must exist in the member network" );
			}

			// Make sure the requested member is one of the members
			$memTaxonomy = $taxonomy->getTaxonomyForNamespace( $this->member->NamespaceUri );
			$memElement = $memTaxonomy->getElementByName( $this->member->LocalName );
			if ( ! $memElement )
			{
				$this->memberPath = "";
				return null;
			}

			$memberName = "{$memTaxonomy->getTaxonomyXSD()}#{$memElement['id']}";

			// Build paths from the parent information available in the $members collection
			// How the paths are built is different for dimensions, defintions and presentations
			// There are three sections below that retrieve paths for each role type.  The
			// generated list of paths are the processed the same way.

			$memberPaths = array();

			// The link role should be in the dimension schema role types list.
			// From this it will be possible to work out if the role is for presention or definition links

			// BMS 2018-02-02	Extended the condition to check whether the arc role specifies a dimensional feature
			$arcRoleTypes = $dimTaxonomy->getAllArcRoleTypes();
			$presentational = $this->arcRole == \XBRL_Constants::$arcRoleParentChild;
			if ( ! $presentational )
			{
				$presentational = isset( $arcRoleTypes['link:presentationArc'][ $this->arcRole ] );
			}
			if ( $presentational )
			{
				$presentationRole = $dimTaxonomy->getPresentationRoleRefs( array( $this->linkRole ) );

				// It will be an array but only need the relevant entry (should only be one)
				if ( isset( $presentationRole[ $this->linkRole ] ) )
				{
					$presentationRole = $presentationRole[ $this->linkRole ];

					// Need to fixup the index because the presentation path indexes do not include the schema document
					foreach ( $presentationRole[ 'paths' ] as $key => $paths )
					{
						foreach ( $paths as $path )
						{
							if ( strpos( $path, $memberName ) === false )
							{
								continue;
							}
							$memberPaths[ "{$dimTaxonomy->getTaxonomyXSD()}#$key" ] = $path;
							break;
						}
					}
				}
			}

			$createPaths = function( $id, $node, $path ) use ( &$createPaths, &$memberPaths )
			{
				$path = $path . ( empty( $path ) ? "" : "/" ) . $id;
				$memberPaths[ $id ] = $path;

				if ( ! isset( $node['children'] ) )
				{
					return;
				}

				foreach ( $node['children'] as $id => $node )
				{
					$createPaths( $id, $node, $path );
				}
			};

			$dimensional = in_array( $this->arcRole, \XBRL_Constants::$arcRoleDimensional );
			if ( $dimensional )
			{
				$temp = array();
				$roots = array();

				// Make sure there are members for the requested role
				$members = $dimTaxonomy->getDefinitionRoleDimensionMembers( $this->linkRole );
				if ( is_array( $members ) && count( $members ) && isset( $members[ "{$memTaxonomy->getTaxonomyXSD()}#{$memElement['id']}" ] ) )
				{
					foreach ( $members as $key => $member )
					{
						if ( ! isset( $member['parents'] ) || ! count( $member['parents'] ) )
						{
							$roots[ $key ] = $key;
							continue;
						}

						foreach( $member['parents'] as $parent => $detail )
						{
							if ( $parent == "{$dimTaxonomy->getTaxonomyXSD()}#{$dimElement['id']}" )
							{
								$roots[ $key ] = $key;
								continue;
							}

							// Look to see if this parent has been added already
							if ( ! isset( $temp[ $parent ] ) )
							{
								$temp[ $parent ] = array( 'label' => $parent );
							}

							// It has so add this member as a child
							if ( ! isset( $temp[ $key ] ) )
							{
								$temp[ $key ] = array( 'label' => $key );
							}

							$temp[ $parent ]['children'][ $key ] =& $temp[ $key ];
						}
					}
				}

				foreach ( $roots as $root )
				{
					$createPaths( $root, $temp[ $root ], "", $memberPaths );
				}

			}

			$role = $dimTaxonomy->getNonDimensionalRoleRefs( array( $this->linkRole ) );
			if ( $role && isset( $role[ $this->linkRole ][ $this->arcRole ]['arcs'] ) )
			{
				$temp = array();
				$roots = array();

				$members = $role[ $this->linkRole ][ $this->arcRole ]['arcs'];

				foreach ( $members as $parent => $children )
				{
					foreach( $children as $key => $detail )
					{
						if ( $parent == "{$dimTaxonomy->getTaxonomyXSD()}#{$dimElement['id']}" )
						{
							$roots[ $key ] = $key;
							continue;
						}

						// Look to see if this parent has been added already
						if ( ! isset( $temp[ $parent ] ) )
						{
							$temp[ $parent ] = array( 'label' => $parent );
						}

						// It has so add this member as a child
						if ( ! isset( $temp[ $key ] ) )
						{
							$temp[ $key ] = array( 'label' => $key );
						}

						$temp[ $parent ]['children'][ $key ] =& $temp[ $key ];
					}
				}

				foreach ( $roots as $root )
				{
					if ( isset( $temp[ $root ] ) )
					{
						$createPaths( $root, $temp[ $root ], "", $memberPaths );
					}
					else if ( ! isset( $memberPaths[ $root ] ) )
					{
						$createPaths( $root, null, "", $memberPaths );
					}
				}

			}

			if ( ! isset( $memberPaths[ $memberName ] ) )
			{
				$this->memberPath = "";
				return null;
			}

			$memberPath = $memberPaths[ $memberName ];

			foreach ( $memberPaths as $path )
			{
				$endsWith = \XBRL::endsWith( $path, $memberPath );

				// Remove self if not requested
				if ( $this->axis != "child-or-self" && $this->axis != "descendant-or-self" )
				{
					if ( $endsWith ) continue;
				}

				$pos = strpos( $path, $memberPath );
				if ( $pos === false ) continue;
				// If the position is zero and the path ends with memberPath then this is 'self'
				// so only consider 'ends with' if pos is > 0
				if ( $pos && $endsWith ) continue;

				$this->candidates[] = $path;
			}

			$this->memberPath = $memberPath;
			$this->index = 0;

		}
	}

	/**
	 * NextItem
	 * @return XPathItem
	 */
	protected function NextItem()
	{
		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $this->context->xbrlTaxonomy;

		$memberPathPartsCount = count( explode( "/", $this->memberPath ) );

		while ( true )
		{
			if ( ! is_array( $this->candidates ) || $this->index >= count( $this->candidates ) )
			{
				return null;
			}

			$candidaterPath = $this->candidates[ $this->index++ ];
			$candidaterPathParts = explode( "/", $candidaterPath );

			if ( $this->axis == "child" || $this->axis == "child-or-self" )
			{
				// The member at the index can only be child.
				// That is, the count of parts can only be the same as $memberPathPartsCount or just one greater
				if ( count( $candidaterPathParts ) > $memberPathPartsCount + 1 )
				{
					continue;
				}
			}

			$member = $candidaterPathParts[ count( $candidaterPathParts ) - 1 ];
			$memTaxonomy = $taxonomy->getTaxonomyForXSD( $member );
			$memberId = trim( strrchr( $member, "#"), "#" );
			$memElement = $memTaxonomy->getElementById( $memberId );
			$result = QNameValue::fromNCName( "{$memTaxonomy->getPrefix()}:{$memElement['name']}", $this->context->NamespaceManager );

			return $result;

		}
		return null;
	}

	/**
	 * Allow the iterators to be reset
	 */
	public function Reset()
	{
		parent::Reset();
		$this->index = 0;
		if ( $this->candidates ) return;
		$this->memberPath = null;
		// $this->candidates = null;
	}

}
