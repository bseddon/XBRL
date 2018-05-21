<?php

/**
 * XBRL Formulas
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

namespace XBRL\Formulas\Resources\Assertions;

use XBRL\Formulas\Resources\Resource;
use lyquidity\xml\exceptions\InvalidOperationException;
use XBRL\Formulas\FactVariableBinding;

/**
 * Base class for XBRL variable filters
 */
class AssertionSet extends Resource
{
	/**
	 * Label of this assertion
	 * @var string
	 */
	public $label;

	/**
	 * The text from an generic label if defined
	 * @var string $description
	 */
	public $description;

	/**
	 * The role of the source link role uri
	 * @var string $extendedLinkRoleUri
	 */
	public $extendedLinkRoleUri;

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		$result = array(
			'type' => 'assertionset',
			'assertionsetType' => 'assertionSet',
			'label' => $label,
		);

		$result = array_merge( $result, parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log ) );

		return $result;
	}

	/**
	 * Stores a node array with a resource type name of an 'assertion set'
 	 * @param array $node A an array representation of the resource node
 	 * @param string $type
	 */
	public function storeFilter( $node, $type )
	{
		$node['type'] = 'assertion';
		$node['assertionType'] = $type;

		return $node;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @throws InvalidOperationException
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		throw new InvalidOperationException( "The toQuery method of the base assertion set class should not be called" );
	}

	/**
	 * Provide a list of arcroles that are valid this resource
	 */
	public function getDescendantArcroles()
	{
		return array( \XBRL_Constants::$arcRoleAssertionSet );
	}

}
