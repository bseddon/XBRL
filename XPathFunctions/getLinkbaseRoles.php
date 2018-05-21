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
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\SequenceTypes;
use lyquidity\xml\TypeCode;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "xbrlInstance.php";

/**
 * Returns a sequence of QNames of all typed dimensions that are reported in the segment or scenario of the item.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:anyURI*	Returns an unordered sequence of the extended link role URIs that contain the subject arc
 * 						role URI, or empty sequence if none.
 *
 * This function has one real argument:
 *
 * arcrole	xs:string	The arcrole value that specifies the network of relationships whose containing link
 * 						roles are to be gathered.
 *
 */
function getLinkbaseRoles( $context, $provider, $args )
{
	try
	{
		$isString = function( /** @var XPath2Item $arg */ $arg )
		{
			return is_string( $arg ) || ( $arg instanceof XPath2Item &&
					in_array( $arg->getSchemaType()->TypeCode, array( XmlTypeCode::String, XmlTypeCode::AnyUri ) ) );
		};

		if ( ! $isString( $args[0] ) )
		{
			throw new \InvalidArgumentException("The arcrole argument is not a string");
		}

		if ( $args[0] instanceof XPath2Item )
		{
			$args[0] = $args[0]->getTypedValue();
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
			$roleRefs = $instance->getFootnoteRoleRefs();
			// footnote linkbases are in the instance document
			if ( isset( $roleRefs['arcroles'][ $args[0] ] ) )
			{
				$roles = $roleRefs['arcroles'][ $args[0] ];
			}
		}
		else if ( $args[0] == \XBRL_Constants::$arcRoleSummationItem )
		{
			$roles = array_keys( $taxonomy->getCalculationRoleRefs() );
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
