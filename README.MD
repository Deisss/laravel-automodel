# Automodel



## Introduction

/!\ NOT PRODUCTION READY - STILL UNDER DEVELOPMENT/TESTS /!\

This Laravel package is a helper to generate model based on the database
structure.  
This project differ from other generator available for Laravel by:
  * being highly customizable: it use a blade template to render, therefor
  it can be customized beyond infinite (including complete rework).
  * lifetime support: rather than building once the model's basics, this package
  has been created with, in mind, the idea to render at any point in time
  the whole set of models needed for your project.
  * versatile: you can enable/disable/change various parameters from
  the original render by tweaking a simple .schema JSON file.
  * friendly: in almost every case, it is able to detect properly for you
  hasOne vs hasMany, belongsToMany and pivot tables, and more...



## Installation

Simply run the following command from the root of your Laravel project:
```bash
composer require deisss/automodel
```
Once installed, as we are not using this generator outside of the
development mode, instead of updating ```config/app.php```,
we edit instead the provider in ```app/Providers/AppServiceProvider.php```, like so:

```php
<?php

// ...

public function register()
{
    if ($this->app->environment() !== 'production') {
        $this->app->register(\Deisss\Automodel\Providers\AutomodelServiceProvider::class);
    }
}
```
That should be enough to use this plugin.



## Commands

The package create two commands:
```bash
php artisan automodel:database
```
Will get the whole database structure, create a ```.schema``` file at the root of your
application.  
Then, from this file, render every possible models found in it (while escaping pivot
tables and more...).  

The second command is the "single table version" rather than the whole database:

```bash
php artisan automodel:table #NAME#
```
From a single table, render the related model. This command is quite versatile, to be
able to render any models you may want directly from the base template.




## First usage

The plugin base it's "intelligence" on the database you've got so far. Therefor you need
to have a MySQL connection working, with a database and some tables installed.
Basically you need to have done:
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



## .schema file

By default the ```automodel:database``` will create a ```.schema``` file at the root of
your project. This file is what the command use to generate all the models
and contains an array of elements, and each of them represent a table.
For one table, here is the possible values/parameters you can configure:
```json
[
    {
        "table": "companies",
        "name": "Company",
        "folder": "app\\Models",
        "namespace": "App\\Models",
        "template": "automodel::model",
        "pivot": true,
        "skip": true,
        "scopes": {
            "searchTableColumn": "table_column>$variable"
        },
        "renames": {
            "newName": ">table_name|table_column"
        },
        "traits": {
            "superName": "\\App\\Traits\\MySuperTrait"
        },
        "removes": {
            "users": "user_id>users|id"
        }
    }
]
```

A little explanation is required to understand:

  * **table**: it has to be unique, it can't be changed, and it's the table
  name where all the rest will apply.
  * **name**: the model's name, by default it's the singular form of the table name
  according to the Eloquent [Model's Conventions](https://laravel.com/docs/5.4/eloquent#eloquent-model-conventions),
  with underscore replaced by the first letter in uppercase and so on.
  * **folder**: where to store the model, by default it will be ```app/models```.
  * **namespace**: which namespace, by default it will be ```app\Models```.
  * **template**: the Blade template to use for rendering the model. This can
  be changed to avoid using the default model if you want. By default it will be ```automodel:model```.
  * **pivot**: if it's a pivot table or not, true = will not generate a model from it.
  * **skip**: does not render the related model, for example ```migrations``` is a skipped table.
  * **scopes**: if you want to add any scope to the model generation.
  * **renames**: if you want to rename a relationship, this one is probably the most used as it
  allow you to avoid collision's in the code with ease.
  * **traits**: if you want to add some traits to the model.
  * **removes**: if you want to skip some relationship (because it's useless to have it).

For ```scopes```, ```renames```, ```traits``` and ```removes``` there is a dedicated topic which
explain how to use them.

TODO: ADD LINK TO THE TOPIC HERE.

Same goes for the template, there is a dedicated wiki entry on how you can extend it to have the
desired rendering.


If a parameter is not set, the default value will be used automatically.


## Template file

TODO: une partie sur l'extend du template comment et pourquoi



## License

See LICENSE.MD file.
