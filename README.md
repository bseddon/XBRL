# XBRL for PHP

## Table of contents
* [Status](#status)
* [About the project](#about-the-project)
* [Purpose](#purpose)
* [Reporting](#reporting)
* [Why PHP?](#why-php)
* [License](#license)
* [Contributing](#contributing)
* [Install](#install)
* [Getting started](#getting-started)
* [Links](#links)
* [Case Study](../../wiki/Case-Study)

Find much more information in the [wiki](../../wiki).

## Status

![Build status parsing](https://www.xbrlquery.com/tests/status.php?test=parse&x=y "Can PHP parse the source files") 
![Build status compile GAAPs](https://www.xbrlquery.com/tests/status.php?test=compile_gaaps&x=y "Can the US and UK GAAP taxonomies be compiled")
![Build status compile extensions](https://www.xbrlquery.com/tests/status.php?test=compile_extensions&x=y "Can US extension taxonomies be compiled")
![Build status load_instances](https://www.xbrlquery.com/tests/status.php?test=load_instances&x=y "Can instance documents be loaded")
![Build status reports](https://www.xbrlquery.com/tests/status.php?test=reports&x=y "Can the test reports be run")

Conformance suite tests

![XBRL 2.1 conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_21&x=y "XBRL 2.1 conformance suite tests")
![XBRL dimensions conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_xdt&x=y "XBRL Dimensions conformance suite tests")
![XPath 2.0 conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_xpath20&x=y "XPath 2.0 conformance suite tests")
![XBRL functions registry conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_functions&x=y "XBRL functions registry conformance suite tests")
![XBRL Formulas conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_formulas&x=y "XBRL Formulas conformance suite tests")
![XBRL Enumerations conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_enumerations&x=y "XBRL Enumerations conformance suite tests")
![XBRL Generics conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_generics&x=y "XBRL Generics conformance suite tests")

![Build status last run date](https://www.xbrlquery.com/tests/status.php?test=date "The date of the last run")

These tests are performed nightly to provide an overview of the status of the source code.  All tests have been run on Linux and Windows.  
This project does not support HHVM.

### Statistics

This project comprises 80629 lines in 250 files

## About the project

The XBRLQuery project started as an idea to take the regulatory information companies must produce and extend that use within organizations.  The benefit 
of using [XBRL](https://www.xbrl.org/) to represent corporate data is that it's verifiable and published and in an agreed, transparent format, a format 
accepted by all major regulatory bodies around the world.

We realised the ability to work as a community to provide useful information from internal or published financial data will allow organizations 
to plan, analyse and react to business driven factors and by doing so via an OpenSource project, it becomes beneficial to all.

An overview of the project is provided below.  There is more information available in the Wiki including and explanation and documentation of the source code
and examples of using the source to create reports.

### Validating

The processor can be validating.  Set a variable to true and both the schemas and instance documents are validated.  Set the variable to false and 
the processor just processes and assumes you know the schemas and instance documents are valid and consistent.

### Specifications supported

* XBRL 2.1 except there is no support for reference linkbases.
* XBRL Dimensions are supported.
* XBRL Generics including generic references, generic links, generic labels and assertions.
* XBRL Formulas.
* XBRL Taxonomy Packages (including support for the various legacy SEC packages).
* XBRL Extensible Enumerations 1.0 and 2.0 PWD.

XBRL Formulas includes support for:

* All the [recommended specifications](https://specifications.xbrl.org/work-product-index-formula-formula-1.0.html)
* The [Formula Tuples](http://www.xbrl.org/Specification/formulaTuples/CR-2011-11-30/formulaTuples-CR-2011-11-30.html) and 
[Variable Scope](http://www.xbrl.org/Specification/variables-scope/CR-2011-11-30/variables-scope-CR-2011-11-30.html) draft specifications.
* The full set of [functions registry](https://specifications.xbrl.org/registries/functions-registry-1.0/) (XFI) are also supported 
including both recommended and draft functions. 

### Taxonomy package support

There are many instances of zip files containing taxonomies.  If a zip file content follows the 
[XBRL Taxonomy Package specification](https://www.xbrl.org/Specification/taxonomy-package/REC-2016-04-19/taxonomy-package-REC-2016-04-19.html) 
then the processor will support it.  However, there are many instances of zip files that contain taxonomies
which were packaged this way long before the packaging specification existed.  The US SEC packages are an example.  In fact
there are two versions of legacy packages available from the SEC: one that uses a JSON file to catalog the package content;
and one that uses an XML file. The processor supports both.  

In addition, the Danish IFRS package is supported as is the taxonomy from the Danish Business Agency (Erhvervsstyrelsen). 
Neither of these packages support the XBRL Taxonomy Packaging specification though the taxonomies are provided in a zip file.

Sometimes it is helpful to extend the core XBRL processinging class to add specific functionality for a taxonomy.  This
is especially true when a taxonomy may be extended.  Examples of extended XBRL processing classes are IFRS and 
[ESMA ESEF](https://www.esma.europa.eu/policy-activities/corporate-disclosure/european-single-electronic-format) taxonomies.  In these 
cases, the base packaging class, such as XBRL_TaxonomyPackage can be extended to return the name of the XBRL processor 
class to use to processes the taxonomies.  Examples provided include:

* XBRL-US-TaxonomyPackage.php
* XBRL-IFRS-Package.php
* XBRL-ESMA-ESEF-Package.php

### Conformance

The project passes almost all conformance tests, the omissions arising because there is no support for reference linkbases.

A separate project [XBRL-tests](https://github.com/bseddon/XBRL-tests) provides a means to verify the project passes the conformance test.  This
project is a copy of the test harness we use to run the conformance tests.

### Signing

Instance documents and taxonomies can also be signed.  The signing techniques used are similar to those use to sign Word or PDF documents 
or emails.  Documents are signed using the private key of a certificate you have so that others are able to verify the contents of a document 
have not changed since you created it.

Signing does not impact the original document but is important for recipients of a taxonomy to know they are working with an unaltered 
copy of the relevant taxonomy and for recipients of instance documents to know the contents have not be altered since the document was 
prepared.

Signing uses only open standards for encryption and verification such as public/private key certificates.  Although the project provides
code to verify a signed instance document, the same process is possible using a standard distribution of most programming languages
such as, but not limited to, Java, C#, Python and C++.

## Purpose

This project has been created by [Lyquidity Solutions Limited](https://www.xbrlquery.com) using the website name of www.xbrlquery.com to 
provide PHP applications with access to validated corporate data contained in XBRL instance documents.  We also provide consulting services 
around these XBRL technologies.  Please contact us at [info-at-xbrlquery.com](mailto:info@xbrlquery.com). 

[XBRL](https://www.xbrl.org/) stands for 'eXtensible Business Reporting Language'.  Naively I thought that word 'Reporting' in this context could be prefixed 
with words like 'Management' or 'Budgeting'.  I thought the overwhelming focus on using XBRL to create submissions to regulatory authorities like the US SEC or
the UK HMRC was a consequence of having so many accounting and governmental organizations involved in XBRL community.  But the reality is the purpose of XBRL 
'reporting' is reporting to regulatory bodies.  It is rarely, if ever, used for other types of reporting.

Most of the tools I have seen that are available commercially or are in the public domain such as [Arelle](http://arelle.org/), [Gepsio](https://gepsio.codeplex.com/) 
or [ABRLAPI](http://www.xbrlapi.org/) focus on preparing instance documents and/or validating those documents against a taxonomy and reporting errors. Clearly 
ensuring data contained in instance documents are consistent with the base taxonomy they claim to represent this is an important first step. In the context of 
'reporting' to statutory authorities it probably is the only goal.  Surely there are other potential uses for data that can be recorded in a document and 
that can be validated against a taxonomy.

Budgeting and management reporting are examples of processes all organizations have to perform regularly.  They are processes which may have specific requirements
in different departments, geographies and responsibility levels. These processes need to generate, transform, transfer and report information in a consistent way. 
A goal of these processes may be to record data in a repository such as a database but that data has to be captured in a variety of locations and needs to be 
captured in a format that allows verification of the data and that the correct data is being captured - tasks for which XBRL is ideally suited.

So the focus of **XBRL Query** is to provide a platform in which XBRL can be used as the means of representing data that is used in corporate processes such as
budgeting and management reporting; to create a platform that uses XBRL as a means to transfer data between the actors in those business reporting processes in
a reliable, repeatable way; to create a platform that allows anyone to validate the content of instance documents on which business decisions may be made; 
and that any document being used is genuine and unchanged.

## XBRL Support

This project includes support for the full XBRL 2.1 specification as well as the XBRL Dimensions 1.0, Generics and XBRL Formulas specification (including the XFI functions). 
The project allows an application to validate taxonomy and instance documents and is able to report any errors discovered.  Error reports will document 
each element in a taxonomy or instance document that fails identifying the offending element and it will include a reference to the specification.  
In the case of the dimensions and formula validation, the message will include the approrpriate error constant as defined in the XDT (dimensions) 
specification.

## Reporting

The processor supports instance document rendering based on the information provided by the taxonomy author that is available in the presentation 
linkbase. This means you can create renderings of an instance document easily without complex code as the presentation linkbase defines the layout 
of each network.

There are many examples in the [Digital Financial Reporting examples](https://github.com/bseddon/XBRL/wiki/Digital-Financial-Reporting-examples) page
including an example of the code to create a rendering of an instance document. You can also visit www.xbrlquery.org to see live examples or even 
create a rendering of your own instance documents.  The [case study](https://github.com/bseddon/XBRL/wiki/Case-Study) example also includes an example
of rendering Danish commerce authority taxonomy based instance documents.

This example shows that the XBRL formulas and calculation linkbase rules compute correctly based on the data available (rules that do not compute will 
be highlighted in red).

Rendering can also include details of the structure of the report and a breakdown of calculation and formula results.

![rollforward](https://user-images.githubusercontent.com/1221824/62862465-c5a23300-bcfd-11e9-8c6a-0cbfa7d7d043.png)

## Why PHP?

[PHP](http://php.net/manual/en/intro-whatis.php) is one of the most widely used languages by web servers. Independent and up-to-date [research by w3techs](https://w3techs.com/technologies/details/pl-php/all/all)
shows PHP is used on approximately 82% of web sites where the server-side language is known. PHP is simple to integrate with all major web servers such as Apache, 
IIS and nginx. As a language it supports object-oriented development, polymorphism, namespaces, closures, and all the other features you'd expect of a modern 
programming language. This background means basing the project on PHP allows the platform to have strong HTTP-based server capabilities wide support and can be 
integrated into many widely used web platforms.

**No compile step/No library chaos**

Like all scripting languages PHP does not require a compile step.  There are advantages to having a compile step the main one being that the compiler 
can perform additional code checks.  But there are downsides as well.  The ability to write code and run can boost productivity especially when prototyping ideas.

**PHP versions and development tools**

To work with the code you will need to use PHP 7.0 or later.  PHP is the latest and greatest and we currently develop using PHP 7.2.6.  In our experience 
version 7.0 is *much* faster and it is likely we have used features of the language that are only available in PHP 7.+.

We have not made use of functions that require PHP extensions not in the standard distribution.  

The default memory limit defined in php.ini is suitable for sites generating regular web pages.  However taxonomies can be large and 
the source makes liberal use of memory to boost performance. We recommend that when you execute examples that you ensure the memory 
limit is raised by including the following line at the top of the entry point file you use to run your program implementations:

```php
ini_set( 'memory_limit', '512M' );
```

XBRL Formulas relies on XPath 2.0 and XPath 2.0 query execution relies on recurive calls.  XPath 2.0 queries generated by XBRL Formulas can result in 
very heavily nested calls so it is recommended that the PHP 'xdebug.max_nesting_level' value is increased.  This only affects while debugging using XDebug

```php
ini_set('xdebug.max_nesting_level', 512);
```

We use [phpDocumentor](https://www.phpdoc.org/) to create the source code documentation from the embedded comments.

We use Eclipse Neon and the PDT package as the IDE.  XDebug is used to provide debugging support.  At the time of writing the Zend debugger does not 
support PHP 7.0 while XDebug does. Nothing about the project or the source has any dependencies on these tools.

**Dependencies**

This project has dependencies on the following projects:

* [pear/Log](https://github.com/pear/Log)
* [lyquidity/xml](https://github.com/bseddon/xml)
* [lyquidity/utilities](https://github.com/bseddon/utilities)
* [lyquidity/XPath2](https://github.com/bseddon/XPath20)

**Example loading and validating an instance document**

Using the project code is straight-forward.  This simple example shows how to read an instance document file, check the 
validity instance document and report any discovered issues. 

```php
$instance = XBRL_Instance::FromInstanceDocument( 'my_instance_document.xml' );
$instance->validate();
if ( XBRL_Log::getInstance()->hasConformanceIssueWarning() )
{
    echo "Validation error\n";
}
```

## License

This project is released under [GPL version 3.0](LICENCE)

**What does this mean?**

It means you can use the source code in any way you see fit.  However, the source code for any changes you make must be made available to others and must be made
available on the same terms as you receive the source code in this project: under a GPL v3.0 license.  You must include the license of this project with any
distribution of the source code whether the distribution includes all the source code or just part of it.  For example, if you create a class that derives 
from one of the classes provided by this project - a new taxonomy class, perhaps - that is derivative.

**What does this not mean?**

It does *not* mean that any products you create that only *use* this source code must be released under GPL v3.0.  If you create a budgeting application that uses
the source code from this project to access data in instance documents, used by the budgeting application to transfer data, that is not derivative. 

## Contributing

We welcome contributions.  See our [contributions page](https://gist.github.com/bseddon/cfe04753192087c82766bee583f519aa) for more information.  If you do choose
to contribute we will ask you to agree to our [Contributor License Agreement (CLA)](https://gist.github.com/bseddon/cfe04753192087c82766bee583f519aa).  We will 
ask you to agree to the terms in the CLA to assure other users that the code they use is not going to be encumbered by a labyrinth of different license and patent 
liabilities.  You are also urged to review our [code of conduct](CODE_OF_CONDUCT.md).

## Install

The project can be installed by [composer](https://getcomposer.org/).   Assuming Composer is installed and a shortcut to the program is called 'composer'
then the command to install this project is:

```
composer require lyquidity/xbrl:dev-master lyquidity/xpath2:dev-master lyquidity/utilities:dev-master lyquidity/xml:dev-master --prefer-dist
```

Or fork or download the repository.  It will also be necessary to download and install the [XML](https://github.com/bseddon/xml), 
[utilities](https://github.com/bseddon/) and [pear/Log](https://github.com/pear/Log) projects.

## Getting started

The example.php file in the examples folder includes illustrations of using the classes.

Assuming you have installed the library using composer then this PHP application will run the examples:

```php
<?php
// This MUST be set before the autoload because the XBRL class autoloaded uses it
global $use_xbrl_functions; $use_xbrl_functions = true;
require_once __DIR__ . '/vendor/autoload.php';
include __DIR__ . "/vendor/lyquidity/XBRL/examples/example.php";
```

Read the getting started section in the [Wiki](../../wiki) where you will find more examples showing how the source can be used to query taxonomies 
instance documents and present their contents.

## Links

[UK Audit Exempt Taxonomy](https://ewf.companieshouse.gov.uk/xbrl_info)<br/>
[UK GAAP and FRS Taxonomies](http://www.xbrl.org.uk/techguidance/taxonomies.html)<br/>
[US GAAP Taxonomies](https://xbrl.us/home/filers/sec-reporting/taxonomies/) <br/>
[XBRL Specifications](https://specifications.xbrl.org/specifications.html)<br/>
[EIOPA Reporting Formats](https://eiopa.europa.eu/Pages/Supervision/Insurance/Reporting-formats.aspx)<br/>
[Bank of England CRD](http://www.bankofengland.co.uk/pra/Pages/regulatorydata/crdfirmstaxonomy.aspx)<br/>
[EBA Reporting Frameworks](https://www.eba.europa.eu/risk-analysis-and-data/reporting-frameworks)<br/>
[ESMA European Single Electronic Format](https://www.esma.europa.eu/policy-activities/corporate-disclosure/european-single-electronic-format)
[Taxonomy package specification](https://www.xbrl.org/Specification/taxonomy-package/REC-2016-04-19/taxonomy-package-REC-2016-04-19.html)<br/>
