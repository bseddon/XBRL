<?php

/**
 * XBRL Inline conformance suite test runner
 * Supports v1, v2, v3 and v4
 *
 * Based on the implementation in Arelle
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2021 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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

namespace lyquidity\ixbrl;

use XBRL_Dictionary;

define( 'IXBRL_TEST_ALL', 'all' );
define( 'IXBRL_TEST_ERRORS', 'errors' );
define( 'IXBRL_TEST_COMPARES', 'compares' );

/**
 * Run all conformance tests
 * @param  string $cacheLocation
 * @param string $$testCasesFolder
 * @param array $testCategory
 * @param bool  $testClass
 * @return void
 */
function TestInlineXBRL( $cacheLocation, $testCasesFolder, $testCategory, $testClass = IXBRL_TEST_ALL )
{
	$log = \XBRL_Log::getInstance();

	$mainDoc = new \DOMDocument();
	if ( ! $mainDoc->load( "$testCasesFolder/index.xml" ) )
	{
		throw new IXBRLException('Failed to load the main test document');
	}

	$documentElement = $mainDoc->documentElement;

	$log->info( "{$documentElement->localName}" );
	$log->info( $documentElement->getAttribute('name') );

	$outputFolder = "$testCasesFolder/output";
	$xpath = new \DOMXPath( $mainDoc );
	if ( is_dir( rtrim( $outputFolder,"/").'/' ) ) 
	{
		\XBRL_Global::removeFiles( $outputFolder );
	}

	if ( ! is_dir( rtrim( $outputFolder,"/").'/' ) ) 
	if ( ! mkdir( $outputFolder ) )
	{
		throw new IXBRLTestCompareException("Unable to create folder '$outputFolder'");
	}

	foreach( $xpath->query( '//testcases', $documentElement ) as $testcases )
	{
		/** @var \DOMElement $testcases */
		$log->info( $testcases->getAttribute('title') );

		foreach( $xpath->query( 'testcase', $testcases ) as $tag => $testcase )
		{
			/** @var \DOMElement $element */
			$testcaseDir = rtrim( "{$testCasesFolder}tests/" . dirname( $testcase->getAttribute('uri') ), '/' ). '/';
			$testcaseFilename = basename( $testcase->getAttribute('uri') );
			testCase( $testcaseDir, $testcaseFilename, $outputFolder, $cacheLocation, $testCategory, $testClass );
		}
	}
}

/**
 * Execute a test case
 * @param string $basename
 * @param string $filename
 * @param string $outputFolder
 * @param string $cacheLocation
 * @param array $testCategory
 * @param bool  $testClass
 * @return boolean
 */
