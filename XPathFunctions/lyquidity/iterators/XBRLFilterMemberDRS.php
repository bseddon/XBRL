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
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\xml\xpath\XPathItem;

/**
 * XBRLIterator (public)
 * Implements a base iterator for XBRL iterators so they can share common functions where they exist
 */
class XBRLFilterMemberDRS extends XBRLIterator implements \Iterator
{
	const CLASSNAME = "lyquidity\XPath2\XBRLFilterMemberDRS";

	/**
	 * The QName of the dimension.
	 * @var QNameValue $dimension
	 */
	private $dimension;

	/**
	 * The QName of the primary item concept that has or inherits hypercube relationships
	 * in the base set of the DRS for which member relationships are to be found.
	 * @var QNameValue $primaryItem
	 */
	private $primaryItem;

	/**
	 * The QName of the dimension member that the selection criteria specified by
	 * the axis parameter are going to be applied relative to.
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
	 * The axis value MUST be one of: DRS-descendant DRS-child
	 * @var string $axis
	 */
	private $axis;

	/**
	 * Constructs a filter
	 * @param ContextProvider	$context		Provides access to
	 * @param QNameValue		$dimension		The QName of the dimension.
	 * @param QNameValue		$primaryItem	The QName of the primary item concept that has or inherits hypercube
	 * 											relationships in the base set of the DRS for which member relationships
	 * 											are to be found.
	 * @param QNameValue		$member			The QName of the dimension member that the selection criteria specified by
	 * 											the axis parameter are going to be applied relative to.
	 * @param xs:string?		$linkRole		The linkrole value that specifies the network of effective relationships
	 * 											to determine the selected members on the specified axis from the member
	 * 											used as the origin.
	 * @param string			$axis			The axis value MUST be one of: DRS-descendant DRS-child
	 */
	public function __construct( $context, $dimension, $primaryItem, $member, $linkRole, $axis )
	{
		parent::__construct( $context );
		$this->dimension = $dimension;
		$this->primaryItem = $primaryItem;
		$this->member = $member;
		$this->linkRole = $linkRole instanceof Undefined ? "" : (string)$linkRole;
		$this->axis = (string)$axis;
	}

