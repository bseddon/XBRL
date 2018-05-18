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

namespace XBRL\Formulas\Resources\Formulas\Aspects;

use XBRL\Formulas\Resources\Resource;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\xml\QName;

/**
 * A class to process a general variable definitions
 */
class Aspect extends Resource
{
	/**
	 * The location of the fact is the ordered sequence of elements from the <xbrli:xbrl> element at the
	 * root of the XBRL instance to the element that is the fact itself. The aspect test for this aspect is:
	 * @var string $locationTest
	 */
	public static $locationTest = "\$aspectTest:a/.. is \$aspectTest:b/..";

	/**
	 * The concept aspect is the concept that the fact reports a value for. The aspect test for this aspect is:
	 * @var string $conceptTest
	 */
	public static $conceptTest = "(namespace-uri(\$aspectTest:a) eq namespace-uri(\$aspectTest:b)) and (local-name(\$aspectTest:a) eq local-name(\$aspectTest:b))";

	// The variabled specification defines the following aspects for all items, but not for tuples:

	/**
	 * The entity identifier aspect is the identifier of the entity that the fact reports a value for.
	 * The aspect test for this aspect is:
	 * @var string $entityIdentifierTest
	 */
	public static $entityIdentifierTest = "(xfi:fact-identifier-scheme(\$aspectTest:a) eq xfi:fact-identifier-scheme(\$aspectTest:b)) and (xfi:fact-identifier-value(\$aspectTest:a) eq xfi:fact-identifier-value(\$aspectTest:b))";

	/**
	 * The period aspect is the period that the fact reports a value for. The aspect test for this aspect is:
	 * @var string $periodTest
	 */
	public static $periodTest = "xfi:nodes-correspond(xfi:period(\$aspectTest:a), xfi:period(\$aspectTest:b))";

	/**
	 * The complete segment aspect is the complete content of the segment, without interpreting it using the
	 * XBRL Dimensions Specification [DIMENSIONS], if a segment is contained by the context of the fact.
	 * The aspect test for this aspect is:
	 * @var string $completeSegmentTest
	 */
	public static $completeSegmentTest = "xfi:nodes-correspond(xfi:segment(\$aspectTest:a), xfi:segment(\$aspectTest:b))";

	/**
	 * The non-XDT segment aspect is the content of the segment, excluding content defined in the XBRL Dimensions
	 * Specification [DIMENSIONS], if a segment is contained by the context of the fact. The aspect test for this aspect is:
	 * @var string $nonXDTSegmentTest
	 */
	// public static $nonXDTSegmentTest = "for \$remainder-a in xfi:fact-segment-remainder(\$aspectTest:a), \$remainder-b in xfi:fact-segment-remainder(\$aspectTest:b) return (: (count(\$remainder-a) eq count(\$remainder-b) and ((count(\$remainder-a) eq 0) or (every \$i in 1 to count(\$remainder-a) satisfies xfi:nodes-correspond(\$remainder-a[\$i],\$remainder-b[\$i])))) :) true";
	public static $nonXDTSegmentTest = "lyquidity:non-XDT-segment-aspect-test(.,\$aspectTest:b)";

	/**
	 * The complete scenario aspect is the complete content of the scenario, without interpreting it using the
	 * XBRL Dimensions Specification [DIMENSIONS], if a scenario is contained by the context of the fact.
	 * The aspect test for this aspect is:
	 * @var string $completeScenarioTest
	 */
	public static $completeScenarioTest = "xfi:nodes-correspond(xfi:scenario(\$aspectTest:a), xfi:scenario(\$aspectTest:b))";

	/**
	 * The non-XDT scenario aspect is the content of the scenario, excluding content defined in the XBRL Dimensions
	 * Specification [DIMENSIONS], if a scenario is contained by the context of the fact. The aspect test for this aspect is:
	 * @var string $nonXDTScenarioTest
	 */
	// public static $nonXDTScenarioTest = "for \$remainder-a in xfi:fact-scenario-remainder(\$aspectTest:a), \$remainder-b in xfi:fact-scenario-remainder(\$aspectTest:b) return (count(\$remainder-a) eq count(\$remainder-b) and ((count(\$remainder-a) eq 0) or (every \$i in 1 to count(\$remainder-a) satisfies xfi:nodes-correspond(\$remainder-a[\$i],\$remainder-b[\$i]) )))";
	public static $nonXDTScenarioTest = "lyquidity:non-XDT-scenario-aspect-test(.,\$aspectTest:b)";

	// The variables specification defines the following aspect for for numeric items only:

