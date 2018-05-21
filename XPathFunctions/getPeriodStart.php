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

use lyquidity\xml\xpath\XPathNavigator;
use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\xpath\XPathNodeType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Value\DateTimeValue;
use lyquidity\xml\exceptions\ArgumentException;
use lyquidity\XPath2\XPath2Exception;

/**
 * Return the period start dateTime for finite durations and the instant dateTime for instants.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return DateTimeValue	Return the period start dateTime for finite durations and the instant
 * 							dateTime for instants. For a start date without time, the dateTime
 * 							returned is the midnight at the start of the date reported, as if the
 * 							time portion were "T00:00:00". For an instant date without time, the
 * 							dateTime returned is is the midnight at the end of the date reported,
 * 							conceptually as if the time portion were "T24:00:00".
 *
 * This function has one real argument:
 *
 * period	element(xbrli:period)	The period element for which the instant state is being sought.
 *
 */
function getPeriodStart( $context, $provider, $args )
{
	if ( count( $args ) != 1 )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPST0017", Resources::XPST0017,
			array(
				"concat",
				count( $args ),
				\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ],
			)
		);
	}

	/**
	 * @var XPath2NodeIterator $arg
	 */
	$arg = $args[0];
	if ( is_null( $arg ) ) return $args;

	$result = null;

	try
	{
		/**
		 * @var XPathNavigator $xbrlContext
		 */
		$period = CoreFuncs::CastArg( $context, $arg->CloneInstance(), SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::Node, XmlTypeCardinality::One ) );

		if ( $period instanceof XPathNavigator && $period->getLocalName() == "period" )
		{
			if ( ! $period->MoveToChild( XPathNodeType::Element ) )
			{
				throw new ArgumentException();
			}

			if ( $period->getLocalName() == "forever" )
			{
				throw XPath2Exception::withErrorCode( "xfie:PeriodIsForever", "Period is forever so does not have a start or end date" );
			}

			while ( $period->getLocalName() != "startDate" && $period->getLocalName() != "instant" )
			{
				if ( ! $period->MoveToNext( XPathNodeType::Element ) )
				{
					throw new ArgumentException();
				}
			}
		}
		else if ( ! $period instanceof XPathNavigator || ( $period->getLocalName() != "startDate" && $period->getLocalName() != "instant" ) )
		{
			throw new ArgumentException();
		}

		$date = trim( $period->getValue() );
		if ( strpos( $date, "T" ) == false )
		{
			// BMS 2018-03-20 Changed to return a DateTimeValue and set the start to 00:00:00
			// 				  This change was for the conformance test 12050 V-06
			// BMS 2018-03-20 Further updated to let this condition flow through.
			//				  Presumably this clause was added to satisfy one of the 60000 series tests
			// return DateTimeValue::Parse( $date . "T00:00:00" );
		}
		else
		{
			return DateTimeValue::Parse( $date );
		}

		if ( strpos( $date, "T" ) == false )
		{
			$date .= ( $period->getLocalName() != "instant" ? "T00:00:00" : "T24:00:00" );
		}

		$result = DateTimeValue::Parse( $date );
		return $result;
	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:PeriodIsForever" )
		{
			throw $ex;
		}
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", $ex->getMessage() );
}
