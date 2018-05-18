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

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Value\DateTimeValue;
use lyquidity\XPath2\Proxy\ValueProxy;
use lyquidity\XPath2\Value\DateValue;
use lyquidity\XPath2\Value\DayTimeDurationValue;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns true if two arguments are equal in period end or instant dateTime. Each argument may be either a
 * xs:date or an xs:dateTime (e.g., xbrldi:dateUnion). If arguments are mixed (one xs:date and other xs:dateTime)
 * the xs:date is defined as the xs:dateTime of the midnight ending the date (24:00 hours of that date).
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return bool	Returns true if the two arguments are equal, considering any argument of xs:date to be at 24:00
 * 				hours of that date.
 *
 * This function has two real arguments:
 *
 * left	node()*	The first node or sequence of nodes.
 * right node()*	The second node or sequence of nodes.
 *
 */
function getEndEqual( $context, $provider, $args )
{
	try
	{
		// There should be two arguments and each argument should be a node iterator or DateTimeValue
		// There shold be the same count in each node.


		$date1 = null;

		if ( $args[0] instanceof XPath2NodeIterator )
		{
			$flag1 = $args[0]->MoveNext();
			if ( ! $flag1 )
			{
				return CoreFuncs::$False;
			}

			/**
			 * @var XPathNavigator $item1
			 */
			$item1 = $args[0]->getCurrent()->CloneInstance();

			$date = trim( $item1->getValue() );
			if ( strpos( $date, "T" ) == false )
			{
				$date .= "T24:00:00";
			}

			$date1 = DateTimeValue::Parse( $date );

		}
		else if ( $args[0] instanceof DateValue )
		{
			$date1 = $args[0];
			$date1 = DateTimeValue::fromDate( $date1->S, $date1->getValue() );
			$date1 = DateTimeValue::AddDayTimeDuration( $date1, DayTimeDurationValue::Parse( "P1D" ) );
		}
		else if ( $args[0] instanceof DateTimeValue )
		{
			$date1 = $args[0];
		}
		else
		{
			throw new \InvalidArgumentException();
		}


		$date2 = null;

		if ( $args[1] instanceof XPath2NodeIterator )
		{
			$flag2 = $args[1]->MoveNext();
			if ( ! $flag2 )
			{
				return CoreFuncs::$False;
			}

			/**
			 * @var XPathNavigator $item2
			 */
			$item2 = $args[1]->getCurrent()->CloneInstance();

			$date = trim( $item2->getValue() );
			if ( strpos( $date, "T" ) == false )
			{
				$date .= "T24:00:00";
			}

			$date2 = DateTimeValue::Parse( $date );
		}
		else if ( $args[1] instanceof DateValue )
		{
			$date2 = $args[1];
			$date2 = DateTimeValue::fromDate( $date2->S, $date2->getValue() );
			$date2 = DateTimeValue::AddDayTimeDuration( $date2, DayTimeDurationValue::Parse( "P1D" ) );
		}
		else if ( $args[1] instanceof DateTimeValue )
		{
			$date2 = $args[1];
		}
		else
		{
			throw new \InvalidArgumentException();
		}

		if ( ! ValueProxy::EqValues( $date1, $date2, $result ) || ! $result )
		{
			return CoreFuncs::$False;
		}

		return CoreFuncs::$True;
	}
	catch ( XPath2Exception $ex)
	{
		if ( $ex->ErrorCode == "xffe:invalidFunctionUse" )
		{
			throw $ex;
		}
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