function testCase( $dirname, $filename, $outputFolder, $cacheLocation, $testCategory, $testClass = IXBRL_TEST_ALL )
{
	$log = \XBRL_Log::getInstance();

	list(
		$baseURIs, $continuation, $exclude, $footnotes, $format, $fraction, 
		$fullSizeTests, $header, $hidden, $html, $ids, $multiIO, $nonFraction, 
		$nonNumeric, $references, $relationships, $resources, $specificationExamples,
		$transformations, $tuple, $xmllang ) = array_values( $testCategory );

	switch( $filename  )
	{
		#region ./baseURIs - checked fail and pass tests and compares

		case "FAIL-baseURI-on-ix-header.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-baseURI-on-xhtml.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "PASS-baseURI-on-ix-references-multiRefs.xml":

		#endregion

		if ( $baseURIs ) break; else return;

		#region ./continuation - checked fail and pass tests and compares

		case "FAIL-continuation-duplicate-id.xml": // Checked
		case "FAIL-continuation-nonNumeric-circular.xml": // Checked Dangling
		case "FAIL-continuation-nonNumeric-circular2.xml": // Checked ContinuationReuse
		case "FAIL-continuation-nonNumeric-invalid-nesting-2.xml": // Checked ContinuationInvalidNesting
		case "FAIL-continuation-nonNumeric-invalid-nesting.xml": // Checked ContinuationInvalidNesting
		case "FAIL-continuation-nonNumeric-self.xml": // Checked - DanglingContinuation
		case "FAIL-continuation-orphaned-cycle.xml": // Checked UnreferencedContinuation
		case "FAIL-continuation-used-twice.xml": // Checked ContinuationReuse
		case "FAIL-footnote-continuation-invalid-nesting-2.xml": // Checked ContinuationInvalidNesting
		case "FAIL-footnote-continuation-invalid-nesting.xml": // Checked - ContinuationInvalidNesting
		case "FAIL-nonNumeric-dangling-continuation-2.xml": // Checked DanglingContinuation
		case "FAIL-nonNumeric-dangling-continuation.xml": // Checked - DanglingContinuation
		case "FAIL-orphaned-continuation.xml": // UnreferencedContinuation
		case "PASS-nonNumeric-continuation-multiple-documents.xml":
		case "PASS-nonNumeric-continuation-other-descendants-escaped.xml":
		case "PASS-nonNumeric-continuation-other-descendants.xml":
		case "PASS-nonNumeric-continuation-out-of-order.xml":
		case "PASS-nonNumeric-continuation-transform.xml":
		case "PASS-nonNumeric-continuation.xml":

		#endregion

		if ( $continuation ) break; else return;

		#region ./exclude - checked fail and pass tests and compares

		case "FAIL-exclude-nonFraction-parent.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "FAIL-misplaced-exclude.xml": // Checked - MisplacedExclude
		case "PASS-element-ix-exclude-complete.xml":
		case "PASS-exclude-nonNumeric-parent.xml":
		case "PASS-multiple-excludes-nonNumeric-parent.xml":

		#endregion

		if ( $exclude ) break; else return;

		#region ./footnotes - checked fail and pass tests and compares

		case "FAIL-element-ix-footnote-04.xml": // Checked DuplicateId
		case "FAIL-footnote-any-attribute.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2
		case "FAIL-footnote-dangling-continuation.xml": // Checked DanglingContinuation
		case "FAIL-footnote-dangling-fromRef.xml": // Checked DanglingRelationshipFromRef
		case "FAIL-footnote-dangling-toRef.xml": // Checked DanglingRelationshipToRef
		case "FAIL-footnote-duplicate-footnoteIDs-different-input-docs.xml": // Checked DuplicateId
		case "FAIL-footnote-duplicate-footnoteIDs.xml": // Checked DuplicateId
		case "FAIL-footnote-invalid-element-content.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "FAIL-footnote-missing-footnoteID.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "PASS-element-ix-footnote-03.xml":
		case "PASS-element-link-footnote-02.xml":
		case "PASS-element-link-footnote-complete-role-defs.xml":
		case "PASS-element-link-footnote-complete.xml":
		case "PASS-element-link-footnote-footnoteArcrole-2.xml":
		case "PASS-element-link-footnote-footnoteArcrole.xml":
		case "PASS-element-link-footnote-footnoteLinkRole-2.xml":
		case "PASS-element-link-footnote-footnoteLinkRole.xml":
		case "PASS-element-link-footnote-footnoteRole-2.xml":
		case "PASS-element-link-footnote-footnoteRole.xml":
		case "PASS-element-link-footnote-nonNumeric-escaped.xml":
		case "PASS-element-link-footnote-nonNumeric-unescaped.xml":
		case "PASS-element-link-footnote-nothidden.xml":
		case "PASS-element-link-footnote-resolved-uris.xml":
		case "PASS-element-link-footnote-xhtml-content-exclude.xml":
		case "PASS-element-link-footnote-xhtml-content.xml":
		case "PASS-elements-footnote-and-nonNumeric-unresolvable-uris-in-exclude.xml":
		case "PASS-footnote-any-attribute.xml":
		case "PASS-footnote-continuation.xml":
		case "PASS-footnote-footnoteLinkRole-multiple-output.xml":
		case "PASS-footnote-footnoteRole-multiple-output.xml":
		case "PASS-footnote-ix-element-content.xml":
		case "PASS-footnote-ix-exclude-content.xml":
		case "PASS-footnote-nested-ix-element-content.xml":
		case "PASS-footnote-nested-xml-base-decls.xml":
		case "PASS-footnote-on-nonFraction.xml":
		case "PASS-footnote-order-attribute.xml":
		case "PASS-footnote-relative-uris-object-tag.xml":
		case "PASS-footnote-uris-with-spaces.xml":
		case "PASS-footnote-valid-element-content.xml":
		case "PASS-footnote-within-footnote.xml":
		case "PASS-footnote-xml-base-xhtml-base-no-interaction.xml":
		case "PASS-footnoteArcrole-multiple-output.xml":
		case "PASS-footnoteRef-on-fraction.xml":
		case "PASS-footnoteRef-on-nonNumeric.xml":
		case "PASS-footnoteRef-on-tuple.xml":
		case "PASS-many-to-one-footnote-complete.xml":
		case "PASS-many-to-one-footnote-different-arcroles.xml":
		case "PASS-many-to-one-footnotes-multiple-outputs.xml":
		case "PASS-multiple-outputs-check-dont-have-empty-footnoteLinks.xml":
		case "PASS-two-footnotes-multiple-output.xml":
		case "PASS-unused-footnote.xml":

		#endregion

		if ( $footnotes ) break; else return;

		#region ./format - checked fail and pass tests compares
		
		case "FAIL-format-numdash-badContent.xml": // Checked - InvalidDataType
		case "FAIL-ix-format-undefined.xml": // Checked - FormatUndefined
		case "PASS-element-ix-nonFraction-ixt-num-nodecimals.xml":
		case "PASS-format-numdash.xml":

		#endregion

		if ( $format ) break; else return;

		#region ./fraction - checked fail and pass tests and compares

		case "FAIL-fraction-denominator-empty.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-minLength-valid, 1831
		case "FAIL-fraction-denominator-illegal-child-node.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_2, 1842
		case "FAIL-fraction-denominator-ix-format-expanded-name-mismatch.xml": // Checked - FormatUndefined
		case "FAIL-fraction-denominator-ix-format-invalid.xml": // Checked - InvalidDataType
		case "FAIL-fraction-denominator-ix-sign-invalid.xml": // Checked - UnknownFractionChild
		case "FAIL-fraction-illegal-content.xml": // Checked - UnknownFractionChild
		case "FAIL-fraction-illegal-nesting-unitRef.xml": // Checked - FractionNestedAttributeMismatch
		case "FAIL-fraction-illegal-nesting-xsi-nil-2.xml": // Checked - FractionNestedNilMismatch
		case "FAIL-fraction-illegal-nesting-xsi-nil.xml": // Checked - FractionNestedNilMismatch
		case "FAIL-fraction-illegal-nesting.xml": // Checked - MultipleNumeratorDenominator
		case "FAIL-fraction-ix-any-attribute.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1867
		case "FAIL-fraction-ix-contextRef-unresolvable.xml": // Checked - UnknownContext
		case "FAIL-fraction-ix-footnoteRef-unresolvable.xml": // Checked - DanglingRelationshipToRef
		case "FAIL-fraction-ix-tupleRef-attr-tuple-missing.xml": // Checked - UnknownTuple
		case "FAIL-fraction-ix-unitRef-unresolvable.xml": // Checked - UnknownUnit
		case "FAIL-fraction-missing-contextRef.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-fraction-missing-denominator.xml": // Checked - IncompleteFraction
		case "FAIL-fraction-missing-numerator-and-denominator.xml": // Checked - IncompleteFraction
		case "FAIL-fraction-missing-numerator.xml": // Checked - IncompleteFraction
		case "FAIL-fraction-missing-unitRef.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-fraction-multiple-denominators.xml": // Checked - MultipleNumeratorDenominator
		case "FAIL-fraction-multiple-numerators.xml": // Checked - MultipleNumeratorDenominator
		case "FAIL-fraction-numerator-denominator-non-xsi-attributes.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-fraction-numerator-empty.xml": // xbrl.core.xml.SchemaValidationError.cvc-minLength-valid, 1831
		case "FAIL-fraction-numerator-illegal-child-node.xml": // xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_2, 1842,
		case "FAIL-fraction-numerator-ix-format-expanded-name-mismatch.xml": // Checked - FormatUndefined
		case "FAIL-fraction-numerator-ix-format-invalid.xml": // Checked - InvalidDataType 
		case "FAIL-fraction-numerator-ix-sign-invalid.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-pattern-valid, 1839
		case "FAIL-fraction-rule-no-other-ixDescendants.xml": // Checked - UnknownFractionChild
		case "FAIL-fraction-rule-no-xbrli-attributes.xml": // Checked - InvalidAttributeContent
		case "PASS-attribute-ix-format-denominator-01.xml":
		case "PASS-attribute-ix-format-numerator-01.xml":
		case "PASS-attribute-ix-name-fraction-01.xml":
		case "PASS-attribute-ix-scale-denominator-01.xml":
		case "PASS-attribute-ix-scale-denominator-04.xml":
		case "PASS-attribute-ix-scale-numerator-01.xml":
		case "PASS-attribute-ix-scale-numerator-04.xml":
		case "PASS-attribute-ix-sign-denominator-01.xml":
		case "PASS-attribute-ix-sign-numerator-01.xml":
		case "PASS-fraction-denominator-ix-format-expanded-name-match.xml":
		case "PASS-fraction-denominator-ix-format.xml":
		case "PASS-fraction-denominator-ix-sign-scale-valid.xml":
		case "PASS-fraction-ix-order-attr.xml":
		case "PASS-fraction-ix-target-attr.xml":
		case "PASS-fraction-ix-tupleRef-attr.xml":
		case "PASS-fraction-nesting-2.xml":
		case "PASS-fraction-nesting-3.xml":
		case "PASS-fraction-nesting-4.xml":
		case "PASS-fraction-nesting.xml":
		case "PASS-fraction-non-ix-any-attribute.xml":
		case "PASS-fraction-numerator-denominator-xsi-attributes.xml":
		case "PASS-fraction-numerator-ix-format-expanded-name-match.xml":
		case "PASS-fraction-numerator-ix-format.xml":
		case "PASS-fraction-numerator-ix-sign-valid.xml":
		case "PASS-fraction-xsi-nil.xml":
		case "PASS-ix-denominator-01.xml":
		case "PASS-ix-denominator-02.xml":
		case "PASS-ix-denominator-03.xml":
		case "PASS-ix-denominator-04.xml":
		case "PASS-simple-fraction.xml":
		case "PASS-ix-numerator-04.xml":
		case "PASS-simple-fraction-with-html-children.xml":

		#endregion

		if ( $fraction ) break; else return;

		#region ./fullSizeTests - no fail tests passes compares

		case "PASS-full-size-unnested-tuples.xml":
		case "PASS-full-size-with-footnotes.xml":
		case "PASS-largeTestNoMarkup.xml":

		#endregion

		if ( $fullSizeTests ) break; else return;

		#region ./header - checked fail and pass tests and compares

		case "FAIL-ix-header-child-of-html-header.xml": // Checked
		case "FAIL-misplaced-ix-element-in-context.xml": // Checked - MisplacedIXElement
		case "FAIL-missing-header.xml": // Checked HeaderAbsent, ReferencesAbsent, ResourcesAbsent
		case "PASS-header-content-split-over-input-docs.xml":
		case "PASS-header-empty.xml":
		case "PASS-single-ix-header-muli-input.xml":

		#endregion

		if ( $header ) break; else return;

		#region ./hidden - checked fail and pass tests and compares

		case "FAIL-empty-hidden.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_b, 1871
		case "FAIL-hidden-empty-tuple-content.xml": // Checked - TupleNonEmptyValidation
		case "FAIL-hidden-illegal-content.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "FAIL-hidden-incorrect-order-in-header.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_d, 1871
		case "FAIL-hidden-not-header-descendant.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "PASS-hidden-nonFraction-content.xml":
		case "PASS-hidden-tuple-content.xml":

		#endregion

		if ( $hidden ) break; else return;

		#region ./html - checked fail tests (no pass/compare tests)

		case "FAIL-a-name-attribute.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866
		case "FAIL-charset-on-meta.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1866
		case "FAIL-empty-class-attribute.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-minLength-valid, xbrl.core.xml.SchemaValidationError.cvc-attribute_3

		#endregion

		if ( $html ) break; else return;

		#region ./ids - checked fail tests (no pass/compare tests)

		case "FAIL-id-triplication.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-context.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-footnote.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-fraction.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-nonFraction.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-nonNumeric.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-references.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-tuple.xml": // Checked DuplicateId
		case "FAIL-non-unique-id-unit.xml": // Checked DuplicateId

		#endregion

		if ( $ids ) break; else return;

		#region ./multiIO - checked fail and pass tests and compares

		case "FAIL-multi-input-duplicate-context-ids.xml": // Checked - DuplicateId
		case "FAIL-multi-input-duplicate-unit-ids.xml": // Checked - DuplicateId
		case "FAIL-two-inputs-each-with-error.xml": // Checked - UnknownContext
		case "FAIL-two-nonIXBRL-inputs.xml": // Checked - UnsupportedDocumentType
		case "PASS-double-input-single-output.xml":
		case "PASS-ix-references-06.xml":
		case "PASS-ix-references-07.xml":
		case "PASS-multiple-input-multiple-output.xml":
		case "PASS-single-input-double-output.xml":
		case "PASS-single-input.xml":

		#endregion

		if ( $multiIO ) break; else return;

		#region ./nonFraction - checked fail and pass tests and compares

		case "FAIL-nonFraction-IXBRLelement-content.xml": // Checked - NonFractionChildElementMixed
		case "FAIL-nonFraction-any-ix-attribute.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867, 1866, 1867
		case "FAIL-nonFraction-decimals-and-precision-attrs.xml": // Checked - PrecisionAndDecimalsPresent
		case "FAIL-nonFraction-double-nesting.xml": // Checked - brl.core.xml.SchemaValidationError.cvc-complex-type_2_4_d, 1871
		case "FAIL-nonFraction-empty-content.xml": // Checked - NonFractionIncompleteContent
		case "FAIL-nonFraction-empty-without-xsi-nil.xml": // Checked - NonFractionIncompleteContent
		case "FAIL-nonFraction-invalid-sign-attr.xml": // Checked - xml.SchemaValidationError.cvc-pattern-valid, xbrl.core.xml.SchemaValidationError.cvc-attribute_3, 1839
		case "FAIL-nonFraction-ix-format-expanded-name-mismatch.xml": // Checked - FormatUndefined
		case "FAIL-nonFraction-ix-format-invalid-minus-sign.xml": // Checked - InvalidDataType
		case "FAIL-nonFraction-ix-format-invalid.xml": // Checked - InvalidDataType
		case "FAIL-nonFraction-missing-context-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-nonFraction-missing-name-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-nonFraction-missing-unit-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-nonFraction-mixed-nesting-2.xml": // Checked - NonFractionChildElementMixed
		case "FAIL-nonFraction-mixed-nesting-3.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "FAIL-nonFraction-mixed-nesting.xml": // Checked - NonFractionChildElementMixed
		case "FAIL-nonFraction-neither-decimals-nor-precision-attrs.xml": // Checked - PrecisionAndDecimalsAbsent
		case "FAIL-nonFraction-nesting-format-mismatch-2.xml": // Checked - NonFractionNestedAttributeMismatch
		case "FAIL-nonFraction-nesting-format-mismatch.xml": // Checked - NonFractionNestedAttributeMismatch
		case "FAIL-nonFraction-nesting-scale-mismatch-2.xml": // Checked - NonFractionNestedAttributeMismatch
		case "FAIL-nonFraction-nesting-scale-mismatch.xml": // Checked - NonFractionNestedAttributeMismatch
		case "FAIL-nonFraction-nesting-unitRef-mismatch.xml": // Checked - NonFractionNestedAttributeMismatch
		case "FAIL-nonFraction-nesting-xsi-nil.xml": // Checked - NonFractionNestedNilMismatch
		case "FAIL-nonFraction-nil-attr-false.xml": // Checked - PrecisionAndDecimalsAbsent
		case "FAIL-nonFraction-nil-decimal-conflict.xml": // Checked- PrecisionAndDecimalsPresent
		case "FAIL-nonFraction-no-format-negative-number.xml": // Checked - FormatAbsentNegativeNumber
		case "FAIL-nonFraction-unresolvable-ix-tupleRef-attr.xml": // Checked - UnknownTuple
		case "FAIL-nonfraction-rule-no-xbrli-attributes.xml": // Checked - InvalidAttributeContent
		case "FAIL-unresolvable-contextRef.xml": // Checked - UnknownContext
		case "FAIL-unresolvable-unitRef.xml": // Checked = UnknownUnit
		case "PASS-attribute-ix-format-nonFraction-01.xml": // Simple value 1234
		case "PASS-attribute-ix-name-nonFraction-01.xml": // Value 12..345 scale 3
		case "PASS-attribute-ix-scale-nonFraction-01.xml": // Value 12345 scale -3
		case "PASS-attribute-ix-scale-nonFraction-04.xml": // Value 12345 scale 10
		case "PASS-attribute-ix-sign-nonFraction-01.xml":
		case "PASS-element-ix-nonFraction-complete.xml":
		case "PASS-element-ix-nonFraction-ixt-numcomma.xml":
		case "PASS-element-ix-nonFraction-ixt-numcommadot.xml":
		case "PASS-element-ix-nonFraction-ixt-numdash.xml":
		case "PASS-element-ix-nonFraction-ixt-numdotcomma.xml":
		case "PASS-element-ix-nonFraction-ixt-numspacecomma.xml":
		case "PASS-element-ix-nonFraction-ixt-numspacedot.xml":
		case "PASS-nonFraction-any-attribute.xml":
		case "PASS-nonFraction-comments.xml":
		case "PASS-nonFraction-decimals-attr.xml":
		case "PASS-nonFraction-ix-format-expanded-name-match.xml":
		case "PASS-nonFraction-ix-format-valid.xml":
		case "PASS-nonFraction-ix-order-attr.xml":
		case "PASS-nonFraction-ix-target-attr.xml":
		case "PASS-nonFraction-ix-tupleRef-attr.xml":
		case "PASS-nonFraction-nesting-2.xml":
		case "PASS-nonFraction-nesting-formats-2.xml":
		case "PASS-nonFraction-nesting-formats.xml":
		case "PASS-nonFraction-nesting-scale.xml":
		case "PASS-nonFraction-nesting.xml":
		case "PASS-nonFraction-precision-attr.xml":
		case "PASS-nonFraction-processing-instructions.xml":
		case "PASS-nonFraction-valid-scale-attr.xml":
		case "PASS-nonFraction-valid-sign-attr.xml":
		case "PASS-nonFraction-valid-sign-format-attr.xml":
		case "PASS-nonFraction-xsi-nil-attr.xml":
		case "PASS-simple-nonFraction.xml":

		#endregion

		if ( $nonFraction ) break; else return;

		#region ./nonNumeric - checked fail and pass tests and compares

		case "FAIL-element-ix-nonNumeric-escape-01.xml": // Checked - InvalidDataType
		case "FAIL-nonNumeric-any-ix-attribute.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867, 1866, 1867
		case "FAIL-nonNumeric-empty-with-format.xml": // Checked - InvalidDataType
		case "FAIL-nonNumeric-illegal-null-namespace-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-nonNumeric-invalid-ix-format-attr.xml": // Checked - InvalidDataType
		case "FAIL-nonNumeric-ix-format-attr-wrong-namespace-binding.xml": // Checked - FormatUndefined
		case "FAIL-nonNumeric-missing-contextRef-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-nonNumeric-missing-name-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-nonNumeric-no-xbrli-attributes.xml": // Checked - InvalidAttributeContent
		case "FAIL-nonNumeric-unresolvable-ix-contextRef.xml": // Checked - UnknownContext
		case "FAIL-nonNumeric-unresolvable-ix-tupleRef-attr.xml": // Checked - UnknownTuple
		case "PASS-attribute-ix-extension-illegalPlacement-01.xml":
		case "PASS-attribute-ix-name-nonNumeric-01.xml":
		case "PASS-element-ix-nonNumeric-complete.xml":
		case "PASS-element-ix-nonNumeric-escape-02.xml":
		case "PASS-element-ix-nonNumeric-escape-03.xml":
		case "PASS-element-ix-nonNumeric-escape-04.xml":
		case "PASS-element-ix-nonNumeric-escape-05.xml":
		case "PASS-element-ix-nonNumeric-escape-06.xml":
		case "PASS-element-ix-nonNumeric-escape-07.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedoteu-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedotus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedotus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-datedotus-03.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongdaymonthuk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongmonthdayus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongmonthyear-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelonguk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelonguk-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-datelongyearmonth-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortdaymonthuk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortmonthdayus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortmonthyear-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortuk-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortuk-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateshortyearmonth-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashdaymontheu-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslasheu-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslasheu-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslasheu-03.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashmonthdayus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-01.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-02.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-03.xml":
		case "PASS-element-ix-nonNumeric-ixt-dateslashus-04.xml":
		case "PASS-element-ordering.xml":
		case "PASS-nonNumeric-any-attribute.xml":
		case "PASS-nonNumeric-empty-not-xsi-nil.xml":
		case "PASS-nonNumeric-escape-with-html-base.xml":
		case "PASS-nonNumeric-ix-format-attr-expanded-name-match.xml":
		case "PASS-nonNumeric-ix-format-attr.xml":
		case "PASS-nonNumeric-ix-order-attr.xml":
		case "PASS-nonNumeric-ix-target-attr.xml":
		case "PASS-nonNumeric-ix-tupleRef-attr.xml":
		case "PASS-nonNumeric-nesting-in-exclude.xml":
		case "PASS-nonNumeric-nesting-numerator.xml":
		case "PASS-nonNumeric-nesting-text-in-exclude.xml":
		case "PASS-nonNumeric-nesting.xml":
		case "PASS-nonNumeric-xsi-nil.xml":
		case "PASS-nonNumeric.xml":

		#endregion

		if ( $nonNumeric ) break; else return;

		#region ./references - checked fail and pass tests and compares

		case "FAIL-empty-references.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_b, 1871
		case "FAIL-ix-references-03.xml": // Checked InvalidAttributeContent
		case "FAIL-ix-references-08.xml": // Checked RepeatedOtherAttributes
		case "FAIL-ix-references-09.xml": // Checked DuplicateId
		case "FAIL-ix-references-namespace-bindings-01.xml": // Checked ReferencesNamespaceClash
		case "FAIL-ix-references-namespace-bindings-02.xml": // Checked ReferencesNamespaceClash
		case "FAIL-ix-references-namespace-bindings-03.xml": // Checked ReferencesNamespaceClash
		case "FAIL-ix-references-namespace-bindings-04.xml": // Checked ReferencesNamespaceClash
		case "FAIL-ix-references-rule-multiple-attributes-sameValue.xml": // Checked RepeatedOtherAttributes
		case "FAIL-ix-references-rule-multiple-id.xml": // Checked RepeatedIdAttribute
		case "FAIL-missing-references-for-all-target-documents.xml": // Checked ReferencesAbsent
		case "FAIL-missing-references.xml": // Checked ReferencesAbsent
		case "FAIL-references-illegal-content.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "FAIL-references-illegal-location.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "FAIL-references-illegal-order-in-header.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a, 1871
		case "PASS-element-ix-references-01.xml":
		case "PASS-ix-references-01.xml":
		case "PASS-ix-references-02.xml":
		case "PASS-ix-references-04.xml":
		case "PASS-ix-references-05.xml":
		case "PASS-ix-references-rule-multiple-matched-target.xml":
		case "PASS-ix-references-rule-multiple-xmlBase.xml":
		case "PASS-references-copy-non-ix-attrs.xml":
		case "PASS-references-ix-target-attr.xml":
		case "PASS-simple-linkbaseRef.xml":
		case "PASS-simple-schemaRef.xml":
		case "PASS-single-references-multi-input.xml":

		#endregion

		if ( $references ) break; else return;

		#region ./relationships - checked fail and pass tests and compares

		case "FAIL-relationship-cross-duplication.xml": // Checked RelationshipCrossDuplication
		case "FAIL-relationship-mixes-footnote-with-explanatory-fact.xml": // Checked RelationshipMixedToRefs
		case "FAIL-relationship-with-no-namespace-attribute.xml": // Checked xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1867
		case "FAIL-relationship-with-xbrli-attribute.xml": // xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1867
		case "FAIL-relationship-with-xlink-attribute.xml": // xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1867
		case "PASS-explanatory-fact-copy-to-owner-target.xml":
		case "PASS-explanatory-fact-cycle.xml":
		case "PASS-explanatory-fact-not-hidden.xml":
		case "PASS-explanatory-fact.xml":
		case "PASS-relationship-to-multiple-explanatory-facts-multiple-outputs.xml":
		case "PASS-relationship-to-multiple-explanatory-facts.xml":
		case "PASS-relationship-with-xml-base.xml":
		case "PASS-tuple-footnotes.xml":

		#endregion

		if ( $relationships ) break; else return;

		#region ./resources - checked fail and pass tests and compares

		case "FAIL-context-without-id.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-missing-resources.xml": // Checked - ResourcesAbsent
		case "FAIL-unit-without-id.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "PASS-empty-resources.xml":
		case "PASS-simple-arcroleRef.xml":
		case "PASS-simple-roleRef.xml":

		#endregion

		if ( $resources ) break; else return;

		#region ./specificationExamples - no fail tests passes compares

		case "PASS-section-10.3-example-1.xml":
		case "PASS-section-11.3-example-2.xml":
		case "PASS-section-15.1-example-3.xml":
		case "PASS-section-15.1-example-4.xml":

			#endregion

		if ( $specificationExamples ) break; else return;

		#region ./transformations - checked fail and pass tests and compares

		case "FAIL-invalid-long-month.xml": // Checked - InvalidDataType
		case "FAIL-invalid-short-month.xml": // Checked - InvalidDataType
		case "FAIL-unrecognised-schema-type.xml": // Checked - FormatUndefined
		case "PASS-sign-attribute-on-nonFraction-positive-input.xml":

		#endregion

		if ( $transformations ) break; else return;

		#region ./tuple - checked fail and pass tests and compares

		case "FAIL-badly-formatted-order-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-attribute_3, 1824
		case "FAIL-badly-nested-tuples.xml": // Checked - TupleCycle
		case "FAIL-duplicate-order-and-value-but-not-attributes.xml": // Checked - OrderDuplicate
		case "FAIL-duplicate-tuple-id-different-input-docs.xml": // Checked - DuplicateTupleId
		case "FAIL-duplicate-tuple-id.xml": // Checked - DuplicateTupleId
		case "FAIL-duplicate-tuple-order-different-values.xml": // Checked - OrderDuplicate
		case "FAIL-illegal-element-nested.xml": // Checked = InvalidTupleChild
		case "FAIL-illegal-element.xml": // Checked = InvalidTupleChild
		case "FAIL-missing-descendants.xml": // Checked - TupleNonEmptyValidation
		case "FAIL-nested-tuple-empty.xml": // Checked - TupleNonEmptyValidation
		case "FAIL-order-attr-denominator.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-order-attr-inNonTuple.xml": // Checked - OrderOnNonTupleChild
		case "FAIL-order-attr-numerator.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-ordering-order-duplicate-stringUnequal.xml": // Checked - OrderDuplicate
		case "FAIL-ordering-order-duplicate.xml": // Checked - OrderDuplicate
		case "FAIL-ordering-partially-missing.xml": // Checked - OrderAbsent
		case "FAIL-orphaned-tuple-content.xml": // Checked - UnknownTuple
		case "FAIL-tuple-any-ix-attribute.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-tuple-content-in-different-targets-tuple-not-in-default.xml": // Checked - InconsistentTargets
		case "FAIL-tuple-content-in-different-targets.xml": // Checked - InconsistentTargets
		case "FAIL-tuple-cycle-by-tupleRef.xml": // Checked - TupleCycle
		case "FAIL-tuple-cycle-child.xml": // Checked - TupleCycle
		case "FAIL-tuple-cycle-grandchildren.xml": // Checked - TupleCycle
		case "FAIL-tuple-empty-no-ix-tupleID.xml": // Checked - TupleNonEmptyValidation
		case "FAIL-tuple-empty.xml": // Checked - TupleNonEmptyValidation
		case "FAIL-tuple-missing-name-attr.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_4, 1868
		case "FAIL-tuple-no-xbrli-attributes.xml": // Checked - InvalidAttributeContent
		case "FAIL-tuple-unresolvable-footnoteRef-attr.xml": // Checked - DanglingRelationshipToRef
		case "FAIL-tuple-xsi-nil-with-tuple-ref.xml":
		case "PASS-attribute-ix-name-tuple-01.xml":
		case "PASS-duplicate-order-same-ws-normalized-value-with-html.xml":
		case "PASS-duplicate-order-same-ws-normalized-value.xml":
		case "PASS-element-ix-tuple-complete.xml":
		case "PASS-element-tuple-reference-multiInput.xml":
		case "PASS-element-tuple-reference.xml":
		case "PASS-exotic-tuple-order.xml":
		case "PASS-nested-tuple-nonEmpty.xml":
		case "PASS-nested-tuple-ix-order-no-tupleRef.xml":
		case "PASS-nested-tuple.xml":
		case "PASS-nonFraction-nesting-reference-conflict.xml":
		case "PASS-ordering-references-nesting-order.xml":
		case "PASS-singleton-tuple.xml":
		case "PASS-tuple-all-content-nested-noTupleID.xml":
		case "PASS-tuple-any-attribute.xml":
		case "PASS-tuple-ix-target-attr.xml":
		case "PASS-tuple-nested-nonNumeric.xml":
		case "PASS-tuple-nesting-reference-conflict.xml":
		case "PASS-tuple-nonInteger-ordering-nested.xml":
		case "PASS-tuple-ordering-nested.xml":
		case "PASS-tuple-scope-inverted-siblings.xml":
		case "PASS-tuple-scope-inverted.xml":
		case "PASS-tuple-scope-nested-nonNumeric.xml":
		case "PASS-tuple-scope-nonNumeric.xml":
		case "PASS-tuple-xsi-nil.xml":

		#endregion

		if ( $tuple ) break; else return;

		#region ./xmllang - checked fail and pass tests and compares

		case "FAIL-xml-lang-not-in-scope-for-footnote.xml": // Checked - FootnoteWithoutXmlLangInScope
		case "FAIL-xml-lang-on-ix-hidden-and-on-footnote.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "FAIL-xml-lang-on-ix-hidden.xml": // Checked - xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2, 1866, 1867
		case "PASS-direct-xml-lang-not-overidden.xml": // Checked
		case "PASS-xml-lang-on-xhtml.xml": // Checked

		#endregion

		if ( $xmllang ) break; else return;


		default:

			return;
	}

	$testDoc = new \DOMDocument();
	if ( ! $testDoc->load( "$dirname$filename" ) )
	{
		throw new IXBRLException('Failed to load the test case document: $filename');
	}

	$documentElement = $testDoc->documentElement;
	$xpath = new \DOMXPath( $testDoc );
	$xpath->registerNamespace('tc', 'http://xbrl.org/2008/conformance' );

	/**
	 * Get the text for an element
	 * @param string $elementName
	 * @param string $node
	 * @return string
	 */
	$getElementText = function( $elementName, $node ) use( $xpath )
	{
		/** @var \DOMNodeList $elements */
		$elements = $xpath->query( $elementName, $node );
		return count( $elements )
			? $number = $elements[0]->textContent
			: '';
	};

	/**
	 * Get an array of text content for an element
	 * @param string $elementName
	 * @param string $node
	 * @param bool $groupByTarget (optional: false) When true the array will be indexed by the named target
	 * @return array
	 */
	$getTextArray = function( $elementName, $node, $groupByTarget = false ) use( $xpath )
	{
		$elements = array();
		foreach( $xpath->query( $elementName, $node ) as $element )
		{
			if ( $groupByTarget )
			{
				$target = $element->getAttribute(IXBRL_ATTR_TARGET);
				$elements[ $target ] = $element->textContent;
			}
			else
			{
				$elements[] = $element->textContent;
			}
		}
		return $elements;
	};

	$number = $getElementText('tc:number', $documentElement );
	$name = $getElementText('tc:name', $documentElement );

	foreach( $xpath->query( 'tc:variation', $documentElement ) as $tag => $variation )
	{
		/** @var \DOMElement $variation */
		$id = $variation->getAttribute(IXBRL_ATTR_ID);
		$description = $getElementText('tc:description', $variation );

		$firstInstances = $getTextArray( 'tc:data/tc:instance[@readMeFirst="true"]', $variation );
		$otherInstances = array_diff( $getTextArray( 'tc:data/tc:instance', $variation ), $firstInstances );
		$result = $xpath->query( 'tc:result', $variation )[0];
		$expected = $result->getAttribute('expected');
		$standard = ! boolval( $result->getAttribute('nonStandardErrorCodes') );
		$errors = array();
		if ( $expected == 'valid' )
		{
			$resultInstances = $getTextArray( 'tc:instance', $result, true );
		}
		else
		{
			$resultInstances = array();
			$errors = $getTextArray( 'tc:error', $result );
			$extras = array();
			foreach( $errors as $error )
			{
				switch( $error )
				{
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_3_2_2':
						$extras[] = '1866';
						$extras[] = '1867';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_4':
						$extras[] = '1868';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_d':
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_b':
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_4_a':
						$extras[] = '1871';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-pattern-valid':
						$extras[] = '1839';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-minLength-valid':
						$extras[] = '1831';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-complex-type_2_2':
						$extras[] = '1842';
						break;
					case 'xbrl.core.xml.SchemaValidationError.cvc-attribute_3':
						$extras[] = '1824';
						break;
					}
			}
	
			$errors = array_merge( $errors, $extras );
		}

		// Select the correct set of tests
		switch( $testClass )
		{
			case IXBRL_TEST_COMPARES:
				if ( $errors ) return;
				break;

			case IXBRL_TEST_ERRORS:
				if ( ! $errors ) return;
				break;

			default:
				break;
		}
	
		$message = "($id) $filename - $description ";
		$message .= " ($expected" . ( $errors ? ": " . join( ',', $errors ) : "" ) . ")";
		$log->info( $message );

		// True if the test result agrees with the expected result
		$success = false;
		global $issues;

		try
		{
			$documentSet = array_map( function( $document ) use( $dirname ) 
			{
				return \XBRL::resolve_path( $dirname, $document );
			}, array_merge( $firstInstances, $otherInstances ) );

			$predictedSet = array_map( function( $document ) use( $dirname ) 
			{
				return \XBRL::resolve_path( $dirname, $document );
			}, $resultInstances );

			/** @var \DOMElement[] */
			$documents = XBRL_Inline::createInstanceDocument( $name, $documentSet, $cacheLocation, true );
			if ( $expected == 'invalid' )
			{
				$log->warning( "The test result (valid) does not match the expected result (invalid)" );
				$error = join( ',', $errors );
				$log->warning( "The expected error is ($error)" );

				$issues[] = array(
					'id' => $id,
					'filename' => $filename,
					'variation' => $number,
					'type' => 'Expected invalid result',
					'expected error' => join( ', ', $errors ),
					'actual error' => 'createInstanceDocument returns false',
					'message' => "The test result (valid) does not match the expected result (invalid)",
				);

				continue;
			}

			// Check there are documents for each target
			$x = array_diff_key( $documents, $predictedSet );
			$y = array_diff_key( $predictedSet, $documents );
			if ( $x || $y )
			{
				$missingTargets = "'" . join( ', ', array_keys( array_merge( $x, $y ) ) ) . "'";
				throw new IXBRLDocumentValidationException( 'UnmatchedTarget', "There are unmatched targets in the generated instance documents: $missingTargets" );
			}

			// Save the instance documents
			foreach( $documents as $target => $document )
			{
				/** @var \DOMDocument $document */
				$predictedFilename = str_replace( 'predicted', 'generated', basename( $predictedSet[ $target ] ) );
				// Create formatted output so 
				$document->formatOutput = true;
				$xml = $document->saveXML();
				if ( ! file_put_contents( "$outputFolder/$predictedFilename", $xml ) )
				{
					throw new IXBRLDocumentValidationException("Unable to saved instance document '$predictedFilename'");
				}
			}

			// Compare the generated documents with the predicted documents
			foreach( $documents as $target => $document )
			{
				/** @var \DOMDocument $document */
				$predictedFilename = $predictedSet[ $target ];
				$outputFilename = str_replace( 'predicted', 'generated', basename( $predictedFilename ) );
				compare( "$outputFolder/$outputFilename", $predictedFilename );
			}

			$success = true;
		}
		catch( IXBRLSchemaValidationException $ex )
		{
			$validator = $ex->getValidator();
			if ( $expected == 'invalid' )
			{
				if ( $validator->hasErrorCode( $errors ) )
				{
					// echo join( ', ', $errors ) . "\n";
					$success = true;
				}
			}

			if ( ! $success )
			{
				if ( $expected == 'valid' )
				{
					$log->warning( "The test result (invalid) does not match the expected result (valid)" );
					$issues[] = array(
						'id' => $id,
						'filename' => $filename,
						'variation' => $number,
						'type' => 'Expected valid result',
						'expected error' => 'none',
						'actual error' => join( ",\n", array_map( function( $error ) use( $validator ) { return $validator->formatError( $error ); }, $validator->errors ) ),
						'message' => $ex->getMessage(),
					);
				}
				else
				{
					$log->warning( "The test result error does not match the expected error ($error)" );
					$issues[] = array(
						'id' => $id,
						'filename' => $filename,
						'variation' => $number,
						'type' => 'Expected invalid result',
						'expected error' => join( ",\n", $errors ),
						'actual error' => join( ",\n", array_map( function( $error ) use( $validator ) { return $validator->formatError( $error ); }, $validator->errors ) ),
						'message' => $ex->getMessage(),
					);
				}

				$validator->displayErrors();
			}

		}
		catch( IXBRLDocumentValidationException $ex )
		{
			if ( $expected == 'valid' )
			{
				$log->warning( "The test result (invalid) does not match the expected result (valid)" );
				$issues[] = array(
					'id' => $id,
					'filename' => $filename,
					'variation' => $number,
					'type' => 'Expected valid result',
					'expected error' => 'none',
					'actual error' => $ex->getErrorCode(),
					'message' => $ex->getMessage(),
				);
			}
			else if ( array_search( $ex->getErrorCode(), $errors ) === false )
			{
				$log->warning( "The test result error does not match the expected error ($error)" );
				$issues[] = array(
					'id' => $id,
					'filename' => $filename,
					'variation' => $number,
					'type' => 'Expected invalid result',
					'expected error' => join( ",\n", $errors ),
					'actual error' => $ex->getErrorCode(),
					'message' => $ex->getMessage(),
				);
			}
			else
			{
				// echo $ex->getErrorCode() . "\n";
				$success = true;
			}

			if ( ! $success )
			{
				// echo $ex;
			}
		}
		catch( IXBRLTestCompareException $ex )
		{
			$log->err( $ex );
			$issues[] = array(
				'id' => $id,
				'filename' => $filename,
				'variation' => $number,
				'type' => 'Expected valid instance document',
				'actual error' => 'IXBRLTestCompareException',
				'message' => $ex->getMessage(),
			);
		}
		catch( IXBRLException $ex ) 
		{
			$log->err( $ex );
			$issues[] = array(
				'id' => $id,
				'filename' => $filename,
				'variation' => $number,
				'type' => 'Expected invalid result',
				'expected error' => join( ', ', $errors ),
				'actual error' => 'IXBRLException',
				'message' => $ex->getMessage(),
			);
		}
		catch( \Exception $ex )
		{
			$log->err( $ex );
			$issues[] = array(
				'id' => $id,
				'filename' => $filename,
				'variation' => $number,
				'type' => 'Expected invalid result',
				'expected error' => join( ', ', $errors ),
				'actual error' => '\Exception',
				'message' => $ex->getMessage(),
			);
		}

	}
}

