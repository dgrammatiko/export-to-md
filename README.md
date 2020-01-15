### Simple CLI Plugin

- Will export all your Joomla articles to a category based directory
- Each file will get the name of the article slug


### How to?

From your Joomla's root path, execute the following commands:

```
cd cli
```

```
git clone git@github.com:dgrammatiko/export-to-md.git
```

```
composer install
```

edit `exportmd.php` and specify a directory name (line 4):
```
define('BASEFOLDER', 'some_folder_name_goes_here');
```

Run this commnd for exporting Joomla's articles:
```
php exportmd.php
```

or this one for exporting k2 items:
```
php exportk2.php
```

That's it, copy the contents of this directory to your 11ty data folder...
