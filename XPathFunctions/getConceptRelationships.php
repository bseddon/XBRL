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
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
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

namespace XBRL\functions;

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\DOM\XmlSchema;
use lyquidity\xml\TypeCode;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "processPresentationRelationship.php";
require_once "processNonDefinitionRelationship.php";

/**
 * Returns a sequence containing the set of effective relationships with the specified relationship to the source concept.
 *
 * Notes
 *
 * An example of the use of this function to implement calculation linkbase validation with weighted values summation
 * is provided in the test case 61100 ConceptRelationsFilter Processing.xml v21. The files of this test case are attached
 * in this directory as an illustration of the concept-relationships use.
 *
 * The schema file is nav-rel-test1.xsd, the calc linkbase file is nav-rel-test1-detached-calculation.xml, the formula
 * assertion file is calc-lb-exact-sum-weighted-children-formula.xml, and an instance file is
 * calc-lb-exact-sum-weighted-children-instance-ok.xml.
 *
 * Comments in the formula assertion file, calc-lb-exact-sum-weighted-children-formula.xml, describe its operation. The
 * generalVariable, $linkRole, binds one-by-one to each link role that has summation-item arcs. The factVariable,
 * $parent, to every fact in instance. (An alternative approach in calc-lb-exact-sum-weighted-children-formula2.xml
 * binds $parent only to concepts in $linkRole that are from-concepts on effective relationships).
 *
 * The factVariable, $family, binds to the sequence of aspect-related facts (ignoring concept aspect).
 *
 * The generalVariable, $rels, binds to the sequence of effective relationship arcs of children of $parent.
 *
 * The factVariable, $weightedChildValues, binds to the sequence of child values multiplied by the weight on the arc
 * from $parent.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:anyAtomicType*		Returns a sequence of effective relationships that are implementation-defined objects or
 * 									relationship surrogates. These objects are opaque as they may be used only as function
 * 									arguments, but not for direct XPath navigation or value access.
 *
 * 									The implementation-defined objects or relationship surrogates are intended to be used
 * 									only as parameters to other functions such as xfi:relationship-from-concept,
 * 									xfi:relationship-to-concept, xfi:relationship-attribute, xfi:relationship-element,
 * 									xfi:link-attribute, and xfi:link-element.
 *
 * 									The implementation-defined objects may be nodes or may be atomic elements, the consuming
 * 									application should allow both kinds of implementation strategies, but not access the
 * 									content of such nodes or value of such elements.
 *
 * 									xfi:relationship type would be defined as xs:anyType.
 *
 * 									The order is the effective relationships order after consideration of arc order value,
 * 									prohibition, and override. The tree-walk of multiple generations is in depth-first order
 * 									(or the inverse for ancestors), a node's descendants are recursively inserted in the
 * 									result subsequences prior to continuing with siblings.
 *
 * This function has three real arguments:
 *
 * source		xs:QName			The QName of the source concept from which to begin navigation.
 * 									The special QName xfi:root is recognized when it is desired to retrieve
 * 									descendants from the 'root' level (who have no parents).
 * linkrole		xs:AnyUri			The linkrole value that specifies the network of effective relationships to
 * 									determine the selected members on the specified axis from the member
 * 									used as the origin. If omitted ("()" or "''") then the default link role is used.
 * arcrole		xs:anyURI			The arcrole value that specifies the network of effective relationships as above.
 * axis			xs:string			The axis value MUST be one of:
 * 										descendant
 * 										child
 * 										ancestor
 * 										parent
 * 										sibling
 * 										sibling-or-descendant
 * 									If the axis value is 'descendant' then the relationships returned include those to
 * 									concepts that are descendants of the source concept QName, in the linkrole network
 * 									of arcrole effective relationships. ('child' is the same as descendant and generations = 1.)
 *
 *  								For the special source QName, xfi:root, descendants are those from the topmost level
 *  								(where the source concepts have no parents).
 *
 *  								If the axis value is 'ancestor' then the relationships returned include those from
 *  								concepts that are ancestors of the source concept QName, in the linkrole network of
 *  								arcrole effective relationships. (For parent, use ancestor and generations = 1.)
 *
 *  								Ancestors of a root concept is an empty result sequence, so a way to test that a
 *  								concept is a root node is to test for an empty result set of parent relationships.
 *
 *  								If the axis value is 'sibling' then the relationships returned include those concepts
 *  								that are siblings of the parents as the source, in the linkrole network of arcrole
 *  								effective relationships. (A concept with multiple parent relationships has, as siblings,
 *  								those concepts with the same parent relationships.)
 *
 *  								If the source concept is at root level (having no parent relationships), then its
 *  								sibling concepts are other root concepts, but as these other root concepts have no
 *  								parent relationships, there are no sibling relationships to any root concept, and an
 *  								empty sequence is always produced for sibling relationships of a root concept.
 *
 *  								If the axis value is 'sibling-or-descendant' then the result set includes siblings,
 *  								per above, plus descendant relationships.
 *
 * generations	xs:integer?			The generations count is the number of descendant generations, and generations=0 means
 *  								all descendants.
 *
 *									The arcrole value that specifies a limit of number of generations (for descendant or
 * 									ancestor), or zero (if unlimited generations navigation is desired.  Generations=1 and
 * 									descendant axis means obtain only children, generations=2 obtains children and
 * 									grandchildren, and generations=0 means all descendants.
 *
 * 									Generations must be omitted or 1 for a parent, child, or sibling axis
 *
 * 									In any case if a descendant or ancestor concept repeats in the path from the source,
 * 									it is not navigated further for unlimited generations navigation (generations=0), but
 * 									it is navigated further for limited generations navigation.  Thus it is not wise to put
 * 									a high integer on generations count if directed cycles may exist.  A demonstration of
 * 									this issue is in function test cases v-09 and V-09a. In V-09 navigation starts at A1,
 * 									for descendants (not self) for unlimited generations.  In this case A1 is not included
 * 									first in the output (because of missing '-or-self') but A1 is a descendant of A13
 * 									(A1->A13->A1) so the A1 occurence under A13 is included in the output, but no further
 * 									navigation from the repeat (directed cycle) beneath A1 is navigated.  In V-09A 7
 * 									generations are requested, to show that the directed cycle is followed for 7 generations.
 *
 * linkname	xs:QName?				This parameter is optional.  If absent the arcrole and linkrole uniquely determine
 * 									a base set, such as when only standard link elements are used with standard arc
 * 									elements and arc roles, or generic arcs have only one possible link element that may
 * 									contain them.
 *
 * 									If provided then generations must also be provided.
 *
 * 									If provided then this parameter specifies the link element to be included in the
 * 									result set. If omitted, or "()", then the link element QName is not used to determine
 * 									an applicable base set network.
 *
 * arcname	xs:QName?				This parameter is optional.   If absent the arcrole and linkrole uniquely determine
 * 									a base set, such as when only standard arc elements are used with standard link
 * 									elements and arc roles, or generic arcs have only one possible arc element.
 *
 * 									If provided then link name and generations must also be provided. If provided then
 * 									this parameter specifies the arc element to be included in the result set.
 *
 * 									If omitted, or "()", then the arc element QName is not used to determine an applicable
 * 									base set network.
 *
 */
