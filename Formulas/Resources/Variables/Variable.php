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

namespace XBRL\Formulas\Resources\Variables;

use XBRL\Formulas\Resources\Resource;
use lyquidity\xml\QName;

/**
 * A class to process a general variable definitions
 */
class Variable extends Resource
{
	/**
	 * The element xlink label
	 * @var string $label
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
	 * The qname of the fact source instance for this variable
	 * @var string $instanceSourceQName
	 */
	public $instanceSourceQName = null;

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
		$this->label = $label;

		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		return $result;
	}

	/**
	 * Stores a node array with a resource type name of 'variable'
 	 * @param array $result A an array representation of the resource node
 	 * @param string $type 'fact' | 'general' | 'parameter'
 	 * @return array;
	 */
	public function storeVariable( $result, $type )
	{
		$result['type'] = 'variable';
		$result['variableType'] = $type;

		return $result;
	}

	/**
	 * Cache of the qname generated from a 'name' array
	 * @var QName  $qname
	 */
	private $qname;

	/**
	 * Return the QName of the 'name' property if any
	 * @return NULL|QName
	 */
	public function getQName()
	{
		// Use the cached name if there is one.
		if ( ! $this->qname )
		{
			if ( ! property_exists( $this, 'name' ) ) return null;
			$this->qname = $qname = new QName( $this->name['originalPrefix'], $this->name['namespace'], $this->name['name'] );
		}

		return $this->qname;
	}

	/**
	 * Returns true if the uncovered aspect facts of the binding are compatible with $uncoveredAspectFacts
	 * @param VariableSet $variableSet
	 * @param Array $uncoveredAspectFacts
	 * @param VariableBinding $binding
	 * @return boolean
	 */
	public function matchUncoveredAspects( $variableSet, $uncoveredAspectFacts, $binding )
	{
		return true;
	}
}
