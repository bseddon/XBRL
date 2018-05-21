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

namespace XBRL\Formulas\Resources\Formulas\Aspects;

use XBRL\Formulas\Resources\Formulas\Formula;

/**
 * A base class for the dimension aspects
 */
class Dimension extends Aspect
{
	/**
	 * QName of the dimension
	 * @var array $dimension
	 */
	public $dimension;

	/**
	 * If true the defined dimension/member(s) will be removed if it exists
	 * @var bool $omit
	 */
	public $omit = false;

	/**
	 * Convers an XBRL_Instance version of a dimension segment or scenario to an indexed version
	 * @param array $context
	 * @param bool $explicit True if the array being converted prepresents an explicit dimension
	 * @param Formula $formula
	 * @param string $aspectDimension
	 * @return array
	 */
	public static function contextToIndex( $context, $explicit, $formula = null, $aspectDimension = null )
	{
		$result = array();

		foreach ( $context as $key => $dimension )
		{
			$qname = qname( $dimension['dimension'], $formula->xbrlInstance->getInstanceNamespaces() );
			if ( $qname->clarkNotation() != $aspectDimension ) continue;
			if ( $explicit )
			{
				$result[ $dimension['dimension'] ][ $dimension['member'] ] = $dimension['member'];
			}
			else
			{
				$result[ $dimension['dimension'] ] = $dimension['member'];
			}
		}

		return $result;
	}

	/**
	 * Convert a dimension into a context
	 * @param array $index
	 * @return array
	 */
	public static function IndexToContext( $index )
	{
		$result = array();
		foreach ( $index as $dimension => $members )
		{
			foreach ( $members as $memberQName => $member )
			{
				if ( is_array( $member ) )
				{
					$x = array( $memberQName => $member );
					// Remove the dimension from the member
					foreach ( $x as $key => $xml )
					{
						// $x[ $key ] =  str_replace( "</$memberQName>", "", str_replace( "<$memberQName>", "", $xml ) );
					}
					$result[] = array( 'dimension' => $dimension, 'member' => $x );
				}
				else
				{
					$result[] = array( 'dimension' => $dimension, 'member' => $member );
				}
			}
		}

		return $result;
	}

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
		// Get the relevant source for this aspect
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$attributes = $node->attributes();
		if ( ! property_exists( $attributes, 'dimension' ) )
		{
			$log->formula_validation( "Dimension Aspect", "Missing the 'dimension' attribute",
				array()
			);
			return null;
		}

		$namespaces = $node->getDocNamespaces( true );
		$dimensionQName = qname( (string)$attributes->dimension, $namespaces );

		$this->dimension = array(
			'name' => $dimensionQName->localName,
			'originalPrefix' => $dimensionQName->prefix,
			'namespace' => $dimensionQName->namespaceURI,
		);

		$result['dimension'] = $this->dimension;

		if ( $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] )->omit )
		{
			$this->omit = true;
		}

		$result['omit'] = $this->omit;

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}
}