/**
 * Compares two documents. 
 * This function grabs each element from the document and confirms:
 * - the same element exists in both
 * - they have equivalent paraents
 * - the same attribute sequence with the same attribute values
 * - the same whitespace normalized content
 *
 * @param \DOMDocument $generated
 * @param string $predictedFilename
 * @return 
 * @throws IXBRLTestCompareException
 */
function compare( $generatedFilename, $predictedFilename )
{
	$basename = basename( $predictedFilename );
	libxml_clear_errors();

	// Begin by opening the predicted file
	if ( ! file_exists( $predictedFilename ) )
	{
		throw new IXBRLTestCompareException("The predicted output instance document '$predictedFilename' does not exist.");
	}

	$predicted = new \DOMDocument();
	// $predictedXml = html_entity_decode( file_get_contents( $predictedFilename ), ENT_XHTML );
	$predictedXml = file_get_contents( $predictedFilename );

	if ( ! $predicted->loadXML( $predictedXml ) )
	{
		throw new IXBRLTestCompareException("The predicted output instance document '$predictedFilename' failed to open.", libxml_get_errors() );
	}

	// Next by open the generated file
	if ( ! file_exists( $generatedFilename ) )
	{
		throw new IXBRLTestCompareException("The predicted output instance document '$generatedFilename' does not exist.");
	}

	$generated = new \DOMDocument();
	if ( ! $generated->load( $generatedFilename ) )
	{
		throw new IXBRLTestCompareException("The predicted output instance document '$generatedFilename' failed to open.", libxml_get_errors() );
	}

	$dictionary = new \XBRL_Dictionary();

	// Get the generated elements and create hashes for each
	$generatedHashes = createHashes( $dictionary, $generated, false );
	// echo print_r( $generatedHashes, true );
	
	// Get the generated elements
	$predictedHashes = createHashes( $dictionary, $predicted, true );
	// echo print_r( $predictedHashes, true );

	$missingPredicted = array_diff_key( $generatedHashes, $predictedHashes );
	$missingGenerated = array_diff_key( $predictedHashes, $generatedHashes );
	// Ignore excess units and contexts in the predicted output
	$missingGenerated = array_filter( $missingGenerated, function( $hash ) { return $hash['e'] != "{http://www.xbrl.org/2003/instance}unit" && $hash['e'] != "{http://www.xbrl.org/2003/instance}context"; } );

	if ( $missingGenerated )
	{
		throw new IXBRLTestCompareException('MismatchedGeneratedElements');
	}

	if ( $missingPredicted )
	{
		throw new IXBRLTestCompareException('MismatchedPredictedElements');
	}
}