	/**
	 * The unit aspect is the unit of the fact. The aspect test for this aspect is:
	 * @var string $unitTest
	 */
	public static $unitTest = "xfi:nodes-correspond(xfi:unit(\$aspectTest:a), xfi:unit(\$aspectTest:b))";

	// Dimension aspects are XBRL dimensions that are reported in the fact's segment or scenario.

	/**
	 * An explicit-dimension aspect test is a dimension aspect test for an explicit dimension.
	 * An explicit-dimension aspect tests is:
	 * where #dimension is the QName of the dimension defining the aspect.
	 * @var string $explicitDimensionTest
	 */
	public static $explicitDimensionTest = "xfi:fact-explicit-dimension-value(\$aspectTest:a,#dimension) eq xfi:fact-explicit-dimension-value(\$aspectTest:b,#dimension)";

	/**
	 * A typed-dimension aspect test is a dimension aspect test for a typed dimension.
	 * A typed dimension value is a value of a typed dimension in an XBRL instance. Syntactically, it is the
	 * single XML fragment with root element that is the child element of the typed-dimension's dimension container.
	 *
	 * Typed-dimension aspect tests are tests of equality between typed dimension values for the same typed dimension.
	 * A typed-dimension domain definition is the element in an XML Schema that defines the content model for a typed
	 * dimension and that is identified as such by an @xbrldt:typedDomainRef attribute on the XML Schema element declaring
	 * a typed dimension.
	 *
	 * Note that [DIMENSIONS] allows more than one typed dimension to use the same typed-dimension domain definition.
	 *
	 * Typed-dimension aspect tests depend upon whether the typed dimension defining the dimension aspect has a
	 * typed-dimension domain definition that, itself, has an equality definition.
	 *
	 * A default typed-dimension aspect test is a typed-dimension aspect test for a typed dimension that does not have an
	 * equality definition associated with its typed-dimension domain definition.
	 *
	 * A custom typed-dimension aspect test is a typed-dimension aspect test for a typed dimension that does have an
	 * equality definition associated with its typed-dimension domain definition.
	 */

	/**
	 * Two element/attribute nodes, A and B have the same name if either they both have QName names, Aqn and Bqn, and the
	 * XPath 2.0 expression (Aqn eq Bqn) evaluates to an effective Boolean value of true when using the empty sequence as
	 * the context item; or they both have names that are not defined in any namespace, An and Bn, and the XPath 2.0
	 * expression (An eq Bn) evaluates to an effective Boolean value of true when using the empty sequence as the context item.
	 *
	 * Two attribute nodes, A and B, are corresponding attributes if the following conditions are all satisfied:
	 *
	 * 	  A and B have the same name
	 *    The sequences of atomic values obtained by atomizing A and B, As and Bs, are the same length and for each item Ai,
	 *    at position i in As, the item Bi at position i in Bs, is such that the XPath 2.0 expression (Ai eq Bi) evaluates to
	 *    an effective Boolean value of true when using the empty sequence as the context item.
	 *
	 * Note that if the attribute nodes, A and B, both atomize to empty sequences then those attribute nodes correspond.
	 *
	 * Two element nodes, A and B, are corresponding elements if the following conditions are all satisfied:
	 *
	 * 	  A and B have the same name
	 *    The sequences of atomic values obtained by atomizing A and B, As and Bs, are the same length and for each item Ai,
	 *    at position i in As, the item Bi at position i in Bs, is such that the XPath 2.0 expression (Ai eq Bi) evaluates to
	 *    an effective Boolean value of true when using the empty sequence as the context item.
	 *    A and B have the same number of attributes [ 1 ]
	 *    For each attribute on element node A, there is a corresponding attribute on element node B.
	 *    A and B have the same number of child elements.
	 *    For each child element of element node A, Ac, there is a corresponding child element of element node B, Bc,
	 *    such that Ac and Bc have the same number of preceding sibling elements.
	 *
	 * Note that, as for attribute nodes, if the element nodes, A and B, both atomize to empty sequences then those element
	 * nodes correspond.
	 *
	 * Two typed dimension values are corresponding typed-dimension values if they are values for the same typed dimension and
	 * their root elements correspond. The default typed-dimension aspect test is:
	 * where #dimension is the QName of the dimension defining the aspect.
	 * @var string $defaultTypedDimensionTest
	 */
	// public static $defaultTypedDimensionTest = "(fn:count(xfi:fact-typed-dimension-value(\$aspectTest:a,#dimension)/*) eq 1) and (fn:count(xfi:fact-typed-dimension-value(\$aspectTest:b,#dimension)/*) eq 1) and (xfi:nodes-correspond(xfi:fact-typed-dimension-value(\$aspectTest:a,#dimension)/*[1],xfi:fact-typed-dimension-value(\$aspectTest:b,#dimension)/*[1]))";
	public static $defaultTypedDimensionTest = "(for \$fact1 in (xfi:fact-typed-dimension-value(\$aspectTest:a,#dimension)/*), \$fact2 in (xfi:fact-typed-dimension-value(\$aspectTest:b,#dimension)/*) " .
		"return (fn:count(\$fact1) eq 1) and (fn:count(\$fact2) eq 1) and (xfi:nodes-correspond(\$fact1[1],\$fact2[1])))[1] cast as xs:boolean";

