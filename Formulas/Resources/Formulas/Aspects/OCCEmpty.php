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

/**
 * A class represent an empty segment/scenario
 */
class OCCEmpty extends Aspect
{
	/**
	 * Value MUST be segment or scenario
	 * @var string|null
	 */
	public $occ;

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->combinable = true;
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
		if ( ! property_exists( $attributes, 'occ' ) )
		{
			$log->formula_validation( "Formula Aspect rule", "'occ' attribute does not exist",
				array(
					'label' => $label
				)
			);
		}
		else
		{
			$this->occ = (string)$attributes->occ;
			if ( $this->occ != 'scenario' && $this->occ != 'segment' )
			{
				$log->formula_validation( "Formula Aspect rule", "The 'occ' attribute value MUST be 'scenario' or 'segment'",
					array(
						'label' => $label
					)
				);
			}
			else
			{
				$result['occ'] = $this->occ;
			}
		}

		$result = parent::storeAspect( $result, $localName );

		return $result;
	}
}