	/**
	 * Creates a copy of this iterator that is independent of the original
	 */
	public function CloneInstance()
	{
		$result = new XBRLFilterMemberDRS( $this->context, $this->dimension, $this->primaryItem, $this->member, $this->linkRole, $this->axis );
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
	 * @var string $memberPath
	 */
	private $memberPath = null;

	/**
	 * A list of the candidates found in the DRS
	 * @var array $candidates
	 */
	private $candidates = null;

	/**
	 * Handles initialilzation of this instance when called by the ancestor moveNext()
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

			// The primary item must be valid
			$piElement = $taxonomy->getElementByName( $this->primaryItem->LocalName );
			if ( ! $piElement )
			{
				// V13 requires an error
				throw XPath2Exception::withErrorCode( "xfie:invalidPrimaryItemConceptQName", "The primary item must exist in the member network" );
			}
			else if ( $piElement['substitutionGroup'] != \XBRL_Constants::$xbrliItem )
			{
				// Test V11
				$this->memberPath = "";
				return null;
			}

			$primaryItemName = "{$taxonomy->getTaxonomyXSD()}#{$piElement['id']}";

			if ( $this->linkRole instanceof Undefined || empty( $this->linkRole ) )
			{
				$primaryItems = $taxonomy->getDefinitionPrimaryItems( false );
				if ( ! isset( $primaryItems[ $primaryItemName ] ) )
				{
					$this->memberPath = "";
					$this->candidates = array();
					return null;
				}

				// $this->linkRole = $primaryItems[ $primaryItemName ]['roles'][0];
				$primaryItem = $primaryItems[ $primaryItemName ];
			}
			else
			{
				$primaryItems = $taxonomy->getDefinitionRolePrimaryItems( $this->linkRole );
				// BMS 2018-02-13 Throwing an error here is not appropriate.
				//				  The concept may not be a primary item in the network
				//				  but that just means there are no candidate members.
				if ( ! isset( $primaryItems[ $primaryItemName ] ) )
				{
					$this->memberPath = "";
					$this->candidates = array();
					return null;
				}
				$primaryItem = array( $this->linkRole => $primaryItems[ $primaryItemName ], 'roles' => array( $this->linkRole ) );
			}

			// if ( ! isset( $primaryItems[ $primaryItemName ] ) )
			// {
			// 	throw XPath2Exception::withErrorCode( "xfie:invalidPrimaryItemConceptQName", "The primary item must exist in the member network" );
			// }

			// Obtain the DRS
			$drs = $taxonomy->getPrimaryItemDRS( $primaryItem );

			// Check the dimension supplied is valid
			$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $this->dimension->NamespaceUri );
			if ( ! $dimTaxonomy )
			{
				throw XPath2Exception::withErrorCode( "xfie:invalidDimensionQName", "The dimension must exist in the member network" );
			}

			// The dimension reference MUST be valid
			$dimElement = $dimTaxonomy->getElementByName( $this->dimension->LocalName );
			if ( ! $dimElement )
			{
				// Test V12 requires an error
				throw XPath2Exception::withErrorCode( "xfie:invalidDimensionQName", "The dimension must exist in the member network" );
			}
			else if ( $dimElement['substitutionGroup'] != "xbrldt:dimensionItem" )
			{
				// Test V09 requires empty result
				$this->memberPath = "";
				return null;
			}

			// Make sure the requested member is one of the members
			$memTaxonomy = $taxonomy->getTaxonomyForNamespace( $this->member->NamespaceUri );
			$memElement = $dimTaxonomy->getElementByName( $this->member->LocalName );
			if ( ! $memElement )
			{
				$this->memberPath = "";
				return null;
			}

			$dimensionName = "{$dimTaxonomy->getTaxonomyXSD()}#{$dimElement['id']}";
			$memberName = "{$memTaxonomy->getTaxonomyXSD()}#{$memElement['id']}";
			$memberPaths = array();

			// Locate the dimension among the hypercubes
			foreach ( $drs as $hyprcubeId => $linkRoles )
			{
				foreach ( $linkRoles as $linkRole => $hypercube)
				{
					// Only use the link role provided if one is provided. Otherwise use all roles.
					if ( ! empty( $this->linkRole ) && $this->linkRole != $linkRole ) continue;

					$memberPaths = array_merge( $memberPaths, $hypercube['dimensions'][ $dimensionName ]['memberpaths'] );
				}
			}

			if ( ! $memberPaths )
			{
				$this->memberPath = "";
				return null;
			}

			if ( ! isset( $memberPaths[ $memElement['id'] ] ) )
			{
				$this->memberPath = "";
				return null;
			}

			$memberPath = null;
			foreach ( $memberPaths[ $memElement['id'] ] as $path )
			{
				if ( strpos( $path, $memberName ) !== false )
				{
					$memberPath = $path;
					break;
				}
			}

			if ( is_null( $memberPath ) )
			{
				return null;
			}

			foreach ( $memberPaths as $paths )
			{
				foreach ( $paths as $path )
				{
					// Remove self
					if ( $path == $memberPath ) continue;

					$pos = strpos( $path, $memberPath );
					if ( $pos === false || $pos != 0 ) continue;
					$this->candidates[] = $path;
				}
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
			if ( is_null( $this->candidates ) || ! is_array( $this->candidates ) || $this->index >= count( $this->candidates ) )
			{
				return null;
			}

			$candidaterPath = $this->candidates[ $this->index++ ];
			$candidaterPathParts = explode( "/", $candidaterPath );

			if ( $this->axis == "DRS-child" )
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
		if ( ! is_null( $this->candidates ) ) return;
		$this->memberPath = null;
	}

}
