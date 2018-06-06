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

namespace XBRL\functions\lyquidity;

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\xml\exceptions\InvalidOperationException;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "source/XPathFunctions/getFactScenarioRemainder.php";
require_once "source/XPathFunctions/getNodesCorrespond.php";

/**
 * Perform the aspect test for non XDT segments.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPathNavigator
 *
 * This function has one real argument:
 *
 * item	schema-element(xbrli:item)	The item element for which the context is being sought.
 *
 */
function getNonXDTScenarioAspectTest( $context, $provider, $args )
{
	if ( count( $args ) != 2 )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPST0017", Resources::XPST0017,
			array(
				"concat",
				count( $args ),
				\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ],
			)
		);
	}

	try
	{
		$remainderA = \XBRL\functions\getFactScenarioRemainder( $context, $provider, array( $args[0] ) );
		$remainderB = \XBRL\functions\getFactScenarioRemainder( $context, $provider, array( $args[1] ) );

		$remainderACount = $remainderA->getCount();
		$remainderBCount = $remainderB->getCount();

		if ( $remainderACount != $remainderBCount )
		{
			return CoreFuncs::$False;
		}

		if ( $remainderACount == $remainderBCount )
		{
			return CoreFuncs::$True;
		}

		// Time to compare two facts
		while ( $remainderA->MoveNext() )
		{
			$moved = $remainderB->MoveNext();

			if ( ! $moved )
			{
				throw new InvalidOperationException( "Unable to move to remainder B in getFactScenarioRemainder" );
			}

			if ( \XBRL\functions\getNodesCorrespond( $context, $provider, array( $remainderA->current(), $remainderB ) ) == CoreFuncs::$False )
			{
				return CoreFuncs::$False;
			}
		}

		return CoreFuncs::$True;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );

}
