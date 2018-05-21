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

use lyquidity\XPath2\Iterator\ChildOverDescendantsNodeIterator\NodeTest;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\Iterator\EmptyIterator;
use lyquidity\XPath2\Undefined;
use lyquidity\xml\MS\XmlQualifiedNameTest;
use lyquidity\XPath2\Iterator\AttributeNodeIterator;
use lyquidity\XPath2\SequenceTypes;
use lyquidity\XPath2\XPath2Item;
use lyquidity\xml\exceptions\IndexOutOfRangeException;
use lyquidity\xml\exceptions\InvalidCastException;
use lyquidity\XPath2\XPath2Exception;

// Make sure any required functions are imported
require_once "checkIsItem.php";
require_once "xbrlInstance.php";

/**
 * Returns strings containing the footnotes that has the specified link role, resource role, and language.
 *
 * @param XPath2Context $context
 * @param NodeProvider	$provider
 * @param array $args
 * @return xs:string*	Returns footnotes (if found) or an empty sequence (if none found).
 *
 * This function has five real arguments:
 *
 * fact			element()	The fact (item or tuple) whose footnotes are to be found.
 * linkrole		xs:string?	The linkrole value that specifies the network of effective relationships of arcs in
 * 							which to find the footnotes. If an empty sequence or empty string ("()" or "''") then
 * 							the default link role is implied.
 * arcrole		xs:string?	The footnote arc role value that specifies the network of effective relationships of
 * 							arcs in which to find the footnotes. If an empty sequence or empty string ("()" or "''")
 * 							then the default footnote arcrole is used.
 * footnoterole	xs:string?	The footnote resource role value that is to be found. If omitted ("()" or "''") then
 * 							the default footnote role is used.
 * lang			xs:string	The language code of the footnote to be retrieved. If a footnote with exact match is not
 * 							found, a closest-sublanguage is returned, e.g., if the argument to $lang is en-UK and a
 * 							footnote is present with en, it is returned.
 */
function getFactFootnotes( $context, $provider, $args )
{
	try
	{
		if ( ! $args[0] instanceof XPath2NodeIterator ||
			 ! ( is_string( $args[1] ) || $args[1] instanceof XPath2Item || $args[1] instanceof Undefined ) ||
			 ! ( is_string( $args[2] ) || $args[2] instanceof XPath2Item || $args[2] instanceof Undefined ) ||
			 ! ( is_string( $args[3] ) || $args[3] instanceof XPath2Item || $args[3] instanceof Undefined ) ||
			 ! ( is_string( $args[4] ) || $args[4] instanceof XPath2Item  )
		)
		{
			throw new \InvalidArgumentException( "Invalid argument(s)" );
		}

		if ( $args[0]->getCount() != 1 || ! $args[0]->MoveNext() )
		{
			throw new IndexOutOfRangeException( "Expecting exactly 1 fact" );
		}

		if ( empty( (string)$args[4] ) )
		{
			throw new \InvalidArgumentException( "Langauge argument cannot be empty" );
		}

		$fact = $args[0]->getCurrent()->CloneInstance();
		$nodeTest = XmlQualifiedNameTest::create( "id" );
		$attribs = AttributeNodeIterator::fromNodeTest( $context, $nodeTest, XPath2NodeIterator::Create( $fact ) );
		$id = $attribs->MoveNext()
			? $attribs->getCurrent()->getValue()
			: "";

		checkIsItem($context, $fact );

		$linkRole = empty( (string)$args[1] ) || (string)$args[1] instanceof Undefined ? \XBRL_Constants::$defaultLinkRole : (string)$args[1];
		$arcRole = empty( (string)$args[2] ) || (string)$args[2] instanceof Undefined ? \XBRL_Constants::$arcRoleFactFootnote : (string)$args[2];
		$footnoteRole = empty( (string)$args[3] ) || (string)$args[3] instanceof Undefined ? \XBRL_Constants::$footnote : (string)$args[3];
		$lang = (string)$args[4];

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $context->xbrlTaxonomy;
		if ( ! $taxonomy )
		{
			throw new InvalidCastException("The taxonomy does not exist in the global context");
		}

		/**
		 * @var \XBRL_Instance $instance
		 */
		$instance = $context->xbrlInstance;
		if ( ! $instance )
		{
			throw new InvalidCastException("The taxonomy does not exist in the global context");
		}

		$factElement = $instance->getElement( $fact->LocalName );

		if ( $factElement )
		{
			$resultFootnotes = array();

			foreach ( $factElement as $key => $entry )
			{
				if ( ! empty( $id ) )
				{
					if ( $entry['id'] != $id )
					{
						continue;
					}
				}
				$resultFootnotes = $instance->getFootnoteForFact( $entry, $lang, $linkRole, $arcRole, $footnoteRole );
			}

			if ( count( $resultFootnotes ) )
			{
				$footnotes = array_map( function( $item ) { return XPath2Item::fromValueAndType( $item, SequenceTypes::$AnyUri->SchemaType ); }, $resultFootnotes );
				return DocumentOrderNodeIterator::fromItemset( $footnotes );
			}
		}

		return EmptyIterator::$Shared;
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
