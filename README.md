# XBRL for PHP

**Table of contents**
* [Status](#status)
* [About the project](#about-the-project)
* [Purpose](#purpose)
* [Why PHP?](#why-php)
* [License](#license)
* [Contributing](#contributing)
* [Install](#install)
* [Getting started](#getting-started)
* [Links](#links)

Find much more information in the [wiki](../../wiki).

## Status

![Build status parsing](https://www.xbrlquery.com/tests/status.php?test=parse&x=y "Can PHP parse the source files") 
![Build status compile GAAPs](https://www.xbrlquery.com/tests/status.php?test=compile_gaaps&x=y "Can the US and UK GAAP taxonomies be compiled")
![Build status compile extensions](https://www.xbrlquery.com/tests/status.php?test=compile_extensions&x=y "Can US extension taxonomies be compiled")
![Build status load_instances](https://www.xbrlquery.com/tests/status.php?test=load_instances&x=y "Can instance documents be loaded")
![Build status reports](https://www.xbrlquery.com/tests/status.php?test=reports&x=y "Can the test reports be run")

![XBRL 2.1 conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_21&x=y "XBRL 2.1 conformance suite tests")
![XBRL dimensions conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_xdt&x=y "XBRL Dimensions conformance suite tests")
![XPath 2.0 conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_xpath20&x=y "XPath 2.0 conformance suite tests")
![XBRL functions registry conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_functions&x=y "XBRL functions registry conformance suite tests")
![XBRL Formulas conformance](https://www.xbrlquery.com/tests/status.php?test=conformance_formulas&x=y "XBRL Formulas conformance suite tests")

![Build status last run date](https://www.xbrlquery.com/tests/status.php?test=date "The date of the last run")

These tests are performed nightly to provide an overview of the status of the source code.  

## About the project

The XBRLQuery project started as an idea to take the regulatory information companies must produce and extend that use within organizations.  The benefit 
of using [XBRL](https://www.xbrl.org/) to represent corporate data is that it's verifiable and published and in an agreed, transparent format, a format 
accepted by all major regulatory bodies around the world.

We realised the ability to work as a community to provide useful information from internal or published financial data will allow organizations 
to plan, analyse and react to business driven factors and by doing so via an OpenSource project, it becomes beneficial to all.

An overview of the project is provided below.  There is more information available in the Wiki including and explanation and documentation of the source code
and examples of using the source to create reports.

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

##XBRL Support

This project includes support for the full XBRL 2.1 specification as well as the XBRL Dimensions 1.0, Generics and XBRL Formulas specification (including the XFI functions). 
The project allows an application to validate taxonomy and instance documents and is able to report any errors discovered.  Error reports will document 
each element in a taxonomy or instance document that fails identifying the offending element and it will include a reference to the specification.  
In the case of the dimensions and formula validation, the message will include the approrpriate error constant as defined in the XDT specification.

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

To work with the code you will need to use PHP 7.0 or later.  PHP is the latest and greatest and we currently develop using PHP 7.1.6.  In our experience 
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

Using the project code is straigh forward.  This simple example shows how to read an instance document file, check the 
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
