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
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "xbrlInstance.php";

/**
 *  Returns a sequence containing the set of QNames representing the concepts that have the specified
 *  relationship to the source concept.  The set of dimension member QNames is in an arbitrary order
 *  (not necessarily that of effective tree relationships order).
 *
 * Notes
 *
 * This directory provides two test case files, one for function testing and one for formula testing.
 * The formula test cases are helpful examples for tree walks, two alternative forms of calculation
 * linkbase validation, Charlie Hoffman's movement example validation, and xbrl-us preview-2009 movement and total validation.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:anyAtomicType*		Returns a sequence of the concept QNames, and arc attribute values.
 * 									If arcattribute(s) are not requested then this is just a sequence of QNames.
 * 									If arcattributes are returned they are consecutive subsequences of the qnames,
 * 									the first attribute values, the next attribute values, etc.
 * 									E.g., the sequence concept-1-qname, concept-2-qname, concept-1-attr-1-value,
 * 									concept-2-attr-1-value,  concept-1-attr-2-value, concept-2-attr-2-value, etc.
 * 									Absent attributes return as an empty string, because if an empty sequence were
 * 									instead returned, it would be dropped by the union operator and prevent iterating
 * 									through the sequence of results.
 *
 * 									The concept QNames are atomic typed xs:QName objects.  The arc attribute values
 * 									are typed according to the Post Schema Validation Infoset of the DTS of the instance
 * 									document, so that calculation arc weight is typed xs:decimal, and preferredLabel is
 * 									typed xs:anyURI (not untyped or xs:string).
 *
 * 									The order within each subsequence (QNames subsequence and any subsequent subsequences
 * 									of arc attribute values) is the effective relationships order after consideration of
 * 									arc order value, prohibition, and override.
 *
 * 									The tree-walk of multiple generations is in depth-first order (or the inverse for
 * 									ancestors), a node's descendants are recursively inserted in the result subsequences
 * 									prior to continuing with siblings.
 *
 * This function has three real arguments:
 *
 * source		xs:QName			The QName of the source concept from which to begin navigation.
 * 									The special QName xfi:root is recognized when it is desired to retrieve
 * 									descendants from the 'root' level (who have no parents).
 * linkrole		xs:AnyUri			The linkrole value that specifies the network of effective relationships to
 * 									determine the selected members on the specified axis from the member
 * 									used as the origin. If omitted ("()" or "''") then the default link role is used.
 * arcrole		xs:anyURI			The arcrole value that specifies the network of effective relationships as above.
 * axis			xs:string			The axis value MUST be one of:
 *										descendant-or-self
 *										descendant
 *										ancestor-or-self
 *										ancestor
 *										sibling
 *										sibling-or-self
 *										sibling-or-abstract-descendant
     *									If the axis value is 'descendant' then the filter-member set includes those concepts
     *									that are descendants of the source concept QName, in the linkrole network of arcrole
     *									effective relationships.  (For child, use descendant and generations = 1.)
     *									For the special sourcd QName xfi:root, descendants are those from the topmost level
     *									(those with no parents).
     *
     * 									If the axis value is 'descendant-or-self' then the filter-member set includes includes
     * 									the source concept and those concepts in the explicit dimension domain that are descendants
     * 									of the concept QName, in the linkrole network of arcrole effective relationships.
     *
     * 									If the axis value is 'ancestor' then the filter-member set includes those concepts that
     * 									are ancestors of the source concept QName, in the linkrole network of arcrole effective
     * 									relationships.  (For parent, use ancestor and generations = 1.)
     *
     * 									If the axis value is 'ancestor-or-self' then the filter-member set includes includes the
     * 									source concept and those concepts in the explicit dimension domain that are ancestors of
     * 									the concept QName, in the linkrole network of arcrole effective relationships.
     *
     * 									If the axis value is 'sibling' then the filter-member set includes those concepts that are
     * 									siblings of the source concept QName, in the linkrole network of arcrole effective
     * 									relationships. A sibling is defined as other children of the same parent, unless the
     * 									concept is root level, then it is other root concepts.
     *
     * 									If the axis value is 'sibling-or-self' then it is the source concept and its siblings.
     *
     * 									If the axis value is 'sibling-or-abstract-descendant' then it is the source concept and
     * 									its siblings, plus in addition for any abstract concept the children and recursively
     * 									children of any abstract children.
     *
     *									If the axis value is 'sibling then the filter-member set includes those concepts that
     *									are siblings of the source concept QName, in the linkrole network of arcrole effective
     *									relationships.
     *
     * generations	xs:integer			The arcrole value that specifies a limit of number of generations (for descendant or
     * 									ancestor), or zero (if unlimited generations navigation is desired.  Generations=1 and
     * 									descendant axis means obtain only children, generations=2 obtains children and
     * 									grandchildren, and generations=0 means all descendants.
     *
     * 									Generations must be 1 for a sibling or sibling-or-self axis.
     *
     * 									In any case if a descendant or ancestor concept repeats in the path from the source,
     * 									it is not navigated further for unlimited generations navigation (generations=0), but
     * 									it is navigated further for limited generations navigation.  Thus it is not wise to put
     * 									a high integer on generations count if directed cycles may exist.  A demonstration of
     * 									this issue is in function test cases v-09 and V-09a. In V-09 navigation starts at A1,
     * 									for descendants (not self) for unlimited generations.  In this case A1 is not included
     * 									first in the output (because of missing '-or-self') but A1 is a descendant of A13
     * 									(A1->A13->A1) so the A1 occurence under A13 is included in the output, but no further
     * 									navigation from the repeat (directed cycle) beneath A1 is navigated.  In V-09A 7
     * 									generations are requested, to show that the directed cycle is followed for 7 generations.
     *
     * arcattributes	xs:QName*		This parameter is optional.  If omitted (or empty sequence) no arc attributes are returned.
     * 									If provided the sequence has QNames of arc attributes to be returned with the sequence
     * 									of concepts QNames.
     * 									For example for preferredLabel of a presentation arc, specify QName('','preferredLabel),
     * 									or weight of a calculation arc, QName('','weight').  Each arc attribute with a value
     * 									has the type according to the Post Schema Validation Infoset, e.g., preferredLabel is
     * 									an xs:anyURI and weight is an xs:decimal.
     * 									(This version provides for empty string results when the attribute does not exist, to
     * 									allow comparison of preferredLabel attribute and other string attributes that are optional.)
     *
     * xbrlinstance	element(xbrli:xbrl)	This parameter is optional.  If absent the target XBRL instance provides the DTS for
     * 									linkbases containing the specified arcrole.  If provided then arcattribute must also be
     * 									provided (it can be specified "(),").
     * 									If provided then the specified XBRL instance provides the DTS for the relationship
     * 									linkbases (such as an instance dynamically loaded by the xbrl-instance function.)
     *
     *
 */
function navigateRelationships( $context, $provider, $args )
{
	try
	{
		if ( ! is_string( $args[0] ) || ! $args[1] instanceof XPath2NodeIterator )
		{
			throw new \InvalidArgumentException("The arcrole argument is not a string");
		}

		if ( $args[1]->getCount() != 1 )
		{
			throw new \InvalidArgumentException( "There can only be one xbrl element" );
		}

		/**
		 * @var \XBRL_Instance $instance
		 */
		$instance = $context->xbrlInstance;

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;

		return EmptyIterator::$Shared;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
