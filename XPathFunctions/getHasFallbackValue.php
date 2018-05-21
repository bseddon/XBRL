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

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns true() for factVariables that have been assigned a fallback value, for use in XPath expressions of a precondition,
 * a consistency assertion, value assertion, formula aspect rule, or generic message XPath expression. The function is not
 * applicable to variable-set variable and filter expressions.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return bool	Returns true() if the argument variable is a factVariable and its value has been determined on the basis
 * 				of the content of the @fallbackValue attribute. Returns false() if the argument variable is a factVariable
 * 				and its value has been determined on the basis of its source sequence rather than the content of a
 * 				@fallbackValue attribute. Returns false() if the argument is not a factVariable (e.g., generalVariable,
 * 				formula parameter, or a variable assigned by an XPath 'for', 'some', or 'every' clause).
 *
 * The function implemented by this function has two arguments:
 *
 * aspect		xs:token			The aspect value MUST be one of: concept entity-identifier location period unit
 * 									complete-segment complete-scenario non-XDT-segment non-XDT-scenario dimension
 * 									See output, below, for the value of the function for each aspect.
 *
 * dimension	xs:QName?			For a dimension aspect, this parameter MUST be provided and is the QName of the dimension.
 * 									For other aspects, this parameter may be omitted, or may be supplied as an empty sequence.
 *
 *
 * @throws xffe:invalidFunctionUse	This error MUST be thrown if the this function is referenced in an XPath expression
 * 									invoked in variable-set variable evaluation, such as by a generalVariable or filter
 * 									XPath expression. A formula processor MAY detect this error as a static compilation-time
 * 									error if it this function is referenced in variable-set variable evaluation expressions
 * 									(by generalVariable or filters).
 * 									A formula processor MUST detect this error as a dynamic run-time error if it this function
 * 									is referenced in variable-set variable evaluation expressions (by generalVariable or
 * 									filters), such as during execution of an XPath or other language implementation of custom
 * 									functions that were invoked dynamically in variable-set evaluation expressions (by
 * 									generalVariable or filters).
 * 									This error MUST be thrown if the this function is referenced in an XPath expression
 * 									invoked in an XPath function related to an existence assertion, including if referenced
 * 									in an XPath expression from generic messages relating to an existence assertion.
 */
function getHasFallbackValue( $context, $provider, $args )
{
	try
	{
		/**
		 * @var QNameValue $qname
		 */
		$qname = CoreFuncs::CastArg( $context, $args[0], SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::QName, XmlTypeCardinality::One ) );

		// For now, throw an exception
		throw XPath2Exception::withErrorCode( "xffe:invalidFunctionUse", "At the moment all use is invalid" );

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
