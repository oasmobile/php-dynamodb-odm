# Object Data Mapping component for DynamoDb

The oasis/dynamodb-odm is an ODM (object data mapping) library for easy use of AWS' powerful key-value database: DynamoDb.

## Installation &amp; Configuration

To get oasis/dynamodb-odm, you can simple require it via `composer`:

```bash
$ composer require oasis/dynamodb-odm
```

### Class Loading

Autoloading for DynamoDb ODM is taken care of by `composer`. You just need to include the composer autoload file in your project:

```php
<?php

require_once "vendor/autoload.php";
```

### Obtaining an ItemManager

Once you have prepared the class loading, you acquire an **ItemManager** instance. The ItemManager class is the primary access point to ODM functionality provided by the library.

```php
<?php
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

$awsConfig     = [
    "profile" => "oasis-minhao",
    "region"  => "ap-northeast-1"
];
$tablePrefix   = "odm-";
$cacheDir      = __DIR__ . "/ut/cache";
$isDev         = true;
$itemNamespace = 'Oasis\Mlib\ODM\Dynamodb\Ut';
$itemSrcDir    = __DIR__ . "/ut";

$im = new ItemManager(
    $awsConfig,
    $tablePrefix,
    $cacheDir,
    $isDev
);
$im->addNamespace(
    $itemNamespace,
    $itemSrcDir
);

```

The explanation of each argument can be found below:

argument        | description                   | default value
---             | ---                           | ---
awsConfig       | configuration array for aws SDK, `profile` and `region` are mandatory.    | **mandatory**
tablePrefix     | a prefix to table names       | **mandatory**
cacheDir        | cache direcotry to store metadata | **mandatory**
isDev           | is development environment or not. Under dev environment, changes to Item class will automatically invalidate cached metadata. Under production environment, this has to be done manually.    | `true`
itemSrcDir      | a source directory under which Item classes can be found | **mandatory**
itemNamespace   | the base namespace for the managed Item classes source directory  | **mandatory**

### Setting Up Command Line Tool

DynamoDb ODM ships with a number of command line tools that are very helpful during development. You can call this command from the Composer binary directory:

```bash
$ ./vendor/bin/oasis-dynamodb-odm
```

You need to register your application's `ItemManager` to the console tool to make use of the built-in command. This is done by creating an **odm-config.php** file under the calling directory, with the following content:

```php
<?php
use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;

// replace with file to your own project bootstrap
require_once 'bootstrap.php';

// replace with your own mechanism to retrieve the item manager
$itemManager = GetItemManager();

return new ConsoleHelper($itemManager);

```

## Mapping

The fundamental functionality of an ODM library is to map object models (i.e. classes) to database sctructure. DynamoDb ODM provides a handy way to establish this mapping with the help of annotations:

```php
<?php
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;

/**
 * @Item(
 *     table="users",
 *     primaryIndex={"id"}
 * )
 */
class User
{
    /**
     * @var int
     * @Field(type="number")
     */
    protected $id;

    /**
     * @var string
     * @Field(type="string")
     */
    protected $name;
}

```

The class above declares a simple User model, wich will be mapped to DynamoDb table "users" (possibly with a prefix). The annotations are explained below:

#### Item

Every PHP object that you want to save to database using ODM is referred to as an "Item". To describe an object as an item, we have to describe the class of this object.

Class annotated with the _@Item_ annotation will be managed by ItemManager. An Item accepts the following attributes:

