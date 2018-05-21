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
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns a sequence containing the set of the uncovered dimensional aspects for use in XPath expressions of a
 * consistency assertion, value assertion, formula aspect rule, or generic message XPath expression. The function is
 * not applicable to variable-set variable evaluation and filter expressions.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return xs:QName	Returns a sequence of the uncovered dimensional aspects given that the aspect-model of the
 * 					variable-set is dimensional. This sequence includes the QNames of the uncovered dimensions
 * 					that are aspects of the variable-set evaluation. For non-dimensional aspects, given the
 * 					dimensional aspect model, see the function xff:uncovered-non-dimensional-aspects. The order
 * 					of uncovered aspect dimension QNames is not specified and may not be consistent even for the
 * 					same fact set. (Note: the definition of a set requires that it have distinct members.) If
 * 					all dimensional aspects are covered, or there are none, the output is an empty sequence.
 *
 * The function implemented by this function has no real arguments:
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
function getUncoveredDimensionalAspects( $context, $provider, $args )
{
	try
	{
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
