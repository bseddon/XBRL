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

require_once __DIR__ . '/lyquidity/iterators/InstanceFactsIterator.php';

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use XBRL\functions\lyquidity\iterators\InstanceFactsIterator;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "checkIsTuple.php";
require_once "xbrlInstance.php";

/**
 * Returns the sequence of all tuples that are direct children of the tuple element of an XBRL
 * instance document (excluding any tuples further nested in enclosed tuples).
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return element(xbrli:tuple)*	Returns a sequence of direct child tuples of the input parameter
 * 									tuple element in the XBRL instance, or the empty sequence if none.
 *
 * This function has one real argument:
 *
 * root element(xbrli:tuple)	The function takes the tuple element of an XBRL instance as its only parameter.
 *
 */
function getTuplesInTuple( $context, $provider, $args )
{
	try
	{
		// There should be one argument and it should be the <xbrl> element
		if ( ( ! $args[0] instanceof XPath2NodeIterator ) || $args[0]->getCount() != 1 )
		{
			throw new \InvalidArgumentException();
		}

		if ( ! $args[0]->MoveNext() || ! checkIsTuple( $context, $args[0], false ) )
		{
			throw new \InvalidArgumentException();
		}

		if ( ! isset( $context->xbrlInstance ) )
		{
			throw new \InvalidArgumentException( "XBRL Instance not set in context" );
		}

		$result = InstanceFactsIterator::fromIterator( $context, $args[0], XFI_TUPLE_TUPLES);
		// $count = $result->getCount();
		return $result;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