- **table**: table name of the object
- **primaryIndex**: primary index, wich can be either an array of keys, or an [_@Index_](#index) annotation object
- **globalSecondaryIndices**: array of global secondary indices; a global secondary index is either an array of keys, or an _@Index_ annotation object
- **localSecondaryIndices**: array of local secondary indices; a local secondary index is either an array of keys, or an _@Index_ annotation object
- **repository**: the repository class name; by default, `\Oasis\Mlib\ODM\Dynamodb\ItemRepository` is used

#### Field

The next step after making a PHP class an Item is mapping its properties to attributes in DynamoDb.

We use _@Field_ annotation to describe class properties which are DynamoDb attributes. The _@Field_ annotation supports following attributes:

- **type**: the type of the attribute, which can be one of the following:
    - string (default)
    - number
    - binary
    - bool
    - null
    - list
    - map
- **name**: the name of the DynamoDb attribute, if it's not the same as the property name. This value defaults to `null`, meaning the attribute key is the same as the property name.

#### Index

When declaring different indexes, we can use the _@Index_ annotation to make the docblock more readable. An _@Index_ is composited with two keys:
- **hash**: the hash key name
- **range**: the range key name, leave empty if no range key for this index

Below is the User class declaration when we add a global secondary index to it:

```php
/**
 * @Item(
 *     table="users",
 *     primaryIndex={"id"},
 *     globalSecondaryIndex={
 *         @Index(hash="class", range="age")
 *     }
 * )
 */
class User
{
    // ...

    /**
     * @var string
     * @Field()
     */
    protected $class;

    /**
     * @var int
     * @Field(type="number")
     */
    protected $age;
}
```

#### CASTimestamp

A _@CASTimestamp_ can be annotated together with a _@Field_ annotation. A field declared as CASTimestamp will be automatically updated to the current timestamp when the content of the object changes.

> **NOTE**: only one _@CASTimestamp_ can be declared in a single item.

## Working with Objects

All objects (items) in ODM are managed. Operations on objects are managed like object-level transaction. Once an object is managed, either by persisted as a new object or fetched from database, its managed state is stored in the ItemManager. Any change to the object will be recorded in memory. Changes to object can then be commited by invoking the `ItemManager#flush()` method on the ItemManager.

The ItemManager can be manually cleared by calling `ItemManager#clear()`. However, any changes that are not committed yet will be lost.

> **NOTE**: it is very **important** to understand, that only `ItemManager#flush()` will cause write operations against the database. Any other methods such as `ItemManager#persist($item)` or `ItemManager#remove($item)` only notify the ItemManager to perform these operations during flush. Not calling `ItemManager#flush()` will lead to all changes during that request being lost.

#### Persisting Item

An item can be made persistent by passing it to the `ItemManager#persist($item)` method. By applying the persist operation on some item, that item becomes **MANAGED**, which means that its persistence is from now on managed by an ItemManager. As a result the persistent state of such an item will subsequently be properly synchronized with the database when `ItemManager#flush()` is invoked.

Example:

```php
<?php
/** @var ItemManger $im */
$user = new User();
$user->setName('Mr.Right');
$im->persist($user);
$im->flush();

```

#### Removing Item

An item can be removed from persistent storage by passing it to the `ItemManager#remove($item)` method. By applying the remove operation on some item, that item becomes **REMOVED**, which means that its persistent state will be deleted once `ItemManager#flush()` is invoked.

Example:

```php
<?php
/** @var ItemManger $im */
/** @var User $user */
$im->remove($user);
$im->flush();

```

#### Detaching Item

An item is detached from an ItemManager and thus no longer managed by invoking the `ItemManager#detach($item)` method on it. Changes made to the detached item, if any (including removal of the item), will not be synchronized to the database after the item has been detached.

DynamoDb ODM will not hold on to any references to a detached item.


Example:

```php
<?php
/** @var ItemManger $im */
/** @var User $user */
$im->detach($user);
$user->setName('Mr.Left');
$im->flush(); // changes to $user will not be synchronized

```

#### Synchronization with the Database

The state of persistent items is synchronized with the database on `flush()` of an ItemManager. The synchronization involves writing any updates to persistent items to the database. When `ItemManager#flush()` is called, ODM inspects all managed, new and removed items and will perform the following operations:

- create new object in database
- update changed attributes for managed items in database
- delete removed item from database

## Fetching Item(s)

DynamoDb ODM provides the following ways, in increasing level of power and flexibility, to fetch persistent object(s). You should always start with the simplest one that suits your needs.

#### By Primary Index

The most basic way to fetch a persistent object is by its primary index using the `ItemManager#get($itemClass, $primayKeys)` method. Here is an example:

```php
<?php
/** @var ItemManager $im */
$user = $im->get(User::class, ["id" => 1]);

```

The return value is either the found item instance or null if no instance could be found with the given identifier.

Essentially, `ItemManager#get()` is just a shortcut for the following:

```php
/** @var ItemManager $im */
/** @var ItemRepository $userRepo */
$userRepo = $im->getRepository(User::class);
$user = $userRepo->get(["id" => 1]);

```

#### By Simple Conditions on Queriable Index

To query for one or more items based on simple conditions, use the `ItemManager#query()` and `ItemManager#queryAndRun()` methods on a repository as follows:

```php
/** @var ItemManager $im */
/** @var ItemRepository $userRepo */
$userRepo = $im->getRepository(User::class);
/** @var Users[] $users */
$users = $userRepo->query(
    "#class = :class AND #age >= :minAge",
    [
        ":class" => "A",
        ":minAge" => 25,
    ],
    "class-age-index"
);

```

> **NOTE**: a simple condition is a condition that uses one and only one index. If the used index contains both _hash key_ and _range key_, the _range key_ may only be used when _hash key_ is also presented in the condition. Furthermore, only equal test operation can be performed against the _hash key_.


#### By Filters on Non-Queriable Index

To query for one or more items which has no associated index, use the `ItemManager#scan()` and `ItemManager#scanAndRun()` methods on a repository as follows:

```php
/** @var ItemManager $im */
/** @var ItemRepository $userRepo */
$userRepo = $im->getRepository(User::class);
/** @var Users[] $users */
$users = $userRepo->scan(
    "#class = :class AND #age >= :minAge AND #name = :name",
    [
        ":class" => "A",
        ":minAge" => 25,
        ":name" => "John",
    ],
);

```
