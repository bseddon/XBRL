<?php

/**
 * XBRL Formulas
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

namespace XBRL\Formulas\Resources\Messages;

 use XBRL\Formulas\Resources\Resource;

 /**
  * Implements the filter class for messages
  * http://www.xbrl.org/Specification/genericMessages/PWD-2009-12-16/genericMessages-PWD-2009-12-16.html#term-message
  */
class Message extends Resource
{
	/**
	 * The language code
	 * @var string|null $lang
	 */
	public $lang;

	/**
	 * The content (xml) of the message
	 * @var string|null $message
	 */
	public $message;

	/**
	 * Mixed content separator to use see: http://www.w3.org/TR/xslt20/#constructing-simple-content
	 * @var string $separator = " ";
	 */
	public $separator;

	public $label;

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$result['type'] = 'message';
		$result['messageType'] = $localName;

		// There MUST be a 'lang' attribute
		$attributes = $node->attributes( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML] );

		if ( property_exists( $attributes, 'lang' ) )
		{
			$this->lang = trim( $attributes->lang );
		}
		else
		{
			$this->lang = $taxonomy->getDefaultLanguage();
		}

		$attributes = $node->attributes();
		if ( property_exists( $attributes, 'separator' ) )
		{
			$this->separator = trim( $attributes->separator );
		}

		$content = array();
		$owner = $domNode instanceof \DOMDocument ? $domNode : $domNode->ownerDocument;

		/** @var \DOMNode $next */
		$next = $domNode->firstChild;
		if ( ! is_null( $next ) )
		{
			do
			{
				$part = $owner->saveXml( $next, LIBXML_NOEMPTYTAG );
				if ( $next->nodeType == XML_TEXT_NODE )
				{
					if ( substr_count( $part, '{' ) > substr_count( $part, '}' ) )
					{
						$log->formula_validation( "Generic Messages 2.1", "There are unmatched opening curly braces",
							array(
								'part' => str_replace( '\n', '', $part ),
								'error' => 'xbrlmsge:missingRightCurlyBracketInMessage'
							)
						);
					}
					else if ( substr_count( $part, '}' ) > substr_count( $part, '{' ) )
					{
						$log->formula_validation( "Generic Messages 2.1", "There are unmatched closing curly braces",
							array(
								'part' => str_replace( '\n', '', $part ),
								'error' => 'xbrlmsge:missingLeftCurlyBracketInMessage'
							)
						);
					}
				}

				$content[] = $part;
			}
			while( ! is_null( $next = $next->nextSibling ) );
		}



		$result['lang'] = $this->lang;
		$result['message'] = implode( "", $content );

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{

	}
}