	/**
	 * The custom typed-dimension aspect test is on defined by an equality definition.
	 * #dimension is the QName of the dimension defining the aspect, and #custom is the XPath expression
	 * contained by the @test attribute on the equality definition that MUST be associated with the typed-dimension's
	 * domain definition if the custom typed-dimension aspect test is to be applicable.
	 * @var string $customTypedDimensionTest
	 */
	public static $customTypedDimensionTest = "(fn:count(xfi:fact-typed-dimension-value(\$aspectTest:a,#dimension)/*) eq 1) and (fn:count(xfi:fact-typed-dimension-value(\$aspectTest:b,#dimension)/*) eq 1) and (#custom)";

	public static $aspectTests = array();

	/**
	 * Indicates whether or not the aspect is combinable.  Location, Period, Concept and Entity Identifier are not
	 * @var boolean
	 */
	public $combinable = false;

	/**
	 * The name of the source to use for the aspect
	 * @var string $source QName of the source to use (optional)
	 */
	public $source = null;

	public static function __static()
	{
		Aspect::$aspectTests = array(
			ASPECT_LOCATION => Aspect::$locationTest,
			ASPECT_CONCEPT => Aspect::$conceptTest,
			ASPECT_COMPLETE_SCENARIO => Aspect::$completeScenarioTest,
			ASPECT_COMPLETE_SEGMENT => Aspect::$completeSegmentTest,
			ASPECT_ENTITY_IDENTIFIER => Aspect::$entityIdentifierTest,
			ASPECT_PERIOD => Aspect::$periodTest,
			ASPECT_NON_XDT_SCENARIO => Aspect::$nonXDTScenarioTest,
			ASPECT_NON_XDT_SEGMENT => Aspect::$nonXDTSegmentTest,
			ASPECT_UNIT => Aspect::$unitTest,
		);
	}

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
		$result['label'] = $label;

		$attributes = $node->attributes();

		if ( property_exists( $attributes, 'source' ) )
		{
			// $qname = qname( trim( $attributes->source ), $node->getDocNamespaces( true ) );
			// $this->source = is_null( $qname ) ? null : $qname->clarkNotation();

			$namespaces = $node->getDocNamespaces( true );

			/**
			 * @var QName $qName
			 */
			// If there is no prefix it should not be resolved to a default namespace
			$source = trim( $attributes->source );
			$qName = strpos( $source, ":" )
				? qname( $source, $namespaces )
				: new QName( "", null, $source );
			$this->source = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);

		}

		$result['source'] = $this->source;

		return $result;
	}

	/**
	 * Stores a node array with a resource type name of 'aspect'
 	 * @param array $node A an array representation of the resource node
 	 * @param string $type 'location' | 'period' | 'concept' | 'entityIdentifier'
	 */
	public function storeAspect( $node, $type )
	{
		$node['type'] = 'aspect';
		$node['aspectType'] = $type;
		$node['combinable'] = $this->combinable;

		return $node;
	}

	/**
	 *
	 * @param VariableSet $variableSet
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return DOMXPathNavigator
	 */
	public function getValue( $variableSet, $evaluationResult, $log )
	{
	}

	/**
	 * Cache of the qname generated from a 'name' array
	 * @var QName $qname
	 */
	private $qname;

	/**
	 * Return the QName of the 'source' property if any
	 * @return QName|null
	 */
	public function getSourceQName()
	{
		// Use the cached name if there is one.
		if ( ! $this->qname )
		{
			if ( ! property_exists( $this, 'source' ) ) return null;
			$this->qname = $qname = new QName( $this->source['originalPrefix'], $this->source['namespace'], $this->source['name'] );
		}

		return $this->qname;
	}

	public function getSourceName()
	{
		$qname = $this->getSourceQName();
		return $qname->prefix
			? "{$qname->prefix}:{$qname->localName}"
			: $qname->localName;
	}
}

Aspect::__static();
