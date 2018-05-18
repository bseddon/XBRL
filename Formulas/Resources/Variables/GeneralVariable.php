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

 namespace XBRL\Formulas\Resources\Variables;

use lyquidity\XPath2\XPath2Expression;
use lyquidity\xml\MS\XmlNamespaceManager;
use XBRL\Formulas\VariableBinding;
use XBRL\Formulas\Evaluation;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use XBRL\Formulas\GeneralVariableBinding;
use lyquidity\XPath2\Undefined;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\Iterator\FlattenNodeIterator;
use lyquidity\XPath2\XPath2Exception;

/**
 * A class to process a general variable definitions
 */
class GeneralVariable extends Variable
{
	/**
	 * Parameter name
	 * @var string $name
	 */
	public $name = array();

	/**
	 * An XPath expression
	 * @var string $select
	 */
	public $select = "";

	/**
	 * Indicates whether the expression result from 'select' should be interpreted as a sequence
	 * @var string $bindAsSequence
	 */
	public $bindAsSequence = false;

	/**
	 * A compiled version of the 'select' property
	 * @var XPath2Expression $xpath2expression
	 */
	public $selectXPath2expression;

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

		if ( ! property_exists( $attributes, "bindAsSequence" ) )
		{
			$log->formula_validation( "Variables", "Missing bindAsSequence attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->bindAsSequence = filter_var( $attributes->bindAsSequence, FILTER_VALIDATE_BOOLEAN );
		$result['bindAsSequence'] = $this->bindAsSequence;

		if ( ! property_exists( $attributes, "select" ) )
		{
			$log->formula_validation( "Variables", "Missing select attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->select = (string) $attributes->select;
		$result['select'] = $this->select;

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
			if ( isset( $this->select ) && ! empty( $this->select ) )
			{
				$expression = XPath2Expression::Compile( $this->select, $nsMgr );
				$this->selectXPath2expression = $expression;
			}

			return true;
		}
		catch ( \Exception $ex )
		{
			\XBRL_Log::getInstance()->formula_validation( "General variable", "Failed to compile select expression",
				array(
					'select' => $this->select,
					'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);
		}
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return $this->selectXPath2expression instanceof XPath2Expression
			? $this->selectXPath2expression->getParameterQNames()
			: array();
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param Evaluation $evaluation The variable set containing the variables
	 * @param array $uncoveredAspectFacts The binding of the parent variable (if there is one) and so uncovered facts
	 * @return VariableBinding A new binding for this variable
	 */
	public function Filter( $evaluation, $uncoveredAspectFacts )
	{
		$variableSet = $evaluation->variableSet;

		// Create a list of the aspects covered by the filters used by this variable
		$binding = new GeneralVariableBinding( $variableSet->xbrlInstance, $this );

		try
		{
			$vars = $evaluation->variableSet->getBindingsAsVars();
			// $count = $vars['c1']->getCount();
			$value = $this->evaluateXPathExpression( $evaluation->variableSet, $this->selectXPath2expression, $vars );
			// $count = $value->getCount();
			if ( $value instanceof XPath2NodeIterator )
			{
				$context = new XPath2Context( $evaluation->variableSet->nsMgr );

				// $binding->facts = DocumentOrderNodeIterator::fromBaseIter( $value, true );
				$binding->facts = new FlattenNodeIterator( $value );
			}
			else
			{
				$binding->facts = DocumentOrderNodeIterator::fromItemset( $value instanceof Undefined ? array() : array( $value ) );
			}

			// $binding->facts = $value instanceof XPath2NodeIterator
			// 	? $value
			//	: DocumentOrderNodeIterator::fromItemset( $value instanceof Undefined ? array() : array( $value ) );
		}
		catch( XPath2Exception $ex)
		{
			if ( $ex->ErrorCode == "XPST0008" )
			{
				\XBRL_Log::getInstance()->formula_validation( "general variable", "Unresolved variable in the select statement",
					array(
						'general variable' => $this->getQName(),
						'select' => $this->select,
						'error' => 'xbrlve:unresolvedDependency',
					)
				);
			}
		}

		return $binding;
	}
}
