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

use lyquidity\XPath2\XPath2Item;
use lyquidity\XPath2\Value\DateTimeValue;
use lyquidity\XPath2\TrueValue;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\Value\DateValue;
use lyquidity\XPath2\Value\DayTimeDurationValue;
use lyquidity\XPath2\DOM\XmlSchema;
use lyquidity\XPath2\FalseValue;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\xml\xpath\XPathNavigator;

/**
 * Return a string representation of a date minimized to exclude any time portion.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPathNavigator
 *
 * This function has one real argument:
 *
 * item	DateValue|DateTimeValue	The item for which a string is to be returned
 * end Boolean True if the date represents a duration end
 *
 */
function getMinimizedDateString( $context, $provider, $args )
{
	if ( count( $args ) != 2 )
	{
		throw XPath2Exception::withErrorCodeAndParams( "GeneralXFIFailure", Resources::GeneralXFIFailure,
			array(
				"lyquidity:minimized-date-string",
				count( $args ),
				\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LYQUIDITY ],
			)
		);
	}

	if ( $args[0] instanceof XPath2Item )
	{
		$args[0] = $args[0]->getTypedValue();
	}

	if ( ! $args[0] instanceof DateTimeValue && ! $args[0] instanceof DateValue )
	{
		throw XPath2Exception::withErrorCodeAndParams( "FORG0006", Resources::FORG0006 . " (DateTime parameter)",
			array(
				"lyquidity:minimized-date-string",
				gettype( $args[0]),
			)
		);
	}

	if ( $args[1] instanceof XPath2Item )
	{
		$args[1] = $args[1]->getTypedValue();
	}

	if ( ! $args[1] instanceof TrueValue && ! $args[1] instanceof FalseValue )
	{
		throw XPath2Exception::withErrorCodeAndParams( "FORG0006", Resources::FORG0006 . " (Boolean parameter)",
			array(
				"lyquidity:minimized-date-string",
				gettype( $args[1]),
			)
		);
	}

	if ( $args[0] instanceof DateValue || ( $args[1] instanceof TrueValue && $args[0]->getSeconds() !== 0.0 ) )
	{
		return XPath2Item::fromValueAndType( (string)$args[0], XmlSchema::$String );
	}

	$date = new DateValue( $args[0]->S, $args[0]->Value );

	if ( $args[1] instanceof TrueValue )
	{
		$date = $date->AddDayTimeDuration( $date, DayTimeDurationValue::Parse("-P1D" ) );
	}

	return XPath2Item::fromValueAndType( (string)$date, XmlSchema::$String );
}
