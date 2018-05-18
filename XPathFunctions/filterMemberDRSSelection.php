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

require_once __DIR__ . '/lyquidity/iterators/XBRLFilterMemberDRS.php';

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\TypeCode;
use lyquidity\xml\MS\XmlTypeCode;
use XBRL\functions\lyquidity\iterators\XBRLFilterMemberDRS;
use lyquidity\XPath2\XPath2Exception;
/**
 * Returns a sequence containing a select set of dimension member QNames for the specified explicit dimension considering
 * only those members that have the specified relationship axis to the specified origin member in the network of effective
 * relationships with the specified link role for the specified arc role. The set of dimension member QNames is in an
 * arbitrary order (not necessarily that of effective tree relationships order).
 *
 * Notes:
 *
 * Note that the relationships considered by this function are those expressed by an arc elements that conform to the
 * requirements set out in the XBRL Dimensions specification.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:QName*	Returns a sequence which is the set of reportable dimension member QNames for the specified
 * 						explicit dimension per the inputs described above. (Note: the definition of a set requires
 * 						that it have distinct members.)
 *
 * 						The relationship source is determined by the primary item concept QName and the dimension
 * 						member QName.
 *
 * 						If a linkrole parameter is provided, then it specifies the base set in which the primary
 * 						items are associated to the combination of hypercubes that is the 'head' of the DRS, e.g.,
 * 						the relationship source primary item concept is the DRS head primary item or inherits
 * 						hypercubes from it, and the effective domain is consecutively related to that base set's
 * 						hypercubes.
 *
 * 						If the linkrole parameter is absent (an empty sequence or an empty string is provided as
 * 						parameter value), then all DRS link roles that connect the primary items and specified
 * 						dimension's domain contribute to the effective domain. An arcrole parameter is not relevant
 * 						for DRS relationship axes.
 *
 * 						The filter-member network is determined by the DRS network arcroles, for the relationships
 * 						from the primary items, to the relationship source and target dimension member.
 *
 * 						If the axis parameter is DRS-child, then the filter-member set includes those domain members
 * 						in the explicit dimension domain that are valid child consecutive-relationship targets.
 *
 * 						If the axis parameter is DRS-descendant, then the filter-member set includes those domain
 * 						members in the explicit dimension domain that are valid descendant consecutive-relationship
 * 						targets.
 *
 * 						The filter member network includes all domain-member consecutive relationships (child or descendant)
 * 						in the effective domain, from the relationship source's dimension member (valid for the
 * 						relationship source primary item), to the relationship target's dimension member (valid for
 * 						the context item fact's primary item).
 *
 * @throw xfie:invalidDimensionQName			This error MUST be thrown if the dimension is not in the reference
 * 												discoverable taxonomy set.
 *
 * @throw xfie:invalidPrimaryItemConceptQName	This error MUST be thrown if the primary item concept QName is not
 * 												in the reference discoverable taxonomy set. (The error is not thrown
 * 												if the concept QName does not have a primary item relationship to the
 * 												dimension, or if it does not yield any member results.)
 *
 * This function has one five arguments:
 *
 * dimension			xs:QName	The QName of the dimension. This input is provided so that the function can check that
 * 									the members selected from the network are members of the dimension, and only return
 * 									those that are.
 *
 * primary-item-concept	xs:QName	The QName of the primary item concept that has or inherits hypercube relationships in
 * 									the base set of the DRS for which member relationships are to be found.
 *
 * member				xs:QName	The QName of the dimension member that the selection criteria specified by the axis
 * 									parameter are going to be applied relative to.
 *
 * linkrole				xs:string?	If a linkrole parameter is provided, then it specifies the base set in which the primary
 * 									items are associated to the combination of hypercubes that is the 'head' of the DRS,
 * 									e.g., the relationship source primary item concept is the DRS head primary item or
 * 									inherits hypercubes from it, and the effective domain is consecutively related to that
 * 									base set's hypercubes.
 *
 * 									The linkrole may be omitted by providing an empty sequence or an empty string value
 * 									for this parameter. When it is omitted, all DRS members of the specified axis are
 * 									provided, for all base sets in which the primary item is related to hypercubes.
 *
 * axis					xs:string	The axis value MUST be one of: DRS-descendant or DRS-child
 *
 * 									If the axis value is 'DRS-child' then the result includes those domain members in the
 * 									explicit dimension domain that are targets of DRS relationships from the member
 * 									identified by QName in the linkrole network (if provided) of the DRS from the primary
 * 									item concept.
 *
 * 									If the axis value is 'DRS-descendant' then the filter-member set includes those domain
 * 									members in the explicit dimension domain that are descendants of the member QName in
 * 									the linkrole network (if provided) of the DRS from the primary item concept.
 *
 */
function filterMemberDRSSelection( $context, $provider, $args )
{
	try
	{
		$isString = function( $arg )
		{
			if ( is_string( $arg ) )
			{
				return true;
			}
			if ( $arg instanceof XPath2Item )
			{
				/**
				 * @var XPath2Item $arg
				 */
				return $arg->getSchemaType()->TypeCode == XmlTypeCode::String;
			}

			return false;
		};

		if ( ! $args[0] instanceof QNameValue || ! $args[1] instanceof QNameValue ||
			 ! $args[2] instanceof QNameValue || ( ! $isString( $args[3] ) && ! $args[3] instanceof Undefined ) ||
			 ! $isString( $args[4] )
		)
		{
			throw new \InvalidArgumentException();
		}

		if ( ! isset( $context->xbrlTaxonomy ) )
		{
			throw new \InvalidArgumentException( "XBRL taxonomy not set in context" );
		}

		$iterator = new XBRLFilterMemberDRS( $context, $args[0], $args[1], $args[2], $args[3], $args[4] );
		$iterator->MoveNext();
		$iterator->Reset();
		return $iterator;

	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidDimensionQName" || $ex->ErrorCode == "xfie:invalidPrimaryItemConceptQName" )
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
