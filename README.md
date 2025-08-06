# Joomla module <mod_wickedteam_sepaexport>

## Description

This project builds a module called **WickedTeam Sepa Export** for the content management system [Joomla](https://www.joomla.org/).

The module requires in Joomla an installation of the [WickedTeam-Component](https://shop.wicked-software.de/).

Using this module, it is possible to display the birthdays of WickedTeam members at a module position.

## Folder structure
- .release -> contains the installation archive of joomla module and the corresponding update XML
- .vscode -> contains the VSCode task file for creating joomla archive
- src -> contains the sources for the module
- joomla -> created during packaging. Holds the module structure as it will be installed in Joomla

## Files
- .gitignore -> contains directories and files which should not be stored in remote repository
- build.xml -> configuration file for packaging with [Phing](https://www.phing.info/)


## Development
### Joomla packaging
For packaging the tool [Phing](https://www.phing.info/) is used. The used configuration file is build.xml. It contains all the tasks to be done. For the execution a VSCode task "Build mod_wickedteam_sepaexport" exists (see tasks.json).
By this all needed files are packed into a zip archive and also the version information in the update XML file is updated corresponding to the version information in the module manifest file src/mod_wickedteam_sepaexport.xml

### Joomla coding style
In order to fullfil [Joomla coding standard](https://github.com/joomla/coding-standards) PHP Codesniffer has been used for formatting php source files.
For installation [Composer](https://getcomposer.org/download/) is used.
After installation of Composer create file composer.json in user directory and add the following lines:
```json
{
    "require": {
        "squizlabs/php_codesniffer": "~2.8"
    },

    "require-dev": {
        "joomla/coding-standards": "~2.0@alpha"
    }
}
```
On Command line execute
```cmd
composer update
```
Installation is done in \<Userdir\>\vendor
and also in \<Userdir\>\AppData\Roaming\Composer\vendor
but Joomla coding style is only installed at \<Userdir\>\vendor\joomla
For the global installation the follwoing commands has to be executed in userdir:
```cmd
composer global require squizlabs/php_codesniffer:~2.8
composer global require joomla/coding-standards:~2.0@alpha
```

**Hint**
Since php codesniffer 2.8 is not optimized for php 7.4, several deprecated warnings are emitted, which leads to a not working sniffer output. Therefore adapt \<Userdir\>\AppData\Roaming\Composer\vendor\squizlabs\php_codesniffer\CodeSniffer.php and add
```php
$errorlevel = error_reporting();
$errorlevel = error_reporting($errorlevel & ~E_DEPRECATED);
```

### Using coding style in Visual Studio Code
Since Visual Studio Code is used as IDE install the extension 'phpcs'.
In settings adapt the following options
- executable path for phpcs
\<Userdir\>\AppData\Roaming\Composer\vendor\bin\phpcs
- phpcs: Standard -> settings.json
"phpcs.standard": "\<Userdir\>\\vendor\\joomla\\coding-standards\\Joomla"

