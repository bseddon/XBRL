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

require_once __DIR__ . '/lyquidity/iterators/XBRLFilterMemberNetwork.php';

use XBRL\functions\lyquidity\iterators\XBRLFilterMemberNetwork;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\TypeCode;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns a sequence containing a selected set of dimension member QNames for the specified explicit dimension
 * considering only those members that have the specified relationship axis to the specified origin member in
 * the network of effective relationships with the specified link role for the specified arc role. The set of
 * dimension member QNames is in an arbitrary order (not necessarily that of effective tree relationships order).
 *
 * Notes:
 *
 * Note that the relationships can be expressed by an arc element that conforms to the requirements set out in
 * the XBRL 2.1 specification. Importantly, the relationships do not have to be expressed by XBRL 2.1 definition
 * arc elements and they do not have to have arcroles defined in the XBRL Dimensions specification. Note that
 * if the QName given for the member argument is not a member of the relevant explicit dimension, the function
 * returns the empty sequence.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName*	Returns a sequence which is the set of reportable dimension member QNames for the specified
 * 						explicit dimension per the inputs described above. (Note: the definition of a set requires
 * 						that it have distinct members.)
 *
 * @throw xfie:invalidDimensionQName	This error MUST be thrown if the dimension is not in the reference
 * 										discoverable taxonomy set.
 *
 * This function has one real argument:
 *
 * dimension	xs:QName	The QName of the dimension. This input is provided so that the function can check that the
 * 							members selected from the network are members of the dimension, and only return those that are.
 *
 * member		xs:QName	The QName of the dimension member that the selection criteria specified by the axis parameter
 * 							are going to be applied relative to.
 *
 * linkrole		xs:string	The linkrole value that specifies the network of effective relationships to determine the
 * 							selected members on the specified axis from the member used as the origin.
 *
 * arcrole		xs:string	The arcrole value that specifies the network of effective relationships as above.
 *
 * axis			xs:string	The axis value MUST be one of: descendant-or-self child-or-self descendant child
 *
 * 							If the axis value is 'child' then the result includes those domain members in the
 * 							explicit dimension domain that are targets of effective arcrole relationships from
 * 							the member identified by QName in the linkrole network.
 *
 * 							If the axis value is 'child-or-self' then the result includes the domain member identified
 * 							by the member QName and those domain members in the explicit dimension domain that are targets
 * 							of effective arcrole relationships from the domain member identified by QName value.
 *
 * 							If the axis value is 'descendant' then the filter-member set includes those domain members
 * 							in the explicit dimension domain that are descendants of the member QName, in the linkrole
 * 							network of arcrole effective relationships.
 *
 * 							If the axis value is 'descendant-or-self' then the filter-member set includes includes the
 * 							domain member identified by the member QName and those domain members in the explicit
 * 							dimension domain that are descendants of the member QName, in the linkrole network of
 * 							arcrole effective relationships.
 *
 */
function filterMemberNetworkSelection( $context, $provider, $args )
{
	try
	{
		if ( ! $args[0] instanceof QNameValue || ! $args[1] instanceof QNameValue )
		{
			throw new \InvalidArgumentException();
		}

		for( $i=2; $i<= 4; $i++ )
		{
			$arg = $args[$i];
			if ( $arg instanceof XPath2Item && $arg->GetTypeCode() == TypeCode::String )
			{
				$args[ $i ] = $arg = $arg->getValue();
			}

			if ( ! is_string( $arg ) )
			{
				throw new \InvalidArgumentException();
			}
		}

		if ( ! isset( $context->xbrlTaxonomy ) )
		{
			throw new \InvalidArgumentException( "XBRL taxonomy not set in context" );
		}

		$iterator = new XBRLFilterMemberNetwork( $context, $args[0], $args[1], $args[2], $args[3], $args[4] );
		$iterator->MoveNext(); // Force evaluation so any errors are triggered
		$iterator->Reset();
		return $iterator;

	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidDimensionQName" )
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
