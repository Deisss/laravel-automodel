# Automodel



## Introduction

/!\ NOT PRODUCTION READY - STILL UNDER DEVELOPMENT/TESTS /!\

This Laravel package is a helper to generate model based on the database
structure.  
This project differ from other generator available by:
  * being highly customizable: it use a blade template to render, therefore,
  it can be customized beyond infinite (including complete rework).
  * lifetime support: rather than building once the model, this package
  aims to render at any point in time the whole set of models needed
  for your project.
  * versatile: you can enable/disable/change various parameters from
  the original render by tweaking a simple .schema JSON file.
  * friendly: in almost every case, it is able to detect properly for you
  hasOne vs hasMany, belongsToMany and pivot tables, and more...

In a way, it aims to be a companion for you during the whole development process.


## Installation

Run the following command from the root folder of your Laravel project:
```bash
composer require deisss/automodel
```
Once installed, as we are not using this generator outside of the
development mode, instead of updating ```config/app.php```,
we edit instead the provider file in ```app/Providers/AppServiceProvider.php```:

```php
<?php

// (...)

public function register()
{
    if ($this->app->environment() !== 'production') {
        $this->app->register(\Deisss\Automodel\Providers\AutomodelServiceProvider::class);
    }
}
```

The plugin should now be active.



## Commands

You have access to two new commands:
```bash
php artisan automodel:database
```
Will get the whole database structure, create a ```.schema``` that is a configuration
file for this plugin.  
Then, from this file, render every possible models found in it (while escaping pivot
tables and more...).  

This command is probably the more usefull of the two, as in this case, if it's well
configured, it should render ALL your models perfectly.

The second command is a more traditional "single table version" rather 
than the whole database:

```bash
php artisan automodel:table #NAME#
```
From a single table, render the related model. This command is quite versatile, to be
able to render any models you may want directly from the base template.


Both of them have a lots of parameters you should check before starting to use them.




## First usage

The plugin base it's "intelligence" on the database you've got so far. Therefor you need
to have a MySQL connection working (at the moment only MySQL is supported),
with a database and some tables installed. Basically you need to have done:
```bash
php artisan migrate
```
Before using this plugin.

Once it's done, installed and you have some tables into your database, now the plugin can
enter into action (NOTE: **THIS WILL ERASE ```app/models``` folder!!!**):
```
php artisan automodel:database --erase-models
```
It should generate every possible models into ```app/models```, except password_resets and
 migrations tables (and all pivot tables).

You can see now there is a ```.schema``` file at the root of your project. This is
where you tweak everything to have the exact render you wanted to have per table.

Let's explain a little bit more about this file.



## Understanding the .schema file

This section has been moved to the [wiki of this project](Understanding-.schema-file-structure).



## A little bit more

We cover only "how to start" with this tutorial, to keep things clear and easy.

But if you take a look at the Wiki, [there is a lot more to discover](home)...


## License

See LICENSE.MD file.
