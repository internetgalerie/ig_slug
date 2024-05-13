# TYPO3 Extension  'ig_slug'

[![Latest Stable Version](https://poser.pugx.org/internetgalerie/ig-slug/v/stable)](https://packagist.org/packages/internetgalerie/ig-slug)
[![Monthly Downloads](https://poser.pugx.org/internetgalerie/ig-slug/d/monthly)](https://packagist.org/packages/internetgalerie/ig-slug)
[![License](https://poser.pugx.org/internetgalerie/ig-slug/license)](https://packagist.org/packages/internetgalerie/ig-slug)

Rebuild URL slugs of pages an other tables

## 1. What does it do?


Extension to rebuild the slugs of the pages or any other table with slugs like e.g. news. It also offers an ovierview of the current slugs and shows which slugs would be changed.

The table with slug fields are automaticly detected. Non admin users only have access to tables if they can change the slug field.

Workspaces are not yet implemented.

## 2. Usage


### 1) Installation

#### Installation using Composer

The recommended way to install the extension is by using [Composer][2]. In your Composer based TYPO3 project root, just do `composer require internetgalerie/ig-slug`.

#### Installation as extension from TYPO3 Extension Repository (TER)

Download and install the extension with the extension manager module.


### 2) CLI

The slugs can also be
#### completely rebuilt in CLI

- - -
Composer-based installation

    vendor/bin/typo3 ig_slug:update tx_news_domain_model_news

Legacy installation

    typo3/sysext/core/bin/typo3 ig_slug:update tx_news_domain_model_news

#### with pid

- - -
Composer-based installation

    vendor/bin/typo3 ig_slug:update tx_news_domain_model_news 99

Legacy installation

    typo3/sysext/core/bin/typo3 ig_slug:update tx_news_domain_model_news 99

#### for page recursive for pid=20

- - -
Composer-based installation

    vendor/bin/typo3 ig_slug:update pages 20 -R

Legacy installation

    typo3/sysext/core/bin/typo3 ig_slug:update pages 20 -R

#### only for default language:

- - -
Composer-based installation

    vendor/bin/typo3 ig_slug:update -L 0 -R -- pages 20

Legacy installation

    typo3/sysext/core/bin/typo3 ig_slug:update -L 0 -R -- pages 20


[1]: https://docs.typo3.org/typo3cms/extensions/ig_slug/
[2]: https://getcomposer.org/
[3]: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/CommandControllers/Index.html