function getConceptRelationships( $context, $provider, $args )
{
	/**
	 * Arguments
	 * source		xs:QName
	 * linkrole		xs:AnyUri
	 * arcrole		xs:anyURI
	 * axis			xs:string
	 * generations	xs:integer?
	 * linkname		xs:QName?
	 * arcname		xs:QName?
	 */

	try
	{
		$isString = function( /** @var XPath2Item $arg */ $arg )
		{
			return is_string( $arg ) || ( $arg instanceof XPath2Item &&
					in_array( $arg->getSchemaType()->TypeCode, array( XmlTypeCode::String, XmlTypeCode::AnyUri ) ) );
		};

		if ( count(  $args ) < 4 )
		{
			throw new \InvalidArgumentException("Too few arguments to the concept relations function");
		}

		if ( $args[0] instanceof XPath2Item )
		{
			$args[0] = $args[0]->getTypedValue();
		}

		if ( ! $args[0] instanceof QNameValue )
		{
			throw new \InvalidArgumentException("The source (first) argument is not a QName");
		}

		if ( ! $isString( $args[1] ) && ! $args[1] instanceof Undefined )
		{
			throw new \InvalidArgumentException("The linkrole (second) argument is not a string or empty");
		}

		if ( ! $isString( $args[2] ) )
		{
			throw new \InvalidArgumentException("The arcrole (third) argument is not a string");
		}

		if ( ! $isString( $args[3] ) )
		{
			throw new \InvalidArgumentException("The axis (fourth) argument is not a string");
		}

		$query = array();

		/**
		 * @var QNameValue $source
		 */
		$source = $args[0];
		$query['source'] =& $source;

		/**
		 * @var string $linkRole
		 */
		if ( $args[1] instanceof XPath2Item )
		{
			$args[1] = $args[1]->getValue();
		}
		$linkRole = empty( $args[1] ) || $args[1] instanceof Undefined ? \XBRL_Constants::$defaultLinkRole : $args[1];
		$query['linkRole'] = $linkRole;

		/**
		 * @var string $arcrole
		 */
		if ( $args[2] instanceof XPath2Item )
		{
			$args[2] = $args[2]->getValue();
		}
		$arcrole = $args[2];
		$query['arcRole'] = $arcrole;

		/**
		 * @var string $axis
		 */
		if ( $args[3] instanceof XPath2Item )
		{
			$args[3] = $args[3]->getValue();
		}
		$axis = $args[3];
		$query['axis'] = $axis;

		// Check the arcrole is not empty
		if ( empty( $arcrole ) )
		{
			throw new \InvalidArgumentException("Parameter arcrole cannot be empty");
		}

		// Check the axis is on of the valid types
		switch ( $axis )
		{
			case "descendant":
			case "child":
			case "ancestor":
			case "parent":
			case "sibling":
			case "sibling-or-descendant":
				break;

			default:
				throw new \InvalidArgumentException("The axis value is not a legal value");
		}

		/**
		 * @var int $generations
		 */
		$generations = null;

		if ( count( $args ) >= 5 && ! $args[4] instanceof Undefined )
		{
			if ( $args[4] instanceof XPath2Item )
			{
				$args[4] = $args[4]->getValue();
			}
			$generations = intval( $args[4] );
			if ( ( $axis == "child" || $axis == "parent" || $axis == "parent" ) && $generations > 1 )
			{
				throw new \InvalidArgumentException( "Invalid generation value given" );
			}
		}
		$query['generations'] =& $generations;

		/**
		 * @var string $linkname
		 */
		$linkname = null;
		// If linkname given the generations MUST be provided
		if ( count( $args ) >= 6 && ! $args[5] instanceof Undefined )
		{
			if ( is_null( $generations ) )
			{
				throw new \InvalidArgumentException( "If linkname is provided then a generations value must also be provided" );
			}

			if ( $args[5] instanceof XPath2Item )
			{
				$args[5] = $args[5]->getValue();
			}
			$linkname = $args[5];
		}
		$query['linkname'] = $linkname;

		/**
		 * @var string $arcName
		 */
		$arcname = null;
		// If arcname given the generations MUST be provided
		if ( count( $args ) >= 7 && ! $args[6] instanceof Undefined )
		{
			if ( is_null( $generations ) )
			{
				throw new \InvalidArgumentException( "If linkname is provided then a generations value must also be provided" );
			}
			$arcname = $args[6];
		}
		$query['arcname'] = $arcname;

		// Provide a default value of zero if no generations value is supplied
		if ( is_null( $generations ) )
		{
			$generations = 0;
		}

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;
		$isRoot = $source == QNameValue::fromNCName( "xfi:root", $context->NamespaceManager );

		if ( $isRoot && ( $axis == 'parent' || $axis == 'sibling' ) ) // Siblings at the root always returns an empty sequence
		{
			return EmptyIterator::$Shared;
		}

		// Retrieve the concept
		$piElement = $taxonomy->getElementByName( $source->LocalName );
		$primaryItemName = $isRoot
			? ""
			: "{$taxonomy->getTaxonomyXSD()}#{$piElement['id']}";

		// Use the hierarchy
		$nodes = array();
		$parents = array();
		$relationshipArcType = "";

		$primaryItems = $taxonomy->getDefinitionPrimaryItems( false );
		if ( isset( $primaryItems[ $primaryItemName ] ) )
		{
			// Dimensional
			$relationshipArcType = \XBRL_Constants::$linkDefinitionArc;
			$drs = $taxonomy->getPrimaryItemDRS( $primaryItems[ $primaryItemName ] );
			return EmptyIterator::$Shared;
		}
		else
		{
			if ( $arcrole == \XBRL_Constants::$arcRoleParentChild )
			{
				// Presentational
				$roleRefs = $taxonomy->getPresentationRoleRefs( array( $linkRole ), false );
				if ( count( $roleRefs ) )
				{
					$roleRef = $roleRefs[ $linkRole ];
					$relationshipArcType = \XBRL_Constants::$linkPresentationArc;

					if ( ( $axis == "descendant" || $axis == "sibling-or-descendant" ) && $isRoot )
					{
						foreach ( $roleRef['hierarchy'] as $id => $child )
						{
							processPresentationRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, 1, $generations, $axis, $child, $id, false );
						}
					}
					else
					{
						// Begin by finding source in the paths
						if ( ! isset( $roleRef['paths'][ $piElement['id'] ] ) )
						{
							return EmptyIterator::$Shared;
						}

						$taxonomy->processNodeByPath(
							$roleRef['hierarchy'],
							$roleRef['paths'][ $piElement['id'] ],
							$primaryItemName,
							function( &$node, $path, $parentKey ) use( &$context, &$taxonomy, &$nodes, $axis, $generations, &$roleRef, &$parents )
							{
								if ( ( $axis == "descendant" || $axis == "child" ) && ( ! isset( $node['children'] ) || ! count( $node['children'] ) ) )
								{
									return;
								}
								processPresentationRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, 0, $generations, $axis, $node, $path, $parentKey );
							}
						);
					}
				}
			}
			else if ( $arcrole == \XBRL_Constants::$arcRoleSummationItem )
			{
				$relationshipArcType = \XBRL_Constants::$linkCalculationArc;

				$roleRefs = $taxonomy->getCalculationRoleRefs( array( $linkRole ) );
				if ( isset( $roleRefs[ $linkRole ] ) )
				{
					$roleRef = $roleRefs[ $linkRole ];

					// Look for the source in the list of arcs
					$parent = array();

					if ( $axis == "descendant" && $isRoot )
					{
						$parent = $roleRef['calculations'];
					}
					else
					{
						if ( isset( $roleRef['calculations'][ $primaryItemName ] ) )
						{
							$parent = $roleRef['calculations'][ $primaryItemName ];
						}
					}

					// Make my life easier by renaming the 'calculations' element as 'arcs'
					$roleRef['arcs'] =& $roleRef['calculations'];
					unset( $roleRef['calculations'] );

					// In the case of summation-item arc nodes the node may be a collection of other nodes
					if ( $axis == "descendant" && $isRoot )
					{
						foreach ( $parent as $parentKey => $parentNodes )
						{
							foreach ( $parentNodes as $id => $child )
							{
								processNonDefinitionRelationship( $context, $taxonomy, $nodes, $parentNodes, $arcRef, 1, $generations, $axis, $child, $parentKey, $parentKey, false, $arcrole );
							}
						}
					}
					else
					{
						foreach ( $parent as $id => $child )
						{
							// Record the child
							processNonDefinitionRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, 1, $generations, $axis, $child, $primaryItemName, $primaryItemName, false, $arcrole );
						}
					}
				}
			}
			else
			{
				// Check the arcrole belongs to a definition link
				$arcroleTypes = $taxonomy->getArcroleTypes();
				$relationshipArcType = \XBRL_Constants::$linkDefinitionArc;
				if ( isset( $arcroleTypes[ $relationshipArcType ] ) )
				{
					if ( isset( $arcroleTypes[ $relationshipArcType ][ $arcrole ] ) )
					{
						$roleRefs = $taxonomy->getNonDimensionalRoleRefs( array( $linkRole ) );
						if ( isset( $roleRefs[ $linkRole ] ) )
						{
							$roleRef = $roleRefs[ $linkRole ];

							// Look for the source in the list of arcs
							if ( isset( $roleRef[ $arcrole ] ) )
							{
								$arcRef = $roleRef[ $arcrole ];
								// Look for the source in the list of arcs
								$parent = null;

								if ( $axis == "descendant" && $isRoot )
								{
									$parent =& $arcRef['arcs'];
								}
								else
								{
									$parent =& $arcRef['arcs'][ $primaryItemName ];
								}

								if ( $parent )
								foreach ( $parent as $id => $child )
								{
									processNonDefinitionRelationship( $context, $taxonomy, $nodes, $parents, $arcRef, 1, $generations, $axis, $child, $primaryItemName, $primaryItemName, false, $arcrole );
								}
							}
						}
					}
				}
				else
				{
					// Could be custom
					// Check if the link is in the gen:link substitution group
					$roleTypes = $taxonomy->getAllRoleTypes();
					if ( ! empty( $linkname ) )
					{
						if ( ! isset( $roleTypes[ $linkname ] ) )
						{
							$linkname = null;
						}
					}
					else
					{
						foreach ( $roleTypes as $link => $roles )
						{
							if ( ! isset( $roles[ $linkRole ] ) )
							{
								continue;
							}

							$linkname = $link;
							break;
						}
					}

					if ( ! $linkname || ! isset( $roleTypes[ $linkname ] ) )
					{
						return EmptyIterator::$Shared;
					}

					// Check if the arc is in the gen:arc substitution group
					$arcroleTypes = $taxonomy->getAllArcRoleTypes();
					if ( ! empty( $arcname ) )
					{
						if ( ! isset( $arcroleTypes[ $arcname ] ) )
						{
							$arcname = null;
						}
					}
					else
					{
						foreach ( $arcroleTypes as $arc => $arcroles )
						{
							if ( ! isset( $arcroles[ $arcrole ] ) )
							{
								continue;
							}

							$arcname = $arc;
							break;
						}
					}

					if ( ! $arcname || ! isset( $arcroleTypes[ $arcname ] ) )
					{
						return EmptyIterator::$Shared;
					}

					$isGeneric = $taxonomy->context->types->resolveToSubstitutionGroup( $linkname, array( \XBRL_Constants::$genLink ) ) ||
								 $taxonomy->context->types->resolveToSubstitutionGroup( $arcname,  array( \XBRL_Constants::$genArc ) );

					$roleRefs = $isGeneric
						? $taxonomy->getGenericRoleRefs( $linkRole )
						: $taxonomy->getCustomRoleRefs( $linkRole );

					if ( ! isset( $roleRefs[ $linkRole ]['arcroles'][ $arcrole ]['links'][ $linkname ]['arcelements'][ $arcname ] ) )
					{
						return EmptyIterator::$Shared;
					}

					$roleRef = $roleRefs[ $linkRole ]['arcroles'][ $arcrole ]['links'][ $linkname ]['arcelements'][ $arcname ];

					// $arcs = $roleRef['arcs'];
					$relationshipArcType = $arcname;

					$parent = array();

					if ( $axis == 'ancestor' || $axis == 'parent' )
					{
						if ( $isRoot )
						{
							return EmptyIterator::$Shared;
						}

						$ancestorList = array();
						$handledParents = array();

						// Create a list of child nodes with the ancestors in a list
						foreach ( $roleRef['arcs'] as $parentKey => $children )
						{
							foreach ( $children as $childKey => $child )
							{
								$handledParents[ $parentKey ][] = $childKey;

								$ancestorList[ $childKey ][] = $parentKey;
								if ( isset( $ancestorList[ $parentKey ] ) )
								{
									$ancestorList[ $childKey ] = array_merge( $ancestorList[ $childKey ], $ancestorList[ $parentKey ] );
									continue;
								}

								// Maybe this child has been handled already as the parent of other children
								if ( ! isset( $handledParents[ $childKey ] ) ) continue;

								// Otherwise add the ancestors of all the handled parent children
								foreach ( $handledParents[ $childKey ] as $handledChild )
								{
									$ancestorList[ $handledChild ] = array_merge( $ancestorList[ $handledChild ], $ancestorList[ $childKey ] );
								}

							}
						}

						if ( isset( $ancestorList[ $primaryItemName ] ) )
						{
							$prefix = $taxonomy->getPrefix();

							foreach ( $ancestorList[ $primaryItemName ] as $ancestor )
							{
								if ( ! isset( $roleRef['arcs'][ $ancestor ][ $primaryItemName ] ) ) continue;

								$ancestorElement = $taxonomy->getElementById( $ancestor );
								$qnAncestor = QNameValue::fromNCNameAndDefault( "$prefix:{$ancestorElement['name']}", $context->NamespaceManager, $taxonomy->getNamespace() );
								// The ancestors are accumulated in reverse order
								$ancestorNodes = array_reverse( $roleRef['arcs'][ $ancestor ][ $primaryItemName ] );

								foreach ( $ancestorNodes as $node )
								{
									$item = new conceptRelationship();
									$item->parent = $qnAncestor;
									$item->type = $source;
									if ( isset( $node['roleUri'] ) )
									{
										$item->roleUri = $node['roleUri'];
									}

									if ( isset( $node['attributes'] ) )
									{
										$item->attributes = $node['attributes'];
									}

									$nodes[] = $item;

								}

								// Only one parent
								if ( $axis == 'parent' ) break;
							}
						}
					}
					else if ( $axis == "descendant" && $isRoot )
					{
						$parent =& $roleRef['arcs'];
						// $parent =& $arcs;
					}
					else
					{
						$parent =& $roleRef['arcs'][ $primaryItemName ];
						// $parent =& $arcs[ $primaryItemName ];
					}

					foreach ( $parent as $id => $child )
					{
						foreach ( $child as $key => $node )
						{
							processNonDefinitionRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, 1, $generations, $axis, $node, $primaryItemName, $primaryItemName, true, $arcrole );
						}
					}

					// $qn = QNameValue::fromNCName( $arcName, $context->NamespaceManager );
					$qn = QNameValue::fromNCName( $arcname, $context->NamespaceManager );
					$nodes = array_map( function( /** @var conceptRelationship $item */ $item ) use( $qn, $isGeneric )
					{
						$item->arcType = $qn;
						$item->isGeneric = $isGeneric;

						return $item;
					}, $nodes );

				}
			}
		}

		$qn = QNameValue::fromNCName( $relationshipArcType, $context->NamespaceManager );
		$nodes = array_map( function( /** @var conceptRelationship $item */ $item ) use( $qn, $query )
		{
			if ( is_null( $item->arcType ) )
			{
				$item->arcType = $qn;
			}
			$item->query = $query;

			return XPath2Item::fromValueAndType( $item, XmlSchema::$AnyType );
		}, $nodes );
		unset( $node );

		return DocumentOrderNodeIterator::fromItemset( $nodes );

	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure, $ex );
}