/**
 * Returns an array of elements indexed by their hashes
 * @param XBRL_Dictionary $dictionary
 * @param \DOMDocument $doc
 * @return \DOMElement[]
 * @param b0ol $predicted (optional: false)
 */
function createHashes( $dictionary, $doc, $predicted )
{
	$xpath = new \DOMXPath( $doc );
	$xpath->registerNamespace( STANDARD_PREFIX_XBRLI, \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI] );

	// Get the generated elements and create hashes for each
	$elements = $xpath->query('//*');

	$hashes = array();
	foreach( $elements as $element )
	{
		list( $hash, $elements ) = createHash( $dictionary, $element, $predicted );
		$hashes[ $hash['hash'] ] = $elements;
	}

	return $hashes;
}

/**
 * Returns a hash for an element generated from an array of:
 * - clark name for the element
 * - clark names for all parents
 * - whitespace eliminated content
 * - attribute values indexed and sorted by clark name
 * @param XBRL_Dictionary $dictionary
 * @param \DOMElement $element
 * @param bool $predicted (optional: false)
 * @return array
 */
function createHash( $dictionary, $element, $predicted = false )
{
	$elements = array( 'e' => XBRL_Inline::createClarkname( $element ) );

	$parent = $element->parentNode;
	$parents = array();
	while( $parent )
	{
		if ( $parent instanceof \DOMDocument ) break;

		$parents[] = XBRL_Inline::createClarkname( $parent );
		$parent = $parent->parentNode;
	}

	$elements['p'] = $parents;

	switch( $element->localName )
	{
		case 'context':
		case 'period':
		case 'xbrl':
			$elements['c'] = '';
			break;

		default:
			$value = trim( preg_replace( '/\s+/', ' ', $element->nodeValue ) ) ;
			if ( is_numeric( $value ) )
			{
				$value = floatval( $value );
			}
			$elements['c'] = $value;
			break;
	}

	$elements['a'] = XBRL_Inline::createSortedAttributesList( $element );

	return array( $dictionary->hashArray( $elements ), $elements );
}

/**
 * A specific exception to report issues comparing generated with predicted results
 */
class IXBRLTestCompareException extends IXBRLException
{
	/**
	 * An array of libxml errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param string $message
	 * @param array $errors (optional: empty array)
	 */
	public function __construct( $message, $errors = array() )
	{
		$this->errors = $errors;
		parent::__construct( $message );
	}
}