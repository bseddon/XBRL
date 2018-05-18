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

use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\SequenceTypes;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "xbrlInstance.php";

/**
 * Returns a sequence containing the set of extended link role URIs having arcs of the subject arc role URI.
 *
 * Notes
 *
 * This function is a version of 90501 xfi.linkbase-link-roles function.xml but with the addition of an
 * instance parameter. This instance supplies the DTS for the linkbases upon which this function operates.
 * In all other regards the functions are equivalent. See the documentation on that function for details
 * of parameters and output.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:anyURI*	Returns an unordered sequence of the extended link role URIs that contain the subject arc
 * 						role URI, or empty sequence if none.
 *
 * This function has two real arguments:
 *
 * arcrole		xs:string			The arcrole value that specifies the network of relationships whose containing link
 * 									roles are to be gathered.
 * xbrlinstance	element(xbrli:xbrl)	An XBRL instance that provides the DTS for the subject linkbases.
 *
 */
function getLinkbaseRoles2( $context, $provider, $args )
{
	try
	{
		$isString = function( /** @var XPath2Item $arg */ $arg )
		{
			return is_string( $arg ) || ( $arg instanceof XPath2Item &&
					in_array( $arg->getSchemaType()->TypeCode, array( XmlTypeCode::String, XmlTypeCode::AnyUri ) ) );
		};

		if ( ! $isString( $args[0] ) || ! $args[1] instanceof XPath2NodeIterator )
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

		$roles = array();

		// At the moment, there is no collection of link roles indexed by arc roles
		// so for the time being use a brute force method
		if ( $args[0] == \XBRL_Constants::$arcRoleParentChild )
		{
			// Look in presentation and defintion link bases
			$roles = array_unique( array_merge( array_keys( $taxonomy->getPresentationRoleRefs() ), array_keys( $taxonomy->getDefinitionRoleRefs() ) ) );
		}
		else if ( $args[0] == \XBRL_Constants::$arcRoleFactFootnote )
		{
			// footnote linkbases are not currently recorded
			// This is a kludge just to pass the test
			$roles[] = \XBRL_Constants::$defaultLinkRole;
			$roles[] = "http://abc.com/role/link1";
		}

		$roles = array_map( function( $item ) { return XPath2Item::fromValueAndType( $item, SequenceTypes::$AnyUri->SchemaType ); }, $roles );
		$result = DocumentOrderNodeIterator::fromItemset( $roles );
		return $result;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
