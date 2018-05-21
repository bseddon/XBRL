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

namespace XBRL\Formulas\Resources\Variables;

use lyquidity\XPath2\XPath2Expression;
use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\XPath2\XPath2Exception;

 /**
  * A class to process a fact variable definitions
  */
class Precondition extends Variable
{
	/**
	 * The pre-condition test
	 * @var string $test
	 */
	public $test;

	/**
	 * The pre-condition test
	 * @var XPath2Expression $testXPathExpression
	 */
	public $testXPathExpression;

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

		$attributes = $node->attributes();

		if ( ! property_exists( $attributes, "test" ) )
		{
			$log->formula_validation( "Precondition", "Missing 'test' attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}
		else
		{
			$this->test = trim( $attributes->test );
			$result["test"] = $this->test;
		}

		$result = parent::storeVariable( $result, $localName );

		return $result;
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		try
		{
			if ( isset( $this->test ) && ! empty( $this->test ) )
			{
				$expression = XPath2Expression::Compile( $this->test, $nsMgr );
				$this->testXPathExpression = $expression;
			}

			return true;
		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "Precondition", "Failed to compile precondition expression",
				array(
					'tesr' => $this->test,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);

			return false;
		}
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return $this->testXPathExpression instanceof XPath2Expression
			? $this->testXPathExpression->getParameterQNames()
			: array();
	}
}
