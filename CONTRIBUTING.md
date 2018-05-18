# Contributing to lyquidity/xml

Firstly, thanks for taking the time to contribute!

The following is a set of guidelines for contributing to this project, which are hosted in the 
[Lyquidity Organization](https://github.com/lyquidity) on GitHub.
These are just guidelines, not rules. Use your best judgement, and feel free to propose changes 
to this document in a pull request.

#### Table Of Contents

[What should I know before I get started?](#what-should-i-know-before-i-get-started)
  * [Code of Conduct](#code-of-conduct)
  * [XML source](#xml-source)
  * [XML Schema specification](#xml-specifications)
  * [PHP versions](#php-versions)

[How Can I Contribute?](#how-can-i-contribute)
  * [Reporting Bugs](#reporting-bugs)
  * [Suggesting Enhancements](#suggesting-enhancements)
  * [Your First Code Contribution](#your-first-code-contribution)
  * [Pull Requests](#pull-requests)

[Styleguides](#styleguides)
  * [Git Commit Messages](#git-commit-messages)

[Additional Notes](#additional-notes)
  * [Issue and Pull Request Labels](#issue-and-pull-request-labels)

## What should I know before I get started?

### Code of Conduct

This project adheres to the Contributor Covenant [code of conduct](CODE_OF_CONDUCT.md).
By participating, you are expected to uphold this code.
Please report unacceptable behavior to [xml-github@lyquidity.com](mailto:xml-github@lyquidity.com).

### XML specifications

The source code has been developed to allow XBRL taxonomies and instance documents that conform to the 
[XBRL 2.1](http://www.xbrl.org/Specification/XBRL-2.1/REC-2003-12-31/XBRL-2.1-REC-2003-12-31+corrected-errata-2013-02-20.html) 
specification and the [XBRL Dimensions 1.0](https://www.xbrl.org/specification/dimensions/rec-2012-01-25/dimensions-rec-2006-09-18+corrected-errata-2012-01-25-clean.html)

While it is not expected that someone wanting to use the source code provided by this project will be familiar
with the detail of these specifications it is expected that people wanting to make contributions to the project 
source will be very familiar with these and other XBRL specifications. 

### XML source

The project source has been created using the [PHP](http://php.net/manual/en/intro-whatis.php) programming language.  
The source code takes advantage of features provided by this language including, but not limited to, classes, namespaces,
closures and reference passing. 

# PHP versions

This source code is designed to support PHP 7.0 and later.  We recommend PHP 7.1.6.

## How Can I Contribute?

### Reporting Bugs

This section guides you through submitting a bug report for XBRLQuery/core. Following these guidelines helps maintainers 
and the community understand your report, reproduce the behavior, and find related reports.

Before creating bug reports, please check [this list](#before-submitting-a-bug-report) as you might find out that you don't 
need to create one. 
When you are creating a bug report, please [include as many details as possible](#how-do-i-submit-a-good-bug-report). 
If you'd like, you can use [this template](#template-for-submitting-bug-reports) to structure the information.

#### Before Submitting A Bug Report

* **Check the issues list** You might be able to find the cause of the problem and fix things yourself or that it has bee reported. 
* **Check if you can reproduce the problem** Always check against the latest version. Although you may want to use
	an older version, if the issue no longer exists it will mean you can find out how the issue was fixed and create a patch.

#### How Do I Submit A (Good) Bug Report?

Bugs are tracked as [GitHub issues](https://guides.github.com/features/issues/). After you've determined 
[which file](#xbrlquery-source) your bug can be found in, create an issue and provide the following information.

Explain the problem and include additional details to help maintainers reproduce the problem:

* **Use a clear and descriptive title** for the issue to identify the problem.
* **Describe the exact steps which reproduce the problem** in as many details as possible. For example, include the branch
		you have used, the taxonomy you are using and the instance document you are using. 
		explaining how you started Atom, e.g. which command exactly you used in the terminal, or how you started Atom otherwise. When listing steps, **don't just say what you did, but explain how you did it**. For example, if you moved the cursor to the end of a line, explain if you used the mouse, or a keyboard shortcut or an Atom command, and if so which one?
* **Provide specific examples to demonstrate the steps**. Include links to files or GitHub projects, or copy/pasteable snippets, 
		which you use in those examples. If you're providing snippets in the issue, use 
		[Markdown code blocks](https://help.github.com/articles/markdown-basics/#multiple-lines).
* **Describe the behavior you observed after following the steps** and point out what exactly is the problem with that behavior.
* **Explain which behavior you expected to see instead and why.**

Include details about your configuration and environment:

* **Which version of PHP are you using?** You can get the exact version by running `php -version` in your terminal.
* **What's the name and version of the OS you're using**?
* **Which version of the source code do you have installed?** 

#### Template For Submitting Bug Reports

    [Short description of problem here]

    **Reproduction Steps:**

    1. [First Step]
    2. [Second Step]
    3. [Other Steps...]

    **Expected behavior:**

    [Describe expected behavior here]

    **Observed behavior:**

    [Describe observed behavior here]

    **Screenshots**

    ![Screenshots which follow reproduction steps to demonstrate the problem](url)

    **PHP version:** [Enter PHP version here]
    **OS and version:** [Enter OS name and version here]

    **Installed PHP extensions:**

    [List of installed PHP extensions here]

    **Additional information:**

    * Problem can be reproduced in all versions of PHP: [Yes/No]
    * Problem started happening recently, didn't happen in an older version: [Yes/No]
    * Problem can be reliably reproduced, doesn't happen randomly: [Yes/No]
    * Problem happens with all taxonomies and instance documents, not only some: [Yes/No]

### Suggesting Enhancements

This section guides you through submitting an enhancement suggestion for XBRLQuery/core, including completely new features 
and minor improvements to existing functionality. Following these guidelines helps maintainers and the community understand 
your suggestion and find related suggestions.

Before creating enhancement suggestions, please check [this list](#before-submitting-an-enhancement-suggestion) as you 
might find out that you don't need to create one. When you are creating an enhancement suggestion, 
please [include as many details as possible](#how-do-i-submit-a-good-enhancement-suggestion). If you'd like, you 
can use [this template](#template-for-submitting-enhancement-suggestions) to structure the information.

#### Before Submitting An Enhancement Suggestion

* **Check if there's already feature which provides that enhancement.** Sometimes changing the way you think about a problem can lead to the use of existing features in new ways.
* **Determine [which file the enhancement should affect](#xbrlquery-source).** Is the suggestion to core taxonomy handling, a specific taxonomy, instance document handing or reporting.
* **Perform a [cursory search](https://github.com/issues?q=+is%3Aissue+user%3Axbrlquery)** to see if the enhancement has already been suggested. If it has, add a comment to the existing issue instead of opening a new one.
* **Make sure it is not already one the  road map**

#### How Do I Submit A (Good) Enhancement Suggestion?

Enhancement suggestions are tracked as [GitHub issues](https://guides.github.com/features/issues/). 
Create an issue and provide the following information:

* **Use a clear and descriptive title** for the issue to identify the suggestion.
* **Provide a step-by-step description of the suggested enhancement** in as many details as possible.
* **Provide specific examples to demonstrate the steps**. Include copy/pasteable snippets which you use in those examples, as [Markdown code blocks](https://help.github.com/articles/markdown-basics/#multiple-lines).
* **Describe the current behavior** and **explain which behavior you expected to see instead** and why.
* **Include screenshots and animated GIFs** which help you demonstrate the steps or point out the part of XBRLQuery/code the suggestion is related to. You can use [this tool](http://www.cockos.com/licecap/) to record GIFs on macOS and Windows, and [this tool](https://github.com/colinkeenan/silentcast) or [this tool](https://github.com/GNOME/byzanz) on Linux.
* **Explain why this enhancement would be useful** to most XBRLQuery users and isn't something that can or should be implemented as a separate package.
* **List some other XBRL tools or applications where this enhancement exists.**
* **Specify which version of PHP you're using.** You can get the exact version by running `php -version` in your terminal.
* **Specify the name and version of the OS you're using.**

#### Template For Submitting Enhancement Suggestions

    [Short description of suggestion]

    **Steps which explain the enhancement**

    1. [First Step]
    2. [Second Step]
    3. [Other Steps...]

    **Current and suggested behavior**

    [Describe current and suggested behavior here]

    **Why would the enhancement be useful to most users**

    [Explain why the enhancement would be useful to most users]

    [List some other text editors or applications where this enhancement exists]

    **Screenshots and GIFs**

    ![Screenshots and GIFs which demonstrate the steps or part of Atom the enhancement suggestion is related to](url)

    **PHP Version:** [Enter PHP version here]
    **OS and Version:** [Enter OS name and version here]

### Your First Code Contribution

Unsure where to begin contributing to Atom? You can start by looking through these `beginner` and `help-wanted` issues:

* [Beginner issues][beginner] - issues which should only require a few lines of code, and a test or two.
* [Help wanted issues][help-wanted] - issues which should be a bit more involved than `beginner` issues.

Both issue lists are sorted by total number of comments. While not perfect, number of comments is a reasonable proxy for 
impact a given change will have.

### Pull Requests

* Include DocBlock-style comments for all functions, properties and classes.  Also add a DocBlock-style comment 
  at the top of each file.
* Include thoughtfully-worded, well-structured tests and include the test in the `./tests` folder.
* Avoid platform-dependent code. 
* It must be possible to run the code under PHP 5.3.x or later and PHP 7.x

## Styleguides

### Git Commit Messages

* Use the present tense ("Add feature" not "Added feature")
* Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
* Limit the first line to 72 characters or less
* Reference issues and pull requests liberally
* When only changing documentation, include `[ci skip]` in the commit description

## Additional Notes

### Issue and Pull Request Labels

This section lists the labels we use to help us track and manage issues and pull requests. 

[GitHub search](https://help.github.com/articles/searching-issues/) makes it easy to use labels for finding 
groups of issues or pull requests you're interested in. For example, you might be interested in 
[open issues across `xbrlquery/core` and all issue that are labeled as bugs, but still need to be reliably reproduced]
(https://github.com/issues?utf8=%E2%9C%93&q=is%3Aopen+is%3Aissue+user%3Axbrlquery+label%3Abug+label%3Aneeds-reproduction) 
or perhaps 
[open pull requests in `xbrlquery/core` which haven't been reviewed yet]
(https://github.com/issues?utf8=%E2%9C%93&q=is%3Aopen+is%3Apr+repo%3Acore%2Fcore+comments%3A0). 
To help you find issues and pull requests, each label is listed with search links for 
finding open items with that label in `xbrlquery/core` only and also across all Atom repositories. 
We  encourage you to read about [other search filters](https://help.github.com/articles/searching-issues/) which 
will help you write more focused queries.

The labels are loosely grouped by their purpose, but it's not required that every issue have a label from 
every group or that an issue can't have more than one label from the same group.

Please open an issue on `xbrlquery/core` if you have suggestions for new labels, and if you notice some labels are 
missing, then please open an issue.

#### Type of Issue and Issue State

| Label name | `xbrlquery/core` | Description |
| --- | --- | --- |
| `enhancement` | [search][search-label-enhancement] | Feature requests. |
| `bug` | [search][search-label-bug] | Confirmed bugs or reports that are very likely to be bugs. |
| `question` | [search][search-label-question] | Questions more than bug reports or feature requests (e.g. how do I do X). |
| `feedback` | [search][search-label-feedback] | General feedback more than bug reports or feature requests. |
| `help-wanted` | [search][search-label-help-wanted] | The Atom core team would appreciate help from the community in resolving these issues. |
| `beginner` | [search][search-label-beginner] | Less complex issues which would be good first issues to work on for users who want to contribute to Atom. |
| `more-information-needed` | [search][search-label-more-information-needed] | More information needs to be collected about these problems or feature requests (e.g. steps to reproduce). |
| `needs-reproduction` | [search][search-label-needs-reproduction] | Likely bugs, but haven't been reliably reproduced. |
| `blocked` | [search][search-label-blocked] | Issues blocked on other issues. |
| `duplicate` | [search][search-label-duplicate] | Issues which are duplicates of other issues, i.e. they have been reported before. |
| `wontfix` | [search][search-label-wontfix] | The Atom core team has decided not to fix these issues for now, either because they're working as intended or for some other reason. |
| `invalid` | [search][search-label-invalid] | Issues which aren't valid (e.g. user errors). |

#### Topic Categories

| Label name | `xbrlquery/core` | Description |
| --- | --- | --- |
| `windows` | [search][search-label-windows] | Related to Atom running on Windows. |
| `linux` | [search][search-label-linux] | Related to Atom running on Linux. |
| `mac` | [search][search-label-mac] | Related to Atom running on macOS. |
| `documentation` | [search][search-label-documentation] | Related to any type of documentation. |
| `performance` | [search][search-label-performance] | Related to performance. |
| `security` | [search][search-label-security] | Related to security. |
| `ui` | [search][search-label-ui] | Related to visual design. |
| `api` | [search][search-label-api] | Related to Atom's public APIs. |
| `uncaught-exception` | [search][search-label-uncaught-exception] | Issues about uncaught exceptions. |
| `crash` | [search][search-label-crash] | Reports of Atom completely crashing. |
| `encoding` | [search][search-label-encoding] | Related to character encoding. |
| `network` | [search][search-label-network] | Related to network problems or working with remote files (e.g. on network drives). |
| `git` | [search][search-label-git] | Related to Git functionality (e.g. problems with gitignore files or with showing the correct file status). |
| `parse` | [search][search-label-parse] | Reports of code causing PHP fatal errors. |
| `load-taxonomy` | [search][search-label-load-taxonomy] | Issues relating to reading a taxonomy. |
| `compile-taxonomy` | [search][search-label-compile-taxonomy] | Issues relating to compiling taxonomy. |
| `instance-document` | [search][search-label-instance-document] | Issues relating loading or using instance documents. |
| `reporting` | [search][search-label-reporting] | Issues relating to creating reports using the reports classes. |

#### Pull Request Labels

| Label name | `xbrlquery/core` | Description
| --- | --- | --- |
| `work-in-progress` | [search][search-label-work-in-progress] | Pull requests which are still being worked on, more changes will follow. |
| `needs-review` | [search][search-label-needs-review] | Pull requests which need code review, and approval from maintainers or Atom core team. |
| `under-review` | [search][search-label-under-review] | Pull requests being reviewed by maintainers or Atom core team. |
| `requires-changes` | [search][search-label-requires-changes] | Pull requests which need to be updated based on review comments and then reviewed again. |
| `needs-testing` | [search][search-label-needs-testing] | Pull requests which need manual testing. |

[search-label-enhancement]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aenhancement
[search-label-bug]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Abug
[search-label-question]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aquestion
[search-label-feedback]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Afeedback
[search-label-help-wanted]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Ahelp-wanted
[search-label-beginner]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Abeginner
[search-label-more-information-needed]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Amore-information-needed
[search-label-needs-reproduction]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aneeds-reproduction
[search-label-windows]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Awindows
[search-label-linux]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Alinux
[search-label-mac]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Amac
[search-label-documentation]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Adocumentation
[search-label-performance]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aperformance
[search-label-security]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Asecurity
[search-label-ui]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aui
[search-label-api]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aapi
[search-label-crash]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Acrash
[search-label-encoding]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aencoding
[search-label-network]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Anetwork
[search-label-uncaught-exception]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Auncaught-exception
[search-label-git]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Agit
[search-label-blocked]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Ablocked
[search-label-duplicate]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aduplicate
[search-label-wontfix]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Awontfix
[search-label-invalid]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Ainvalid
[search-label-parse]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aparse
[search-label-load-taxonomy]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Aload-taxonomy
[search-label-compile-taxonomy]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Acompile-taxonomy
[search-label-instance-document]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Ainstance-document
[search-label-reporting]: https://github.com/issues?q=is%3Aopen+is%3Aissue+repo%3Axbrlquery%2Fcore+label%3Areporting
[search-label-work-in-progress]: https://github.com/pulls?q=is%3Aopen+is%3Apr+repo%3Axbrlquery%2Fcore+label%3Awork-in-progress
[search-label-needs-review]: https://github.com/pulls?q=is%3Aopen+is%3Apr+repo%3Axbrlquery%2Fcore+label%3Aneeds-review
[search-label-under-review]: https://github.com/pulls?q=is%3Aopen+is%3Apr+repo%3Axbrlquery%2Fcore+label%3Aunder-review
[search-label-requires-changes]: https://github.com/pulls?q=is%3Aopen+is%3Apr+repo%3Axbrlquery%2Fcore+label%3Arequires-changes
[search-label-needs-testing]: https://github.com/pulls?q=is%3Aopen+is%3Apr+repo%3Axbrlquery%2Fcore+label%3Aneeds-testing

[beginner]: https://github.com/issues?utf8=%E2%9C%93&q=is%3Aopen+is%3Aissue+label%3Abeginner+label%3Ahelp-wanted+sort%3Acomments-desc
[help-wanted]: https://github.com/issues?q=is%3Aopen+is%3Aissue+label%3Ahelp-wanted+sort%3Acomments-desc
