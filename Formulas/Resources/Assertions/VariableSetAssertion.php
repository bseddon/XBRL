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

 namespace XBRL\Formulas\Resources\Assertions;

 use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\FactVariableBinding;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\xml\interfaces\IXmlSchemaType;
use XBRL\Formulas\Resources\Resource;

 /**
  * A class to process a formula definitions
  */
class VariableSetAssertion extends VariableSet
{
	/**
	 * Flag to indicate whether the test is required.  A 'valueAssertion' require a test while an 'existenceAssertion' does not.
	 * @var boolean $testRequired
	 */
	protected $testRequired = true;

	/**
	 * An XPath expression attribute that, when evaluated, MUST yield a boolean result
	 * @var string $test
	 */
	public $test = "";

	/**
	 * The text from an generic label if defined
	 * @var string $description
	 */
	public $description;

	/**
	 * A list of messages generated when an assertion is true
	 * @var array[string] $satisfiedMessages
	 */
	public $satisfiedMessages = array();

	/**
	 * A list of messages generated when an assertion is true
	 * @var array[string] $satisfiedMessages
	 */
	public $unsatisfiedMessages = array();

	/**
	 * A list of messages generated during evaluation
	 * @var array
	 */
	public $generatedSatisifiedMessages = array();

	/**
	 * A list of messages generated during evaluation
	 * @var array
	 */
	public $generatedUnsatisifiedMessages = array();

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
		$attributes = $node->attributes();

		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );
		$result['variablesetType'] = 'variablesetAssertion';

		if ( property_exists( $attributes, "test" ) )
		{
			$this->test = trim( $attributes->test );
			$result['test'] = $this->test;
		}
		else
		{
			if ( $this->testRequired )
			{
				$log->formula_validation( "Variables", "Missing 'test' attribute", array(
					'error' => 'xbrlve:missingRequiredAttribute'
				) );
			}
		}

		return $result;
	}

	/**
	 * Get any messages defined for the assertion
	 * @param string $arcRole
	 * @param string $lang
	 * @return array
	 */
	private function getMessages( $arcRole, $lang = null )
	{
		$arcs = $this->xbrlTaxonomy->getGenericArc( $arcRole, $this->extendedLinkRoleUri, $this->label );

		$messages = array();

		foreach ( $arcs as $arc )
		{
			if ( $arc['frompath'] != $this->path ) continue;

			$this->xbrlTaxonomy->getGenericResource( 'message', 'message', function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$messages, $arc, $lang )
			{
				// BMS 2019-02-11

				// if ( $resource['label'] != $arc['to'] ) return true;
				if ( $resource['path'] != $arc['topath'] ) return true;
				if ( ! is_null( $lang ) )
				{
					if ( $resource['lang'] != $lang && $resource['lang'] != strstr( $lang, "-", true ) )
					{
						return true;
					}
				}

				$messages[ $arc['to'] ] = $resource['message'];

				return true;
			}, $arc['toRoleUri'], $arc['to'] );
		}

		return $messages;
	}

	/**
	 * Get the satisfied and unsatisfied messages (if any)
	 * @param string $lang
	 * @return bool
	 */
	public function validateMessages( $lang = null )
	{
		$this->satisfiedMessages = $this->getMessages( \XBRL_Constants::$arcRoleAssertionSatisfiedMessage, $lang );
		$this->unsatisfiedMessages = $this->getMessages( \XBRL_Constants::$arcRoleAssertionUnsatisfiedMessage, $lang );

		$severityArcs = $this->xbrlTaxonomy->getGenericArc( \XBRL_Constants::$arcRoleAssertionUnsatisfiedSeverity, $this->extendedLinkRoleUri, $this->label );

		foreach ( $severityArcs as $arc )
		{
			// BMS 2019-02-11
			if ( $this->path != $arc['frompath'] ) continue;

			$this->xbrlTaxonomy->getGenericResource( 'resource', 'severity', function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$arc )
			{
				// if ( $resource['label'] != $arc['to'] ) return true;

				switch( $resource['label'] )
				{
					case 'error':
						$this->severity = ASSERTION_SEVERITY_ERROR;
						return false;

					case 'ok':
						$this->severity = ASSERTION_SEVERITY_OK;
						return false;

					case 'warning':
						$this->severity = ASSERTION_SEVERITY_WARNING;
						return false;
				}

				return true;
			}, $arc['toRoleUri'] , $arc['to'] );
		}

		return true;
	}

	/**
	 * Process all the messages in the set using the variables in $vars
	 * @param array[string] $messageSet
	 * @param array[FactVariableBinding]
	 * @param XPath2ItemProvider $provider (optional) A specific provider to use.  ExistenceAssertion passes one.
	 * @param
	 */
	public function processMessages( $messageSet, $vars, $provider = null )
	{
		$generatedMessages = array();

		foreach ( $messageSet as $message )
		{
			// There must be the same number of { and } characters afer {{ and }} are accounted for
			if ( substr_count( $message, "}" ) - substr_count( $message, "}}" ) * 2 >> substr_count( $message, "{" ) - substr_count( $message, "{{" ) * 2 )
			{
				\XBRL_Log::getInstance()->formula_validation( "Messages", "Mismatched curly brackets", array(
					'error' => 'xbrlmsge:missingLeftCurlyBracketInMessage'
				) );
			}

			// There must be the same number of { and } characters afer {{ and }} are accounted for
			if ( substr_count( $message, "{" ) - substr_count( $message, "{{" ) * 2 >> substr_count( $message, "}" ) - substr_count( $message, "}}" ) * 2 )
			{
				\XBRL_Log::getInstance()->formula_validation( "Messages", "Mismatched curly brackets", array(
					'error' => 'xbrlmsge:missingRightCurlyBracketInMessage'
				) );
			}

			// Each message needs to be split into its text nodes and substitutions
			// Substitutions are placed within open/close curly brackets
			if ( ! preg_match_all( "/(((?'text'.*?)\{(?'xpath'.*?)\})+?)?(?'tail'.*?)/s", $message, $matches ) ) return;

			$generatedMessage = "";

			foreach ( $matches['text'] as $key => $text )
			{
				$generatedMessage .= $text;
				$xpath = trim( $matches['xpath'][ $key ] );

				if ( $xpath )
				{
					$contextItems = array(
						"variableSet" => $this
					);
					$result = $this->evaluateXPath( $this, $xpath, $vars, $contextItems, $provider );
					if ( $result instanceof XPath2NodeIterator )
					{
						foreach ( $result as $node )
						{
							$generatedMessage .= $node->getValue();
						}
					}
					else if ( $result instanceof IXmlSchemaType )
					{
						$generatedMessage .= (string)$result;
					}
					else if ( $result instanceof DOMXPathNavigator )
					{
						$generatedMessage .= $result->getValue();
					}
					else
					{
						$generatedMessage .= (string)$result;
					}
				}

				$generatedMessage .= $matches['tail'][ $key ];
			}

			// Replace tags as they are not needed for comparison with the test text
			// $x = preg_replace( "/(?'tag'<(.*?)>.*?<\\/\\2>)\\s*/s", "", $generatedMessage, -1 );

			$generatedMessages[] = $generatedMessage;
		}

		return $generatedMessages;
	}

}
