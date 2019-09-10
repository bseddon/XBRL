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
use lyquidity\XPath2\XPath2Context;

// Make sure any required functions are imported
require_once "getFactExplicitDimensionValue.php";

/**
 * Note: this function never made it to a recommended status so for now it passes the parameters to
 * xfi:fact-explicit-dimension-value.  This function probably should make sure the dimension uses the scenario OCC
 *
 * Returns the child element of the segment or scenario that contains the typed dimension value if there is a value
 * for the dimension in either the segment or scenario of the item and returns the empty sequence otherwise.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName?	Returns the QName of the value for the dimension that is reported in the segment or scenario
 * 						of the item and the empty sequence if the dimension is not reported for the item and no default
 * 						is applicable. (Reports the dimension default value when applicable.)
 *
 * @throws xfie:invalidExplicitDimensionQName	This error MUST be thrown the dimension QName is not a explicit dimension
 * 												in the reference discoverable taxonomy set. This error is only raised if
 * 												the QName is not that of a dimension. If the dimension is valid and inapplicable
 * 												to the fact, then the fact does not have that dimension, and false is returned.
 *
 * This function has two real arguments:
 *
 * item			schema-element(xbrli:item)	The item that the dimension is to be reported for.
 * dimension	xs:QName					The QName of the dimension being tested for.
 *
 */
function getFactExplicitScenarioDimensionValue( $context, $provider, $args )
{
	return getFactExplicitDimensionValue( $context, $provider, $args );
}
