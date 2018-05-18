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
 * @version 0.1.1
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
 * @param array $roleRef A reference to the presentation role being processed
 * @param int $depth The current iteration depth (for the descendant axis)
 * @param int $generations The maximum depth (zero or null mean infinite)
 * @param string $axis The name of the axis to use
 * @param array $node The current node located for $path
 * @param string $path The path used to locate the node
 * @param array $parentKey The key to the parent of node
 */
function processPresentationRelationship( &$context, &$taxonomy, &$nodes, &$parents, &$roleRef, $depth, $generations, $axis, &$node, $path, $parentKey )
{
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

			if ( ! isset( $node['children'] ) )
			{
				break;
			}

			foreach ( $node['children'] as $id => $child )
			{
				$childElement = $taxonomy->getElementById( $id );
				$qnChild = QNameValue::fromNCName( "$prefix:{$childElement['name']}", $context->NamespaceManager );

				$item = new conceptRelationship();
				$item->parent = $qnNode;
				$item->type = $qnChild;

				$nodes[] = $item;
			}

			break;

		case "descendant":

			if ( $parentKey && $depth > 0 )
			{
				$parentElement = $taxonomy->getElementById( $parentKey );
				$qnParent = QNameValue::fromNCName( "$prefix:{$parentElement['name']}", $context->NamespaceManager );

				$item = new conceptRelationship();
				$item->parent = $qnParent;
				$item->type = $qnNode;

				$nodes[] = $item;
			}

			if ( isset( $node['children'] ) )
			{
				// Need to handle more than one level
				foreach ( $node['children'] as $id => $child )
				{
					processPresentationRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, $depth + 1, $generations, $axis, $child, "$path/{$child['label']}", $node['label'] );
				}
			}

			break;

		case "ancestor":

			$parts = explode( "/", $path );

			// Trim the last one
			array_pop( $parts );
			$parts = array_reverse( $parts );

			// Restrict the number of ancestors to the number in the generations
			if ( ! is_null( $generations ) && $generations > 0 )
			{
				while ( count( $parts ) > $generations )
				{
					array_pop( $parts );
				}
			}

			foreach ( $parts as $id )
			{
				$parentElement = $taxonomy->getElementById( $id );
				$qnParent = QNameValue::fromNCName( "$prefix:{$parentElement['name']}", $context->NamespaceManager );

				$item = new conceptRelationship();
				$item->parent = $qnParent;
				$item->type = $qnNode;

				$nodes[] = $item;
			}

			break;

		case "sibling":

			// Go to parent and collect all the *other* children
			$parts = explode( "#", $parentKey );
			$label = $node['label'];

			if ( ! $parentKey )
			{
				break;
			}

			$taxonomy->processNodeByPath(
				$roleRef['hierarchy'],
				$roleRef['paths'][ $parts[1] ],
				$parentKey,
				function( &$node, $path, $parentKey ) use( &$context, &$taxonomy, &$nodes, &$roleRef, $axis, $generations, $label )
				{
					$prefix = $taxonomy->getPrefix();
					$parentElement = $taxonomy->getElementById( $node['label'] );
					$qnParent = QNameValue::fromNCName( "$prefix:{$parentElement['name']}", $context->NamespaceManager );

					if ( ! isset( $node['children'] ) )
					{
						return;
					}

					foreach ( $node['children'] as $id => $child )
					{
						if ( $id == $label )
						{
							continue;
						}

						$childElement = $taxonomy->getElementById( $id );
						$qnChild = QNameValue::fromNCName( "$prefix:{$childElement['name']}", $context->NamespaceManager );

						$item = new conceptRelationship();
						$item->parent = $qnParent;
						$item->type = $qnChild;

						$nodes[] = $item;
					}
				}
			);

			break;

		case "parent":

			$parts = explode( "/", $path );
			if ( count( $parts ) == 1 ) break;

			// Trim the last one - it's me!
			array_pop( $parts );

			// The one on the end is my parent
			$parent = array_pop( $parts );

			$parentElement = $taxonomy->getElementById( $parent );
			$qnParent = QNameValue::fromNCName( "$prefix:{$parentElement['name']}", $context->NamespaceManager );

			$item = new conceptRelationship();
			$item->parent = $qnParent;
			$item->type = $qnNode;

			$nodes[] = $item;

			break;

		case "sibling-or-descendant":

			processPresentationRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, $depth, $generations, "sibling", $node, $path, $parentKey );
			processPresentationRelationship( $context, $taxonomy, $nodes, $parents, $roleRef, $depth, $generations, "descendant", $node, $path, $parentKey );

			break;
	}
}
