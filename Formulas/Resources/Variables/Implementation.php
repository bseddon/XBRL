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

use lyquidity\xml\MS\XmlNamespaceManager;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\XPath2Context;

/**
 * A class to process a general variable definitions
 * http://www.xbrl.org/Specification/customFunctionImplementation/REC-2011-10-24/customFunctionImplementation-REC-2011-10-24.html
 */
class Implementation extends Variable
{
	/**
	 * An array of input names
	 * @var array $input
	 */
	public $inputs = array();

	/**
	 * The XPath expression to evaluate
	 * @var string $output
	 */
	public $output;

	/**
	 * A list of steps to execute
	 * @var array
	 */
	public $steps = array();

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

		$result['type'] = 'customfunction';
		$result['customfunctionType'] = 'implementation';

		$inputs = array();
		foreach ( $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CFI ] )->input as $input )
		{
			$attributes = $input->attributes();
			if ( property_exists( $attributes, 'name' ) )
			{
				$inputs[] = trim( $attributes['name'] );
			}
			else
			{
				$log->formula_validation( "Custom function implementation", "Parameter name not given", array() );
			}
		}

		$this->inputs = $inputs;

		$result['inputs'] = $this->inputs;

		$steps = array();
		foreach ( $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CFI ] )->step as $step )
		{
			$attributes = $step->attributes();
			if ( property_exists( $attributes, 'name' ) )
			{
				$steps[ trim( $attributes['name'] ) ] = trim( $step );
			}
			else
			{
				$log->formula_validation( "Custom function implementation", "Parameter step name not given", array() );
			}
		}

		$this->steps = $steps;
		$result["steps"] = $this->steps;

		if ( count( $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CFI ] )->output ) )
		{
			$this->output = trim( $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CFI ] )->output );
		}
		else
		{
			$log->formula_validation( "Custom function implementation", "There is no output (formula) definition", array() );
		}

		$result["output"] = $this->output;

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
		return parent::validate( $variableSet, $nsMgr );
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return parent::getVariableRefs();
	}

	/**
	 * Execute the implementation
	 * @param Signature $signature
	 * @param XPath2Context $context
	 * @param NodeProvider $provider
	 * @param array $args
	 * @param array[Parameter] $parameters
	 * @return mixed
	 */
	public function execute( $signature, $context, $provider, $args, $parameters )
	{
		if ( count( $args ) != count( $this->inputs ) )
		{
			\XBRL_Log::getInstance()->formula_validation(
				"Custom function",
				"The number of parameters passed does not match the number of inputs expected",
				array(
					'error' => 'xbrlcfie:inputMismatch',
					'name' => "{$signature->name['originalPrefix']}:{$signature->name['name']}"
				)
			);
		}

		$vars = array();
		foreach ( $this->inputs as $key => $name )
		{
			$vars[ $name ] = $args[ $key ];
		}

		foreach ( $parameters as $qname => $parameter )
		{
			$vars[ $qname ] = $parameter->select;
		}

		$variableSet = new VariableSet();
		$variableSet->xbrlInstance = $context->xbrlInstance;
		$variableSet->xbrlTaxonomy = $context->xbrlTaxonomy;
		$variableSet->nsMgr = $context->NamespaceManager;

		foreach ( $this->steps as $name => $step )
		{
			$result = $this->evaluateXPath( $variableSet, $step, $vars );
			$vars[ $name ] = $result;
		}

		return $this->evaluateXPath( $variableSet, $this->output, $vars );
	}
}
