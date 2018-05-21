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
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "xbrlInstance.php";

/**
 * Obtain the value of the xbrli:balance attribute on an XBRL concept.
 *
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return xs:string	If the xbrli:balance attribute is omitted from the concept, then the empty string
 * 						is returned. Otherwise the value of the balance attribute is returned as a string.
 *
 * This function has one real argument:
 *
 * concept-name	xs:QName
 *
 */
function getConceptBalance( $context, $provider, $args )
{
	try
	{
		// There should be one argument and it should be the <xbrl> element
		if ( ( ! $args[0] instanceof QNameValue ) )
		{
			throw new \InvalidArgumentException();
		}

		if ( ! isset( $context->xbrlInstance ) )
		{
			throw new \InvalidArgumentException( "XBRL Instance not set in context" );
		}

		/**
		 * @var \XBRL_Instance $instance
		 */
		$instance = $context->xbrlInstance;

		// Look up the node namespace in the instance taxonomy
		$taxonomy = $instance->getTaxonomyForNamespace( $args[0]->NamespaceUri );
		if ( ! $taxonomy )
		{
			throw XPath2Exception::withErrorCode( "xfie:invalidConceptQName", "Invalid QName" );
		}

		$taxonomyElement = $taxonomy->getElementByName( $args[0]->LocalName );
		if ( ! $taxonomyElement )
		{
			return "";
		}

		// This node MUST have a substitution group attribute and the attribute MUST resolve to xbrli:tuple or xbrli:item
		if ( ! isset( $taxonomyElement['balance'] ) )
		{
			return "";
		}

		return $taxonomyElement['balance'];
	}
	catch ( XPath2Exception $ex )
	{
		if ( $ex->ErrorCode == "xfie:invalidConceptQName" )
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
