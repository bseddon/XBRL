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

use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Value\QNameValue;

/**
 * Add the relationships to the nodes list
 * @param XPath2Context $context The global context
 * @param XBRL $taxonomy The active taxonomy
 * @param array $nodes The list of nodes being created
 * @param array $parents A list of the parents used
 * @param array $arcRef A reference to the non-dimension role being processed
 * @param int $depth The current iteration depth (for the descendant axis)
 * @param int $generations The maximum depth (zero or null mean infinite)
 * @param string $axis The name of the axis to use
 * @param array $node The current node located for $path
 * @param string $path The path used to locate the node
 * @param array $parentKey The key to the parent of node
 * @param bool $multipleChildNodes
 * @param string $arcRole The arcRole of the relationship
 * @param bool True if the 'to' element can have multiple nodes such as when processing generic links but not when processing calculation links
 */
function processNonDefinitionRelationship( &$context, &$taxonomy, &$nodes, &$parents, &$arcRef, $depth, $generations, $axis, &$node, $path, $parentKey, $multipleChildNodes, $arcRole )
{
	$parents[] = $parentKey;

	$prefix = $taxonomy->getPrefix();
	$element = $taxonomy->getElementById( $node['label'] );
	$qnNode = QNameValue::fromNCName( "$prefix:{$element['name']}", $context->NamespaceManager );

	if ( ! is_null( $generations ) && $generations > 0 &&  $depth > $generations )
	{
		return;
	}

	switch ( $axis )
	{
		case "child":

			if ( $parentKey )
			{
				$parentElement = $taxonomy->getElementById( $parentKey );
				$qnParent = QNameValue::fromNCName( "$prefix:{$parentElement['name']}", $context->NamespaceManager );
			}
			else
			{
				$qnParent = null;
			}

			$item = new conceptRelationship();
			$item->parent = $qnParent;
			$item->type = $qnNode;
			if ( isset( $node['roleUri'] ) )
			{
				$item->roleUri = $node['roleUri'];
			}

			if ( $arcRole == \XBRL_Constants::$arcRoleSummationItem && isset( $node['weight'] ) )
			{
				// BMS 2018-04-09 Test candidates changed.
				$item->attributes = array( 'weight' => array( 'type' => 'xs:decimal', 'value' => $node['weight'] ) );
			}
			else if ( isset( $node['attributes'] ) )
			{
				$item->attributes = $node['attributes'];
			}

			$nodes[] = $item;

			break;

		case "descendant":

			if ( $parentKey )
			{
				$parentElement = $taxonomy->getElementById( $parentKey );
				$qnParent = QNameValue::fromNCName( "$prefix:{$parentElement['name']}", $context->NamespaceManager );

				$item = new conceptRelationship();
				$item->parent = $qnParent;
				$item->type = $qnNode;
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

			// Need to handle more than one level.  Look in the $arcRef
			if ( ! isset( $arcRef['arcs'][ $node['to'] ] ) )
			{
				break;
			}

			// Prevent endless loops unless there is a specific generation depth defined
			if ( $generations == 0 && in_array( $node['to'], $parents ) )
			{
				return;
			}

			foreach ( $arcRef['arcs'][ $node['to'] ] as $id => $child )
			{
				if ( $multipleChildNodes )
				{
					foreach ( $child as $key => $childNode )
					{
						processNonDefinitionRelationship( $context, $taxonomy, $nodes, $parents, $arcRef, $depth + 1, $generations, $axis, $childNode, "$path/{$childNode['to']}", $node['to'], $multipleChildNodes, $arcRole );
					}
				}
				else
				{
					processNonDefinitionRelationship( $context, $taxonomy, $nodes, $parents, $arcRef, $depth + 1, $generations, $axis, $child, "$path/{$child['to']}", $node['to'], $multipleChildNodes, $arcRole );
				}
			}

			break;

		case "ancestor":

			// This case is handled directly in getConceptRelations()
			break;

		case "sibling":

			break;

		case "parent":

			// This case is handled directly in getConceptRelations()
			break;

		case "sibling-or-descendant":

			break;
	}
}
